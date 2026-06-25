(function () {
  "use strict";

  function setStatusMessage(root, message, isError) {
    var statusBox = root.querySelector(".cpt-status");
    if (!statusBox) {
      return;
    }

    statusBox.hidden = !message;
    statusBox.textContent = message || "";
    statusBox.className =
      "cpt-status alert " + (isError ? "alert-danger" : "alert-success");
  }

  function escapeHtml(value) {
    return String(value)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/\"/g, "&quot;")
      .replace(/'/g, "&#39;");
  }

  function collectAlbumPayload(root) {
    var albums = root.querySelectorAll(".cpt-album[data-album-id]");
    var payload = {};

    for (var i = 0; i < albums.length; i++) {
      var album = albums[i];
      var albumId = album.getAttribute("data-album-id");
      if (!albumId) {
        continue;
      }

      var nameField = album.querySelector(
        'input[name="cpt_album[' + albumId + '][name]"]',
      );
      var commentField = album.querySelector(
        'textarea[name="cpt_album[' + albumId + '][comment]"]',
      );
      var visibilityField = album.querySelector(
        'select[name="cpt_album[' + albumId + '][visibility]"]',
      );
      var sharedUsersField = album.querySelector(
        'select[name="cpt_album[' + albumId + '][shared_users][]"]',
      );
      payload[albumId] = {
        name: nameField ? nameField.value : "",
        comment: commentField ? commentField.value : "",
        visibility: visibilityField ? visibilityField.value : "public",
      };

      var representativeField = album.querySelector(
        'input[name="cpt_album[' + albumId + '][representative_picture_id]"]',
      );
      if (representativeField) {
        payload[albumId].representative_picture_id = representativeField.value;
      }

      if (sharedUsersField && payload[albumId].visibility === "shared") {
        payload[albumId].shared_users = Array.prototype.slice
          .call(sharedUsersField.options)
          .filter(function (option) {
            return option.selected;
          })
          .map(function (option) {
            return option.value;
          });
      }
    }

    return payload;
  }

  function collectOwnerProfilePayload(profile) {
    if (!profile) {
      return null;
    }

    var rootAlbumId = profile.getAttribute("data-root-album-id");
    if (!rootAlbumId) {
      return null;
    }

    var fields = profile.querySelectorAll(
      ".cpt-owner-profile-field[data-field-key]",
    );
    var payload = {
      root_album_id: parseInt(rootAlbumId, 10),
      fields: {},
    };

    for (var i = 0; i < fields.length; i++) {
      var field = fields[i];
      var fieldKey = field.getAttribute("data-field-key");
      var fieldType = field.getAttribute("data-field-type") || "text";
      if (!fieldKey) {
        continue;
      }

      if (fieldType === "controlled") {
        var select = field.querySelector("select");
        payload.fields[fieldKey] = {
          tag_id: select && select.value ? parseInt(select.value, 10) : 0,
        };
        continue;
      }

      if (fieldType === "controlled_multi") {
        var multiSelect = field.querySelector("select");
        payload.fields[fieldKey] = {
          tag_ids: multiSelect
            ? Array.prototype.slice
                .call(multiSelect.options)
                .filter(function (option) {
                  return option.selected;
                })
                .map(function (option) {
                  return parseInt(option.value, 10);
                })
                .filter(function (value) {
                  return !isNaN(value) && value > 0;
                })
            : [],
        };
        continue;
      }

      if (fieldType === "availability_range") {
        var fromSelect = field.querySelector('select[data-role="from"]');
        var toSelect = field.querySelector('select[data-role="to"]');
        payload.fields[fieldKey] = {
          from_value: fromSelect ? fromSelect.value : "",
          to_value: toSelect ? toSelect.value : "",
        };
        continue;
      }

      var input = field.querySelector("input, textarea");
      payload.fields[fieldKey] = {
        value_text: input ? input.value : "",
      };
    }

    return payload;
  }

  function showToaster(message, isError) {
    if (!message || typeof window.pwgToaster !== "function") {
      return;
    }

    window.pwgToaster({ text: message, icon: isError ? "error" : "success" });
  }

  function submitWsRequest(method, token, payload) {
    var params = new URLSearchParams();
    params.set("pwg_token", token);
    params.set("payload", JSON.stringify(payload));

    return fetch("ws.php?format=json&method=" + encodeURIComponent(method), {
      method: "POST",
      headers: {
        "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8",
      },
      body: params.toString(),
      credentials: "same-origin",
    }).then(function (response) {
      return response.json();
    });
  }

  function getWsErrorMessage(data) {
    return data && data.message
      ? data.message
      : window.CPT_I18N_SAVE_ERROR || "An error has occurred.";
  }

  function syncSharedUsersVisibility(album) {
    var visibilityField = album.querySelector(".cpt-visibility-select");
    var sharedGroup = album.querySelector(".cpt-shared-users-group");
    var sharedSelect = album.querySelector(".cpt-shared-users-select");
    if (!visibilityField || !sharedGroup) {
      return;
    }

    var isShared = visibilityField.value === "shared";
    sharedGroup.hidden = !isShared;
    if (sharedSelect) {
      sharedSelect.disabled = !isShared;
    }
  }

  function initAlbumManager(root) {
    var albums = root.querySelectorAll(".cpt-album[data-album-id]");
    for (var i = 0; i < albums.length; i++) {
      syncSharedUsersVisibility(albums[i]);
    }
  }

  function renderRepresentativeOptions(album, images) {
    var optionsRoot = album.querySelector(".cpt-representative-options");
    var emptyMessage = album.querySelector(".cpt-representative-empty");
    if (!optionsRoot || !emptyMessage) {
      return;
    }

    optionsRoot.innerHTML = "";
    emptyMessage.hidden = images.length > 0;

    images.forEach(function (image) {
      var button = document.createElement("button");
      button.type = "button";
      button.className =
        "btn btn-light btn-sm text-start cpt-representative-option";
      button.setAttribute("data-image-id", String(image.id));
      button.setAttribute("data-image-label", image.label || "");
      button.setAttribute("data-image-src", image.src || "");
      button.innerHTML =
        '<span class="d-flex align-items-center gap-2">' +
        (image.src
          ? '<img src="' +
            escapeHtml(image.src) +
            '" alt="' +
            escapeHtml(image.label || "") +
            '" class="cpt-representative-thumb" loading="lazy" />'
          : "") +
        '<span class="cpt-representative-option-label">' +
        escapeHtml(image.label || "") +
        "</span></span>";

      var col = document.createElement("div");
      col.className = "col-12 col-sm-6 col-lg-4";
      col.appendChild(button);
      optionsRoot.appendChild(col);
    });
  }

  function updateRepresentativeSelection(album, imageId, label, src) {
    var input = album.querySelector(".cpt-representative-input");
    var current = album.querySelector(".cpt-representative-current");
    var currentLabel = current
      ? current.querySelector(".cpt-representative-label")
      : null;
    var clearButton = album.querySelector(".cpt-clear-representative");
    if (!input || !current || !currentLabel) {
      return;
    }

    input.value = imageId ? String(imageId) : "";
    currentLabel.textContent =
      label || current.getAttribute("data-empty-label") || "";

    var thumb = current.querySelector("img");
    if (src) {
      if (!thumb) {
        thumb = document.createElement("img");
        thumb.className = "cpt-representative-thumb";
        thumb.loading = "lazy";
        current.insertBefore(thumb, current.firstChild);
      }
      thumb.src = src;
      thumb.alt = label || "";
    } else if (thumb) {
      thumb.remove();
    }

    if (clearButton) {
      clearButton.hidden = !imageId;
    }
  }

  function loadRepresentativeOptions(album, token) {
    var picker = album.querySelector(".cpt-representative-picker");
    if (!picker) {
      return;
    }

    if (album.getAttribute("data-representatives-loaded") === "1") {
      picker.hidden = !picker.hidden;
      return;
    }

    var albumId = album.getAttribute("data-album-id");
    if (!albumId) {
      return;
    }

    var params = new URLSearchParams();
    params.set("method", "core_privacy_toggle.album.images");
    params.set("format", "json");
    params.set("album_id", albumId);
    params.set("pwg_token", token);

    fetch("ws.php?" + params.toString(), {
      method: "GET",
      credentials: "same-origin",
    })
      .then(function (response) {
        return response.json();
      })
      .then(function (data) {
        if (!data || data.stat !== "ok") {
          throw new Error("load_failed");
        }

        renderRepresentativeOptions(album, data.result.images || []);
        album.setAttribute("data-representatives-loaded", "1");
        picker.hidden = false;
      })
      .catch(function () {
        var manager = album.closest(".cpt-album-manager");
        if (manager) {
          setStatusMessage(
            manager,
            window.CPT_I18N_SAVE_ERROR || "An error has occurred.",
            true,
          );
        }
      });
  }

  function updateAlbumHeaders(root, payload) {
    Object.keys(payload).forEach(function (albumId) {
      var header = root.querySelector(
        '.cpt-album[data-album-id="' + albumId + '"] .card-header strong',
      );
      if (!header || !header.parentNode) {
        return;
      }
      header.parentNode.lastChild.textContent =
        " " + (payload[albumId].name || "");
    });
  }

  document.addEventListener("click", function (event) {
    var representativeButton = event.target.closest(
      ".cpt-load-representatives",
    );
    if (representativeButton) {
      var representativeAlbum = representativeButton.closest(
        ".cpt-album[data-album-id]",
      );
      var tokenInput = document.getElementById("pwg_token");
      if (representativeAlbum && tokenInput && tokenInput.value) {
        loadRepresentativeOptions(representativeAlbum, tokenInput.value);
      }
      return;
    }

    var representativeOption = event.target.closest(
      ".cpt-representative-option",
    );
    if (representativeOption) {
      var optionAlbum = representativeOption.closest(
        ".cpt-album[data-album-id]",
      );
      if (optionAlbum) {
        updateRepresentativeSelection(
          optionAlbum,
          representativeOption.getAttribute("data-image-id"),
          representativeOption.getAttribute("data-image-label"),
          representativeOption.getAttribute("data-image-src"),
        );
      }
      return;
    }

    var clearRepresentative = event.target.closest(".cpt-clear-representative");
    if (clearRepresentative) {
      var clearAlbum = clearRepresentative.closest(".cpt-album[data-album-id]");
      if (clearAlbum) {
        var current = clearAlbum.querySelector(".cpt-representative-current");
        updateRepresentativeSelection(
          clearAlbum,
          "",
          current ? current.getAttribute("data-empty-label") : "",
          "",
        );
      }
      return;
    }

    var profileButton = event.target.closest(".cpt-owner-profile-save-button");
    if (profileButton) {
      var profileCard = profileButton.closest(".cpt-owner-profile");
      var profileManager = profileCard || document;
      var profileTokenField = document.getElementById("pwg_token");
      if (!profileCard || !profileTokenField || !profileTokenField.value) {
        return;
      }

      var profilePayload = collectOwnerProfilePayload(profileManager);
      if (!profilePayload) {
        return;
      }

      setStatusMessage(profileCard, "", false);
      profileButton.disabled = true;

      submitWsRequest(
        "core_privacy_toggle.owner_profile.update",
        profileTokenField.value,
        profilePayload,
      )
        .then(function (data) {
          if (data && data.stat === "ok") {
            setStatusMessage(
              profileCard,
              data.result ||
                window.CPT_I18N_SAVE_SUCCESS ||
                "Your changes have been saved.",
              false,
            );
            showToaster(data.result, false);
            return;
          }

          var message = getWsErrorMessage(data);
          setStatusMessage(profileCard, message, true);
          showToaster(message, true);
        })
        .catch(function () {
          var message = window.CPT_I18N_SAVE_ERROR || "An error has occurred.";
          setStatusMessage(profileCard, message, true);
          showToaster(message, true);
        })
        .finally(function () {
          profileButton.disabled = false;
        });
      return;
    }

    var button = event.target.closest(".cpt-save-button");
    if (!button) {
      return;
    }

    var manager = button.closest(".cpt-album-manager");
    var tokenField = document.getElementById("pwg_token");
    if (!manager || !tokenField || !tokenField.value) {
      return;
    }

    var payload = collectAlbumPayload(manager);

    setStatusMessage(manager, "", false);
    button.disabled = true;

    submitWsRequest(
      "core_privacy_toggle.albums.update",
      tokenField.value,
      payload,
    )
      .then(function (data) {
        if (data && data.stat === "ok") {
          updateAlbumHeaders(manager, payload);
          setStatusMessage(
            manager,
            data.result ||
              window.CPT_I18N_SAVE_SUCCESS ||
              "Your changes have been saved.",
            false,
          );
          showToaster(data.result, false);
          return;
        }

        var message = getWsErrorMessage(data);
        setStatusMessage(manager, message, true);
        showToaster(message, true);
      })
      .catch(function () {
        var message = window.CPT_I18N_SAVE_ERROR || "An error has occurred.";
        setStatusMessage(manager, message, true);
        showToaster(message, true);
      })
      .finally(function () {
        button.disabled = false;
      });
  });

  document.addEventListener("change", function (event) {
    var visibilityField = event.target.closest(".cpt-visibility-select");
    if (!visibilityField) {
      return;
    }

    var album = visibilityField.closest(".cpt-album[data-album-id]");
    if (!album) {
      return;
    }

    syncSharedUsersVisibility(album);
  });

  document.addEventListener("DOMContentLoaded", function () {
    if (
      typeof window.CPT_ALBUM_HTML !== "string" ||
      window.CPT_ALBUM_HTML.trim() === ""
    ) {
      return;
    }

    // Locate Customize form across themes
    var selectors = [
      // Most specific first
      "form#profile",
      "#theProfilePage form#profile",
      '#theProfilePage form[action*="profile.php"]',
      'form[action*="profile.php"][method="post"]',
      '#content form[action*="profile.php"]',
      "#content form",
      'main form[action*="profile.php"]',
    ];
    var form = null;
    for (var i = 0; i < selectors.length; i++) {
      form = document.querySelector(selectors[i]);
      if (form) break;
    }
    if (!form) {
      return;
    }
    if (form.querySelector(".cpt-album-manager")) {
      return;
    }
    if (form.classList.contains("cpt-enhanced")) {
      return;
    }
    form.classList.add("cpt-enhanced");

    // Prefer a profile form that already holds a pwg_token (CSRF token)
    if (!form.querySelector('input[name="pwg_token"]')) {
      var candidate = document.querySelector('form input[name="pwg_token"]');
      if (candidate) {
        var real = candidate.closest("form");
        if (real) form = real;
      }
    }

    // Create a new section/fieldset near the end of the form, before submit buttons
    var fs = document.createElement("fieldset");
    fs.className = "cpt-section";
    // We previously added a visible legend here. The redesigned template now
    // includes its own card header, so we avoid duplicating the heading.
    // Preserve accessibility by providing an aria-label instead of a legend.
    fs.setAttribute(
      "aria-label",
      window.CPT_I18N_MY_GALLERIES || "My Galleries",
    );
    var container = document.createElement("div");
    container.className = "cpt-section-body";
    container.innerHTML = window.CPT_ALBUM_HTML;
    fs.appendChild(container);

    // Always insert at the very top of the form for robustness
    if (form.firstChild) {
      form.insertBefore(fs, form.firstChild);
    } else {
      form.appendChild(fs);
    }
    // Safety: if not inside the form (edge theme quirk), force move
    if (fs.parentNode !== form) {
      try {
        form.insertBefore(fs, form.firstChild);
      } catch (e) {
        form.appendChild(fs);
      }
    }

    initAlbumManager(fs);
  });
})();

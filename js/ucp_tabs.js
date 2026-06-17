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
    var params = new URLSearchParams();
    params.set("pwg_token", tokenField.value);
    params.set("payload", JSON.stringify(payload));

    setStatusMessage(manager, "", false);
    button.disabled = true;

    fetch("ws.php?format=json&method=core_privacy_toggle.albums.update", {
      method: "POST",
      headers: {
        "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8",
      },
      body: params.toString(),
      credentials: "same-origin",
    })
      .then(function (response) {
        return response.json();
      })
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
          if (typeof window.pwgToaster === "function") {
            window.pwgToaster({ text: data.result, icon: "success" });
          }
          return;
        }

        var message =
          data && data.message
            ? data.message
            : window.CPT_I18N_SAVE_ERROR || "An error has occurred.";
        setStatusMessage(manager, message, true);
        if (typeof window.pwgToaster === "function") {
          window.pwgToaster({ text: message, icon: "error" });
        }
      })
      .catch(function () {
        var message = window.CPT_I18N_SAVE_ERROR || "An error has occurred.";
        setStatusMessage(manager, message, true);
        if (typeof window.pwgToaster === "function") {
          window.pwgToaster({ text: message, icon: "error" });
        }
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

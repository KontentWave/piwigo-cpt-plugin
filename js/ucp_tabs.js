(function () {
  "use strict";

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
    var lg = document.createElement("legend");
    lg.textContent = window.CPT_I18N_MY_GALLERIES || "My Galleries";
    fs.appendChild(lg);
    var container = document.createElement("div");
    container.className = "cpt-section-body";
    container.innerHTML = window.CPT_ALBUM_HTML;
    fs.appendChild(container);

    // Hidden marker so server can verify presence even if no albums changed
    var marker = document.createElement("input");
    marker.type = "hidden";
    marker.name = "cpt_album_marker";
    marker.value = "1";
    fs.appendChild(marker);

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
  });
})();

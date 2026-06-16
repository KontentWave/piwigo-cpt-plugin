(function () {
  "use strict";

  document.addEventListener("DOMContentLoaded", function () {
    if (
      typeof window.CPT_ALBUM_PAGE_HTML !== "string" ||
      window.CPT_ALBUM_PAGE_HTML.trim() === ""
    ) {
      return;
    }

    if (document.querySelector(".cpt-album-quick-toggle")) {
      return;
    }

    var content = document.querySelector('div[data-role="content"]');
    if (!content) {
      return;
    }

    var wrapper = document.createElement("div");
    wrapper.innerHTML = window.CPT_ALBUM_PAGE_HTML;

    var shortcut = wrapper.firstElementChild;
    if (!shortcut) {
      return;
    }

    content.insertBefore(shortcut, content.firstChild);
  });
})();

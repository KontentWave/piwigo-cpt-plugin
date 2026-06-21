(function () {
  "use strict";

  function buildFragment(html) {
    var wrapper = document.createElement("div");
    wrapper.innerHTML = html;

    if (!wrapper.firstElementChild) {
      return null;
    }

    var fragment = document.createDocumentFragment();
    while (wrapper.firstChild) {
      fragment.appendChild(wrapper.firstChild);
    }

    return fragment;
  }

  document.addEventListener("DOMContentLoaded", function () {
    if (
      typeof window.CPT_ALBUM_PAGE_HTML !== "string" ||
      window.CPT_ALBUM_PAGE_HTML.trim() === ""
    ) {
      return;
    }

    if (
      document.querySelector(
        ".cpt-owner-profile-public, .cpt-owner-profile-mobile, .cpt-owner-profile-desktop",
      )
    ) {
      return;
    }

    var mobileAnchor = document.querySelector(
      "#content-description-mobile, #content-description-mobile-fallback",
    );
    var desktopAnchor = document.querySelector("#content-description-desktop");

    if (mobileAnchor) {
      var mobileContainer = document.createElement("div");
      mobileContainer.className =
        "cpt-owner-profile-mobile col-outer col-12 py-3 d-lg-none";

      var mobileFragment = buildFragment(window.CPT_ALBUM_PAGE_HTML);
      if (mobileFragment) {
        mobileContainer.appendChild(mobileFragment);
        mobileAnchor.insertAdjacentElement("afterend", mobileContainer);
      }
    }

    if (desktopAnchor) {
      var desktopContainer = document.createElement("div");
      desktopContainer.className =
        "cpt-owner-profile-desktop py-3 d-none d-lg-block";

      var desktopFragment = buildFragment(window.CPT_ALBUM_PAGE_HTML);
      if (desktopFragment) {
        desktopContainer.appendChild(desktopFragment);
        desktopAnchor.insertAdjacentElement("afterend", desktopContainer);
      }
    }

    if (mobileAnchor || desktopAnchor) {
      return;
    }

    var content = document.querySelector('#content, div[data-role="content"]');
    if (!content) {
      return;
    }

    var fallbackFragment = buildFragment(window.CPT_ALBUM_PAGE_HTML);
    if (!fallbackFragment) {
      return;
    }

    content.insertBefore(fallbackFragment, content.firstChild);
  });
})();

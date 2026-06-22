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

  function removeDuplicateNodes(
    fragment,
    hasExistingQuickToggle,
    hasExistingOwnerProfile,
  ) {
    if (!fragment) {
      return fragment;
    }

    if (hasExistingQuickToggle) {
      var quickToggles = fragment.querySelectorAll
        ? fragment.querySelectorAll(".cpt-album-quick-toggle")
        : [];
      for (var i = 0; i < quickToggles.length; i++) {
        quickToggles[i].remove();
      }
    }

    if (hasExistingOwnerProfile) {
      var ownerProfiles = fragment.querySelectorAll
        ? fragment.querySelectorAll(".cpt-owner-profile-public")
        : [];
      for (var j = 0; j < ownerProfiles.length; j++) {
        ownerProfiles[j].remove();
      }
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

    var mobileAnchor = document.querySelector(
      "#content-description-mobile, #content-description-mobile-fallback",
    );
    var desktopAnchor = document.querySelector("#content-description-desktop");
    var hasExistingQuickToggle = !!document.querySelector(
      ".cpt-album-quick-toggle",
    );
    var hasExistingOwnerProfile = !!document.querySelector(
      ".cpt-owner-profile-public, .cpt-owner-profile-mobile, .cpt-owner-profile-desktop",
    );

    if (mobileAnchor) {
      var mobileContainer = document.createElement("div");
      mobileContainer.className =
        "cpt-owner-profile-mobile col-outer col-12 py-3 d-lg-none";

      var mobileFragment = removeDuplicateNodes(
        buildFragment(window.CPT_ALBUM_PAGE_HTML),
        hasExistingQuickToggle,
        hasExistingOwnerProfile,
      );
      if (mobileFragment) {
        mobileContainer.appendChild(mobileFragment);
        if (mobileContainer.childNodes.length > 0) {
          mobileAnchor.insertAdjacentElement("afterend", mobileContainer);
        }
      }
    }

    if (desktopAnchor) {
      var desktopContainer = document.createElement("div");
      desktopContainer.className =
        "cpt-owner-profile-desktop py-3 d-none d-lg-block";

      var desktopFragment = removeDuplicateNodes(
        buildFragment(window.CPT_ALBUM_PAGE_HTML),
        hasExistingQuickToggle,
        hasExistingOwnerProfile,
      );
      if (desktopFragment) {
        desktopContainer.appendChild(desktopFragment);
        if (desktopContainer.childNodes.length > 0) {
          desktopAnchor.insertAdjacentElement("afterend", desktopContainer);
        }
      }
    }

    if (mobileAnchor || desktopAnchor) {
      return;
    }

    var content = document.querySelector('#content, div[data-role="content"]');
    if (!content) {
      return;
    }

    var fallbackFragment = removeDuplicateNodes(
      buildFragment(window.CPT_ALBUM_PAGE_HTML),
      hasExistingQuickToggle,
      hasExistingOwnerProfile,
    );
    if (!fallbackFragment) {
      return;
    }

    content.insertBefore(fallbackFragment, content.firstChild);
  });
})();

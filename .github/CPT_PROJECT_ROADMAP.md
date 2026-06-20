## PROJECT_ROADMAP.md (The Future 🗺️)

### Project Vision

To empower Piwigo gallery owners by seamlessly integrating powerful album management tools directly into their existing User Control Panel (UCP). This "Community Plus" plugin bridges the gap left by the default Community plugin, making album ownership more intuitive and self-sufficient, including real-world user album trees where one Community-owned root album contains multiple manageable child albums.

### Current State Snapshot

Phase 1 is now delivered in practice: profile and UCP album editing is live, album privacy can also be toggled from public and mobile album pages, current Community ownership metadata is supported, album-level selected-user sharing is implemented in the UCP, and the plugin has working translations for the active gallery languages.

A real-world Community limitation has now been addressed in CPT: Community may model "user albums" as one direct album per user, while galleries often use one user root album with several child albums. CPT now adds an inherited-ownership layer so child albums under a Community-owned root album can be managed by the same owner. Small local Community integration patches also now restrict the add-photos target selector to that same owner tree and hide the bulk photo privacy-level action in favor of album-level sharing. With those stabilization steps in place, the roadmap can move deeper into photo-level features.

### Phase 1 (MVP): UCP Album Management Basics

This phase constitutes our **Minimal Viable Product (MVP)**. It delivers the core value of self-service album management and validates the project's main idea by providing the most essential features directly to the user.

- **Feature: List User's Albums in UCP: ** Create a new tab or section in the UCP that displays a list of all albums owned by the logged-in user.
- **Feature: Edit Album Properties:** For each album in the list, provide an "Edit" interface that allows the owner to:
  - Change the album name.
  - Set album privacy ("private for me and admins").
  - Edit the album description/comment.

Delivered extras beyond the original MVP definition:

- **Public/Mobile Album Privacy Shortcut:** Owners can switch the current album between public and private directly from the album page.
- **Album Sharing in UCP:** Owners can set a managed album to public, private, or shared with a selected allow-list of users.
- **Modern Community Compatibility:** Ownership works with direct `categories.community_user`, legacy `categories.user_id`, inherited ownership for descendant albums, and the exclusive-contributor fallback for incomplete metadata.
- **Expanded Localization:** CPT strings are available for the gallery languages currently in use.

### Phase 1.5: Inherited Community Album Tree Ownership (Implemented)

This phase hardens CPT against the practical Community plugin limitation where a user can directly own only one Community user album, while the gallery may contain multiple child albums below that owned root.

- **Feature: Inherited Album Ownership:** Treat descendant albums of a Community-owned root album as manageable by the same owner.
- **Feature: UCP Child Album Listing:** Show owned descendant albums in the `My Galleries` UCP manager even when the child album has no direct `community_user` value.
- **Feature: Public Album Shortcut for Descendants:** Show the owner-only public/mobile privacy shortcut on descendant albums.
- **Feature: Permission Sync With Effective Owner:** When a descendant album is made private or shared, preserve access for admin, the effective owner, and selected shared users.
- **Feature: Regression Protection:** PHPUnit coverage now protects direct ownership, inherited ownership, explicit child-owner override, and exclusive-contributor fallback. Cypress smoke coverage now also protects descendant toggle flows, Smart Pocket rendering, explicit child-owner override, representative-image controls, and Community upload-target scoping.

Implementation direction:

- Keep the Community plugin unmodified unless a future need forces a maintained fork.
- Add CPT helpers for effective ownership, using direct owner metadata first, then inherited ownership through the album ancestor chain, then the conservative exclusive-contributor fallback.
- Ensure non-owners cannot manage another user's descendant albums.
- Keep the album-page quick toggle simple: public/private only. Advanced sharing remains in the UCP editor.

This phase is complete and establishes the ownership foundation needed for future album-scoped features such as representative image selection.

### Phase 2: UCP Photo Management (Started)

Once the MVP is established, the next logical step is to allow basic photo management from the UCP.

- **Feature: Set Album Representative:** Implemented as an MVP. Owners can now choose or clear the album's cover image from photos already assigned to that album.
- **Feature: Edit Photo Properties:** Allow owners to edit the title and description of individual photos in their albums.
- **Feature: Photo Sorting:** Allow owners to change the order of photos within their albums.

Priority note:

- The representative image chooser is now in place as the first Phase 2 slice; next work is broader browser automation and UI polish around that flow.
- Photo privacy controls were explored on the Community side during integration work, but they are not yet a CPT-owned UCP feature and the Community bulk photo privacy-level UI is intentionally hidden in this deployment.

### Phase 3: Advanced Features (Brainstorming)

This is a long-term-vision section for ideas we can explore later.

- **Feature: UCP Photo Upload:** A simplified photo uploader directly on the UCP page.
- **Feature: Dashboard/Stats:** A small dashboard in the UCP showing view counts and comments for the user's albums.
- **Feature: Owner Grouped Album Landing Page:** Optionally add a higher display level that groups albums under each owner and highlights the newest child album. This is now safer because inherited ownership is implemented.

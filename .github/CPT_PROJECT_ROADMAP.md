## PROJECT_ROADMAP.md (The Future 🗺️)

### Project Vision

To empower Piwigo gallery owners by seamlessly integrating powerful album management tools directly into their existing User Control Panel (UCP). This "Community Plus" plugin will bridge the gap left by the default Community plugin, making album ownership more intuitive and self-sufficient.

### Current State Snapshot

Phase 1 is now delivered in practice: profile and UCP album editing is live, album privacy can also be toggled from public and mobile album pages, current Community ownership metadata is supported, and the plugin has working translations for the active gallery languages. The roadmap below therefore focuses on what remains, not on the already-completed MVP baseline.

### Phase 1 (MVP): UCP Album Management Basics

This phase constitutes our **Minimal Viable Product (MVP)**. It delivers the core value of self-service album management and validates the project's main idea by providing the most essential features directly to the user.

- **Feature: List User's Albums in UCP: ** Create a new tab or section in the UCP that displays a list of all albums owned by the logged-in user.
- **Feature: Edit Album Properties:** For each album in the list, provide an "Edit" interface that allows the owner to:
  - Change the album name.
  - Set album privacy ("private for me and admins").
  - Edit the album description/comment.

Delivered extras beyond the original MVP definition:

- **Public/Mobile Album Privacy Shortcut:** Owners can switch the current album between public and private directly from the album page.
- **Modern Community Compatibility:** Ownership works with `categories.community_user`, legacy `categories.user_id`, and the exclusive-contributor fallback for incomplete metadata.
- **Expanded Localization:** CPT strings are available for the gallery languages currently in use.

### Phase 2: UCP Photo Management (Future Idea)

Once the MVP is established, the next logical step is to allow basic photo management from the UCP.

- **Feature: Set Album Representative:** Allow the owner to choose the album's cover image (thumbnail) from the photos within it.
- **Feature: Edit Photo Properties:** Allow owners to edit the title and description of individual photos in their albums.
- **Feature: Photo Sorting:** Allow owners to change the order of photos within their albums.

Priority note:

- The representative image chooser remains the most natural next CPT feature because it is already called out in the current project sheet as deferred but expected.
- Photo privacy controls were explored on the Community side during integration work, but they are not yet a CPT-owned UCP feature.

### Phase 3: Advanced Features (Brainstorming)

This is a long-term-vision section for ideas we can explore later.

- **Feature: UCP Photo Upload:** A simplified photo uploader directly on the UCP page.
- **Feature: Dashboard/Stats:** A small dashboard in the UCP showing view counts and comments for the user's albums.
- **Feature: Owner Grouped Album Landing Page:** Optionally add a higher display level that groups albums under each owner and highlights the newest child album.

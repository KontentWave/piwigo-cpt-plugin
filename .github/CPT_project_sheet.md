## CPT_project_sheet.md (The Present 📜)

This document is the living technical specification for the "Core Privacy Toggle" plugin. It will be updated as development progresses.

### Phase 1 (MVP): UCP Album Management & Core Privacy Toggle (Updated Draft)

#### `Action`

Empower non-admin gallery owners by integrating album management controls (name, description, privacy, and representative image) directly into their User Control Panel (UCP), enhancing self-sufficiency and reducing administrative overhead.

#### Design Evolution Note

Initial concept used an ARIA tabbed interface. During integration with varied themes (e.g. Bootstrap Darkroom) we simplified to a progressive enhancement that injects a single structured fieldset section ("My Galleries") into the existing profile form. This reduced fragility, avoided layout clashes, and preserved full functionality with JavaScript disabled (the section simply does not appear when no albums qualify).

### `Task` (Current Implementation Plan)

1. **Plugin Skeleton**: `core_privacy_toggle/` with `main.inc.php`, `include/`, `template/`, `language/`, `js/`.
2. **Profile Hook**: `add_event_handler('loc_begin_profile', 'cpt_setup_ucp_tabs');` (legacy name retained for compatibility; function now injects a section not tabs).
3. **Ownership Detection**:
   - Preferred: `categories.user_id` (Community plugin supplied).
   - Fallback (when column absent): treat albums as editable if all images within an album were uploaded by the current user ("exclusive contributor" heuristic).
4. **Album Data Fetch**:
   - Collect `id, name, comment, status` for qualifying albums.
   - Skip enhancement if none found (baseline page untouched).
5. **Template Partial**: `template/ucp_album_manager.tpl` renders only input controls (loop). No outer form wrapper. Escapes output.
6. **Progressive Enhancement Injection**:
   - PHP renders partial to string and exports via inline script (`window.CPT_ALBUM_HTML`).
   - JS (`js/ucp_tabs.js`) on DOM ready locates the profile form with robust selectors and inserts a `<fieldset class="cpt-section">` containing the partial before the submit block (or appended at end).
7. **Submission Handling**:
   - On POST, iterate `$_POST['cpt_album']` entries, validate ownership (preferred or fallback), apply sanitized updates (name, comment, privacy status). Add info message upon success.
8. **Privacy Toggle**:
   - Checkbox -> `status=private`; absence -> `status=public`.
   - (Representative image selection deferred to later phase.)
9. **Internationalization**: All UI strings routed through translation files; legend key `My Galleries` exported separately.
10. **Fallback Messaging**:

- Admin hint if ownership column missing.
- User-visible limited mode banner when only fallback albums are shown.

### Accessibility

Simplified section requires standard form semantics only:

- Fieldset + legend provide grouping.
- Labels bound via `for` / `id`.
- No custom widgets means no additional ARIA roles required.
- Future representative image selector (Phase 1 extension or Phase 2) must maintain keyboard operability.

### Security & Validation

- Server is sole authority: each album update gated by ownership / exclusive-contributor check.
- SQL built with escaped values; status constrained to allowed set.
- Early returns keep logic small and auditable.

### Testing Plan (Adjusted)

**PHPUnit**

1. Retrieval (direct ownership) returns only expected albums when `user_id` column present.
2. Retrieval (fallback) returns only exclusive contributor albums when column absent.
3. Unauthorized update attempt rejected (no side effects) for both ownership modes.
4. Data update persists name & comment (UTF-8 + length edge cases).
5. Privacy toggle transitions (public ↔ private) correctly update status.

**Cypress / E2E**

1. Owner sees "My Galleries" section (not tabs) when they have qualifying albums.
2. Limited mode banner appears when using fallback heuristic.
3. Editing name + description + privacy and submitting shows success notice and persists after reload.
4. Private album hidden from another (non-owner) user after save.
5. Album made public again becomes visible to other user.
6. User with no qualifying albums sees no section.

### Deferred / Out of Scope (for MVP)

- Representative image selection UI.
- Bulk permission artifact adjustments beyond status flag.
- Advanced pagination or search across large album sets.
- Full ARIA tab widget (replaced by simpler section).

### Operational Notes

- Asset injection uses both head and footer regions with a guard to survive theme variance.
- Fallback may be replaced by native ownership once Community (or another plugin) populates `categories.user_id`.
- Clearing template cache or hard-refresh may be needed after deploying updated JS or template partials.

### Future Considerations

- Introduce a migration routine to add `user_id` to categories if absent (opt-in) for installations that want first-class ownership.
- Add representative image selection via thumbnail chooser with lazy loading.
- Provide REST/WebService endpoints mirroring the profile functionality for SPA or mobile clients.

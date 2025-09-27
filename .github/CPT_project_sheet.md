## CPT_project_sheet.md (The Present 📜)

This document is the living technical specification for the "Core Privacy Toggle" plugin. It will be updated as development progresses.

### Phase 1 (MVP): UCP Album Management & Core Privacy Toggle (Implemented ✅)

#### `Action`

Empower non-admin gallery owners by integrating album management controls (name, description, privacy, and representative image) directly into their User Control Panel (UCP), enhancing self-sufficiency and reducing administrative overhead.

#### Design Evolution Note

Initial concept used an ARIA tabbed interface. During integration with varied themes (e.g. Bootstrap Darkroom) we simplified to a progressive enhancement that injects a single structured fieldset section ("My Galleries") into the existing profile form. This reduced fragility, avoided layout clashes, and preserved full functionality with JavaScript disabled (the section simply does not appear when no albums qualify).

### `Status` (As of 2025-09-27)

Phase 1 functionality is fully implemented and manually & automatically validated. The representative image chooser was intentionally deferred and remains the first candidate for the next phase.

### Implementation Summary

Delivered components & behaviors:

1. **Plugin Skeleton** with structured directories (`include/`, `template/`, `language/`, `js/`).
2. **Profile Hook Integration** via `cpt_setup_ucp_tabs` (legacy name) attaching on `loc_begin_profile` to process POST then expose the enhancement.
3. **Dual Ownership Model**:
   - Primary: `categories.user_id` (Community plugin).
   - Fallback: exclusive contributor heuristic (all images in album uploaded by current user) when ownership column absent.
4. **Album Data Handling**: Secure fetch of `id, name, comment, status`; early escape if zero qualifying albums (no intrusive markup).
5. **Template Partial** (`ucp_album_manager.tpl`): Now a Bootstrap card layout with per‑album sub‑cards; fully escaped output; empty state message when no albums yet.
6. **Progressive Enhancement Injection**: Server renders partial string → exported through inline JS → client script injects inside existing profile `<form>` (no nested forms) with accessibility preserved (`aria-label`).
7. **Submission & Validation**: Unified POST handler validates ownership per album, applies sanitized updates, and aggregates a success info message.
8. **Privacy Toggle & Permission Sync**: Switching to private inserts explicit `user_access` rows (admin + owner); switching to public removes them. Permission changes trigger user cache purge for immediate visibility updates across sessions.
9. **Cache Invalidation**: Purges `user_cache` table entries after privacy transition; session flag supports subsequent permission recalculation.
10. **Internationalization**: All visible strings localized (EN + FR) including empty state and limited-mode banner.
11. **Fallback Messaging**: Admin diagnostic hint for missing ownership column; user-facing limited mode banner when operating via fallback heuristic only.
12. **Security**: Ownership re‑checked server-side for every album before write; only whitelisted columns updated; status constrained to `public|private`.
13. **Accessibility**: Semantic form controls, proper labels, grouped cards; removal of duplicate legends while retaining screen-reader context via `aria-label`.
14. **Styling**: Modern card-based UI consistent with existing profile sections; minimal, scoped CSS additions; JS cache-busting via filemtime query param.
15. **Testing**: Comprehensive PHPUnit suite (logic + security + edge cases + privacy transitions + fallback) and Cypress smoke test (login + environment resilience). Gherkin feature file drafted for richer future E2E expansion.
16. **CI**: GitHub Actions workflows for PHPUnit and Cypress integrated.

### Remaining (Deferred) Items

- Representative image selection UI & persistence.
- Rich E2E scenarios from `ucp_album_management.feature` (currently only smoke executed).
- Multi-browser CI matrix and code coverage reporting.
- Optional migration path to populate or enforce `categories.user_id` on legacy installs.

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

**PHPUnit (Implemented)**

An isolated in-memory harness simulates required Piwigo tables (`categories`, `images`, `image_category`, `user_access`, `user_cache`) so logic runs without a full Piwigo bootstrap. A minimal template stub and SQL pattern matcher (`pwg_query`) exercise only the query shapes the plugin emits.

Covered by test classes in `core_privacy_toggle/tests/`:

1. `AlbumRetrievalTest` – Direct ownership retrieval (user_id column present) and fallback exclusive-contributor retrieval when column absent.
2. `AlbumUpdateSecurityTest` – Unauthorized update attempt ignored (no field mutations, returns false).
3. `PrivacyToggleTest` – Private transition inserts explicit `user_access` rows (admin + owner), public transition removes them; now also asserts user cache purge flag each direction.
4. `AlbumFieldPersistenceTest` – Name, comment, and status (`public`→`private`) update persists with UTF‑8 characters.
5. `FallbackUpdateTest` – Updates permitted/denied via fallback heuristic (exclusive contributor vs. mixed contributors) when ownership column missing.
6. `AlbumEdgeCasesTest` – Blank name ignored (original retained), whitespace-only comment stored as empty string, long multi-byte description persists.

Additional behaviors explicitly / implicitly exercised:

- Cache purge path (`cpt_purge_user_cache`) asserted through a test harness flag.
- One-shot session flag side-effect (not directly asserted; low risk, minimal logic).

Deferred / Not Unit-Tested Yet (future candidates):

- Injection/integration path via `cpt_setup_ucp_tabs` (would require fuller template + POST environment simulation for end-to-end assurance – left to Cypress/E2E scope).
- Mixed contributor edge cases where images added after initial exclusivity break fallback ownership (can be added if regression discovered).

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

### Continuous Integration

GitHub Actions workflow (`.github/workflows/phpunit.yml` under the plugin directory) runs the plugin PHPUnit suite on push & PR affecting plugin files. Matrix kept minimal (PHP 8.2) because logic is version-agnostic; can be extended later for 8.1/8.3 if needed.

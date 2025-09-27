# Core Privacy Toggle (Phase 1 Implemented ✅)

Empower non‑admin gallery users to manage their own albums (name, description, privacy) directly from their User Control Panel (UCP).

> NOTE: This README mirrors the living spec in `.github/CPT_project_sheet.md`. For architectural depth or Phase 2 outlook, consult that file.

---

## 📌 Key Features

| Capability                   | Description                                                                                                 |
| ---------------------------- | ----------------------------------------------------------------------------------------------------------- |
| Inline Album Editing         | Edit album name & description directly on the Profile page (no navigation to admin).                        |
| Privacy Toggle               | One‑click switch between Public and Private per owned album.                                                |
| Permission Sync              | Private → creates explicit `user_access` rows (admin + owner). Public → cleans them up.                     |
| Dual Ownership Model         | Prefers `categories.user_id` (Community plugin). Falls back to “exclusive contributor” heuristic if absent. |
| Immediate Visibility Updates | Purges `user_cache` so privacy changes take effect for other users right away.                              |
| Progressive Enhancement      | Works without JavaScript (JS just improves layout).                                                         |
| Internationalization         | English & French translations shipped; easily extendable.                                                   |
| Accessibility                | Native form controls, labelled groups, no keyboard traps.                                                   |
| Test Coverage                | PHPUnit logic tests + Cypress smoke test in CI.                                                             |

---

## ⚠️ Prerequisite: Community Plugin

For first‑class ownership detection this plugin expects the **Community** plugin to be installed and active, because it adds the `user_id` column to the `categories` table.  
If Community is missing, Core Privacy Toggle gracefully enters **Limited (Fallback) Mode**: it will only list albums where every photo inside was uploaded by the current user.

Fallback is intentionally conservative to prevent accidental elevation of control over shared albums.

---

## 🏗 Architecture Overview

| Component                                          | Role                                                                                                 |
| -------------------------------------------------- | ---------------------------------------------------------------------------------------------------- |
| PHP (`main.inc.php`, `include/functions.inc.php`)  | Business logic: ownership checks, database reads/writes, permission sync, cache purge.               |
| Smarty Template (`template/ucp_album_manager.tpl`) | Renders inner album inputs (card layout). No form tag or business logic.                             |
| JavaScript (`js/ucp_tabs.js`)                      | Locates the profile form and injects the rendered partial. Adds hidden marker + accessibility label. |
| Language Files (`language/*/plugin.lang.php`)      | I18n keys for all UI strings.                                                                        |
| Styles (`template/style.css`)                      | Light, scoped adjustments to match theme cards.                                                      |
| Tests (`tests/`)                                   | In‑memory DB simulation for deterministic logic tests.                                               |

The server always performs validation; the browser never “decides” ownership.

---

## 🔐 Ownership & Security Model

1. Attempt direct ownership using `categories.user_id` (Community).
2. If absent, compute exclusive‑contributor albums: albums where `COUNT(DISTINCT added_by) == 1` and that `added_by` equals the logged user.
3. Each submitted album ID is re‑checked before any write.
4. Only `name`, `comment`, and `status` can be changed; unexpected fields are ignored.
5. Status toggle drives permission synchronization + user cache purge.
6. Output is fully escaped to prevent XSS.

---

## 🔄 Privacy State Transitions

| State Change     | Actions Performed                                                                                                |
| ---------------- | ---------------------------------------------------------------------------------------------------------------- |
| Public → Private | Remove prior explicit rows (clean slate), add `user_access` rows for admin (id=1) and owner, purge `user_cache`. |
| Private → Public | Delete album’s `user_access` rows, purge `user_cache`.                                                           |

Cache purge ensures other sessions immediately reflect visibility changes without waiting for natural expiration.

---

## 🧩 Progressive Enhancement

Without JavaScript: the server injects a hidden marker + inner markup early so form submission still works.  
With JavaScript: the fieldset is repositioned (or inserted at top) and styled via cards—no functional dependency on JS.

---

## 🌐 Internationalization

Add a new locale: copy `language/en_UK/plugin.lang.php` → `language/<locale>/plugin.lang.php` and translate values. All user‑visible strings are keyed.

---

## ♿ Accessibility Commitments

- `<fieldset aria-label="My Galleries">` groups controls (legend removed to avoid duplicate visual heading).
- Each album sub-card header visually separates albums (can become an actual `<h5>` later).
- Pure native inputs; checkbox focus and keyboard behavior unchanged.
- Future representative image selector will implement roving focus & ARIA states.

---

## 🛠 Installation

1. Install and activate the **Community** plugin (recommended).
2. Copy this directory to `piwigo/plugins/core_privacy_toggle/` or install via Piwigo’s plugin manager if published.
3. Activate Core Privacy Toggle in the Piwigo admin panel.
4. (Optional) Clear compiled templates/cache for immediate JS/style pickup.
5. Visit a user’s Profile page (`profile.php`) to confirm the “My Galleries” card is present when the user owns qualifying albums.

---

## ▶ Usage Flow

1. User opens Profile page.
2. Plugin injects the album management card if any owned (or exclusive) albums exist.
3. User edits fields and submits the main Profile form.
4. Plugin validates ownership, persists changes, syncs permissions if privacy altered, purges user cache, and adds a confirmation message.
5. Reload page to verify updated values (or check from a different user session for privacy changes).

---

## 🧪 Testing & CI

- **PHPUnit**: In‑memory harness fakes core tables (`categories`, `images`, `image_category`, `user_access`, `user_cache`).
- **Cypress**: Smoke test (login + environment health).
- **Gherkin Feature**: `/.github/features/ucp_album_management.feature` enumerates richer scenarios for future automation.
- **CI**: GitHub Actions workflows run PHPUnit and Cypress on push / PR.

Planned Phase 2 additions: coverage reports & multi‑browser matrix (Firefox).

---

## 🧭 Fallback (Limited) Mode

Displayed banner: “Limited mode: only albums exclusively containing your photos are listed.”  
This prevents users from editing collaborative albums when direct ownership metadata is missing.

---

## 🚧 Phase 2 (Draft Roadmap)

1. Representative image selector (accessible thumbnail picker).
2. Automate all scenarios in feature file (privacy visibility cross-user, limited banner, empty state).
3. Coverage reporting & badge (clover + threshold gate).
4. Multi‑browser E2E (Firefox; optional WebKit/Playwright).
5. Ownership migration helper to populate `categories.user_id`.
6. WebService endpoints (`cpt.album.list` / `cpt.album.update`).
7. Pagination or collapse behavior for very large album counts.

---

## 🔐 Security Posture (Summary)

- Server authoritative ownership verification per album ID.
- All dynamic SQL escaped; restricted update column set.
- Automatic permission synchronization & cache purge for privacy transitions.
- Output escaping in templates prevents XSS.

---

## ❓ FAQ

**Q: Why don’t I see any albums?**  
Either you don’t own any (per Community’s `user_id`) or, in fallback mode, there are no albums where all images were uploaded by you.

**Q: Can I bulk-toggle multiple albums?**  
Not in Phase 1—submit once for all changed rows though (batch via single form post). Bulk UI may arrive in a later phase.

**Q: Do I need JavaScript?**  
No. JS enhances layout only.

**Q: Where is representative image selection?**  
Deferred to Phase 2 (see roadmap).

---

## 🤝 Contributing

1. Fork & branch (`feat/your-feature`).
2. Add/adjust PHPUnit tests (and future coverage if enabled).
3. Run CI locally if possible; ensure no regressions.
4. Submit PR referencing related issue / roadmap item.

Prefix any new global functions with `cpt_` and escape template variables.

---

## 📄 License

See `LICENSE.txt` in this directory (inherits standard Piwigo plugin distribution license terms).

---

## 🔍 Metadata

- Internal directory name: `core_privacy_toggle`
- Plugin page: http://piwigo.org/ext/extension_view.php?eid=543 (placeholder if unpublished)
- Requires: Piwigo ≥ 15.6.0, PHP ≥ 8.1, Community plugin (recommended for full feature set)

---

## 🧾 Changelog (Excerpt)

| Version         | Date       | Notes                                                                                                 |
| --------------- | ---------- | ----------------------------------------------------------------------------------------------------- |
| 0.1.0 (Phase 1) | 2025-09-27 | Initial MVP: UCP album editing, privacy toggle, fallback mode, i18n (EN/FR), cache purge, tests & CI. |

---

## 🙌 Acknowledgments

- Piwigo Core & Community plugin authors.
- Test & feedback contributors.

Feel free to open issues for bugs, enhancement ideas, or accessibility feedback.

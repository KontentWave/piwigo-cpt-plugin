# Core Privacy Toggle (Phase 2 Started ✅)

Empower non‑admin gallery users to manage their own albums (name, description, privacy) directly from their User Control Panel (UCP).

> NOTE: This README mirrors the living spec in `.github/CPT_project_sheet.md`. For architectural depth or Phase 2 outlook, consult that file.

---

## 📌 Key Features

| Capability                   | Description                                                                                                                                                                                                                         |
| ---------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Inline Album Editing         | Edit album name & description directly on the Profile page (no navigation to admin).                                                                                                                                                |
| Privacy Toggle               | One‑click switch between Public and Private per owned album, including inherited descendant albums under a Community-owned root.                                                                                                    |
| Permission Sync              | Private → creates explicit `user_access` rows (admin + owner). Public → cleans them up.                                                                                                                                             |
| Ownership Model              | Prefers direct `categories.community_user` ownership, supports legacy `categories.user_id`, inherits ownership to descendants of owned roots, and falls back to an “exclusive contributor” heuristic when no explicit owner exists. |
| Immediate Visibility Updates | Purges `user_cache` so privacy changes take effect for other users right away.                                                                                                                                                      |
| Progressive Enhancement      | Works without JavaScript (JS just improves layout).                                                                                                                                                                                 |
| Internationalization         | English, French, Slovak, Spanish, Hungarian, Russian, Ukrainian, and Chinese translations shipped; easily extendable.                                                                                                               |
| Accessibility                | Native form controls, labelled groups, no keyboard traps.                                                                                                                                                                           |
| Representative Image         | Owners can choose or clear an album cover image from photos already inside that album.                                                                                                                                              |
| Test Coverage                | PHPUnit logic tests + Cypress workflow scaffold in CI, including descendant-album ownership scenarios.                                                                                                                              |

---

## ⚠️ Prerequisite: Community Plugin

For first‑class ownership detection this plugin expects the **Community** plugin to be installed and active, because current Community versions add the `community_user` column to the `categories` table. Legacy Community installs may still use `user_id`.  
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
| Styling                                            | Profile UI relies on the active theme; album-page toggle ships a lightweight plugin stylesheet.      |
| Tests (`tests/`)                                   | In‑memory DB simulation for deterministic logic tests.                                               |

The server always performs validation; the browser never “decides” ownership.

---

## 🔐 Ownership & Security Model

1. Attempt direct ownership using `categories.community_user` when present, or legacy `categories.user_id` when running against older Community installs.
2. If an album has no direct owner, walk its ancestor chain using `uppercats` or `id_uppercat`; if an ancestor is directly owned by the user, that descendant is treated as inherited ownership.
3. If no explicit owner exists anywhere in the tree, compute exclusive‑contributor ownership: albums where `COUNT(DISTINCT added_by) == 1` and that `added_by` equals the logged user.
4. Explicit child ownership blocks inheritance from a parent owner.
5. Each submitted album ID is re‑checked before any write.
6. Only `name`, `comment`, and `status` can be changed; unexpected fields are ignored.
7. Status toggle drives permission synchronization + user cache purge.
8. Output is fully escaped to prevent XSS.

---

## 🔄 Privacy State Transitions

| State Change     | Actions Performed                                                                                                              |
| ---------------- | ------------------------------------------------------------------------------------------------------------------------------ |
| Public → Private | Remove prior explicit rows (clean slate), add `user_access` rows for admin (id=1) and the effective owner, purge `user_cache`. |
| Private → Public | Delete album’s `user_access` rows, purge `user_cache`.                                                                         |

Cache purge ensures other sessions immediately reflect visibility changes without waiting for natural expiration.

---

## 🧩 Progressive Enhancement

Without JavaScript: classic profile form submission still works where the theme posts `profile.php`.  
With JavaScript: the album manager is injected into the live profile form and saved through a CPT webservice endpoint on AJAX-driven profile pages.

---

## 🌐 Internationalization

Add a new locale: copy `language/en_UK/plugin.lang.php` → `language/<locale>/plugin.lang.php` and translate values. All user‑visible strings are keyed.

---

## ♿ Accessibility Commitments

- `<fieldset aria-label="My Galleries">` groups controls (legend removed to avoid duplicate visual heading).
- Each album sub-card header visually separates albums (can become an actual `<h5>` later).
- Pure native inputs; checkbox focus and keyboard behavior unchanged.
- Representative image selection currently uses native buttons and a lazy-loaded picker list; a richer keyboard model can be added later if the UI becomes denser.

---

## 🛠 Installation

1. Install and activate the **Community** plugin (recommended).
2. Copy this directory to `piwigo/plugins/core_privacy_toggle/` or install via Piwigo’s plugin manager if published.
3. Activate Core Privacy Toggle in the Piwigo admin panel.
4. (Optional) Clear compiled templates/cache for immediate markup/JS pickup.
5. Visit a user’s Profile page (`profile.php`) to confirm the “My Galleries” card is present when the user owns qualifying albums.

---

## 🎨 Theme Compatibility (Current Status)

This Phase 1 implementation was visually tuned against the **Bootstrap Darkroom** theme. Other themes behave as follows:

| Theme              | Status / Observed Behavior     | Notes                                                                                                            |
| ------------------ | ------------------------------ | ---------------------------------------------------------------------------------------------------------------- |
| Bootstrap Darkroom | Optimized                      | Baseline spacing, card borders, and typography aligned.                                                          |
| Modus              | Minor style drift              | Card padding & heading weight differ; needs small CSS overrides.                                                 |
| Elegance           | Minor style drift              | Similar to Modus; neutral styling but lacks card shadow consistency.                                             |
| Smart Pocket       | Supported with album-page shim | Public album privacy toggle is injected with plugin JS/CSS because the theme skips the usual public plugin slot. |

Planned adjustments (Phase 2 or community PRs welcome):

1. Add per-theme lightweight override file (auto-selected via theme id) to normalize spacing & typography.
2. Provide a no-frills fallback style block when advanced theme classes are absent.
3. Detect mobile/simplified themes (e.g. Smart Pocket) and switch to a single-column, borderless list layout.

Temporary Workarounds:

- Admins can drop a custom CSS file in `local/css/` targeting `.cpt-album` (or the wrapping `.cpt-album-manager` container) to adjust spacing or typography.

If you test additional themes, please open an issue with a screenshot + theme name to expand this matrix.

---

## ▶ Usage Flow

1. User opens Profile page.
2. Plugin injects the album management card if any owned (or exclusive) albums exist.
3. User edits fields and saves either through the classic profile form or the CPT AJAX endpoint used by theme-driven profile pages.
4. Plugin validates ownership, persists changes, syncs permissions if privacy altered, purges user cache, and adds a confirmation message.
5. Owned album pages also expose a direct privacy toggle on supported public/mobile themes.

---

## 🧪 Testing & CI

- **PHPUnit**: In‑memory harness fakes core tables (`categories`, `images`, `image_category`, `user_access`, `user_cache`).
- **Cypress**: Smoke coverage now includes descendant-album visibility, privacy toggle state changes, UCP descendant listing, owner-profile save/render, representative-image live picker flows, selected-user sharing, AJAX album-save interception/persistence, Smart Pocket mobile rendering, and optional explicit child-owner override scenarios when seeded.
- **Gherkin Features**: `/.github/features/ucp_album_management.feature` and `/.github/features/inherited_album_ownership.feature` enumerate richer scenarios for future automation.
- **CI**: GitHub Actions workflows run PHPUnit and Cypress on push / PR.

### Local Cypress Prerequisites

Use the local runtime assumptions below if you want the shipped Cypress spec to pass unchanged on another machine.

1. Start a local Piwigo runtime reachable at `http://127.0.0.1:8092`, or override it with `CYPRESS_baseUrl`.
2. Install Cypress dependencies in `/home/marcel/projects/piwigo/_qa/cypress` with `npm install`.
3. Enable the Smart Pocket mobile theme:

```sh
cd /home/marcel/projects/piwigo
/usr/bin/mariadb --socket="$PWD/.dev/mysql-run/mysqld.sock" -u piwigo piwigo_cpt -e "UPDATE piwigo_config SET value='smartpocket' WHERE param='mobile_theme';"
```

4. Seed the descendant-ownership scenario used by the spec:

```sh
cd /home/marcel/projects/piwigo
/usr/bin/mariadb --socket="$PWD/.dev/mysql-run/mysqld.sock" -u piwigo piwigo_cpt -e "UPDATE piwigo_categories SET community_user=6, id_uppercat=NULL, uppercats='1020' WHERE id=1020; UPDATE piwigo_categories SET community_user=NULL, id_uppercat=1020, uppercats='1020,1008', status='public' WHERE id=1008; UPDATE piwigo_categories SET community_user=NULL, id_uppercat=1020, uppercats='1020,1009', status='public' WHERE id=1009; UPDATE piwigo_categories SET community_user=NULL, id_uppercat=1020, uppercats='1020,1010', status='public' WHERE id=1010;"
```

5. The default local credentials expected by the spec are `slecna1` / `000`. Override with `CYPRESS_albumOwnerUser` and `CYPRESS_albumOwnerPass` if your runtime differs.
6. Run the suite with `npx cypress run --spec cypress/e2e/smoke.cy.ts`.
7. The shared-visibility scenario defaults to `slecna2` / `000` as the selected viewer. Override with `CYPRESS_sharedViewerUser` and `CYPRESS_sharedViewerPass` if your runtime differs.

Optional explicit child-owner override scenario:

1. Prepare a dedicated descendant album with a different direct owner, for example by assigning one child album to user `slecna2` (`id=7`) in your local database.
2. Run Cypress with `CYPRESS_childOverrideAlbumId`, and optionally `CYPRESS_childOverrideOwnerUser` / `CYPRESS_childOverrideOwnerPass`, to enable the override assertions.

Planned Phase 2 additions: coverage reports & multi‑browser matrix (Firefox).

---

## 🧭 Fallback (Limited) Mode

Displayed banner: “CPT: Limited mode enabled — only albums exclusively containing your photos are listed.”  
This prevents users from editing collaborative albums when direct ownership metadata is missing.
The fallback limited-mode banner and the album-free/no-qualifying-albums UCP absence path are now both covered in Cypress smoke.

---

## 🚧 Phase 2 (Draft Roadmap)

1. Coverage reporting & badge (clover + threshold gate).
2. Multi‑browser E2E (Firefox; optional WebKit/Playwright).
3. Ownership migration helper to normalize Community ownership metadata when needed.
4. Broaden CPT webservices for richer SPA/mobile clients.
5. Pagination or collapse behavior for very large album counts.

---

## 🔐 Security Posture (Summary)

- Server authoritative ownership verification per album ID.
- All dynamic SQL escaped; restricted update column set.
- Automatic permission synchronization & cache purge for privacy transitions.
- Output escaping in templates prevents XSS.

---

## ❓ FAQ

**Q: Why don’t I see any albums?**  
Either you don’t own any per Community ownership metadata (`community_user` on current installs, `user_id` on older ones) or, in fallback mode, there are no albums where all images were uploaded by you.

**Q: Can I bulk-toggle multiple albums?**  
Not in Phase 1—submit once for all changed rows though (batch via single form post). Bulk UI may arrive in a later phase.

**Q: Do I need JavaScript?**  
No. JS enhances layout only.

**Q: Where is representative image selection?**  
It is now available in the UCP album manager. Owners can choose a cover image from photos already assigned to that album, or clear the current cover image.

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

| Version           | Date       | Notes                                                                                                                                                          |
| ----------------- | ---------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| 0.1.1 (Phase 1.5) | 2026-06-18 | Added inherited ownership for descendant albums under Community-owned roots, tree-ordered retrieval, effective-owner permission sync, and regression coverage. |
| 0.1.0 (Phase 1)   | 2025-09-27 | Initial MVP: UCP album editing, privacy toggle, fallback mode, i18n (EN/FR), cache purge, tests & CI.                                                          |

---

## 🙌 Acknowledgments

- Piwigo Core & Community plugin authors.
- Test & feedback contributors.

Feel free to open issues for bugs, enhancement ideas, or accessibility feedback.

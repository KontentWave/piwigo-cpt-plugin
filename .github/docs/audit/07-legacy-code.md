# Legacy Code Audit — Core Privacy Toggle

**Scope:** dead code, skeleton remnants, deprecated patterns, compatibility shims,
orphaned assets. Each item lists a disposition recommendation.

---

## L1 — Piwigo plugin-skeleton remnants

| Location                                                                                                                                          | Remnant                                                                                                                               | Disposition                                                 |
| ------------------------------------------------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------- | ----------------------------------------------------------- |
| [maintain.class.php](../../../maintain.class.php)                                                                                                 | All five lifecycle methods empty with skeleton doc comments                                                                           | Implement (X2) — don't delete                               |
| [admin.php](../../../admin.php) + [admin/config.php](../../../admin/config.php) + [admin/template/config.tpl](../../../admin/template/config.tpl) | "Phase 1: no configuration needed" placeholder page                                                                                   | Grow into real config (X3) or remove menu link until needed |
| [main.inc.php L63-L67](../../../main.inc.php#L63-L67)                                                                                             | `if (defined('IN_ADMIN')) { /* no hooks retained */ }` empty block + "Removed: public page, menu blocks, demo webservice…" tombstones | Delete block & comments                                     |
| [include/admin_events.inc.php L17-L18](../../../include/admin_events.inc.php#L17-L18)                                                             | "Skeleton admin events removed" tombstones                                                                                            | Delete comments                                             |
| [language/en_UK/plugin.lang.php L3-L11](../../../language/en_UK/plugin.lang.php#L3-L11)                                                           | Skeleton advice comment + "Removed legacy skeleton/demo translation keys."                                                            | Delete comments                                             |
| [main.inc.php L10-L14](../../../main.inc.php#L10-L14)                                                                                             | "called by Piwigo in include/common.inc.php line 137"                                                                                 | Reword without line number                                  |

Note: `include/admin_events.inc.php` defines
`core_privacy_toggle_admin_plugin_menu_links` but **nothing registers or includes it**
in the current tree (`main.inc.php` never `require`s the file nor adds the
`get_admin_plugin_menu_links` handler). Verify: if truly unwired, the admin page is
unreachable — either wire it up or drop the whole admin surface for now.

## L2 — Dead functions (no callers in production or tests)

| Function                                           | Location                                                                        | Note                                                                                                                     |
| -------------------------------------------------- | ------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------ |
| `cpt_theme_uses_album_page_js_profile_placement()` | [functions.inc.php L236-L243](../../../include/functions.inc.php#L236-L243)     | Hardcodes `bootstrap_darkroom`; only referenced by its own definition. Delete or wire into the theme-adapter design (X4) |
| `cpt_get_album_explicit_owner_id()`                | [functions.inc.php L1168-L1174](../../../include/functions.inc.php#L1168-L1174) | Pure alias of `cpt_get_album_direct_owner_id()`                                                                          |
| `cpt_get_effective_owner_root_album_data()`        | [functions.inc.php L1002-L1018](../../../include/functions.inc.php#L1002-L1018) | Fetches root-album row; no callers — leftover from the unshipped owner-profile feature                                   |

(Verify with a final grep before deleting; `cpt_get_effective_owner_root_album_id_for_user`
is used only by the dead `..._data` function and should go with it if so.)

## L3 — Ghost feature: owner public profile

Shipped artifacts for a feature with **zero production code**:

- [tests/bootstrap.php](../../../tests/bootstrap.php): `CPT_OWNER_PROFILE_TABLE`, `CPT_MUNICIPALITY_TABLE`
  constants, `owner_profile` in-memory table, `owner_profile_table.tpl` renderer,
  INSERT/DELETE/SELECT emulation branches, `cpt_test_set_owner_profile_*` helpers.
- [language/en_UK/plugin.lang.php](../../../language/en_UK/plugin.lang.php): ~70 keys (Nationality, City, Measurements,
  weekday names, services vocabulary, "My Public Profile", …) — replicated across up to
  8 locales.
- JS: `.cpt-owner-profile-*` selectors in [js/album_page_toggle.js L38-L45](../../../js/album_page_toggle.js#L38-L45)
  de-duplicate markup that no production code emits.
- Docs: extensive owner-profile sections in [.github/docs/CPT_project_sheet.md](../CPT_project_sheet.md).

**Disposition:** strip from shipped code/tests/language files until the feature lands
(keep the spec in docs). This shrinks the PEM package, removes translator burden, and
un-confuses the test suite. Content note: this vocabulary also signals a
domain-specific deployment; a general-purpose PEM release should not ship it.

## L4 — Deprecated / era-marker patterns

| Pattern                                          | Location                                                                        | Assessment                                                                                                                                                                                                                     |
| ------------------------------------------------ | ------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| `stripslashes()` on WS input                     | [functions.inc.php L730](../../../include/functions.inc.php#L730)               | Required by current core behavior (core add-slashes), but magic-quotes-era smell — annotate with core version reference (S10)                                                                                                  |
| Legacy `user_id` ownership column support        | ownership detection                                                             | **Keep** — deliberate compat with older Community installs; documented ✔                                                                                                                                                       |
| `array()` long syntax mixed with `[]`            | throughout                                                                      | Normalize to `[]` (M2)                                                                                                                                                                                                         |
| ES5 JS (`var`, prototype iteration, no modules)  | both JS files                                                                   | Acceptable for legacy-browser reach, but the same files already use `fetch`, `URLSearchParams`, `closest`, arrow-function-free ES5 — i.e., IE support is _already broken_. Standardize on ES2017+ (`const/let`, `async/await`) |
| `$GLOBALS['__cpt_*']` test seams & caches        | [functions.inc.php L1256-L1288](../../../include/functions.inc.php#L1256-L1288) | Replace with injectable resolver (X1/M8)                                                                                                                                                                                       |
| Hardcoded user id `1`                            | permission sync                                                                 | Legacy Piwigo assumption — replace with `$conf['webmaster_id']` (S4)                                                                                                                                                           |
| `DESC <table>` for schema introspection          | `cpt_get_album_ownership_column`                                                | Works on MySQL/MariaDB only; fine for Piwigo, but `SHOW COLUMNS`/information_schema is the conventional form                                                                                                                   |
| Session key `is_admin` via `pwg_get_session_var` | debug/notice gating                                                             | Core never sets this key — effectively dead conditions; use `is_admin()` (S7)                                                                                                                                                  |

## L5 — Orphaned / stray assets

| Artifact                                                                                                                    | Issue                                                                                                                                           | Disposition                                                                      |
| --------------------------------------------------------------------------------------------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------- | -------------------------------------------------------------------------------- |
| [tools/https\_\_\_piwigo.local_albums_profile.html](../../../tools/https___piwigo.local_albums_profile.html)                | Saved crawl of a live local gallery page committed to repo                                                                                      | Delete (or move to test fixtures if actually referenced — nothing references it) |
| `vendor/` committed                                                                                                         | Dev-only dependencies in distribution tree                                                                                                      | Exclude from release package (M9)                                                |
| `language/fr_FR/`, `hu_HU/`, `ru_RU/`, `sk_SK/`, `uk_UA/`, `zh_CN/`                                                         | Folders exist; verify each contains `plugin.lang.php` + `index.php` guard (workspace listing shows only `es_ES` with a lang file besides en_UK) | Complete or remove empty locale dirs                                             |
| [language/en_UK/intro.html](../../../language/en_UK/intro.html), [description.txt](../../../language/en_UK/description.txt) | Verify contents match current feature set (PEM listing text)                                                                                    | Review at release                                                                |

## L6 — Test-suite legacy

- Emulator branches for queries production no longer issues (e.g.
  `SELECT COUNT(id) AS cnt FROM categories WHERE <col> = X`,
  `SELECT 1 FROM categories WHERE id=… AND <col>=…`,
  `SELECT user_id FROM categories WHERE id=…`) — the production code now fetches all
  categories and filters in PHP, so these regexes match nothing. Dead branches make
  the emulator look more covered than it is. Prune together with M6's
  fail-on-unknown-SQL change.
- `beStrictAboutTestsThatDoNotTestAnything="false"` in [phpunit.xml.dist](../../../phpunit.xml.dist) —
  legacy accommodation; remove and fix any assertion-less tests.

## Prioritized cleanup order

1. Wire or remove the unreachable admin menu (L1 — functional bug).
2. Delete dead functions + ghost-feature scaffolding (L2, L3).
3. Normalize style + remove tombstone comments (L1, L4/M2) in one mechanical commit.
4. Release hygiene: strip `vendor/`, `tools/` snapshot, unused language keys (L5).

# Core Privacy Toggle — Production Audit

**Audit date:** 2026-07-19
**Plugin version:** 1.0.0
**Scope:** All files in `plugins/core_privacy_toggle` (~3,950 LOC: PHP 2,300 / JS 650 / Smarty 100 / CSS 320 / tests 1,100)
**Target platform:** Piwigo 14+/15, PHP 7.4–8.x, MySQL/MariaDB, optional Community plugin

## Documents

| #   | Document                                                         | Area                                               |
| --- | ---------------------------------------------------------------- | -------------------------------------------------- |
| 1   | [01-security.md](01-security.md)                                 | Security (OWASP-aligned)                           |
| 2   | [02-performance-optimization.md](02-performance-optimization.md) | Optimization / performance                         |
| 3   | [03-expandability.md](03-expandability.md)                       | Potential for future expandability                 |
| 4   | [04-reliability.md](04-reliability.md)                           | Reliability / dependability                        |
| 5   | [05-maintainability.md](05-maintainability.md)                   | Maintainability                                    |
| 6   | [06-error-handling.md](06-error-handling.md)                     | Standardized error handling with custom exceptions |
| 7   | [07-legacy-code.md](07-legacy-code.md)                           | Legacy code inventory                              |

## Executive summary

The plugin is functionally solid and shows good security instincts at the boundaries
(ownership re-checks before every write, CSRF tokens on both webservice endpoints and the
album-page quick toggle, consistent output escaping in templates and JS, integer casting
of IDs, whitelisted status/visibility values). Unit-test coverage of the core ownership
and update logic is unusually good for a Piwigo plugin — since `589bc05` the suite is
tracked in the repository and runs green in CI (M9/M3 fixed).

The dominant production risks identified were **not** classic injection/XSS holes — they were:

1. **A per-request reconciliation job in the `init` hook** that scans the whole
   `categories` table with N+1 follow-up queries on _every page load for every
   user — anonymous guests included_, since Piwigo assigns guests a real user id
   (performance, scalability, availability). See
   [02-performance-optimization.md](02-performance-optimization.md) §P1.
   **✅ Fixed in `10b6dbd`.**
2. **Gallery-wide cache wipe on every privacy change** — the raw `user_cache`
   `DELETE` always fires; a `function_exists()`-guarded `invalidate_user_cache()`
   call (TRUNCATE by default in Piwigo 15, normally undefined on front-end paths)
   can add a second, heavier wipe. Amplified up to N× by save-all submissions. See
   [01-security.md](01-security.md) §S5 and [02-performance-optimization.md](02-performance-optimization.md) §P4.
   **✅ Fixed in `10b6dbd`** (single deferred `need_update` invalidation per save).
3. **Silent-failure error model** — every DB failure returns `null`/`false`/`[]`
   with no plugin-level logging (core `pwg_query()` only emits a generic SQL
   warning). The major consistency hole in multi-statement privacy transitions is
   now fixed with a MyISAM-safe snapshot/restore sequence, but the broader logging /
   exception model still remains open. See [04-reliability.md](04-reliability.md)
   and [06-error-handling.md](06-error-handling.md).
4. **Representative-image loader leaked `pwg_token` in a GET query string**
   (logs/history/referrer exposure). See [01-security.md](01-security.md) §S2.
   **✅ Fixed in `21e4aa4`.**

## Findings at a glance

| Severity | Count | Highlights                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                          |
| -------- | ----- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Critical | 0     | —                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                   |
| High     | 3     | Per-request full scan in `init` (P1, **✅ fixed**); global `user_cache` purge as DoS vector (S5/P4, **✅ fixed**); non-transactional privacy propagation (R2, **✅ fixed**)                                                                                                                                                                                                                                                                                                                                                                                                                                                                                         |
| Medium   | 16    | pwg_token in GET URL (S2, **✅ fixed**); user enumeration via shareable-user list (S3); hardcoded webmaster id 1 (S4, **✅ fixed**); no column whitelist in `cpt_update_album` (S6, **✅ fixed**); N+1 queries on profile page (P2); full-table descendant scan (P3); double script injection (P6); monolithic core with no seams (X1); empty `maintain.class.php` (X2); silent DB failures (R1); surprising propagation edges (R3, **✅ fixed**); misleading session-flag mechanism (R4, **✅ fixed**); monolithic 1,317-line functions file (M1); CI present but non-functional (M3, **✅ fixed**); documentation drift (M4); 570-line regex SQL test double (M6) |
| Low      | 14+   | See individual documents                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                            |
| Info     | 10+   | Legacy inventory, dead code, doc drift                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                              |

## Top 10 prioritized recommendations

1. ~~**Remove or gate the `init`-hook reconciliation**~~ **✅ Done** (`10b6dbd`) — the `init` call was removed; propagation is event-driven at save time. (High, P1)
2. ~~**Replace the gallery-wide cache wipe with `invalidate_user_cache(false)` once
   after the complete save**~~ **✅ Done** (`10b6dbd`) — single deferred invalidation per
   save; `need_update='true'` UPDATE on front-end paths where core's function is
   undefined; idempotent re-saves skip invalidation. (High, S5/P4)
3. ~~**Wrap privacy transitions (status + descendants + `user_access` sync) in a transaction**~~ **✅ Superseded** — Piwigo 15 stores `categories` and `user_access` as MyISAM, so transaction statements do not protect these writes. **✅ Done correctly in `139e844`** with snapshot/verify/restore semantics, restore verification, and critical logging on restore failure. (High, R2)
4. ~~**Move `pwg_token` out of the GET query string** in `js/ucp_tabs.js` (`loadRepresentativeOptions`) — use POST.~~ **✅ Done** (`21e4aa4`). (Medium, S2)
5. **Introduce the custom exception hierarchy + boundary handlers** defined in [06-error-handling.md](06-error-handling.md); log every swallowed DB failure. (Medium)
6. ~~**Whitelist updatable columns inside `cpt_update_album()`** (`name`, `comment`, `status`) as defense-in-depth.~~ **✅ Done** (`139e844`). (Medium, S6)
7. **Batch queries in `cpt_fetch_albums_owned_by()`** — one query for categories, one for owners, one for `user_access`, one for representatives. (Medium, P2)
8. ~~**Replace hardcoded user id `1`** with `$conf['webmaster_id']` / admin-status logic.~~ **✅ Done** (`139e844`). (Medium, S4)
9. **Fill in `maintain.class.php`** — create config defaults on install, clean up config and session flags on uninstall. (Medium, X2)
10. **Delete dead code and leftover artifacts** (dead functions, owner-profile test stubs, ~70 unused language keys, `tools/` HTML snapshot). (Low, L-series)

## Method

Manual line-by-line review of all PHP/JS/TPL/CSS files, cross-referenced against Piwigo
core behavior (`profile.php` token handling, `ws.php` slashes handling, `user_cache`
semantics) and the plugin's own test suite (untracked at audit time; tracked and
running in CI since `589bc05`). No dynamic scanning was performed.

## Revision history

- **2026-07-20 (remediation, round 3)** — `139e844` + `21e4aa4`: **R2, R3, S2, S4, S6 fixed**.
  - `139e844`: replaced the incorrect transaction-based R2 remediation with a
    MyISAM-safe snapshot/verify/restore sequence for root status, descendant statuses,
    and `user_access` rows; failures now restore and verify the pre-write snapshot,
    escalate restore failures to critical logs, invalidate caches on restore failure,
    and surface safe user-facing failures. The same commit also replaced hardcoded
    webmaster id `1` with `$conf['webmaster_id']`, joined `user_infos.status` for
    shareable-user admin filtering, enforced the `name`/`comment`/`status` whitelist
    inside `cpt_update_album()`, and fixed shared/public descendant propagation.
  - `21e4aa4`: moved representative-image loading from GET to POST so `pwg_token`
    never appears in the request URL.
  - Verification: local PHPUnit green at **41 tests / 152 assertions**, GitHub
    Actions green on `21e4aa4`, and live smoke on Piwigo 15 + Community +
    MariaDB/MyISAM covered public/private/shared transitions, descendant propagation,
    two-user cache visibility, custom webmaster id, non-owner denial, and POST-only
    representative-image requests.
- **2026-07-19 (remediation, round 2)** — `9ffc60a`: **R2 fixed** — privacy
  transitions were documented as transaction-safe, but that claim proved incorrect on
  Piwigo 15 because `categories` and `user_access` are MyISAM. Superseded by the
  2026-07-20 remediation above.
- **2026-07-19 (remediation, round 1)** — fixes landed and verified by CI:
  - `589bc05` + `4116921`: **M3/M9 fixed** — `tests/` and `phpunit.xml.dist` tracked,
    `phpunit.yml` rewritten for the standalone repo (checkout into
    `plugins/core_privacy_toggle` to satisfy main.inc.php's folder-name guard),
    stale `cypress.yml` removed. First green PHPUnit status on `4116921`.
  - `10b6dbd`: **P1, P4/S5, R4 fixed** — `init`-hook reconciler and
    `cpt_permissions_changed` session flag removed; raw `user_cache` DELETE replaced
    by one deferred `need_update='true'` invalidation per save request
    (`invalidate_user_cache(false)` in admin context); sync function reports change
    status so no-op saves skip invalidation. 29 tests / 96 assertions green.
  - Remaining release blocker at the time: **R2** (fixed in round 2).
- **2026-07-19 (rev 3)** — corrections after second external review (ChatGPT "Sol"):
  - Cache-invalidation advice fixed: per-user targeting (owner/shared/guest) is
    **insufficient for public↔private changes** — every normal user may hold cached
    visibility. Recommend `invalidate_user_cache(false)` once after the complete
    save; target individual users only for shared-list-only edits (S5/P4/R4, rec #2).
  - P4 wipe count corrected: the core `invalidate_user_cache()` call is
    `function_exists()`-guarded and normally undefined on front-end paths — "always
    one raw wipe, potentially a second full truncation", not "twice".
  - README count fixed: **16** Medium findings (was reported as 10).
  - Test-suite references qualified: the suite exists only in the working tree; the
    repo tracks no tests or PHPUnit config, so CI cannot run them.
  - R1/A09 reworded to "no **plugin-level** structured logging" — core `pwg_query()`
    already emits SQL warnings via `trigger_error()`.
- **2026-07-19 (rev 2)** — corrections after external review (ChatGPT "Sol") and
  re-verification against code/core/git:
  - P1 broadened: reconciler also runs for **guest** requests (`$conf['guest_id']`
    gives guests a real user id); save-all submissions amplify the cache wipe N×.
  - S5/P4/R4 fix corrected: core `invalidate_user_cache()` **TRUNCATEs by default**
    in Piwigo 15 (only `false` sets `need_update`); recommend targeted invalidation,
    not removal of the purge.
  - S1 downgraded Medium → Low (core `profile.php` token check covers current wiring).
  - M3 rewritten: CI workflows **do exist** but are non-functional (wrong monorepo
    paths, gitignored `tests/`/`phpunit.xml.dist`, unreachable Cypress base URL).
  - M7/M9/L5 corrected: all 8 locales ship lang files; `vendor/`/`tools/` are
    gitignored, not committed; the gitignored **test suite** is the real repo gap.
- **2026-07-19 (rev 1)** — initial audit.

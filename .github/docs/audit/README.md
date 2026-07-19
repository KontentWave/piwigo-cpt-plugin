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
and update logic is unusually good for a Piwigo plugin.

The dominant production risks are **not** classic injection/XSS holes — they are:

1. **A per-request reconciliation job in the `init` hook** that scans the whole
   `categories` table with N+1 follow-up queries on _every page load for every
   logged-in user_ (performance, scalability, availability). See
   [02-performance-optimization.md](02-performance-optimization.md) §P1.
2. **Full `user_cache` table deletion** triggered by a non-admin user action
   (self-inflicted DoS vector on large galleries). See
   [01-security.md](01-security.md) §S5 and [02-performance-optimization.md](02-performance-optimization.md) §P4.
3. **Silent-failure error model** — every DB failure returns `null`/`false`/`[]`
   with no logging, and multi-statement privacy transitions are not transactional,
   so partial failures leave albums in inconsistent permission states. See
   [04-reliability.md](04-reliability.md) and [06-error-handling.md](06-error-handling.md).
4. **CSRF token sent as a GET query parameter** by the representative-image AJAX
   loader (token leakage via logs/referrer). See [01-security.md](01-security.md) §S2.

## Findings at a glance

| Severity | Count | Highlights                                                                                                                                                                                                                                                                                                                          |
| -------- | ----- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Critical | 0     | —                                                                                                                                                                                                                                                                                                                                   |
| High     | 3     | Per-request full scan in `init` (P1); global `user_cache` purge as DoS vector (S5/P4); non-transactional privacy propagation (R2)                                                                                                                                                                                                   |
| Medium   | 9     | pwg_token in GET URL (S2); user enumeration via shareable-user list (S3); hardcoded webmaster id 1 (S4); no column whitelist in `cpt_update_album` (S6); N+1 queries on profile page (P2); double script injection (P6); silent DB failures (R1); misleading session-flag mechanism (R4); monolithic 1,317-line functions file (M1) |
| Low      | 14+   | See individual documents                                                                                                                                                                                                                                                                                                            |
| Info     | 10+   | Legacy inventory, dead code, doc drift                                                                                                                                                                                                                                                                                              |

## Top 10 prioritized recommendations

1. **Remove or gate the `init`-hook reconciliation** (`cpt_reconcile_private_owner_root_descendants_for_user`) behind an event-driven trigger or a cheap sentinel check — do not run it on every request. (High, P1)
2. **Replace `cpt_purge_user_cache()` full-table `DELETE`** with Piwigo's sanctioned `invalidate_user_cache()` mechanism only. (High, S5/P4)
3. **Wrap privacy transitions (status + descendants + `user_access` sync) in a transaction** or at least an ordered, verified sequence with rollback-on-failure. (High, R2)
4. **Move `pwg_token` out of the GET query string** in `js/ucp_tabs.js` (`loadRepresentativeOptions`) — use POST. (Medium, S2)
5. **Introduce the custom exception hierarchy + boundary handlers** defined in [06-error-handling.md](06-error-handling.md); log every swallowed DB failure. (Medium)
6. **Whitelist updatable columns inside `cpt_update_album()`** (`name`, `comment`, `status`) as defense-in-depth. (Medium, S6)
7. **Batch queries in `cpt_fetch_albums_owned_by()`** — one query for categories, one for owners, one for `user_access`, one for representatives. (Medium, P2)
8. **Replace hardcoded user id `1`** with `$conf['webmaster_id']` / admin-status logic. (Medium, S4)
9. **Fill in `maintain.class.php`** — create config defaults on install, clean up config and session flags on uninstall. (Medium, X2)
10. **Delete dead code and leftover artifacts** (dead functions, owner-profile test stubs, ~70 unused language keys, `tools/` HTML snapshot). (Low, L-series)

## Method

Manual line-by-line review of all PHP/JS/TPL/CSS files, cross-referenced against Piwigo
core behavior (`profile.php` token handling, `ws.php` slashes handling, `user_cache`
semantics) and the plugin's own test suite. No dynamic scanning was performed.

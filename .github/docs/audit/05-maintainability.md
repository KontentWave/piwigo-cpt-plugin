# Maintainability Audit — Core Privacy Toggle

**Scope:** code organization, style consistency, documentation accuracy, test quality,
tooling.
**Verdict:** the logic is readable function-by-function, and naming is consistent
(`cpt_` prefix throughout). The main threats to maintainability are the single giant
include file, a brittle hand-rolled SQL emulator that must be extended for every new
query, documentation drift, and style inconsistency that suggests multiple authoring
eras without a formatter.

---

## Findings

### M1 — MEDIUM — 1,317-line single include with 40+ functions

[include/functions.inc.php](../../../include/functions.inc.php) — see [03-expandability.md](03-expandability.md) §X1 for the proposed
decomposition. Even without classes, splitting into `include/ownership.inc.php`,
`include/persistence.inc.php`, `include/ws.inc.php`, `include/render.inc.php` would cut
review scope per change dramatically.

### M2 — LOW — Inconsistent code style within and across files

- Indentation: tabs in most of functions.inc.php, but `cpt_handle_album_form` mixes
  4-space and tab indentation mid-function ([functions.inc.php L745-L822](../../../include/functions.inc.php#L745-L822)).
- Array syntax: `array()` and `[]` used interchangeably, sometimes in the same function.
- Single-line guarded statements (`if (...) { return; }`) vs. expanded blocks vary.
- Mixed quoting and spacing in SQL string assembly.

**Recommendation:** adopt PER-CS/PSR-12 via `friendsofphp/php-cs-fixer` or `phpcbf`
(dev dependency), plus Prettier for JS/CSS. One-time reformat commit, then CI check.

### M3 — MEDIUM — CI present but non-functional

> **✅ FIXED** in `589bc05` + `4116921` (2026-07-19): `tests/` and `phpunit.xml.dist`
> are now tracked; `phpunit.yml` was rewritten for the standalone repo layout and
> checks out into `plugins/core_privacy_toggle` (main.inc.php's folder-name guard);
> the stale Cypress workflow was removed. First green run: PHPUnit on `4116921`.
> Still open from the recommendation: phpstan + cs-check steps.

Workflows exist ([phpunit.yml](../../workflows/phpunit.yml), cypress.yml — since removed) but cannot succeed as configured:

- Both trigger on and reference monorepo paths (`albums/plugins/core_privacy_toggle/**`)
  that do not exist in this repository — the repo root **is** the plugin, so the path
  filters never match and the phpunit config path is wrong.
- [.gitignore](../../../.gitignore) excludes **`/tests/` and `phpunit.xml.dist`** — the repository contains
  no test suite at all, so even with corrected paths the PHPUnit job has nothing to run.
- The Cypress job depends on `_qa/cypress/**` (lives in the Piwigo monorepo, not this
  repo) and defaults to `https://piwigo.local/albums`, unreachable from GitHub runners.
- Consequently the audit commit shows zero checks/runs.

**Recommendation:** track `tests/` and `phpunit.xml.dist` in git; fix workflow paths to
repo-root layout; either vendor the Cypress suite into this repo with a CI-provisioned
Piwigo instance or move that workflow to the monorepo where `_qa/cypress` lives. Then
add `phpstan/phpstan` (level 6 is attainable — the code already uses scalar type hints
and nullable returns) with a Piwigo core stub file, plus a cs-check step.

### M4 — MEDIUM — Documentation drift and misleading comments

- README: "Only `name`, `comment`, and `status` can be changed" — representative
  picture and `user_access` sharing are also changed.
- README: "Works without JavaScript (JS just improves layout)" — the album manager
  partial is **only injected by JS** (`cpt_inject_album_manager_assets` exposes it via
  `window.CPT_ALBUM_HTML`; there is no server-rendered fallback into the profile form
  besides the `PLUGINS_PROFILE` block — verify which path actually renders per theme
  and align the claim).
- README points to `.github/CPT_project_sheet.md`; the file lives at
  [.github/docs/CPT_project_sheet.md](../CPT_project_sheet.md).
- `main.inc.php` comment "called by Piwigo in include/common.inc.php line 137" — brittle
  line-number reference.
- The "other sessions" comment on the session flag is wrong (see
  [04-reliability.md](04-reliability.md) §R4).
- Multiple "Removed: …" tombstone comments narrate history that belongs in git
  ([main.inc.php L63-L67](../../../main.inc.php#L63-L67), [admin_events.inc.php L17-L18](../../../include/admin_events.inc.php#L17-L18), language file).

### M5 — LOW — Duplicated logic

- `cpt_inject_album_page_assets()` and `cpt_inject_album_manager_assets()` share the
  JSON-encode-and-inline pattern with different flag names — extract a helper.
- `cpt_count_albums_owned_by()` is `count(cpt_fetch_albums_owned_by())` — the full
  fetch (with representative lookups!) runs just to produce a count that gates an early
  return, after which `cpt_fetch_albums_owned_by()` **runs again**
  ([functions.inc.php L32](../../../include/functions.inc.php#L32) + [L51](../../../include/functions.inc.php#L51)). Fetch once, count locally.
- `cpt_get_album_explicit_owner_id()` is an alias of `cpt_get_album_direct_owner_id()`
  with no callers (dead — see [07-legacy-code.md](07-legacy-code.md)).
- Ancestor resolution exists twice (`uppercats` parse + iterative `id_uppercat` walk);
  the fallback walk is only needed when `uppercats` is corrupt — Piwigo maintains it.

### M6 — MEDIUM — Test double is a 570-line regex SQL interpreter

[tests/bootstrap.php](../../../tests/bootstrap.php) re-implements MySQL with `preg_match` per query shape. Every
new/changed query in production code requires a matching regex, silently returning an
empty result otherwise — i.e., a production query change can pass tests while being
completely unexercised (the default branch returns `new ArrayIterator([])`).

It also emulates tables/queries that no production code issues (owner-profile,
municipality) — dead test scaffolding (X7/L6).

**Recommendation:**

- Short term: make the emulator **fail loudly** on unrecognized SQL
  (`throw new RuntimeException('Unhandled test SQL: '.$sql)`), and delete the
  owner-profile branches.
- Medium term: route all SQL through a repository layer (X1) and test repositories
  against SQLite/MySQL in CI; test services against an in-memory repository fake —
  no regex needed.

### M7 — LOW — Language file hygiene

[language/en_UK/plugin.lang.php](../../../language/en_UK/plugin.lang.php) contains ~70 keys with no consumer in the
codebase (owner-profile vocabulary: Nationality, Measurements, availability weekdays,
service descriptions, etc.). All 8 locales ship `plugin.lang.php` files ✔ — which
means the unused keys multiply translator effort eightfold.

### M8 — INFO — Naming & API notes

- Function names are long but self-describing ✔ (`cpt_reconcile_private_owner_root_descendants_for_user`).
- `cpt_update_album(int $album_id, array $fields, bool $debug=false, array $permission_options = [], ?int $owner_user_id = null)` —
  a boolean debug flag and two optional arrays is a growing-parameter smell; an options
  object/array or the service split (X1) resolves it.
- `$GLOBALS['__cpt_force_ownership_column']` / `__cpt_ownership_column_cache` — test
  seams living in production code; acceptable if documented, better replaced by an
  injectable resolver (X1).

### M9 — INFO — Repository artifacts

> **✅ PARTLY FIXED** in `589bc05` (2026-07-19): `tests/` and `phpunit.xml.dist` are
> now tracked and run in CI. The `tools/` HTML snapshot and release-checklist items
> below remain open.

- `vendor/`, `tools/`, `tests/`, and `phpunit.xml.dist` are **gitignored** (present
  only in the working tree). Good for vendor/tools; **wrong for the test suite** —
  the published repo carries no tests and CI cannot run them (see M3). Track `tests/`
  and `phpunit.xml.dist`.
- [tools/https\_\_\_piwigo.local_albums_profile.html](../../../tools/https___piwigo.local_albums_profile.html) — captured crawl of a real
  local gallery page in the working tree; untracked, but delete locally to avoid
  accidental future inclusion (information-leak concern).
- [pem_metadata.txt](../../../pem_metadata.txt) — keep in sync with main.inc.php header on release (add a
  release checklist).

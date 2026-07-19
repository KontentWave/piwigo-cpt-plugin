# Expandability Audit — Core Privacy Toggle

**Scope:** architecture headroom for the roadmap (owner public profiles, more field
types, more themes, Piwigo/Community version drift).
**Verdict:** the plugin's _behavioral_ extension points are decent (hooks fired, WS
methods, template partials), but the _code structure_ — one 1,317-line procedural file
with global state — will resist Phase 2+ growth. Several roadmap features already leak
into the codebase as dead scaffolding, which shows the friction.

---

## Findings

### X1 — MEDIUM — Monolithic procedural core with no seams

[include/functions.inc.php](../../../include/functions.inc.php) mixes at least six responsibilities:

| Responsibility                 | Representative functions                                                                      |
| ------------------------------ | --------------------------------------------------------------------------------------------- |
| Hook orchestration / rendering | `cpt_setup_ucp_tabs`, `cpt_attach_album_page_toggle`, `cpt_inject_*`                          |
| Ownership resolution           | `cpt_get_album_ownership_rule_for_user`, `cpt_get_album_effective_owner_id`, ancestor walkers |
| Album persistence              | `cpt_update_album`, `cpt_get_album_status`, representative CRUD                               |
| Permission sync                | `cpt_sync_album_visibility_permissions`, `cpt_purge_user_cache`                               |
| Form/WS input handling         | `cpt_handle_album_form`, `cpt_ws_*`                                                           |
| Schema detection               | `cpt_get_album_ownership_column`                                                              |

**Recommendation:** introduce a namespaced class layer (PSR-4 via the existing
composer.json, `"autoload": {"psr-4": {"Cpt\\": "src/"}}`) while keeping the flat
`cpt_*` functions as thin wrappers for hook registration:

```
src/
  Ownership/OwnershipResolver.php     (column detection + rules + caches)
  Repository/AlbumRepository.php      (all category/image/user_access SQL)
  Service/PrivacyService.php          (update + propagation + permission sync)
  Service/RepresentativeService.php
  Http/ProfileController.php          (hook handlers)
  Http/WsController.php               (webservice methods)
  Support/AssetInjector.php
  Exception/…                         (see 06-error-handling.md)
```

This immediately enables: dependency injection of a DB adapter (unblocking real unit
tests without the regex SQL emulator — see M6), per-request cache objects instead of
`static`/`$GLOBALS` caches, and safe reuse by other plugins.

### X2 — MEDIUM — `maintain.class.php` is entirely empty

[maintain.class.php](../../../maintain.class.php) — all five lifecycle methods are no-ops, yet the plugin:

- reads `$conf['core_privacy_toggle']` (never created → `safe_unserialize` on a
  missing key every init),
- writes `$_SESSION['cpt_permissions_changed']` (never cleaned on deactivate),
- will need schema for roadmap features (owner-profile table already exists in the
  test bootstrap as `CPT_OWNER_PROFILE_TABLE`).

**Recommendation:** implement `install()` (default serialized config + future schema
via `CREATE TABLE IF NOT EXISTS`), `update()` (version-gated migrations — the hook
plumbing exists precisely for this), and `uninstall()`
(`conf_delete_param('core_privacy_toggle')`, drop plugin tables). This is the single
most important enabler for shipping Phase 2 database features safely.

### X3 — LOW — No configuration surface despite obvious knobs

[admin/config.php](../../../admin/config.php) is a static placeholder. Behaviors that should be admin-tunable
before wider deployment:

- enable/disable exclusive-contributor fallback mode (security-relevant, S8),
- shareable-user scope (S3),
- descendant privacy propagation on/off,
- album-page quick toggle on/off per theme,
- payload size limits.

The plumbing (admin page, template, menu link) already exists — only the form and a
`conf_update_param` call are missing. Design the config array schema now to avoid
migration churn later.

### X4 — LOW — Theme coupling is heuristic and hardcoded

- `cpt_theme_uses_album_page_js_profile_placement()` hardcodes `bootstrap_darkroom`
  and is currently **dead code** ([functions.inc.php L236-L243](../../../include/functions.inc.php#L236-L243)).
- [js/ucp_tabs.js L470-L487](../../../js/ucp_tabs.js#L470-L487) carries a 7-entry selector waterfall to find the
  profile form; [js/album_page_toggle.js L57-L66](../../../js/album_page_toggle.js#L57-L66) hardcodes Bootstrap-Darkroom
  anchors (`#content-description-mobile`, …).

**Recommendation:** centralize theme adapters (a small JS map keyed by
`get_themeconf('id')` exported server-side, or a filterable
`trigger_change('cpt_theme_anchors', …)`) so new themes are a data change, not a code
change across two JS files.

### X5 — LOW — Extension events are asymmetric

The plugin fires `CPT_after_privacy_change` (good) but offers no _before_ event, no
event for name/comment edits, no filter over the album list, and no filter over
shareable users. Other plugins cannot veto or augment behavior.

**Recommendation:** add filterable triggers:
`cpt_editable_albums` (trigger_change on the fetched list),
`cpt_before_album_update` (veto/modify fields),
`cpt_shareable_users` (scope control — also solves S3 extensibly).

### X6 — INFO — Versioning/compat scaffolding

- Plugin version exists only in the `main.inc.php` header comment; define a
  `CORE_PRIVACY_TOGGLE_VERSION` constant for asset cache-busting (P7) and migrations (X2).
- No PHP/Piwigo minimum documented in code; the codebase uses `?->`-free but
  `fn()`/arrow functions and typed properties in tests → effectively PHP 7.4+. Declare
  it in composer.json (`"require": {"php": ">=7.4"}`) and README.
- WS method names (`core_privacy_toggle.*`) are well-namespaced ✔ — keep this pattern.

### X7 — INFO — Roadmap features already ghosting through the code

The test bootstrap contains full emulation for an owner-profile feature
(`CPT_OWNER_PROFILE_TABLE`, `owner_profile_table.tpl` renderer, municipality table),
and ~70 language keys for it exist in [language/en_UK/plugin.lang.php](../../../language/en_UK/plugin.lang.php),
but zero production code ships. This "ghost feature" state confuses contributors and
inflates the PEM package. Either land the feature behind a flag or strip the
scaffolding until it lands (see [07-legacy-code.md](07-legacy-code.md) §L6).

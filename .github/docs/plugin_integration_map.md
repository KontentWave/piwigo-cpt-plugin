# Plugin Integration Map

## Plugin dependency map

- `core_privacy_toggle`: not in the controlling path for Community upload target selection; descendant-album filtering here does not drive Community's `add_photos` selector.
- `core_privacy_toggle`: does own the non-admin album privacy action for Community-owned user trees; `cpt_update_album()` now propagates `private` status (mode strictly `private`) to descendants when the toggled album is the effective owner root. The former `init`-hook reconciler (per-request full-catalog scan, audit P1) was removed — propagation is enforced event-driven at save time; `cpt_reconcile_private_owner_root_descendants_for_user()` remains available for explicit event-driven healing. `shared` mode does NOT propagate to descendants.
- `community`: owns user-root album creation, upload/create permission computation, session permission caching, and the upload-page selector refresh path.
- `two_factor`: participates only at the authenticated-session boundary by redirecting to `identification.php?tf` and now clearing Community-owned session permission caches before and after validation.

## Shared Piwigo hooks/events

- `try_log_user`: `two_factor` intercepts successful primary login in `tf_try_log_user()` and moves the session into pending 2FA validation.
- `loc_end_index`: `community_index()` routes `add_photos` and `edit_photos` requests into Community-owned page controllers.
- `loc_begin_profile`, `loc_begin_index`, `loc_end_index`: CPT owns the front-end owner-facing privacy actions from the profile/UCP flow and the public album-page quick toggle.
- `ws_add_methods`: Community registers `community.categories.getList`, which is the filtered non-admin album list intended for upload selectors.

## Shared database tables

- `categories`: Community marks user root albums with `community_user` and computes descendant upload targets from the category tree.
- `categories`: CPT updates privacy with per-row `UPDATE ... WHERE id = ... LIMIT 1` statements (parent + one per descendant when propagating) instead of core's recursive `set_cat_status()` / `get_subcat_ids()`; descendant discovery scans the whole `categories` table and the sequence is non-transactional.
- `user_cache_categories`: Community category visibility depends on this core cache table being refreshed after root-album creation.
- `user_infos`: `two_factor` clears lockout state here during successful post-validation login completion.

## Shared config keys

- `community_cache_key`: Community global cache-buster for per-session permission payloads.
- `two_factor`: plugin config controls whether the user is redirected into the pending 2FA challenge before reaching Community album management pages.

## Shared services/includes

- `plugins/community/include/functions_community.inc.php`: computes `community_user_permissions`, auto-creates user root albums, and stores Community session cache keys.
- `plugins/community/template/add_photos.tpl`: client-side upload form, selector refresh, and upload-start validation logic.
- `plugins/core_privacy_toggle/include/functions.inc.php`: `cpt_update_album()` syncs `user_access` for the album and (for owner-root private toggles) all descendants, then marks the user cache dirty; the owning entry point flushes once per request via `cpt_flush_user_cache_invalidation()` → `cpt_invalidate_user_cache()`, which sets `need_update='true'` on `user_cache` (or calls core `invalidate_user_cache(false)` in admin context) instead of the former gallery-wide `DELETE`.
- `plugins/two_factor/includes/events.inc.php`: `tf_try_log_user()` marks the session unvalidated and clears integrated plugin caches.
- `plugins/two_factor/includes/functions.inc.php`: `tf_login_and_redirect()` clears Two Factor state and integrated plugin caches before redirecting to the gallery.
- `include/menubar.inc.php`: core renders the Albums dropdown summary from `$user['nb_total_images']`, independent of whether any top-level album node remains visible in the menu tree.
- `admin/include/functions.php`: provides `create_virtual_category()` and `invalidate_user_cache()` used by Community's root-album creation path, plus the recursive permission helpers core admin uses for sub-album privacy propagation.

## Risky couplings

- Community caches upload permissions in `$_SESSION['community_user_permissions']`, `$_SESSION['community_cache_key']`, and `$_SESSION['community_user_id']`; stale values can survive across a partial login unless the integration clears them.
- Community's first-request root-album auto-create path depends on immediate core cache invalidation; without `invalidate_user_cache()`, the same request can compute upload targets from stale category visibility data.
- Community's upload-page selector must use `community.categories.getList`; falling back to generic `pwg.categories.getList` can reintroduce the top-level container album after same-page child-album creation.
- The upload UI remains deceptively usable unless validation is bound to the actual `#startUpload` control instead of dead selectors.
- A private Community root with still-public descendants produces a confusing state: the menu tree can become empty because the private root is hidden, while core still shows a non-zero Albums photo count from descendant public photos through `$user['nb_total_images']`.
- CPT recurses into descendants only for strict `private` owner-root toggles; a `shared` root leaves descendants untouched, and the init-hook reconciler later forces such trees fully private with an empty share list — silently discarding owner-configured shares.
- CPT's classic profile POST path has no local CSRF check; it depends on core `profile.php` calling `check_pwg_token()` before `loc_begin_profile`. Re-hosting the handler on another hook would silently drop CSRF protection.
- CPT's per-request `init` reconciliation performs full `categories` scans with N+1 lookups for every logged-in album owner on every page view — a shared-database load coupling affecting all plugins.
- Core `invalidate_user_cache()` (admin/include/functions.php) TRUNCATEs both `user_cache` and `user_cache_categories` when called with defaults (`$full = true`); the function is not loaded on front-end requests without an explicit include. CPT now avoids both hazards: it calls `invalidate_user_cache(false)` when the core function is loaded and otherwise issues the equivalent `need_update='true'` UPDATE directly, exactly once per save request.

## Last inspection notes

- 2026-07-11: confirmed the controlling behavior is outside `core_privacy_toggle`; Community owns upload target computation and selector refresh.
- 2026-07-11: verified local `two_factor` now clears Community session caches in both `tf_try_log_user()` and `tf_login_and_redirect()`.
- 2026-07-11: verified local Community now invalidates user/category cache after direct root-album creation and refreshes same-page selectors through `community.categories.getList`.
- 2026-07-16: inspected the lingering Albums dropdown photo count after privatizing a Community user root. This is not owned by Community or the theme: core only renders the count, while CPT currently privatizes the selected root album without propagating status/access changes to descendant image-holder albums.
- 2026-07-19: full production audit of CPT (see `.github/docs/audit/`). Verified core `profile.php` enforces `check_pwg_token()` before `loc_begin_profile` (CPT relies on it); confirmed CPT descendant propagation now exists for private owner roots but is non-transactional; flagged global `user_cache` DELETE and per-request reconciler as cross-plugin load risks; found CPT admin menu handler (`include/admin_events.inc.php`) is never included/registered, so the admin page link is unreachable.
- 2026-07-19 (rev 2): audit corrected after external review — CPT's `init` reconciler also runs for guest requests (guests have a real user id via `$conf['guest_id']`); core `invalidate_user_cache()` truncates by default (see risky couplings); CPT repo CI workflows exist but are non-functional (monorepo paths, gitignored test suite).
- 2026-07-19 (fixes): CI repaired (tests tracked, checkout into `plugins/core_privacy_toggle`); audit P1 fixed — `init`-hook reconciler removed along with the `cpt_permissions_changed` session flag (R4); audit P4/S5 fixed — raw `user_cache` DELETE replaced by a single per-request `need_update='true'` invalidation (deferred via dirty flag, flushed once after the complete save), and `cpt_sync_album_visibility_permissions()` now returns whether it changed anything so idempotent re-saves skip invalidation.

# Performance & Optimization Audit — Core Privacy Toggle

**Scope:** query patterns, hook cost, caching, asset delivery, front-end behavior.
**Verdict:** correctness-first code with several O(N) / N+1 patterns that are invisible
on small galleries but will dominate page cost on installations with hundreds of albums
or thousands of users. One pattern (P1) runs on **every request**.

---

## Findings

### P1 — HIGH — Full-catalog reconciliation runs on every page load for every logged-in user

[main.inc.php L96-L98](../../../main.inc.php#L96-L98) → `cpt_reconcile_private_owner_root_descendants_for_user()`
([include/functions.inc.php L1135-L1166](../../../include/functions.inc.php#L1135-L1166))

The `init` handler calls the reconciler for the current user on _every_ request. Per
request it executes:

1. `cpt_fetch_albums_owned_by()` — `SELECT id, name, comment, status FROM categories
ORDER BY id ASC` (**entire table**), then per row:
   - `cpt_get_album_effective_owner_id()` → owner lookup + ancestor walk (1–k queries),
   - for owned rows: `cpt_get_album_shared_user_ids()`, representative-picture lookup
     (2 queries), visibility mode (another status + user_access query), tree-sort-key
     ancestor lookups.
2. For each private owned root: `cpt_get_descendant_album_ids()` — **another full
   `categories` scan** per root.

On a gallery with 1,000 albums this is easily 1,000–3,000+ queries _per page view_ for
an album-owning user. The `static` per-request caches do not help across requests.

**Recommendations (in order of preference):**

- Make reconciliation event-driven only: it is already triggered where it matters
  (after `cpt_update_album` status changes). Remove the `init` call.
- If drift-healing is required, run it at most once per session
  (`pwg_get_session_var` sentinel) or behind a timestamped conf value (e.g. hourly),
  and only for users known to own albums (cheap indexed `EXISTS` probe first).

### P2 — MEDIUM — N+1 query pattern in `cpt_fetch_albums_owned_by()`

[include/functions.inc.php L333-L369](../../../include/functions.inc.php#L333-L369)

Ownership filtering happens in PHP over a full-table result instead of SQL. Each
retained album then triggers 5–8 additional queries via `cpt_build_album_editor_row()`.

**Recommendation:** batch into a fixed number of queries:

```sql
-- 1. candidate albums + ownership in one shot
SELECT id, name, comment, status, uppercats, representative_picture_id, <owner_col>
FROM categories;
-- 2. shared users for all candidate ids
SELECT cat_id, user_id FROM user_access WHERE cat_id IN (...);
-- 3. representative images
SELECT id, name, file, path, representative_ext FROM images WHERE id IN (...);
```

Resolve effective owners in PHP from the single result set (`uppercats` already encodes
the ancestor path — no per-album ancestor queries are needed at all). This collapses
~6 queries/album to 3 queries total and also fixes the tree-sort N+1
(`cpt_get_album_tree_sort_key` can use the already-fetched `uppercats`).

### P3 — MEDIUM — `cpt_get_descendant_album_ids()` scans the whole categories table

[include/functions.inc.php L1013-L1034](../../../include/functions.inc.php#L1013-L1034)

**Recommendation:** use the indexed materialized-path pattern Piwigo itself uses:

```sql
SELECT id FROM categories WHERE uppercats LIKE '<escaped_uppercats>,%'
```

(or `FIND_IN_SET(<id>, uppercats)` as core does), plus the descendant status updates in
**one** `UPDATE ... WHERE id IN (...)` instead of one `UPDATE ... LIMIT 1` per
descendant ([functions.inc.php L1199-L1213](../../../include/functions.inc.php#L1199-L1213)).

### P4 — HIGH — Full `user_cache` DELETE on every privacy change

[include/functions.inc.php L1300-L1310](../../../include/functions.inc.php#L1300-L1310)

See [01-security.md](01-security.md) §S5 for the abuse angle. Purely as performance:
after each toggle, _every_ user's next request pays the permission-recomputation cost,
plus the plugin also calls `invalidate_user_cache()` (which already marks rows for
rebuild) — the raw `DELETE` is redundant work with gallery-wide fallout. It also runs
`SHOW TABLES LIKE` on every invocation.

**Recommendation:** delete the function body down to `invalidate_user_cache()`. If a
targeted purge is needed, scope it to affected user ids.

### P5 — LOW — Repeated single-row lookups already answerable from prior results

Examples:

- `cpt_handle_album_page_toggle()` fetches album status via `cpt_get_album_status()`
  although `$page['category']['status']` is already loaded by core.
- `cpt_update_album()` re-reads previous status with a dedicated query although most
  callers already know it.
- `cpt_get_shareable_user_options()` is re-executed **inside the payload loop** of
  `cpt_handle_album_form()` for every album ([functions.inc.php L786](../../../include/functions.inc.php#L786)) — hoist it out
  of the loop (it is identical for each iteration).

### P6 — MEDIUM — UCP script/CSS injected twice per page

[include/functions.inc.php L620-L648](../../../include/functions.inc.php#L620-L648) (`cpt_inject_album_manager_assets`)

`ucp_tabs.js` is appended to **both** `head_elements` and `footer_msgs`, so browsers
download/parse/execute the 528-line script twice (the DOM guards prevent double
mutation, but not double execution). The inline bootstrap is likewise duplicated.

**Recommendation:** prefer `$template->scriptLoader` / `func_combine_script` (as
`cpt_prepare_album_page_toggle()` already correctly does) with a single injection point;
keep the head/footer fallback only when `scriptLoader` is absent.

### P7 — LOW — Asset versioning via `filemtime` on every request

Four `file_exists` + `filemtime` stat calls per profile view. Negligible alone; if the
plugin grows, switch to a version constant (also removes a failure mode on stat-cache
quirks / read-only deployments).

### P8 — LOW — Front-end nits

- [js/ucp_tabs.js L470-L487](../../../js/ucp_tabs.js#L470-L487): seven sequential `querySelector` attempts on every
  profile DOMContentLoaded — fine, but could be a single combined selector list.
- `renderRepresentativeOptions` builds HTML strings per image; large albums (1,000+
  photos) will render slowly and the WS endpoint has **no pagination/limit** on
  `cpt_fetch_album_representative_options` — cap and paginate server-side.
- `collectAlbumPayload` serializes **all** albums on save, causing writes (and cache
  purges — see P4) for unchanged albums. Track dirty state and submit only changed
  entries; server-side, skip no-op updates by comparing old/new values.

### P9 — INFO — Missing index awareness

Fallback/exclusive-contributor queries (`GROUP BY ic.category_id HAVING COUNT(DISTINCT
i.added_by)…`) aggregate over the full `image_category` × `images` join. Piwigo
indexes cover `image_category(category_id)`; `images.added_by` typically has no index.
Acceptable while fallback mode is rare; document it, or gate fallback mode behind
config (see [01-security.md](01-security.md) §S8).

---

## Quick-win summary

| Fix                                                     | Effort  | Impact                   |
| ------------------------------------------------------- | ------- | ------------------------ |
| Remove `init` reconciliation (P1)                       | Trivial | Site-wide, every request |
| Drop raw `user_cache` DELETE (P4)                       | Trivial | Site-wide after toggles  |
| Hoist `cpt_get_shareable_user_options` out of loop (P5) | Trivial | Save path                |
| Single script injection (P6)                            | Small   | Profile page weight      |
| Batch album fetch (P2)                                  | Medium  | Profile page latency     |
| Indexed descendant lookup (P3)                          | Small   | Toggle latency           |

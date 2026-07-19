# Reliability & Dependability Audit — Core Privacy Toggle

**Scope:** failure modes, consistency, concurrency, graceful degradation, observability.
**Verdict:** the plugin degrades gracefully on the _read_ side (missing columns, absent
template features, theme quirks) but the _write_ side has consistency gaps: multi-step
privacy transitions are not atomic, failures are invisible, and one cache-consistency
mechanism does not do what its comments claim.

---

## Findings

### R1 — MEDIUM — Every database failure is silently swallowed

Pattern repeated ~30 times across [include/functions.inc.php](../../../include/functions.inc.php):

```php
$res = pwg_query($sql);
if (!$res) { return []; }   // or return null / return false / continue silently
```

Consequences:

- A transient DB error during `cpt_fetch_albums_owned_by()` renders the profile page
  _as if the user owned nothing_ — indistinguishable from the legitimate empty state.
- A failed ownership lookup returns `null`, which downstream reads as "denied" (safe
  direction ✔) — but a failed `INSERT INTO user_access` after a successful
  `status='private'` UPDATE is not detected at all (see R2).
- There is no **plugin-level** structured logging. Core's `pwg_query()` does raise a
  PHP warning (`my_error()` → `trigger_error`) on query failure, but the plugin adds
  no context of its own, cannot distinguish failure from a legitimate empty state,
  and always continues silently.

**Recommendation:** adopt the exception + logging model in
[06-error-handling.md](06-error-handling.md). At minimum, wrap `pwg_query` in a helper that logs failures
with query context before returning the sentinel.

### R2 — HIGH — Privacy transitions are not atomic

> **✅ FIXED** in `9ffc60a` (2026-07-19): steps 1–4 now run inside a single
> transaction (`pwg_query('BEGIN')` / `COMMIT` / `ROLLBACK`); every write is
> verified and any failed step rolls back the whole transition, so a private
> album can no longer end up without `user_access` rows and a private root can
> no longer keep public descendants. On failure no cache invalidation and no
> success message occur — callers surface a translatable error instead
> (`cpt_update_album()` now returns bool). Covered by two new rollback tests.

`cpt_update_album()` ([functions.inc.php L1170-L1246](../../../include/functions.inc.php#L1170-L1246)) performs, in order, with **no
transaction and no per-step verification**:

1. `UPDATE categories SET status=…` (parent)
2. one `UPDATE` per descendant (propagation)
3. `DELETE FROM user_access WHERE cat_id=…` per affected album
4. `INSERT INTO user_access …` per affected album
5. cache invalidation + purge

Failure between 3 and 4 leaves a **private album with no `user_access` rows** — the
owner locks _themselves_ out (admins still see it). Failure between 1 and 2 leaves a
private root with public descendants — which the `init`-hook reconciler was presumably
added to heal (see P1); i.e., a per-request full-table scan exists to compensate for a
missing transaction.

**Recommendation:** wrap steps 1–4 in a transaction. Piwigo core exposes the raw
connection; a simple `pwg_query('BEGIN')` / `COMMIT` / `ROLLBACK` (guarded by driver
capability) is enough. Then delete the reconciler from `init` (P1). Also check the
result of the `INSERT` in step 4 and restore/fail loudly on error.

### R3 — MEDIUM — Descendant propagation semantics have surprising edges

- Propagation only happens when the toggled album is the **effective owner root** and
  mode is strictly `private` (`cpt_should_propagate_private_status_to_descendants`).
  Toggling that same root to `shared` (private + user list) does **not** touch
  descendants, leaving mixed visibility that the reconciler will later flip to fully
  private (because it forces `['mode' => 'private', 'shared_user_ids' => []]`,
  [functions.inc.php L1157](../../../include/functions.inc.php#L1157)) — silently **removing shares the owner configured**.
- Descendants are propagated with `mode=private` regardless of the parent's shared
  list ([functions.inc.php L1227-L1231](../../../include/functions.inc.php#L1227-L1231)), so "share root with Alice" gives Alice the
  root but not its sub-albums; the UI does not communicate this.

**Recommendation:** decide and document one policy (most intuitive: propagate the
_same_ permission set to descendants of an owned root), implement it symmetrically in
toggle + reconciler, and add tests for the shared-root case.

### R4 — MEDIUM — `$_SESSION['cpt_permissions_changed']` cannot notify "other sessions"

> **✅ FIXED** in `10b6dbd` (2026-07-19): the session flag and its `init` consumer
> were removed together with the P4 fix; the per-request `need_update` invalidation
> covers all sessions correctly.

[main.inc.php L91-L95](../../../main.inc.php#L91-L95), [functions.inc.php L1220](../../../include/functions.inc.php#L1220)

The comments say the flag is a "one-shot flag for **other sessions** to re-evaluate
permissions" — but PHP sessions are per-user: the flag is set and later consumed in the
_owner's own_ session. Other users are actually covered by the `user_cache` purge
(P4/S5). The mechanism is therefore redundant where it works and misleading where it
does not.

**Recommendation:** remove the session flag (and its `init` consumer) once the
invalidation strategy from P4 is adopted (`invalidate_user_cache(false)` once after
the complete save — note core's default `$full = true` truncates rather than marks;
per-user `need_update` rows only for shared-list-only edits); that mechanism covers
all sessions correctly.

### R5 — LOW — Concurrency: no locking on read-modify-write

Two concurrent submissions (double-click save + quick toggle, or two tabs) interleave
status reads and permission rewrites; last-writer-wins on `categories.status` is fine,
but interleaved DELETE/INSERT on `user_access` can produce duplicate-key errors
(silently swallowed, R1) or a mixed permission set. The save button disables during
flight ✔, but the WS endpoint itself is unguarded.

**Recommendation:** the R2 transaction largely fixes this; alternatively use
`INSERT IGNORE` / upsert semantics for `user_access` rows.

### R6 — LOW — Redirect-based toggle loses feedback on failure

`cpt_handle_album_page_toggle()` pushes success info to `$_SESSION['page_infos']` and
redirects — but since `cpt_update_album()` returns `void` and swallows failures, the
user sees "Album privacy updated." even when nothing was written.

**Recommendation:** have `cpt_update_album()` return success/throw, and only enqueue
the success message when true.

### R7 — LOW — Environment-degradation paths are good but untested in production combos

Positive observations worth preserving:

- Missing ownership column → limited mode with explicit user/admin notices ✔
- Missing `scriptLoader`/`cssLoader`/`append`/`get_template_vars` → guarded with
  `method_exists`/`isset` fallbacks ✔
- Missing `DerivativeImage`/`ImageStdParams` → empty thumbnail with `Throwable` catch ✔
  (the **only** try/catch in the plugin)
- Folder-rename guard in [main.inc.php L19-L28](../../../main.inc.php#L19-L28) ✔

Gap: these fallbacks are exercised only by the regex-based SQL emulator in tests, not
against a real Piwigo + MySQL (see M6). CI workflows for PHPUnit and Cypress **do
exist** ([.github/workflows](../../workflows/phpunit.yml)) but are currently non-functional (wrong monorepo
paths, missing tracked test suite, unreachable base URL — see M3); repairing them and
running a smoke lane against a live instance would materially raise dependability
confidence.

### R8 — INFO — `filemtime`/`realpath` failure modes

`cpt_attach_profile_block()` and template `set_filename` calls use `realpath()` which
returns `false` on exotic deployments (phar, symlinked read-only mounts); guarded in
one place ([functions.inc.php L288-L291](../../../include/functions.inc.php#L288-L291)) but not where `set_filename` is called
directly with `realpath(...)` results ([functions.inc.php L73](../../../include/functions.inc.php#L73), [L196](../../../include/functions.inc.php#L196)).
`filemtime` on a missing file is guarded by `file_exists` ✔.

---

## Consistency matrix (write paths)

| Path                                        | Token                             | Ownership re-check   | Atomic        | Failure visible                                         |
| ------------------------------------------- | --------------------------------- | -------------------- | ------------- | ------------------------------------------------------- |
| Profile form POST (`cpt_handle_album_form`) | core `check_pwg_token` (implicit) | ✔ per album          | ✘             | partial (bool return, but per-album failures invisible) |
| WS `albums.update`                          | ✔ explicit                        | ✔ per album          | ✘             | partial                                                 |
| WS `album.images`                           | ✔ explicit                        | ✔                    | n/a (read)    | ✔ (`PwgError`)                                          |
| Album-page quick toggle                     | ✔ explicit                        | ✔                    | ✘             | ✘ (always claims success)                               |
| Representative update                       | via callers                       | ✔ + membership check | single stmt ✔ | ✔ bool                                                  |

# Security Audit — Core Privacy Toggle

**Scope:** OWASP Top 10 alignment, CSRF, authZ, SQLi, XSS, information disclosure, DoS.
**Verdict:** No critical injection or authentication bypass found. The write paths are
consistently guarded by ownership re-checks and token validation. Findings below are
hardening and abuse-resistance issues.

---

## What is done well

- **CSRF:** Both webservice methods (`cpt_ws_get_album_images`, `cpt_ws_update_albums`)
  and the album-page quick toggle validate `pwg_token` with strict comparison.
  The classic profile-form POST path (`cpt_setup_ucp_tabs`) is protected upstream:
  Piwigo's `profile.php` calls `check_pwg_token()` for any non-empty `$_POST`
  _before_ firing `loc_begin_profile` ([profile.php L24-L31](../../../../../profile.php#L24-L31)).
- **Authorization:** every album id in a submitted payload is re-verified with
  `cpt_album_is_owned_by()` before any write ([functions.inc.php L847-L855](../../../include/functions.inc.php#L847-L855)); representative-image
  updates additionally verify image-album membership ([functions.inc.php L525-L541](../../../include/functions.inc.php#L525-L541)).
- **SQLi:** IDs are cast with `(int)`, string values pass `pwg_db_real_escape_string()`,
  `status`/`visibility` are validated against closed whitelists, and the ownership
  column name comes from a two-value internal whitelist (`community_user`/`user_id`)
  derived from `DESC` output — never from user input.
- **XSS:** Smarty templates escape all output (`|escape`); JS uses `textContent` for
  status messages and an `escapeHtml()` helper before the one `innerHTML` sink in
  `renderRepresentativeOptions`; inline JSON bootstraps use
  `JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_AMP|JSON_HEX_QUOT`.
- **Guest handling:** WS endpooints reject guests (`is_a_guest()`), empty-user contexts
  bail early everywhere.
- Directory-listing stubs (`index.php`) present in plugin subdirectories.

---

## Findings

### S1 — LOW — Non-JS profile POST relies entirely on core's token check (implicit contract)

[include/functions.inc.php L24-L29](../../../include/functions.inc.php#L24-L29)

`cpt_setup_ucp_tabs()` processes `$_POST['cpt_album']` with **no local token check**.
Today this is safe because `profile.php` calls `check_pwg_token()` for any non-empty
`$_POST` before firing `loc_begin_profile` — there is no current exposure. The risk is
latent: if the handler is ever attached to another hook (it already runs three
index-page hooks), or a theme triggers `loc_begin_profile` outside `profile.php`, the
write path becomes CSRF-able silently.

**Recommendation:** add an explicit `check_pwg_token()` (or a soft token comparison
that aborts with an error) inside `cpt_setup_ucp_tabs()` before calling
`cpt_handle_album_form()`. Cheap defense-in-depth; documents the contract.

### S2 — MEDIUM — `pwg_token` transmitted in GET query string

[js/ucp_tabs.js L268-L283](../../../js/ucp_tabs.js#L268-L283) (`loadRepresentativeOptions`)

```js
params.set("pwg_token", token);
fetch("ws.php?" + params.toString(), { method: "GET", ... })
```

The CSRF token ends up in server access logs, proxy logs, and browser history.
`pwg_token` is derived from the session and valid for the whole session — leakage
meaningfully weakens CSRF protection for the WS write endpoint.

**Recommendation:** send the request as POST with the token in the body (the save path
`submitWsRequest` already does this correctly), or at minimum move the token to a header.

### S3 — MEDIUM — Full user enumeration exposed to any album owner

[include/functions.inc.php L556-L575](../../../include/functions.inc.php#L556-L575) (`cpt_get_shareable_user_options`)

Every user who owns (or exclusively contributed to) a single album receives the complete
username list of the gallery (minus webmaster/guest/self), rendered into the profile
page. On multi-tenant/public-registration galleries this is a privacy and
reconnaissance issue (username harvesting for credential stuffing).

**Recommendation:** make the shareable-user source configurable (e.g. same-group users
only, or an admin-defined group), and/or convert the multi-select into a
server-side-validated autocomplete that never ships the full list.

### S4 — MEDIUM — Hardcoded webmaster/admin user id `1`

[include/functions.inc.php L1085](../../../include/functions.inc.php#L1085) (`$allowed_user_ids = [1];`),
[L569](../../../include/functions.inc.php#L569) (`NOT IN (1,...)`), [L594](../../../include/functions.inc.php#L594) (`$user_id === 1` skip in shared-list read).

Piwigo does not guarantee the webmaster has id 1 (`$conf['webmaster_id']` exists for a
reason). Consequences when the assumption breaks:

- The real webmaster loses `user_access` to private albums (availability/lockout).
- An ordinary user who happens to have id 1 is silently granted access to **every
  private album** managed through this plugin (authorization defect).

**Recommendation:** use `$conf['webmaster_id']`; note that Piwigo admins bypass
`user_access` anyway, so consider whether the row is needed at all.

### S5 — HIGH — Global `user_cache` purge is a user-triggerable DoS vector

[include/functions.inc.php L1300-L1310](../../../include/functions.inc.php#L1300-L1310) (`cpt_purge_user_cache`)

`DELETE FROM <prefix>user_cache` wipes the per-user permission cache for **all** users
whenever **any** owner toggles privacy. A single non-admin user can loop the quick
toggle (or the WS endpoint — no rate limiting) and force continuous cache rebuilds for
the whole gallery. On large installations each rebuild is expensive (permission
recomputation per user per request).

**Recommendation:**

- Use **targeted** invalidation: mark only affected users for rebuild
  (`UPDATE user_cache SET need_update='true' WHERE user_id IN (owner, shared users,
guest)`), or call core's `invalidate_user_cache(false)` (sets `need_update` for all
  users without truncating). ⚠️ Do **not** simply call `invalidate_user_cache()` with
  defaults: in Piwigo 15 `$full = true` **TRUNCATEs both** `user_cache` and
  `user_cache_categories` — heavier than the plugin's current raw `DELETE`. Note also
  that the function lives in `admin/include/functions.php`, which is not loaded on
  front-end requests — the plugin must `include_once` it or issue the targeted SQL
  itself.
- Do not just delete `cpt_purge_user_cache()` — without a replacement invalidation,
  other users keep stale visibility.
- Consider a lightweight rate limit / cooldown on privacy toggles per user.
- Note the save-all amplification: `cpt_handle_album_form()` always includes `status`
  in updates, so a single "Save Changes" for N albums triggers up to N gallery-wide
  purges (see [02-performance-optimization.md](02-performance-optimization.md) §P4).

### S6 — MEDIUM — `cpt_update_album()` interpolates column names from array keys

[include/functions.inc.php L1189-L1194](../../../include/functions.inc.php#L1189-L1194)

```php
foreach ($fields as $col => $val) {
    $assignments[] = $col . "='" . pwg_db_real_escape_string($val) . "'";
}
```

All current call sites pass only `name`/`comment`/`status`, so this is not exploitable
today, but the function is the single low-level write helper and its own docblock admits
the design ("simple dynamic SQL"). Any future caller (or a third-party plugin calling
this public function) that passes a user-influenced key becomes an SQL injection.

**Recommendation:** enforce `$allowed = ['name','comment','status'];` inside the
function and reject/throw on anything else (pairs naturally with the exception model in
[06-error-handling.md](06-error-handling.md)).

### S7 — LOW — Debug output can echo raw SQL into page infos

[include/functions.inc.php L1195](../../../include/functions.inc.php#L1195), [L1101-L1104](../../../include/functions.inc.php#L1101-L1104)

Gated behind `CPT_DEBUG && pwg_get_session_var('is_admin')`, so exposure requires a code
change plus admin session — acceptable, but SQL text with escaped user data in the HTML
response is an information-disclosure anti-pattern. Prefer `error_log`/file logging
(see [06-error-handling.md](06-error-handling.md)).

Note also that `pwg_get_session_var('is_admin')` is a plugin-invented session key — core
Piwigo does not set it; real admin checks should use `is_admin()`.

### S8 — LOW — Exclusive-contributor fallback can grant control over admin-created albums

[include/functions.inc.php L858-L900](../../../include/functions.inc.php#L858-L900)

When no ownership column exists, a user who is the sole uploader to an album — including
an album an **admin** created and populated on the user's behalf, or a formerly-shared
album whose other photos were removed — gains rename/describe/privacy control over it.
The README calls this "intentionally conservative", and it is reasonably so, but the
privacy toggle on such an album will also rewrite its `user_access` rows.

**Recommendation:** document the risk in admin-facing docs; optionally require the
fallback mode to be explicitly enabled via a config flag.

### S9 — LOW — No rate limiting / abuse controls on WS endpoints

`core_privacy_toggle.albums.update` accepts an arbitrary-size JSON payload and iterates
every entry, performing several queries per album id even for non-owned ids (ownership
check itself costs queries). Combined with S5 this amplifies abuse.

**Recommendation:** cap payload size (e.g. max 200 album entries), bail out early on
malformed entries, and add the S5 mitigations.

### S10 — INFO — `stripslashes()` on WS payload is correct but fragile

[include/functions.inc.php L730](../../../include/functions.inc.php#L730)

Piwigo core adds slashes to request values before WS dispatch, so the `stripslashes()`
is currently required. If core ever drops that behavior, payloads containing quotes
will be corrupted silently. Guard with a JSON-decode-then-retry pattern or a version
check comment referencing the core line.

---

## OWASP Top 10 (2021) mapping

| Category                           | Status                                                                   |
| ---------------------------------- | ------------------------------------------------------------------------ |
| A01 Broken Access Control          | Good ownership gating; S4 (id-1 assumption), S8 (fallback heuristic)     |
| A02 Cryptographic Failures         | N/A (no crypto handled by plugin)                                        |
| A03 Injection                      | No exploitable path found; S6 defense-in-depth                           |
| A04 Insecure Design                | S5 (global cache purge), S3 (user list exposure)                         |
| A05 Security Misconfiguration      | S7 (debug flag); index.php stubs present ✔                               |
| A06 Vulnerable Components          | Only dev dependency (PHPUnit ^11.5); keep updated                        |
| A07 Identification & Auth Failures | Guest checks present ✔; S2 (token in URL)                                |
| A08 Software & Data Integrity      | No update/deserialization risk; `safe_unserialize` used ✔                |
| A09 Logging & Monitoring Failures  | **No logging at all** — see [06-error-handling.md](06-error-handling.md) |
| A10 SSRF                           | N/A                                                                      |

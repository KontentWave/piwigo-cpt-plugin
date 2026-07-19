# Standardized Error Handling with Custom Exceptions — Design & Gap Audit

**Scope:** current error-handling inventory, target exception architecture, boundary
mapping, logging strategy, migration plan.

---

## 1. Current state

| Mechanism                                     | Where                                                                        | Problem                                                                       |
| --------------------------------------------- | ---------------------------------------------------------------------------- | ----------------------------------------------------------------------------- |
| Sentinel returns (`null`, `false`, `[]`, `0`) | ~30 sites in [include/functions.inc.php](../../../include/functions.inc.php) | Failure indistinguishable from legitimate empty state; no context; no logging |
| `void` writes                                 | `cpt_update_album`, `cpt_sync_album_visibility_permissions`                  | Callers cannot detect failure; success messages shown regardless (R6)         |
| `PwgError` objects                            | WS endpoints only                                                            | Correct for WS, but constructed ad-hoc with magic numbers                     |
| `$page['errors'][]`                           | one site (invalid token on quick toggle)                                     | Correct surface for page flow, used inconsistently                            |
| `try/catch`                                   | exactly one (`cpt_get_album_image_square_src`, catches `\Throwable`)         | Broad catch discards the error silently                                       |
| Debug echo                                    | `CPT_DEBUG` + `$page['infos']` SQL dumps                                     | Not a logging strategy; admin-facing HTML output                              |
| Logging                                       | **none**                                                                     | Zero operator observability (OWASP A09)                                       |

There are **no custom exceptions** anywhere in the plugin today.

---

## 2. Target architecture

### 2.1 Exception hierarchy

```
Cpt\Exception\CptException                (abstract, extends \RuntimeException)
├── Cpt\Exception\DatabaseException       (query text*, driver error; *sanitized)
├── Cpt\Exception\OwnershipException      (album id, user id, rule evaluated)
├── Cpt\Exception\ValidationException     (field name, rejected value class)
├── Cpt\Exception\TokenException          (CSRF failure)
├── Cpt\Exception\NotFoundException       (album/image lookups)
└── Cpt\Exception\EnvironmentException    (missing column/table/template capability)
```

Sketch:

```php
namespace Cpt\Exception;

abstract class CptException extends \RuntimeException
{
    /** @var array<string,scalar|null> structured context for logs */
    protected array $context = [];

    public function __construct(string $message, array $context = [], ?\Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
        $this->context = $context;
    }

    public function getContext(): array { return $this->context; }

    /** Machine-readable code for WS mapping and log filtering. */
    abstract public function getErrorCode(): string;   // e.g. 'cpt.db_failure'

    /** HTTP-ish status used at the WS boundary. */
    public function getWsStatus(): int { return 500; }

    /** Message safe to show end users (l10n key). Never leaks SQL/ids. */
    public function getUserMessageKey(): string { return 'An error has occurred.'; }
}

final class OwnershipException extends CptException
{
    public function getErrorCode(): string { return 'cpt.ownership_denied'; }
    public function getWsStatus(): int { return 403; }
    public function getUserMessageKey(): string { return 'Access denied'; }
}

final class DatabaseException extends CptException
{
    public function getErrorCode(): string { return 'cpt.db_failure'; }
}
```

Key principles:

- **Two-audience messages:** `getMessage()`/context = operator detail (logs only);
  `getUserMessageKey()` = localized, generic, safe for HTML/WS responses. This fixes
  the current pattern of echoing SQL to the page (S7) _and_ the pattern of hiding
  everything (R1).
- **Exceptions carry structured context** (`album_id`, `user_id`, `query_hash`), not
  formatted strings — enables grep-able logs.
- Extend `\RuntimeException` so uncaught bubbles still hit PHP's handler rather than
  fataling as type errors.

### 2.2 Throw sites (interior code throws, never prints)

| Current sentinel                              | Replace with                                                                                                                                     |
| --------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------ |
| `pwg_query(...) === false` → `return null/[]` | `throw new DatabaseException('categories status lookup failed', ['album_id' => $id])` from a `Cpt\Db::query()` wrapper                           |
| `cpt_album_is_owned_by()` false at write time | `OwnershipException` (read paths keep boolean — ownership _checks_ are flow control, ownership _violations at write boundaries_ are exceptional) |
| Invalid `status`/`visibility`/payload shape   | `ValidationException`                                                                                                                            |
| Token mismatch                                | `TokenException`                                                                                                                                 |
| Missing ownership column where required       | `EnvironmentException` (callers may catch → limited mode)                                                                                        |

### 2.3 Boundary handlers (only places that catch)

The plugin has exactly four entry surfaces; each gets one translator:

```php
// WS boundary
function cpt_ws_update_albums($params, &$service)
{
    try {
        return Cpt\Http\WsController::updateAlbums($params);
    } catch (Cpt\Exception\CptException $e) {
        Cpt\Log::warning($e);
        return new \PwgError($e->getWsStatus(), l10n($e->getUserMessageKey()));
    } catch (\Throwable $e) {
        Cpt\Log::error($e);
        return new \PwgError(500, l10n('An error has occurred.'));
    }
}

// Hook boundary (profile / index pages): degrade, never break the host page
function cpt_setup_ucp_tabs(): void
{
    try {
        Cpt\Http\ProfileController::setup();
    } catch (Cpt\Exception\CptException $e) {
        Cpt\Log::warning($e);
        global $page;
        $page['errors'][] = l10n($e->getUserMessageKey());
    } catch (\Throwable $e) {
        Cpt\Log::error($e);           // swallow: plugin must not take down profile.php
    }
}
```

Rules:

- **Hook handlers never leak exceptions** into Piwigo page rendering — catch-all at the
  boundary, log, degrade to no-enhancement (preserves the plugin's existing graceful
  philosophy, but now observable).
- **WS handlers map exception → `PwgError`** via the exception's own status/message —
  removes magic numbers scattered through `cpt_ws_*`.
- The redirect path (quick toggle) enqueues the _failure_ message on exception instead
  of unconditionally claiming success (fixes R6).

### 2.4 Logging

Piwigo-native options, in preference order:

1. `trigger_notify('cpt_log', ...)` + file log via core logger when available
   (Piwigo ≥ 13 ships `logger()`/`pwg_log` facilities), fallback to
2. `error_log('[CPT] '.$e->getErrorCode().' '.$e->getMessage().' '.json_encode($e->getContext()))`.

Levels: `error` (unexpected `\Throwable`), `warning` (CptException at boundary),
`info` (privacy transitions — an audit trail of who toggled what is valuable for a
privacy feature; log `user_id`, `album_id`, `old→new status`).

**Replace `CPT_DEBUG` page-info dumps** with `Cpt\Log::debug()` gated by the same flag.

---

## 3. Migration plan (incremental, test-safe)

| Step | Change                                                                                                                                                          | Risk                                       |
| ---- | --------------------------------------------------------------------------------------------------------------------------------------------------------------- | ------------------------------------------ |
| 1    | Add `src/Exception/*` + `Cpt\Log`; wire composer PSR-4 autoload (guard: plain `require_once` fallback since Piwigo plugins can't assume composer runtime)       | none                                       |
| 2    | Introduce `Cpt\Db::query()` wrapper that throws `DatabaseException`; migrate _write_ queries first (`cpt_update_album`, permission sync, representative update) | low — call sites currently ignore failures |
| 3    | Add boundary try/catch to the 4 surfaces (2 WS, 2 hook groups)                                                                                                  | low                                        |
| 4    | Convert write-path sentinels to throws (`cpt_handle_album_form` inner loop: collect per-album failures, report aggregate)                                       | medium — update tests to expect exceptions |
| 5    | Convert read-path sentinels where empty ≠ error (album fetch); keep boolean checks where empty is legitimate                                                    | medium                                     |
| 6    | Update test bootstrap: emulator throws on unrecognized SQL (M6) — aligns test failure mode with production                                                      | low                                        |

Success criteria: zero `if (!$res) { return ...; }` without a log; every user-visible
"saved" message backed by a verified write; WS error responses carry stable
`getErrorCode()` values documented for API consumers.

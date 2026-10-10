# Settings domain and API outcome fixes

Date: 2026-10-09. Implements [AR04–05](../audits/FULL_REVIEW_ARCHITECTURE.md) for the settings/API boundary. Source and focused fixtures only; no shared WordPress bootstrap, package, deployment or external request. Import persistence outcome is covered by the separate root batch.

| Owner | Change |
| --- | --- |
| [Settings.php](../../noobrocket/inc/Engine/Admin/Settings/Settings.php) | Existing boolean/enum policy moves here. Shared helpers normalize exact supported booleans, nonnegative whole cache lifespan, available choices and paired CDN rows. Full forms retain missing-checkbox semantics and existing default policies. Invalid CDN hosts or scopes retain the complete saved CDN group while unrelated valid changes remain eligible. |
| [SetOption.php](../../noobrocket/inc/Engine/Abilities/Options/SetOption.php) | Single-option requests use those helpers without invoking a whole-form reset. Permission, malformed input, unsupported mode/type, invalid boolean/enum/lifespan/list/CDN values fail explicitly before writing. Existing allowlist/read-only policy remains. |
| API writer | CDN hosts and their positional scopes are normalized/stored together in one existing options update. Other fields are read from current settings and retained. Requested normalized values are read back before reporting completion. |

## Behavior

Boolean values accepted: `true`, `false`, `1`, `0`, and exact strings `true`, `false`, `1`, `0`. The string `false` disables. Other representations produce an explicit API validation error rather than silently enabling or clearing a setting. Native submitted booleans use the same domain values; invalid submitted boolean values retain their saved value with Settings API feedback. Missing checkboxes still use the native full-form behavior.

Cache lifespan uses nonnegative whole intervals, with zero for unlimited. Writable units match the native Hours/Days choices: `HOUR_IN_SECONDS`, `DAY_IN_SECONDS`. API invalid enum feedback names the actual choices. Readable legacy data is not migrated by this batch; the existing GetOptions response schema still permits historical minute values, but the setter cannot create new minute configurations.

CDN hosts retain native HTTP(S)/bare hostname/path normalization, blank removal, first duplicate ownership and corresponding scopes. Scope values: `all`, `images`, `css_and_js`, `css`, `js`. Updating hosts appends or replaces according to `update_mode`, aligns their scopes and removes dangling scopes when hosts are removed. Scope lists always replace the positional vector; duplicate scope values remain distinct rows. The ability description and parameter guidance state this exception. Extra/invalid scope positions fail explicitly.

Valid no-ops return `success: true`, `outcome: unchanged`, without calling the writer. Changed requests return `applied` only when every normalized requested field reads back correctly. A refusing writer returns `failed`; a changed result that differs from the normalized requested group returns `adjusted`, with success false and current selected value. This also catches a CDN writer that stores hosts but changes/rejects companion scopes. Default third-party allowlist extensions retain their supplied value policy and ordinary writer hooks; no new extension registry is introduced.

## Proof and limits

| Gate | Result |
| --- | --- |
| [Settings/API fixture](../../tests/wordpress-settings-domain-api.php) | 78 assertions passed; in-memory writers exercise refusal, no-op, transformation, companion-field readback, malformed direct input, capability rejection and third-party allowlist extension. Also reruns 29 native CDN assertions. |
| [CDN fixture](../../tests/wordpress-cdn-validation.php) | 29 assertions passed; invalid scope now preserves the saved CDN group instead of broadening resource rewriting to All. |
| [Link/prerender fixture](../../tests/wordpress-link-prerender.php) | 117 assertions plus six isolated context assertions passed. API invalid boolean assertions now require explicit validation errors; fixture loads the native error class and captures native settings feedback. No site writes. |
| PHP syntax | Two changed owners and four affected/new fixtures pass PHP 8.3 syntax checks. |
| [Local CSS settings fixture](../../tests/wordpress-local-css-settings.php) | The invalid API mode assertion now requires WP_Error. Syntax verified here; runtime gate requires a separately marked disposable WordPress install and is left to consolidated isolated-runtime verification. |

Receipts under `C:/Users/Guillaume/.codex/logs/commands/`: `20261009-223411-917-ef0388455fe24a378d72c0afcf869596.log` (settings/API), `20261009-223411-922-4164587de28a4d9fad33655a0df88967.log` (link/prerender), `20261009-223412-123-6d7dfefff2ac4b90b4062e4105dc6e52.log` (syntax).

No REST server, real database writer refusal, concurrent native request, production API call, older PHP runtime or WordPress installation was exercised by these isolated fixtures. This batch consolidates the reviewed boolean/CDN/lifespan/enum domain rules; it does not claim every existing plugin-specific field sanitizer has been unified.

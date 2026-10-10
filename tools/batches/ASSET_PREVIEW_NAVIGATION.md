# Saved-draft preview navigation

## Cause and behavior

“Preview saved draft” submitted the same POST command as inspection in the current tab. The administrative handler validated and redirected to the signed frontend page. The shared submit listener treated preview as departure: it reviewed pending edits, set leaving/pending state and disabled Cancel. There was no preview-specific automatic return redirect in the frontend owner.

Visual preview now opens in another tab and leaves the original editor usable. The entered URL remains editable. The existing button has a native POST/new-tab fallback; maintained admin JavaScript enhances it to a normal anchor for middle-click, modifier keys and browser tab/window commands. URL edits and native form reset update the link, while nonce and reviewed draft revision stay tied to the loaded form. Native URL validity applies to primary and middle-click activation; right-click keeps the browser context menu. The endpoint comes from the form's action attribute because its hidden `action` input shadows the DOM `form.action` property. The helper explains that only saved draft rules are previewed; unsaved settings and rule input stay in the editor.

The existing Asset Manager handler accepts GET only for preview navigation. Capability, admin nonce, current draft revision and site scheme/host/port/frontend URL validation run before redirecting. Add, remove, discard, publish and restore remain POST-only. Frontend preview authorization, captured draft rules, no-cache/noindex behavior and private inventory capture stay in their existing owner. No new endpoint, popup shim, state store, dependency or preview worker was added.

Inspection retains its existing deliberate operation and Return-to-Asset-Manager flow. Preview does not automatically reload the original editor after inventory capture: doing so would discard pending input. Stale links are rejected rather than silently previewing a newer draft.

## Validation and scope

The isolated PHP test executes the native Admin/Rules owners without WordPress, database or network. It covers GET/POST preview signing, edited URL/query/fragment handling, revision conflicts, forbidden destinations, every GET mutation, malformed method/command, capability and nonce failures. Protected settings, public rules, private draft, rejected entry, inventory and history remain unchanged; only existing error-notice feedback may be recorded.

Browser navigation proof uses the maintained JavaScript and a synthetic local endpoint. Native WordPress review checks real markup, link ownership, editable destination, focus and layout at 1440/390/320px in both themes and English/French without submitting actions. It uses an owned session and request-only fixtures, then compares protected state without restoring an older baseline.

| Check | Result |
| --- | --- |
| Isolated preview server boundary | 57 assertions passed |
| Real browser navigation through synthetic endpoints | 57 assertions passed: primary/repeated/middle/Ctrl/Shift opens, keyboard and field Enter, null opener, GET/POST payload ownership, native reset, invalid input, retained pending work, existing action guards and no-JavaScript fallback |
| Native WordPress presentation | 61 checks passed across six theme/language/width configurations; screenshots reviewed |
| Existing settings transactions | 71 browser assertions passed |
| Existing asset and reviewed-state gates | 27 and 14 isolated assertions passed |
| Source parsing | Changed PHP files and maintained JavaScript passed syntax checks |
| Catalog preservation | 74,787 compiled lookups passed across 36 catalogs; prior entries, metadata and version preserved |
| Runtime preservation | Product options, private asset metadata, locale and all four preexisting sessions preserved; owned QA session, fixture and private artifacts removed |

The initial exact runtime comparison detected only cron timestamps advancing. Event configuration for all three changed hooks stayed identical; the discrepancy is recorded rather than restored. `runtime-preservation.json` retains the exact comparison and `preservation-summary.json` records the scoped result.

Browser context-menu entries belong to the browser/OS and were not activated by automation; native anchor semantics and uninterrupted right-click were verified. Synthetic navigation does not prove frontend performance or cache behavior. Native review blocks operation requests, while the isolated PHP test covers server authorization and signing.

Browser and native proof, screenshots, catalog proof and preservation summary live in `.artifacts/asset-preview-links-20261010/`; focused command receipts live under `C:/Users/Guillaume/.codex/logs/commands/`. WordPress serves the maintained `noobrocket-admin.js` directly with a file-time version. There is no generated/minified counterpart for this file. French helper copy belongs in FR/CA PO/MO; other existing locales retain English fallback. No package or live deployment was produced.

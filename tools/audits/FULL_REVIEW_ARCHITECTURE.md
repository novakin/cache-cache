# NoobRocket architecture and reliability review

Date: 2026-10-09. Maintained source: `noobrocket/`, product 1.3.1 / inherited engine 3.23.5.1. Review changes documentation and owned isolated probes only. No WordPress settings, drafts, history, queues, provider services or packages were changed. No live request, installation, deployment or build archive was performed for this review.

The existing WordPress architecture is suitable. Keep its event subscribers, native Settings API, options, per-user draft metadata, Action Scheduler, filesystem cache and same-server CSS worker. The main correction is clearer ownership of validation, reviewed state and completed writes. Replacing the framework would increase migration risk without fixing those boundaries.

Findings below distinguish **reproduced with isolated mocks**, **source-confirmed**, and **proposed after measurement**. A source trace or passing assertion count does not establish production speed, broad compatibility or absence of other defects.

## Current ownership and invariants

| Boundary | Current owner and flow | Preserve |
| --- | --- | --- |
| Requirements and edition policy | `noobrocket.php:106` loads `inc/free.php`; requirements check gates `inc/main.php`. `free.php:72` normalizes local options and forces unsupported cloud options off on reads/adds/updates. | Standalone identity; no license requirement; unsupported cloud features cannot return through an import. |
| Composition and event registration | `inc/main.php:33` constructs `Plugin`; `Plugin.php:140` sets up the existing League Container and event manager. Admin/front/common subscriber lists determine active behavior. Providers define services lazily. | Hook order, compatibility filters, existing public interfaces and WordPress request types. A service definition alone is not an active endpoint. |
| Early cache delivery | `Engine/Cache` writes the marked drop-in/configuration; the early loader can serve a hit before normal plugin boot. Cache generation and optimization run on eligible misses. | Another plugin's drop-in ownership; public/private variants; bypasses; atomic file publication; stale cutoff. Bootstrap improvements do not improve a request already served by the early loader. |
| Native settings | `Engine/Admin/Settings/Page.php:210` registers one settings option; `Settings.php:184` handles the native complete-form sanitizer. `inc/admin/options.php:79` adds pre-update behavior; `:18` generates configuration and invalidates cache after an update. Additional feature subscribers normalize their own values. | Native capability/nonce enforcement; one explicit Save boundary; unsupported setting removal; intentional partial-error behavior such as preserving invalid CDN rows while accepting unrelated valid changes. |
| API writes | `Engine/Abilities/Options/SetOption.php:294` validates the allowlist, normalizes a value, calls `update_noobrocket_option`, and reads back the result. Its permission callback requires `noobrocket_manage_options`. | Permission callback, allowlist, read-only derived state and updates through the normal options API. API transport validation does not replace domain validation. |
| Import/export | `inc/admin/admin.php:361` stages a ten-minute per-user import review, binds confirmation to its token/current settings and repeats validation. Export is capability/nonce protected and downloads the saved settings option. | Current-site cache keys and product/engine markers; import excludes cleanup action and asset/history state; credential values remain concealed in the review. The exported settings file can contain saved provider credentials. |
| Asset publication | `Engine/Optimization/Assets/Admin.php:69` reads the site's per-user draft; `:188` validates and publishes under `Rules.php:69` native short-operation lock. Public base comparison prevents overwriting another publication. | Private preview, 403/no-cache/noindex boundaries, validated scopes and recursive dependency protection. The public base check and the exact reviewed private draft are separate invariants. |
| History | `Assets/Snapshots.php:37` allowlists restorable settings and excludes credential-like keys; `:99` retains five local snapshots. Restore preserves protected current values, saves a recovery snapshot and checks the final result. | Bounded history, protected site keys/credentials, native save/invalidation paths and recovery when a two-option restore only partly succeeds. |
| Local CSS jobs | `LocalCSS/ServiceProvider.php:29` reuses the existing per-site UsedCSS table with local states; the same-server Node/Chromium worker handles generation. | Lease ownership, result validation, original-CSS fallback, public eligibility and conservative dynamic-state handling. No new queue service is needed. |
| Lifecycle | Native activation preflight rejects foreign cache ownership. Product and engine version migration paths are separate. Network uninstall enumerates all site IDs and restores blog context in `finally`. | Saved choices during ordinary product updates; shared dependency/plugin state; preservation of image derivatives still referenced by cached HTML. |
| Compatibility | `ThirdParty/Plugins/SubscriberFactory.php:64` is the registry; `PluginResolver.php:34` gates participating classes. Legacy procedural integration files are also loaded. | Real third-party names/API fields; default-active behavior for integrations not yet converted; filter ordering. Cosmetic renaming must not change external contracts. |

## Confirmed gaps and smallest corrections

### AR01 — Publish does not identify the private draft the user reviewed — P1

**Reproduced with isolated mocks.** `Assets/Admin.php:103` rereads current per-user metadata on submission. `:188–211` validates that draft and its public base, but the form has no expected private-draft revision. `Assets/Frontend.php:77` also previews whichever private draft exists at request time; the inventory does not record the draft revision.

Sequence: tab A reviews rule `reviewed`; tab B replaces its private draft with rule `replacement`, retaining the same public base; tab A submits Publish. The replacement is accepted. Existing stale-publication checks still work; this is a different boundary.

**Proposal:** add one revision to the existing draft metadata, change it on edits/discard, and carry the expected revision in publication/preview forms. Compare it while holding the existing native edit lock. Record the revision with preview evidence. Reject a replaced draft with a readable review-again response, preserving the current draft. Do not introduce a publication service or additional draft table.

**Acceptance:** two tabs cannot publish a private version other than the one displayed; another administrator's public update still conflicts; invalid or unchanged drafts retain their current behavior; preview evidence names the tested version and URL.

### AR02 — History restore can apply differences absent from the displayed review — P1

**Reproduced with isolated mocks.** `Snapshots.php:57` calculates differences against current saved values, but `Admin.php:146–151` submits only snapshot ID and confirmation. `Snapshots.php:131–140` reads the latest settings and restores the target without comparing a reviewed saved-state version.

The probe reviews a snapshot where JavaScript minification is unchanged. An intervening save enables it. The old review then restores it to disabled although that difference was absent from the review. A recovery snapshot retains the intervening value; that useful protection does not make the original consent accurate.

**Proposal:** reuse the import's bounded review/current-state pattern for restore. Bind snapshot ID, current restorable settings and public rules to the reviewed proposal. Recheck inside the existing lock. If current state or retained target changes, show fresh differences rather than applying the old review. Ordinary settings writers need consistent coordination if this is to protect overlapping writes, not only old tabs.

**Acceptance:** changed current values, changed rules, an evicted snapshot or another reviewed target require a new review; protected credentials/site keys remain current; a failed restore leaves a readable recoverable outcome.

### AR03 — Database cleanup dispatch belongs to a pre-update filter — P1

**Reproduced with an isolated recording optimization double.** `Engine/Admin/Database/Subscriber.php:53` hooks `save_optimize` to `pre_update_option_noobrocket_settings`. At `:136–166`, it removes `submit_optimize`, selects categories and calls `process_handler` before WordPress attempts the settings SQL write. `Optimization.php:50` cancels/replaces the background queue and dispatches it. The probe confirms dispatch while saved settings are still unchanged and the final filtered settings equal the saved values; it performs no cleanup or real database operation.

Consequently, a no-op or rejected settings write can still start the cleanup action. The current explicit save-and-cleanup review explains the combined action, but validation filters still should not dispatch destructive work.

**Proposal:** keep value normalization pure. Give immediate cleanup an explicit native action with its own capability, nonce, selected scope and confirmation. Reuse `Optimization::process_handler`; scheduling remains a setting. If retaining the combined Save-and-cleanup action temporarily, dispatch only after its settings outcome has been checked, while handling unchanged settings intentionally. Do not rely exclusively on `updated_option`, which does not run for an identical value.

**Acceptance:** review-only validation never starts cleanup; rejected saves never claim both steps succeeded; unchanged settings can still perform a specifically confirmed cleanup; scheduled and immediate scope are named separately. Task batching/table scope are reviewed in the optimization report.

### AR04 — Native forms and Abilities apply different settings rules — P1

**Reproduced with isolated mocks; registration source confirmed.** Native `Settings.php:364–411,584` validates/normalizes CDN hosts and aligns allowed resource scopes. `SetOption.php:153–156,428–445` treats hosts and scopes as generic text arrays. `Page.php:210` registers the native full-form callback through the admin settings subscriber; this does not provide a universal sanitizer on ordinary frontend REST requests. `update_noobrocket_option` merges and calls the normal option writer, without calling the native domain validator itself.

The probe confirms the form rejects `not a hostname`, while the ability retains it, and the ability accepts `arbitrary-scope`. Generic boolean coercion at `SetOption.php:363` also treats the schema-permitted string `false` as enabled. API lifespan units include minutes (`:117`), while the native form permits hours/days (`Settings.php:224`). These are observable contract differences, not proof of an unauthenticated exploit.

**Proposal:** extract the existing domain normalizers into the settings-owned layer, starting with CDN rows, booleans and lifespan. Reuse them from forms, import and Abilities. Keep full-form defaults separate from single-field updates: sending one API field through the whole-form sanitizer would reset unrelated unchecked/missing fields. Reject invalid API values clearly instead of silently preserving/coercing them. Retain typed transport schemas and native permission checks.

**Acceptance:** equivalent form/import/API proposals produce equivalent saved domain values; invalid hosts, scopes and boolean-like strings produce explicit errors; updating one field preserves all unrelated settings; valid third-party allowlist extensions retain their defined policy.

### AR05 — Some action responses claim a completed save without checking it — P1

**Reproduced for Abilities with an isolated refusing writer; source-confirmed for import.** `SetOption.php:320–327` always returns `success: true` after calling a void wrapper, even when the requested value did not persist. `inc/admin/admin.php:390–392` likewise reports “Settings imported and saved” after `Options::set`. `inc/classes/admin/class-options.php:81` discards `update_option`'s return value. Asset publication and restore already read back/check their own results; reuse that pattern.

**Proposal:** distinguish unchanged, applied, validation-adjusted and failed outcomes at the operation owner. Return a write result from the owned options wrapper where useful, but verify normalized saved values: `update_option(false)` alone cannot distinguish unchanged from failure. Do not mark failure for an intentionally idempotent no-op or expose credential differences in notices.

**Acceptance:** a refused changed write is reported as failure; unchanged valid values remain successful/no-op; transformed values are identified accurately; import confirmation is consumed or retained according to its documented retry behavior.

### AR06 — Ordinary history entries are recorded before successful commit — P2

**Reproduced with isolated mocks.** `Snapshots.php:21–23,78–95` records old state from pre-update/pre-add filters. `:108` writes and truncates history before the main settings/rules write. A proposed change therefore can consume a history slot even if the main write never commits. Concurrent settings writes also read/replace the whole five-entry history option; only publication/restore currently share the asset lock.

**Proposal:** record ordinary change history on successful native updated/added hooks, which provide old/new values, preserving the explicit pre-restore recovery snapshot for the separately verified multi-option restore. Review coordination with AR02 before adjusting hook timing. This is an ownership change, not a reason to add an audit-log platform.

**Acceptance:** no-op, rejected and failed ordinary saves do not evict useful snapshots; successful saves record the actual previous state once; restore still captures one recoverable prior state; simultaneous supported writes have an explicit retention policy.

### AR07 — Uninstall omits pending import reviews and asset notice transients — P2

**Source-confirmed.** Import stores `noobrocket_import_review_<user>` containing both current and proposed settings for ten minutes (`inc/admin/admin.php:370,466–474`). Asset errors store `noobrocket_asset_notice_<user>` for one minute (`Assets/Admin.php:166`). `NoobRocketUninstall.php:127–130` has dynamic cleanup prefixes for CDN verification/PageSpeed notices, but neither prefix; `:281–285` removes similarly named user metadata, which is a different storage boundary. Pending import records can contain provider credentials until expiration.

**Proposal:** extend owned lifecycle cleanup to these exact records. Reuse existing dynamic cleanup for database transients and use native `delete_transient` for known active records where a persistent cache provider owns them. Keep finite expirations and never flush the shared transient/object-cache group. Do not delete the user's downloaded export file.

**Acceptance:** owned pending review/notice records are removed on uninstall for every site; unrelated records and other plugins' cache groups remain untouched; expired cache-backed entries retain normal provider expiry if enumeration is unavailable.

### AR08 — A focused maintained regression fixture currently cannot reach its assertions — P1 verification prerequisite

**Executed failure, exit 1.** `tests/wordpress-cdn-validation.php:19,31–33` does not load/mock the added `noobrocket_sanitize_prerender_options` helper; production `Settings.php:327` now calls it. Native fixture execution fails with an undefined-function fatal before the CDN assertions. This is a fixture bootstrap failure, not evidence that real WordPress saving fails, where `free.php` loads the helper.

**Proposal:** repair the fixture bootstrap using the actual pure policy helper or a small consistent local bootstrap. Add this isolated check to a documented focused test entry point so future setting additions cannot silently strand it. Avoid a global mega-fixture with hundreds of unrelated mocks.

**Acceptance:** the unmodified fixture command reaches all CDN assertions without a review-only shim; strict warning handling remains; no real WordPress/database/network is loaded.

### AR09 — Declared developer/recovery contracts differ from reachable maintained behavior — P2

**Source-confirmed.** `composer.json:141` references `noobrocket/tests/Unit/phpunit.xml.dist`, and integration scripts likewise reference a test tree absent from maintained `noobrocket/`. Production vendor dependencies contain no executable PHPUnit/PHPStan in this workspace. Maintained checks actually live at repository-root `tests/`; upstream reference tests live separately in `base-wp-rocket/`.

The legacy Safe Mode service is defined in `Engine/Admin/ServiceProvider.php:51–61`, but `Plugin.php:201–236` does not register its subscriber, and no alternate registration was found. Therefore the reset omissions in `DeactivationIntent.php:119–154` and the unsafe export-header parser in `noobrocket-settings-common.js:37–43` are **dormant code**, not current reachable user failures. The reset-method probe confirms local CSS, modern images and block removal would remain enabled if that legacy method were deliberately reactivated.

**Proposal:** document the active test workflow and service map first. Remove misleading unused Composer scripts or adapt them to the actual supported test entry points; keep upstream suites clearly separate. Prefer removing dormant deactivation modal code if no supported workflow uses it. If recovery becomes a product requirement, implement it coherently through existing validated settings/history owners and explicitly cover modern optimizers/assets, rather than switching the old subscriber back on unchanged.

**Acceptance:** every documented command has available prerequisites and a valid path; each recovery UI maps to an active authorized endpoint and defined scope; dead-service removal preserves activation/deactivation, abilities and compatibility callbacks.

### AR10 — Delegated performance managers can reach unrelated plugin deactivation — P1

**Reproduced with an isolated recording operation; active registration source confirmed.** `inc/admin/admin.php:165–182` registers `admin_post_deactivate_plugin`. It checks a generic `deactivate_plugin` nonce and `noobrocket_manage_options`, then passes the submitted plugin basename directly to `deactivate_plugins`. The conflict notice at `inc/admin/ui/notices.php:135–147` exposes this nonce to NoobRocket managers. No native per-plugin deactivation capability or conflict-target check exists. NoobRocket explicitly exposes its management capability to role editors (`Engine/Capabilities/Subscriber.php:38–43`).

WordPress's own plugin action checks `current_user_can( 'deactivate_plugin', $plugin )` before calling `deactivate_plugins` (`wp-admin/plugins.php:200` in the local WordPress source). The helper itself does not supply that caller authorization. The probe gives only NoobRocket's management capability, submits an unrelated synthetic basename, and reaches a recording deactivation stub. No real plugin is disabled. Default administrators ordinarily have the necessary WordPress permissions; the gap affects delegated/custom roles.

**Proposal:** namespace the action, bind the nonce to the target basename, validate the intended conflict action, and require the native per-plugin deactivation capability in both the notice button and endpoint. Reuse WordPress's deactivation helper after these checks. Keep the ability to resolve real plugin conflicts; do not broaden NoobRocket management into general plugin administration.

**Acceptance:** a performance-only manager cannot disable plugins; an authorized administrator can deactivate a displayed conflicting plugin; changing the target invalidates its nonce; network/plugin-specific restrictions still follow WordPress's native capability mapping. Return a readable denied outcome without a mutation.

## Optimization and maintainability proposals

| Proposal | Evidence and expected scope | Smallest useful step and proof |
| --- | --- | --- |
| Reduce repeat settings-policy work where it is measured | `get_noobrocket_option` creates options wrappers and rereads the filtered whole option for each call (`inc/functions/options.php:17–22`); `free.php` normalizes safelist/options on every read. Many injected controllers already use shared `Options_Data`. This is not proof of a TTFB regression. | Profile cold anonymous misses, uncached authenticated routes and admin renders. Use existing injected settings in controllers with repeated reads. Do not add a global memoization layer that misses `switch_to_blog`, programmatic updates or per-read option filters. Retain helper behavior for public integrations. |
| Avoid unnecessary diagnostics on unrelated routes | `Page::render_page` constructs every section, including seven database-category counts (`Page.php:234,1237–1241`), even when Overview/Tools is selected. All section panels are rendered for client navigation. | Root UI report owns measured HTML/DOM and route behavior. First defer expensive evidence/count work until requested or reuse a short-lived explicitly refreshed result. Keep form values/dirty tracking available; do not replace the whole UI with a SPA to avoid seven queries. |
| Trim disabled module/bootstrap work only after reachability measurement | Common providers include dormant cloud/marketing classes; `PerformanceMonitoringServiceProvider` is declared in both admin/common paths. Some tables/factories initialize during boot, including ATF and performance-hint factories. The container deduplicates provider registration by class identifier, so the duplicate declaration does not prove duplicate SQL. | Measure required files/services, SQL and time by request type. Remove clearly unreachable provider/template dependencies in coherent feature slices, retain shared UsedCSS/preload tables used locally, and preserve existing hint/compatibility behavior. Do not delete modules merely because their names mention legacy products. |
| Continue compatibility gating within the existing resolver | `PluginResolver.php:53` preserves default-active behavior for classes without `PluginCompatibilityInterface`; the registry and constructor argument builder already own this concern. Legacy procedural integrations still load in `3rd-party/3rd-party.php`. | Convert measured high-cost integrations one at a time to accurate installed-plugin detection; prove both active and absent paths plus ordering. Avoid a new adapter or plugin-discovery framework. |
| Reduce settings-page constructor knowledge during related changes | `Page.php:135` takes eleven constructor parameters, including disabled monitoring/subscription contexts, and contains all section construction. It remains understandable at the section-method level, but mixes policy, evidence and provider presentation. | When correcting a feature section, move that section's evidence/policy to its existing feature owner and reuse shared rendering. Extract provider settings together only if it makes ownership clearer. Do not create a class for every checkbox or split the file solely to meet a line-count target. |
| Make release inputs reproducible before pruning dependencies | `tools/build.py:12–20` makes a CRC-checked archive from the current maintained tree, excluding worker `node_modules`. It does not verify source/generated-asset consistency, dependency inventory or translations. Composer/vendor and prefixed `inc/Dependencies` are already shipped. | Add a separate verify step for pinned dependency/autoload inventory, owned generated assets and translated catalogs, then archive only the declared release inputs. Keep package generation stopped until requested. Do not install/update dependencies as part of ordinary packaging. |
| Make supported versions a testable claim | Headers support PHP 7.4 / WordPress 5.8, while this review runs isolated checks on PHP 8.3.35. These checks cannot prove the minimum-version, production MySQL, Nginx/PHP-FPM or multisite compatibility matrix. | Prioritize minimum PHP, a supported current PHP, minimum/current WordPress and Linux/MySQL lifecycle proof in disposable environments. Add WooCommerce/page-builder cases that exercise their real cache bypass and invalidation contracts. A narrow real matrix is better than another large mock-only suite. |

These improvements target extension/admin cost and cache misses. Cache hit rate, invalidation granularity, cold-render concurrency, worker retries, image transfer and browser render cost are covered by the cache/optimization reviews. Measure them independently: a faster settings render is not evidence of a faster visitor page, and a smaller plugin directory does not improve an early cache hit automatically.

## Implementation sequence

| Order | Work | Dependencies and stopping proof |
| --- | --- | --- |
| 1 | Restore a reliable verification baseline: AR08, truthful root test commands from AR09, failing-before/passing-after focused cases. | Keep production source unchanged while repairing test setup. Separate isolated checks from intentionally mutating disposable-site checks. Existing fixture marker alone does not authorize using a user's active preview. |
| 2 | Correct the active deactivation authority boundary (AR10), then bind reviewed publication/restore state: AR01 and AR02; define supported overlapping settings-write coordination. | Reuse native per-plugin capabilities, existing draft metadata, bounded review pattern and native lock. Test delegated roles, changed target, another tab, another user, evicted target, unchanged data and failure recovery. No new storage service. |
| 3 | Consolidate settings domain normalization and truthful action outcomes: AR04 and AR05. | Do one feature group at a time; retain single-field versus complete-form semantics. Check equivalent form/API/import inputs, field errors, unchanged writes and rejected storage. |
| 4 | Correct action/history lifecycle: AR03, AR06 and AR07. | Validation remains pure; independent cleanup/review has exact selected scope; successful change history is recorded once; credential/review cleanup stays site-owned. Verify on a new disposable runtime before wider lifecycle runs. |
| 5 | Apply measured UI/bootstrap optimizations and coherent dead-code removal. | Use baseline PHP/SQL/HTML/DOM/asset observations from the root review. Keep existing framework; stop when the measured hot path and actual user task improve without compatibility regressions. |
| 6 | Prove release and supported-host behavior; publish only on a separate explicit request. | Linux/MySQL/PHP-FPM, direct server rules, multisite, active commerce/builders and current/minimum supported versions. Maintain a rollback path and source/generated artifact equality. |

## Verification performed and limits

All current runs use PHP 8.3.35 or the installed Node runtime and owned in-memory/temp fixtures. No fixture bootstrapped the shared WordPress site. Source findings were reread from the current maintained files; previous completion documents were not treated as proof.

| Command/probe | Result | Scope/limit |
| --- | --- | --- |
| `php tests/wordpress-settings-import.php` | PASS, 14 assertions. | Staging/current-state/token/cancellation behavior under fixture mocks; no refused database write or real concurrent request. |
| `php tests/wordpress-assets-second-pass.php` | PASS, 26 assertions. | Dependency protection, private inventory, public-base conflicts, retained history metadata and rendering. Does not bind the reviewed private draft/current restore state. |
| `php tests/wordpress-uninstall-network.php` | PASS, 638 assertions. | 105 mocked blogs, context restoration and known cleanup ownership. Does not enumerate the omitted new transient prefixes. |
| `php tests/wordpress-cdn-validation.php` | FAIL, exit 1, undefined `noobrocket_sanitize_prerender_options`. | AR08 fixture bootstrap failure. Do not count this as a passing maintained test. |
| `.artifacts/full-review-20261009/architecture-review-probes.php` | PASS, 6 review assertions reproducing current gaps; also reruns the 26 existing asset assertions. | Publication/restore/history/cleanup use existing native methods with in-memory options and a recording cleanup double. No real conflicting requests, cleanup, SQL or provider changes. |
| `.artifacts/full-review-20261009/architecture-settings-probes.php` | PASS, 4 review assertions reproducing current gaps; 29 existing CDN assertions pass under an explicitly temporary prerender bootstrap shim. | Confirms original failure category and API/form/outcome differences. The shim is not a product/test fix and does not verify prerender policy. |
| `.artifacts/full-review-20261009/architecture-safe-mode-probe.php` | PASS. | Native latent reset method preserves the added optimization flags. Registration trace confirms current app does not activate this subscriber. Initial owned-probe path typo was corrected; it is not a maintained source defect. |
| `.artifacts/full-review-20261009/architecture-deactivation-probe.php` | PASS. | Native active endpoint reaches an unrelated recording target with only NoobRocket management authority. No real plugin operation; fixture bootstrap was corrected to supply its `plugin_basename` stub. |
| `npm audit --omit=dev --json --prefix noobrocket/local-css` | PASS, exit 0; zero registry-reported advisories. | Read-only query of current pinned worker dependency graph; no install/update. Not a PHP dependency or application-security audit, and not proof that dependencies have no unknown defects. |

Full logs are retained under `C:/Users/Guillaume/.codex/logs/commands/`; relevant receipts include `20261009-213834-012-a3d646aeabd24b09903014d11286d06b.log` (import), `20261009-213834-048-8385de5322294a44a79d4699f00e7bf5.log` (assets), `20261009-213834-048-d00932762f264387a43b7aa0ffd4e1a0.log` (uninstall), `20261009-213834-037-4e3e2dfc9e6140b7940dd3fdedc0c341.log` (maintained CDN failure), `20261009-215025-336-59e0e4f7555f4f84ad059de5e95a7cc3.log` / `20261009-214233-331-3c963e87691546889e12a8978e612dae.log` (review probes), `20261009-214417-054-2207365744b74a79b4b4879f7e4f6046.log` (latent reset), `20261009-215202-230-3c0e213467d54dbbbe5846c1a6136e0e.log` (deactivation authority), and `20261009-214015-982-ada2d908439445dba5dfe1e497dd65d5.log` (npm audit).

No PHPUnit/PHPStan run is claimed. Full production database failures, simultaneous real requests, network activation/uninstall and minimum software versions remain unverified in this pass. Root owns the current broad syntax gate and source-preservation receipt.

## Explicitly rejected expansion

- No framework rewrite, generic repository/service layer, new queue daemon or new caching backend.
- No class-per-field schema system, global transaction bus or speculative plugin discovery abstraction.
- No broad regex/DOM micro-optimization without measured cost and semantic proof.
- No blanket memoization of filtered settings or filesystem evidence.
- No database transaction wrapped around WordPress hooks that also mutate files, background jobs or providers; retain verified outcomes and recovery at the owning operation.
- No dependency removal or integration renaming based on file size, name or an inactive local example alone.
- No “100% optimized”, “bug-free” or comparative-speed claim from syntax, mock checks or this architecture review.

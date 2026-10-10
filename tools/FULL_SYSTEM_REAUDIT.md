# Cache-Cache full-system reaudit — 2026-10-10

The maintained plugin was reviewed again across cache delivery, preload, lifecycle, settings and providers, optimization, workers and every admin route. Confirmed defects were reproduced and fixed in their existing owners. The supplied Cache-Cache identity is now integrated into the real WordPress panel. No package, commit, push or live deployment was produced. The workspace has no Git metadata; task-specific before snapshots preserve comparison evidence without overwriting accumulated work.

This is the current review. The [2026-10-09 full-system review](FULL_SYSTEM_REVIEW.md), [implementation plan](IMPROVEMENT_PLAN.md) and [batch record](batches/BATCH_FIXES.md) retain their historical findings and proofs. They are not current unresolved-finding lists or proof that every later revision passes.

## Coverage and architecture

Review followed registered request paths and state ownership, then used isolated native owners and read-only WordPress rendering. It covered maintained code and integration boundaries; bundled dependencies and the preserved `base-wp-rocket/` reference were not manually re-audited line by line. Tests importing WordPress classes did not bootstrap the protected preview. Native UI checks used a header-gated request fixture, an owned session and blocked operation requests.

| Area | Reviewed ownership and retained invariants | Detailed evidence |
| --- | --- | --- |
| Page cache | Early anonymous disk delivery, response privacy admission, variant layout, executable policies, complete file publication, exact invalidation ownership, expiry and server delivery contracts. Private early delivery remains unavailable without native session verification. | [Cache/lifecycle reaudit](audits/REAUDIT_CACHE_ARCHITECTURE_20261010.md), [prior cache corrections](batches/CACHE_FIXES.md). |
| Preload and lifecycle | Native URL table normalization, completion evidence, pending/running admission, configured pause, activation dispatch, network deactivation and capability scope. | [Cache/lifecycle reaudit](audits/REAUDIT_CACHE_ARCHITECTURE_20261010.md). |
| Settings and providers | Existing settings-domain transactions, import review, private asset draft persistence, capability/nonce boundaries and provider request ownership. Cloudflare/Sucuri operations use saved credentials; cleanup remains separate from settings Save. | Standalone settings-domain, import, provider-boundary and private-draft fixtures; [prior implementation contracts](batches/BATCH_FIXES.md). |
| CSS/JS and resources | Actual minify/combine admission, valid HTML attributes, modules, CSP/SRI, unavailable sources, exact resource origins, CSS imports, fonts and dimensions. | [Optimization reaudit](audits/REAUDIT_OPTIMIZATION_20261010.md). |
| Local CSS, images and browser preparation | Existing serial worker, bounded retries and publication identity, conservative CSS originals, responsive image families and beneficial derivatives, asset dependencies, shared hints and speculation-rule exclusions. | [Optimization reaudit](audits/REAUDIT_OPTIMIZATION_20261010.md), existing isolated generator/link/image fixtures. |
| Admin UI and branding | All 14 routes, shared shell, widths, controls, feedback, task hierarchy, provider states, asset workflows, history/import comparisons, themes, translations and no-JavaScript behavior. | [UI/brand reaudit](audits/REAUDIT_UI_BRAND_20261010.md), final native matrix and scoped accessibility proof. |

Keep the WordPress hooks, subscribers, container, native forms and existing queues. New helpers belong to the owners of shared invariants: cache-file readiness and affected-site deactivation. No new cache service, scheduler, parser dependency, general transaction framework or SPA was added. Cache completion means usable required variants exist; enabled settings and queued work do not prove completed coverage.

## Confirmed defects corrected

| ID | Failure | Correction |
| --- | --- | --- |
| RC01 | Preload completion used a differently normalized URL from insertion. | Reuse the query owner's stored-URL normalization. |
| RC02 | Empty/stale mobile companions counted as complete; basename replacement corrupted index-prefixed directories. | Check readable nonempty bytes and required freshness; derive companion names from the basename. |
| RC03 | Preload detection lowercased literal permalink paths and missed real cache files on Linux. | Match the native writer: preserve literal case and normalize encoded hexadecimal bytes. |
| RC04 | Network deactivation left early policies on losing blogs, cancelled independently active queues and removed the originating independent blog's capabilities. | Share current-network affected-site enumeration across policy, queue, cron and role owners; preserve independently active sites and restore blog context in `finally`. |
| RC05 | Activation ran a blocking homepage worker, even when preload was disabled. | Queue through the existing async hook and respect saved enabled/paused state. |
| QOPT-01 | Valid unquoted script attributes lost execution; module/JSON-LD types and quoted `>` were misread. | Extend the existing attribute parser; preserve assignment bytes and non-JavaScript types. |
| QOPT-02 | Actual minification/import/dimension owners matched site text anywhere in a URL. | Compare parsed hosts exactly, preserving explicit CDN and relative-URL behavior. |
| QOPT-03 | Modules/native opt-outs and configured exclusions were admitted through alternate quoting or remote/empty-template branches. | Apply intrinsic and configured guards before selecting the source branch. |
| QOPT-04 | One unavailable script source produced a partial bundle that removed every original. | Abandon partial combination and preserve the original group. |
| QOPT-05 | Script transformation discarded nonce/SRI metadata, breaking enforcing CSP or integrity expectations. | Preserve protected original tags through the existing intrinsic admission guard, including inline combination. No policy weakening or synthetic integrity metadata. |
| UI01–UI02 | Production retained the old identity and duplicated CSS setup links. | Supplied Cache-Cache theme-specific SVGs, one accessible brand name, matching semantic tokens and one browser-friendly setup destination. |
| UI03–UI04 | Compact navigation and several controls were unusable without JavaScript. | Keep native destinations/forms reachable, hide actions that require JavaScript and explain PageSpeed limitations. |
| UI05 | Later shared CSS centered narrow provider headings/actions. | Correct the responsible responsive rule at matching specificity. |
| UI06 | Singular asset/rule/history counts used plural grammar in both languages. | Native `_n` and translated composition; French stays in authoritative PO/MO catalogs. |

The independent final code review found the initially missed inline nonce branch; it was returned to the optimization owner for reproduction, correction and native/browser regression proof. Reviews do not justify declaring the app perfect or assigning a numerical beauty score.

## Brand and data compatibility

Cache-Cache is the displayed product name in plugin metadata, settings menus, notices, labels, capabilities and the supplied logo system. The technical directory, `NoobRocket` namespace, `noobrocket` text domain, options, hooks, routes and protocol identifiers stay stable. `NOOBROCKET_PLUGIN_SLUG` is explicitly `noobrocket`, rather than derived from the display name. This prevents a visual rename from losing settings, drafts, history or enqueue/routing ownership.

Catalog metadata is derived from the plugin header. Existing translations were retained when display messages changed; the updater verifies placeholders and compiled singular/contextual/plural lookups. All 36 PO/MO catalogs were compiled, with 82,055 lookups verified. French singular/plural forms and no-JavaScript guidance live in PO/MO, not a source-language dictionary. The bundled browser help now describes persistent toasts and new-tab saved-draft previews accurately.

## Page-by-page result

| Route | Reviewed result |
| --- | --- |
| Overview | Cache evidence comes first; preload/CSS share aligned summaries. Configuration, recorded batches, worker readiness and whole-site coverage remain distinct. PageSpeed prerequisites and recovery remain visible. |
| Preload | Cache warming and browser next-page preparation remain independent choices; exclusions and advanced queue detail stay with their task. |
| Cache rules | Cache policy/lifespan precede specialist exclusions. Shared width, labels and prerequisite states remain consistent. |
| CSS & JavaScript | Main delivery choices precede minification and folded advanced controls. One required local-worker setup link; conservative originals and warnings remain discoverable. |
| Media | Loading, modern image delivery and dimensions use the shared setting rows. Required dependencies and unavailable legacy compatibility retain explanations. |
| Asset Manager | Inspection, private editing, preview and confirmed publication retain distinct ownership. Populated/stale inventories preserve editor access; rule/resource summaries now use correct plurals. |
| CDN | Hostnames, scope, exclusions and verification stay ordered. Dynamic Add is hidden without JavaScript; native existing fields remain usable. |
| Integrations | Shared divided provider/compatibility rows; switches, identity and Configure destinations remain aligned. |
| Cloudflare | Saved credential readiness precedes concrete provider options and cache action. Narrow heading alignment corrected; inert no-JavaScript Show hidden. |
| Sucuri | Firewall-key requirements precede action readiness. Narrow heading alignment corrected; native key submission retained. |
| Heartbeat | Context controls use shared rows and narrow stacking; no page-specific width or ordering exception. |
| Database | Cleanup scope/counts stay separate from recurring settings. Opened warnings, translated counts and destructive review were inspected. |
| Settings history | Compact expandable comparisons retain native restore ownership; singular additional-setting summaries corrected. |
| Tools | Active review precedes transfer controls; Import/Export remain one coherent task surface. No duplicate Asset Manager/history footer navigation restored. |

See [design guidelines](DESIGN_GUIDELINES.md) for ongoing hierarchy, interaction, translation and acceptance rules. Mockups inform restraint; production evidence and accessibility own acceptance.

## Verification and limits

Focused source/fixture evidence is recorded in the three domain reports and [tests/README.md](../tests/README.md). Artifacts remain under `.artifacts/reaudit-20261010/` and `.artifacts/reaudit-final-20261010/`; private session/baseline files are removed after cleanup rather than published.

| Final gate | Result and boundary |
| --- | --- |
| Syntax | PASS: 877 maintained PHP source/fixture files and 51 JavaScript/worker/browser-fixture files. Vendor, Dependencies and node_modules excluded. PHP 8.3.35 and installed Node syntax only; no WordPress bootstrap. |
| Cache/preload/lifecycle | PASS: 41 preload outcomes, 29 settings/activation, 27 native lifecycle assertions on Windows and Linux, 541 modeled database-lifecycle assertions and nine case-sensitive native cache-layout assertions. Actual files/roles with modeled blog/cron/scheduler state. |
| Script optimization | PASS: 39 native resource and 47 Delay Chromium assertions; 62 native minifier and 110 Chromium delivery assertions. Covers external/inline CSP nonce preservation, SRI, modules, valid attributes, useful ordinary optimization and unavailable-source recovery. |
| Settings/API/provider ownership | PASS: 78 shared settings/API, 29 CDN validation, 16 import transaction, 84 provider boundary and 32 private metadata/history assertions. Isolated writers; zero external provider requests. |
| Native page matrix | PASS: all 14 routes in 448 default/open render records across EN/FR, light/dark, 1440/390px and selected 320px states. Zero page overflow, page errors or operation requests. All routes share 1008px desktop/356px narrow content widths in their baseline configuration. 3,988 collected button/select/summary/submit instances meet 44px height. |
| Accessibility and visual review | PASS: 112 scoped axe states with zero violations. All 58 distinct incomplete contrast nodes checked with native computed colors, minimum 7.578:1. Automated rules and computed colors do not prove screen-reader behavior; all-route screenshots and opened workflows were also inspected. |
| Branding/shared interactions | PASS: 495 native shell/no-JavaScript checks, 248 shared-control checks, 405 appearance checks using seven synthetic native Save redirects, 34 semantic contrast pairs, six native grammar states and 27 isolated asset/history assertions. No settings-save request reached the protected runtime. |
| Translations | PASS: 36 compiled PO/MO catalogs, 82,055 verified compiled lookups; original translations retained. |
| Protected preview cleanup | PASS: product options, history, private metadata, locale, domain bookkeeping and all four pre-existing sessions preserved. Owned session, MU fixture and three private QA files removed. No baseline restored. |

Quiet command logs are retained under `C:/Users/Guillaume/.codex/logs/commands/`. Final complete-source syntax receipt: `20261010-010159-618-880602bb5803497cb21c00ed64d94bcf.log`. Final 448-state capture receipt: `20261010-005820-469-640c151ce93248059947fbc30608d15a.log`; geometry receipt: `20261010-010300-967-d8b5fa6094d5476691bd7653b42985c3.log`. Final translation receipt: `20261010-005758-113-a82144bc98ba49debb93f145f5b69767.log`. Domain reports retain focused failing-before/passing-after and accessibility receipts.

The exact native-state comparison initially returned FAIL because the `cron` row's timestamps advanced normally. Follow-up diagnosis confirmed the four affected hooks retained identical event configurations; every other protected row remained equal. Sanitized evidence is `.artifacts/reaudit-20261010/runtime-preservation-exact.json` and `preservation-summary.json`. Cleanup receipt: `20261010-010426-200-adc012b3b34a44e4a8ec0db67db19ad2.log`; successful scoped diagnosis/private-file removal: `20261010-010446-006-991c349cd3434c5594ab5fef77af931c.log`. Cron was neither rolled back nor represented as an exact-byte PASS.

The baseline settings response contains about 278–290kB of HTML and 2,687–2,688 DOM elements in the measured EN/FR request-only states, with five owned scripts and three owned styles. This is admin output, not visitor payload or a latency measurement. No lazy-rendering framework was introduced without measured evidence that this shell is a bottleneck.

No production FCP/LCP/CLS, p95, cache coverage or matched WP Rocket superiority claim follows from source checks or local browser rendering. Minimum PHP/WordPress execution, native multisite Action Scheduler behavior, external provider accounts, representative image quality, assistive-technology/RTL interaction and target-host worker CPU/RSS remain unproved here.

The known expiry filesystem walk remains proportional to the owned tree; the earlier 50k-URL/200k-file Windows profile took about 11–12 seconds. Cold font preparation retains its bounded synchronous budget. Neither was replaced by speculative persistent machinery. Next performance work should measure these paths on an owned representative runtime, then change the responsible owner only if timing demonstrates a material bottleneck. Static Nginx delivery still requires the documented operator configuration for PHP-enforced stale-refresh policies.

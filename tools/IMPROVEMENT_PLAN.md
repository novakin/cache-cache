# NoobRocket improvement plan — 2026-10-09

Improve the current plugin in bounded batches. Preserve working WordPress architecture and defaults. Correct privacy, authorization, reviewed state and fallback before increasing optimization. Then reduce measured background, PHP and browser costs. Finish each batch with focused proof; stop when its acceptance holds.

This document retains the original approved plan. The resulting fixes, proof and measured/deferred boundaries are tracked in [the current batch implementation record](batches/BATCH_FIXES.md). Proposal wording below is historical; it does not mean the recorded corrections remain unfixed. See the [full review](FULL_SYSTEM_REVIEW.md), [architecture findings](audits/FULL_REVIEW_ARCHITECTURE.md), [cache findings](audits/FULL_REVIEW_CACHE.md), [optimization findings](audits/FULL_REVIEW_OPTIMIZATIONS.md), [provider findings](audits/FULL_REVIEW_PROVIDERS.md) and [card audit](CARD_BY_CARD_AUDIT.md) for the original evidence. IDs refer to those reports; repeated IDs indicate a dependency, not another defect.

Effort is relative: **S** is one owner/focused change, **M** crosses a few existing owners or needs native fixtures, **L** requires a real compatibility/load matrix. These are planning sizes, not promised days or speed gains. Priority follows consequence rather than file size.

## 0. Establish a trustworthy baseline

| Work | Owner / effort | Completion gate |
| --- | --- | --- |
| Preserve the current source and start local version history | Workspace / S | All 1,673 initially inventoried files match; six pre-existing dot-path omissions are documented. Initial complete source-control baseline includes only intentional files and excludes secrets/runtime evidence. No remote publication or history rewriting. |
| Repair the unavailable CDN fixture bootstrap and document available tests (AR08–09) | Root `tests/`, developer docs / S | Native fixture reaches its assertions with actual policy helper; documented commands point to available inputs. Preserve strict warning handling. |
| Establish editable frontend inputs and repeatable generation | Existing asset/tool ownership / M | Rebuilding produces the declared settings JS/CSS and catalogs deterministically. No manual patch only in minified files. Do not rebuild/package as part of this review. |
| Promote new reproductions to focused regression cases during each fix | Responsible native owner / S per batch | Current failing behavior is demonstrated before a change; repaired behavior and important adjacent invariants pass after. Reuse current test structure. |

No implementation batch can be called complete from syntax or historical check totals alone. A mutating fixture requires a separate marked disposable site; the user's current preview is not a throwaway merely because it is localhost.

## 1. Correct privacy, authorization and operation ownership

| Order / findings | Smallest coherent work and owner | Expected benefit / effort | Required proof |
| --- | --- | --- | --- |
| 1A — CACHE-01–02 | Cache loader/publication gate: native-authenticated private delivery or temporary early bypass; honor explicit public-response privacy directives. | Correct private/public boundary / M | Valid/invalid/revoked sessions, harmless cookies, private/no-store headers, existing policy invalidation. Public anonymous fast path stays intact. Define User Cache headers separately. |
| 1B — CACHE-03–04 | Existing configuration/filesystem owners: publish complete PHP policy/drop-in atomically; reject incomplete config; exact owned host/user directory selection. | Availability and site isolation / M | Concurrent old/new reads, empty/truncated files, write/rename failure, OPcache contract, sibling hosts, foreign drop-in and multilingual/subsite paths. Disposable filesystem proofs only. |
| 1C — AR10 | Conflict notice/native endpoint: target-bound action/nonce and core per-plugin authority. | Least authority for delegated managers / S | Performance-only role denied; authorized real conflict resolution works; target/network restrictions enforced. No unrelated plugin operation. |
| 1D — AR01–02 | Existing private draft/review metadata, publication/restore owners and lock: bind expected draft/current saved state to the displayed proposal. | Apply only reviewed changes / M | Two tabs, changed current setting, another publisher, replaced/evicted target, unchanged proposal and recovery failure. Preserve draft/saved/public distinctions. |
| 1E — AR04–05 | Settings-owned feature normalizers reused by form/import/Abilities; verify resulting normalized stored values. | Consistent settings and truthful outcomes / M | Invalid host/scope/boolean values rejected; partial API updates preserve unrelated fields; refused changed writes fail; valid no-op remains successful. `update_option(false)` alone is not a write outcome. |
| 1F — AR03, OPT-03, OPT-06 | Native Database action and existing process: immediate cleanup owns its nonce/review/scope; supported current-site tables; bounded resumable categories with cumulative counts. | Prevent unintended work and large maintenance spikes / M | Validation never dispatches; confirmed same-state cleanup still works; foreign/subsite tables untouched; large dataset resumes without skips/repeats; displayed counts match operation. Keep WordPress deletion hooks. |
| 1G — AR06–07 | Existing history/lifecycle owners: ordinary history after successful commit; preserve explicit pre-restore recovery; remove exact owned review/notice records on uninstall. | Useful history and complete owned cleanup / M | Failed/no-op saves do not evict; successful change recorded once; overlapping-write policy; persistent-cache and multisite cleanup without shared flush. Depends on 1D–1F contracts. |
| 1H — OPT-01–05 | Existing font/remote-asset/delay/beacon owners: publish only usable resources, reject HTTP error bodies, bounded external fetch, conservative CSP fallback and effective hash cap. | Working optimized pages and bounded inputs / M | Disk failure, HTTP 404/429/503, query-dependent assets, enforcing/report-only CSP, script order/interaction and over-limit/malformed/duplicate beacon data. Preserve original URLs/scripts on unsupported preparation. |
| 1I — PROVIDER-01–05 | Existing provider subscriber/API owners: redact secret-bearing error context, retain internal cron invalidation, preserve zero TTL and pass the validated exact target zone to provider operations. | Credential safety and fresh edge cache / M | Mocked failures never log credentials; cron local regeneration triggers configured edge purge; manual requests still require capability/nonce; prior zero restores; simultaneous zone/setting changes target the reviewed zone; suffix matching is exact. No real provider mutation in a fixture. |

These batches can be split by invariant, but do not scatter the same validator or write-result policy across endpoints. No global transaction service is needed. Files, provider calls and jobs cannot be made safe merely by wrapping WordPress hooks in a SQL transaction.

## 2. Make caching effective without background spikes

| Work / findings | Owner and smallest approach | Benefit / effort | Decision and acceptance |
| --- | --- | --- | --- |
| Truthful warm-up completion — CACHE-08 | Current cache writer/preload row: success follows verified required variant publication, not just buffer transformation. | Real warm-cache coverage / M | Veto, error/redirect, failed write and uncacheable response are not completed warm-ups. Existing valid hits and configured mobile variants work; failed work recovers. |
| Bound refresh admission and queue work — CACHE-05–06 | Existing expiry controller, preload staging and Action Scheduler: reuse current admission/backpressure; count without full hydration; one URL membership set; pending/running semantics. | Lower background SQL/I/O/CPU when backlog exists / M | Profile 1k/10k/50k URLs, synchronized expiry and compressed/mobile pairs. Bounded admissions, no starvation, hard stale deadline, correct pause/completion and public p95 under regeneration. |
| Prove a useful queue index — CACHE-07 | Existing table migration after native SQL EXPLAIN. Add one demonstrated composite index, if justified. | Faster large-queue queries / M | Plans/timings at 1k/10k/100k rows plus write overhead. Migration idempotence and supported database/index limits. No index solely from an assumption. |
| Canonical automatic discovery — CACHE-09 | Existing home/sitemap crawler: parsed site origin/root ownership before queuing page URLs. | Avoid irrelevant requests/jobs / S | Relative/subdirectory/multilingual links accepted; host suffix/embedded URL/off-site pages rejected. Preserve supported external sitemap source configuration. |
| Bounded CSS retries — OPT-07 | Existing Jobs retry column/state; fixed transient/permanent category, ceiling/backoff and explicit manual reset. | Less wasted Chromium/server work / S–M | Stable failure stops automatically; transient failure recovers; manual Retry works; bad jobs do not starve new ones; originals always remain. |
| Freshness and scoped CSS invalidation — OPT-08–09 | Existing worker result/invalidation owners: retain computed source identity; supported version/purge contract; narrow only proven local changes. | Preserve useful generated work / M | Same-URL supported replacement rebuilt; obsolete result rejected; changed term/post/archive/home pages fresh; global style changes rebuild all. Warm hits perform no stylesheet freshness fetch. |

Cache effectiveness is hit coverage plus correct freshness. A faster hit is irrelevant if most useful pages miss or stale/private content is served. Keep immediate content invalidation. Do not implement a distributed cache coordinator, a cache-catalog service or more workers to compensate for inaccurate queue state.

## 3. Reduce visitor bytes and cold render cost

| Work / findings | Approach in existing owner | Benefit / effort | Proof and stop condition |
| --- | --- | --- | --- |
| Modern images — OPT-10 | Require a meaningful byte benefit at selected quality; record intentional skips in attachment metadata; preserve original/external ownership and complete responsive families. | Lower image transfer where encoding helps / M | Tiny/larger encode stays on useful original; beneficial family works at each width/DPR; visual quality and image LCP measured. No broken family or endless skip retries. |
| Cold fonts — OPT-11 after OPT-01 | Bound remote work/cooldown first. If cold cost is material, prepare through existing background machinery and retain working original delivery until complete. | Lower cold TTFB and fewer repeated failures / M | Slow/failing provider bounded; fallback and later ready state work; CSP/CLS preserved. Do not add a font daemon. |
| Critical/used CSS — OPT-12 | Measure inline/original/generated/wire bytes and first/repeat render. Remove proven duplication/needless safelists before changing conservative grammar/state retention. | Less render-blocking work or HTML bytes / M | Representative templates, breakpoints, menus/modals/store interactions. Retain unsupported rules/fallback. No universal inline size cutoff or score-driven removal. |
| Native image lazy mode — OPT-19 | Evaluate the already available native-image path; retain above-fold/LCP exclusions and JS for frames/backgrounds/dynamic cases. | Potentially less browser JS / M | First-image/LCP timing, videos/iframes, dynamic images and interactions. Keep existing default unless matrix demonstrates improvement. |
| Asset graph/metadata — OPT-15–16 | Empty-rule fast path; request-local immutable rule/duplicate image lookup reuse where measured. Re-read late-enqueue queues at each necessary phase. | Lower uncached PHP/filesystem cost / S–M | Empty/100-rule profile and late enqueues; dependency protection, originals and dimensions unchanged. No persistent metadata service. |
| Hints, LRC and worker budget — OPT-13–14,17–18 | Profile total host/browser cost. Use aggregate generator bounds, existing bridge command consolidation or scoped native CSS only when evidence warrants. | Less wasted generation/sampling work / M | Total Chromium/PHP/Node CPU/RSS, job latency, request rate, long tasks, anchors/scroll restoration and actual LCP/CLS benefit. Node-only memory is not total worker cost. |

Speculation Rules prerendering and browser fallback already exist. Improve exclusions/activation compatibility only where a real site case fails. Keep it opt-in and intent-based; do not introduce broad automatic prerendering as a substitute for first-load optimization.

## 4. Finish the interface as one app

Use the [current card audit](CARD_BY_CARD_AUDIT.md) and [design guidelines](DESIGN_GUIDELINES.md). Retain semantic light/dark colors, system typography, divided settings, native disclosures, navigation icons and the hidden sidebar scrollbar. The mockup informs hierarchy but is not the acceptance target.

| Batch | Existing owner | Completion gate |
| --- | --- | --- |
| Shared native states — SH01–03 | Native checkbox/radio and primary-button components | Checked/unchecked, enabled/disabled, focus/hover distinguishable in both themes at 320/390/1440px. One indicator owner; disabled Publish cannot look available. |
| Cancel and dirty tracking — SH04, ADM-01 | Existing form owner and saved baseline | No document reload; restore values and added/removed CDN rows; one change calculation per update; preserve route/theme/focus and private saved asset draft. Keep unfinished-entry confirmation. |
| Asset Manager/history/import task flow | Existing templates/editor/review owners, after 1D–1E | Select an inspected asset without raw-ID entry; error beside editor; short rules fully usable; unchanged/stale review clear; recognizable history summaries; active import decision first. EN/FR and keyboard proof. |
| Database and operational status | Existing scoped action/settings and diagnostic owners, after 1F/2 | Deletion scope/counts/confirmation agree; recurring scope clear; queue/worker/image/provider enabled, available, pending and ready states are distinct. No false completion. |
| Providers, CDN and setup | Existing fields/verification/setup surfaces | Labels/controls/acquisition help stay together; concrete remote effects visible; saved resource scope supplies a useful CDN sample; narrow long URLs fit; setup precedes advanced exceptions. |
| Shared hierarchy and feedback — SH05–08 | Existing buttons, task headers, row/disclosure/footer/tray | Stationary button bounds, consistent help/navigation roles and compact copy. Meaningful error severity, persistent readable outcomes, inline local errors and focus clear of feedback. No repeated oversized cards or arbitrary equal heights. |

Inspect all 14 routes at 1440px and 390px in English/French and both themes. Use selected 320px and intermediate laptop widths for controls, credentials, inventory and comparisons. Include clean/dirty, off/on, configured/empty, pending/success/error, unchanged/stale and recovery states. Check Back, direct hashes, keyboard order and no-JavaScript recovery for changed tasks. Keep translations in PO/MO and meaningful HTML in the owner.

A numerical 95/100 target is not a verification method. Completion means no unresolved confirmed defects in the changed surfaces, coherent task success and visual review at real sizes. Human task timing/screen-reader checks add evidence; assertion/screenshot counts cannot substitute for them.

## 5. Optimize plugin/admin cost only where measured

Start with ADM-01's repeated work and the confirmed style conflicts. Record admin PHP/SQL/render cost, asset wire bytes, startup scripting and input latency before/after. Recover reproducible bundle inputs before pruning legacy modules. Retain GSAP while active warning handlers depend on it; retain early appearance application.

Profile repeated settings reads, feature/table initialization, database-category counts and absent third-party integration work on cold public, uncached authenticated and admin requests. Reuse injected `Options_Data` and current resolver where appropriate. Preserve filtered option semantics, `switch_to_blog`, programmatic writes, late enqueues and compatibility ordering. Remove proven unreachable slices coherently, including misleading dormant recovery paths if unused. Do not split classes to satisfy arbitrary line limits or introduce a class for each field.

Stop a refactor when responsibility is clearer and the measured task/hot path improves without regression. Smaller directory size alone does not improve static-cache TTFB.

## 6. Prove release readiness before packaging

| Gate | Required evidence |
| --- | --- |
| Focused behavior | New defect regressions plus existing relevant native checks; unavailable/failing fixtures reported accurately. |
| Supported platform | Minimum/current declared PHP and WordPress, native Linux MySQL/MariaDB and PHP-FPM; syntax on PHP 8.3 alone is insufficient. |
| Server delivery | Actual Nginx/Apache hit/miss, bypass, invalidation, gzip and query/mobile/private policy. Supported stale refresh uses PHP routing with direct Nginx page-cache rewrites disabled; static hits do not enforce the PHP deadline. Include file-cache behavior after purge. |
| Real integrations | Disposable commerce/account/cart/checkout, page builders, CSP site, block/classic themes and mapped-domain multisite. |
| Worker/media capacity | Sandboxed unprivileged Chromium; host encoder support; total process CPU/memory; lease/retry/recovery and failure fallback. |
| UI | Current all-route/state evidence, both themes/locales, keyboard/focus and documented remaining accessibility limits. |
| Inputs/lifecycle | Reproducible generated assets/catalogs, pinned dependencies/autoload, declared release file inventory, activation/update/uninstall/rollback ownership. No dependency install during packaging. |

Use existing `tools/production-check.mjs`, `tools/linux-production-proof.sh`, `tools/server-load.mjs` and focused tests. Prepare a rollback artifact only when implementation reaches release verification. Packaging, production changes and deployment remain separate explicit tasks.

## Performance measurement and decision rules

Use the existing [benchmark protocol](BENCHMARKS.md). Keep site snapshot, theme/content, options, server, browser and tooling equal. Declare and prove each cache state; compare warmed static hits separately from warmed PHP hits and cold renders. Test homepage, article, archive, image-heavy and interactive/store templates, first/repeat browser visits, desktop/mobile and representative constrained hardware/network profiles. Current tooling is unthrottled unless explicitly configured; a mobile viewport is not a slow-device simulation.

| Objective | Baseline and decision metric | Guardrail |
| --- | --- | --- |
| Effective caching | Hit coverage, cold/warm TTFB, required variant existence and invalidation correctness | No private/stale responses or wrong-host purge; same content and policy. |
| Controlled background work | Queue age, requests/actions per job, task maximum duration, peak host CPU/RSS, SQL and public p95 under workload | Bounded work, no starvation, pause/recovery and hard stale deadline. |
| Faster initial render | Compressed HTML/CSS/JS/image bytes, FCP/LCP/CLS, failed resources and browser long tasks | Menus/forms/store behavior, quality, CSP and fallback preserved. Lab interaction latency is not field INP. |
| Better admin | Render SQL/time, startup/input work and realistic task success | Saved/draft/public ownership, EN/FR, keyboard, both themes and narrow sizes. |
| Useful speculation | Prepared/activated route success, extra bytes and activation side effects | Exclusions, normal navigation, Save-Data and opt-in scope. |

Repeat comparable runs and retain raw samples/variance. Accept a performance change only when the measured target improves beyond noise with no material regression in another important profile. Do not promise percentages or superiority before matched evidence. Set a practical host load budget from the actual VPS capacity and idle baseline; do not invent one universal concurrency value.

## Deliberately excluded work

No new SPA, component framework, cache backend, queue service, worker HTTP API, font daemon, transaction bus or global memoization layer. No broad parallel generation, compulsory speculation, blanket removal of CSS safety, direct bulk deletes bypassing WordPress hooks or weakened CSP. No code golf: prefer named domain operations, readable branches and explicit failure/fallback over compressed expressions. Refactor where ownership improves; do not replace clear existing patterns for novelty.

The plan is complete when each selected batch's proof passes and its documentation reflects the resulting behavior. This original plan marked work proposed; [the implementation record](batches/BATCH_FIXES.md) now identifies resulting corrections and remaining evidence. Historical deployments and unrelated passing fixtures do not establish current release readiness.

# Optimization batch — 9 October 2026

Implements the confirmed resource, fallback and cleanup defects in [the optimization review](../audits/FULL_REVIEW_OPTIMIZATIONS.md). Reuses the existing WordPress filesystem, HTTP API, background queue, CSS table, image editor and cron. No new service, queue framework, packaging or deployment. All measurements below are isolated local fixtures; they do not establish visitor speed or superiority over WP Rocket.

## Implemented behavior

| Review | Change and owner | Evidence / limit |
| --- | --- | --- |
| OPT-01 | `Media/Fonts/Filesystem.php` publishes fonts and their stylesheet through temporary files and a same-directory native rename only after complete readable bytes exist. Failed writes/renames retain original provider delivery. It avoids the filesystem move helper's copy fallback. Full URL identity determines local font filenames. | Native injected partial-write/rename failures, recovery and readable published fonts. Cached historic CSS is not parsed on every visitor request; purge outdated font artifacts when validating an upgrade. |
| OPT-02 | `Optimization/DelayJS/HTML.php` rejects enforcing CSP headers/meta; its Subscriber verifies the selected usable runtime before making scripts inert. Report-only policies retain delay. | Native transform proof plus real Chromium original-script execution under enforcing header/meta, report-only, ordinary and missing-runtime states. Conservative fallback: no CSP weakening or nonce-aware policy parser. |
| OPT-03 | Database counts and `OPTIMIZE TABLE` share current-blog native core-table enumeration. Foreign, plugin, network and sibling-blog tables are outside this operation. Identifiers are quoted; server error rows count as failure. | Native malformed-result/foreign-table sentinel and server-message proof. Keeps the existing non-InnoDB/free-space restriction; this is not network database maintenance. |
| OPT-04 | `AssetsLocalCache.php` accepts only HTTP 200 through safe HTTP requests with a 3-second timeout and 2 MiB response cap. Complete remote host/query identity reaches cache paths and both minifiers. `UrlTrait.php` compares actual hosts. | Native 404/429/503/oversized-body rejection, query variants, cached reuse and suffix/query-host tests. Original asset remains the fallback. |
| OPT-05 | LRC rejects nonstring/over-64 KiB payloads before decode, bounds input arrays to 1,000 hashes, deduplicates and stops at its declared accepted-entry cap (default 20). | Native oversized, malformed, duplicate and first-20 proof. Shared beacon bounds belong to the architecture batch. |
| OPT-06 | Native database tasks select at most 50 IDs using keyset cursors, persist cumulative successes/failures and check native time/memory/cancellation between deletions. | Native 137-object resume across process instances, interrupted-category resume, failed-object preservation and cumulative/cancelled result proof. A native deletion callback can itself exceed a soft deadline; calls are not forcibly interrupted. |
| OPT-07 | Local CSS attempts increment on claim; automatic work stops after three attempts. Transient failures back off 300/600/1,200 seconds. Unsupported CSP/grammar stops automatically; manual Retry resets attempts and tokens. | Native Jobs state/file fixture plus Chromium unsupported-category assertions. Retry eligibility still requires an actual scheduled server-worker run. |
| OPT-08 | Generator source identity is retained through worker → bridge → Jobs publication. Existing claim/fingerprint contracts reject obsolete completion. No visitor-time freshness fetch/hash is added. | Native source-hash/token proof and real generator hash. Same-URL manual replacements use the invalidation contract below. This does not implement automatic polling for stylesheet changes. |
| OPT-09 | Removed duplicate direct create/edit/delete-term CSS invalidation. Public changes remain owned by public-page cache purges; private terms alone no longer invalidate all CSS. | Source-confirmed owner tracing. Public taxonomy/global settings invalidation remains conservative because its page-cache owner globally purges. Fine-grained public taxonomy retention is deferred until affected archive/home/post coverage is proven. |
| OPT-10 | Modern derivatives are generated/delivered only when at least 5% smaller than the original. Intentional skips stay in owned attachment metadata; changed source bytes retry. Refresh removes only digest-matching owned artifacts. | Native benefit/skip/change/ownership fixtures. External sidecars can be skipped for delivery but are never removed. Complete responsive families and original fallback remain required. Uses native encoder quality; Linux GD/Imagick visual-quality validation remains a release requirement. |
| OPT-11 | A frontend font owner has one 3-second cold-download budget; each request uses at most 2 seconds, with 2 MiB response bounds and a 5-minute per-stylesheet failure cooldown. | Native timeout/budget/no-more-requests/cooldown proof. This bounds download admission for that owner, not all PHP/filter work. Fonts still prepare synchronously on misses; no separate background font service was added without installed-host timing. |

Production source under `noobrocket/inc/Engine/`; worker source under `noobrocket/local-css/`. Changes are PHP 7.4-compatible in syntax and retain original cache/asset fallback ownership. Executed PHP is 8.3.35 on Windows; the supported-platform release matrix is not claimed here.

## Immediate database cleanup — AR03

`admin-post.php?action=noobrocket_database_cleanup` is a separate POST operation. Requires `noobrocket_manage_options`, the native `_wpnonce` for `noobrocket_database_cleanup`, `confirmed=1` and `noobrocket_cleanup[]` containing only the seven registered categories. Invalid scope returns 400, unauthorized requests 403 and an already queued/running cleanup 409. A successful request returns to `#database`.

The settings validator removes legacy `submit_optimize` intent without dispatching deletion. Immediate cleanup cannot save unrelated pending settings or change the recurring schedule. Recurring cron still uses saved categories; the page/dialog ownership changes belong to the settings batch.

The existing background queue receives a per-blog action suffix on multisite. Start uses an atomic native option and only releases its own timestamp value. Results persist by category and distinguish complete, failed and cancelled work. Cancellation preserves completed totals, including when a worker finishes a native deletion after cancellation. Completed deletions cannot be undone.

The plugin deactivation entry retains WordPress's native `network_wide` flag and passes it through the existing `noobrocket_deactivation` event. Site-only deactivation stops that site's recurring/background hooks, batches, process lock and progress. Network-wide deactivation enumerates all sites in the current network, switches/restores each blog in `try/finally` and cancels each site's exact queue and cron state. Cancellation markers follow the switched current blog even though the original process was constructed on one site. Legacy unsuffixed health hooks are cleared per affected blog; the old unsuffixed network queue/lock is retired once. Other-network and foreign data remains untouched. An idle site does not receive a fabricated cancelled-cleanup error notice.

Reactivation does not revive removed batches. Restoring a saved recurring cleanup schedules its first event one full native recurrence interval ahead, rather than at the reactivation request's current time. Saved category/frequency choices remain intact. Native deletion callbacks still cannot be forcibly interrupted; cancellation is checked between calls.

The standalone uninstall owner removes exact native site-option queue keys/locks/crons per switched blog and retires the old unsuffixed network queue once. Neither lifecycle path flushes a global object-cache group. Native uninstall fixtures cover 105 sites, multiple owned batches and foreign/sibling sentinels. The network-deactivation fixture executes the real plugin entry, subscriber registry and inherited background queue over 105 modeled sites, including empty-queue behavior on reactivation, future scheduling, site-only scope and switch restoration on an injected failure. This is native source execution with modeled WordPress storage, not a physical multisite deployment.

## CSS invalidation contract

Ready CSS is keyed by mode, page, stylesheet URL/media manifest and safelist; retained source identity records what the worker processed. Normal theme/plugin upgrades and existing page/domain purges invalidate the relevant results. To replace stylesheet bytes manually under an unchanged URL, use a versioned asset URL or explicitly regenerate CSS and page cache. Do not expect retained source hashes alone to detect a file edit without a new worker job.

Global theme/plugin/style changes continue to invalidate all CSS. Public taxonomy changes currently use the global page-cache purge, so their CSS invalidation remains broad. No guarantee of selective public-taxonomy retention is made. Warm cache hits do not issue stylesheet freshness requests.

## Measurement decisions — OPT-12–19

| Review | Bounded evidence and decision |
| --- | --- |
| OPT-12 | A 43,547-byte synthetic article sheet (3,187 gzip bytes) produced 2,120-byte critical CSS (360 gzip) and 2,157-byte used CSS (378 gzip), across four viewport cases. Print grammar and the explicit interaction safelist remain intact. No arbitrary size cutoff or conditional-grammar pruning; installed-site wire bytes and FCP/LCP are still needed. |
| OPT-13 | Six serial fixture jobs completed in roughly 1.76–1.91 seconds each, totaling 24 viewport captures. Wall time does not measure complete Node/Chromium/PHP CPU or RSS. Retain serial work, leases and current bounds; no arbitrary aggregate resource budget or parallel worker infrastructure. Host resource profiling remains required. |
| OPT-14 | No disposable installed WordPress bridge timing available. Retain the CLI bootstrap boundary; no persistent PHP daemon or new IPC protocol. Combine commands only if actual bootstrap cost is material. |
| OPT-15 | Empty asset-rule fast path belongs to the settings/architecture batch. Native dependency and late-enqueue ownership remain unchanged. |
| OPT-16 | Implemented per-render picture derivative memoization, including negative results. For 100 repeated images over 25 warm-filesystem samples, baseline median was 7.5808 ms/200 root lookups; byte-benefit checking before memo was 8.11 ms/200; memoized median was 0.3199 ms/2. For 100 unique images, final median was 9.0763 ms/200, versus baseline 7.76 ms: byte-benefit validation has a cost and memoization helps repetition. Dimensions memoization deferred without its own profile. These are synthetic PHP timings, not visitor savings. |
| OPT-17 | No installed-site beacon transfer/long-task/request-rate comparison. Keep useful native hints and supported opt-outs; do not remove the runtime to improve an unmeasured score. Shared payload bounds are implemented in the architecture batch. |
| OPT-18 | No reproduced CLS/anchor/Back-scroll defect. No new element-sizing infrastructure. Requires a representative long-article browser matrix before sizing/exclusion changes. |
| OPT-19 | Native image-lazy default unchanged. Compare first-image/LCP, dynamic media, background and iframe/video behavior before switching defaults. |

Raw measurement artifacts: `.artifacts/batch-fixes-20261009/optimization-profile.json`, `picture-profile-before.json`, `picture-profile-unmemoized.json`, `picture-profile-after.json`. Harnesses: [optimization-profile.mjs](optimization-profile.mjs), [picture-profile.php](picture-profile.php). Synthetic source/HTTP stays on ephemeral loopback servers. An initial profile assertion omitted the required interaction safelist; the corrected fixture explicitly safelists its dynamic menu state. Production pruning behavior was not changed for that fixture error.

## Dashboard and settings card refinements

OP01/05/06/08/09: empty preload state no longer claims completion; off/paused/recorded jobs have matching descriptions. Failure severities are red, while missing-worker readiness is amber only when work needs a worker. Ready CSS does not imply an idle worker must be running. Deep worker/failure evidence sits below the summary grid at full width, with one rendered setup destination. Original CSS omits its irrelevant regeneration action. Object-cache help uses the shared navigation footer.

Settings setup comes before CSS exceptions. Delay/defer descriptions name their actual trigger; Delay links to the native exclusion deep link. Combining names its exact prerequisites and risk; Safe Mode wording is sentence case. Preload/media progress links sit in their owning task footer. Specialist cache policies reuse configured disclosures; the sole dependent YouTube choice no longer requires an extra disclosure. Heartbeat/font descriptions use shorter concrete effects. English/French/light/dark/narrow visual verification and shared styles belong to the root acceptance matrix; no numeric UX score is claimed.

## Focused verification

### Settings-save preload latency

The save callback previously warmed the homepage synchronously after optimization changes. Its verified preload request could block for up to 20 seconds, then sleep between requests; enabling preload could also crawl the homepage synchronously when sitemap discovery fell back to links. A single-worker PHP preview cannot serve that loopback until the settings request returns. Root's isolated runtime receipt owns the measured timing; the source trace alone does not establish production-server latency.

`Preload/Admin/Settings.php` now queues homepage warming and domain-change discovery through the existing Action Scheduler hooks. `Preload/Subscriber.php` queues initial discovery when preload becomes enabled. Settings callbacks perform no preload HTTP or sleep. The actual worker retains blocking response/publication verification, desktop/mobile coverage, timeouts and existing failure handling. Paused/disabled preload admits no new settings-owned work; domain changes still clear obsolete preload rows.

The admin owner reads current saved settings through `get_noobrocket_option('manual_preload', 0)` instead of its old injected snapshot. A mixed save that disables preload and changes optimization settings therefore stays disabled regardless of callback registration order. Existing `pre_get_noobrocket_option_manual_preload` and `get_noobrocket_option_manual_preload` overrides remain supported. Unused synchronous-controller/options dependencies were removed from this settings owner and its service registration.

`tests/wordpress-settings-preload.php` runs native event registration, the after-save callback, service registration and queue methods against in-memory boundaries. Its 21 assertions cover async admission, queue deduplication, disabled/paused state, mixed-save callback orders, current settings/filter ownership and zero HTTP; a preload-controller sleep trap also rejects synchronous work. `wordpress-preload-outcomes.php` retains 32 publication/outcome/discovery assertions. Both pass. Synchronous filesystem invalidation/config writes and active worker requests remain separate costs; this fix does not claim instant saves for arbitrarily large cache directories or solve single-worker loopbacks inside an independently running preload worker.

- `wordpress-optimizer-resources.php`: 30 native fallback/timeout/identity/CSP/LRC assertions.
- `delay-csp-browser.mjs`: 18 real Chromium script-execution assertions.
- `wordpress-database-cleanup.php`: 27 native scope, resume, result, overlap, action and lifecycle assertions.
- `wordpress-local-css-retries.php`: 25 native retry/token/source-identity assertions.
- `local-css-generator.mjs`: 47 Chromium generator/unsupported-category assertions; interactions suite: 18.
- `wordpress-picture-generator.php`: 36 native ownership/benefit/editor/filesystem assertions; picture-profile `--verify`: 6.
- `wordpress-uninstall-network.php`: 850 isolated native lifecycle assertions across 105 sites.
- `wordpress-database-lifecycle.php`: 537 native entry/event/background lifecycle assertions across 105 modeled sites; receipt `20261009-230924-216-9c9ac4cf7b6945659794de97cd29004b.log`.
- `wordpress-dashboard-state.php`: 13 native template state/ownership assertions.

Command receipts live under `C:/Users/Guillaume/.codex/logs/commands`; failed invocation/profile logs are preserved. Marked disposable-WordPress suites are not run against the shared preview. Installed Linux encoders and physical multisite deployment, representative menus/forms/CSP applications and measured site paint/loading remain distinct release checks. No live HTTP, plugin packaging or deployment performed by this batch.

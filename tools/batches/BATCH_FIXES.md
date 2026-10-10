# NoobRocket batch implementation — 2026-10-09

Later revision: [2026-10-10 Cache-Cache full-system reaudit](../FULL_SYSTEM_REAUDIT.md). This record preserves the earlier batch and its verification; new cache, optimizer and UI corrections are documented in that reaudit.

Maintained source, generated assets, translations, focused regressions and developer documentation implement the approved [improvement plan](../IMPROVEMENT_PLAN.md). The [full review](../FULL_SYSTEM_REVIEW.md) and its linked audits preserve the pre-fix findings; this record describes the resulting behavior and its proof. No plugin package, commit, push or live deployment was produced. The workspace has no Git repository.

## Behavior and ownership

| Area | Implemented behavior | Detailed proof |
| --- | --- | --- |
| Public/private cache — CACHE-01–04 | Anonymous early hits remain. Early private delivery/publication bypasses because native WordPress session identity is unavailable there; User Cache is visibly unavailable. Explicit private/no-store responses cannot publish. Complete executable policy/drop-in files publish atomically. Purge and expiry operate on exact owned host/secret directories and refuse symlink traversal. | [Cache corrections](CACHE_FIXES.md), native cache policy/header/ownership/writer fixtures. |
| Preloading — CACHE-05–09 | Refresh uses existing pending rows and scheduler admission. Counts avoid full backlog hydration; exact action membership and running state prevent false completion. A warm-up completes only after usable required cache variants exist. Automatic discovery stays within the canonical site boundary. | [Cache corrections and native SQL decision](CACHE_FIXES.md), [native server proof](NATIVE_WORDPRESS_PROOF.md). |
| Queue SQL — CACHE-07 | One measured `(status(32), is_locked, modified)` index is added through the existing table migration. Refused DDL retains the previous version for retry; repeated migration preserves rows and is a no-op. | Native MariaDB EXPLAIN/ANALYZE and 11 migration assertions at 1k/10k/100k rows; [query timing table](CACHE_FIXES.md#native-sql-decision). |
| Settings/API/import — AR04–05, AR08 | Settings-owned boolean, enum, lifespan and paired CDN rules serve native forms and partial API updates. Normalized values read back before success. Import remains a reviewed native action and verifies saved outcome. The CDN fixture now loads its actual helper. | [Settings domain/API corrections](SETTINGS_DOMAIN_FIXES.md), native import and CDN fixtures. |
| Draft publication/history — AR01–02, AR06 | Expected draft and saved-state revisions bind actions to their review. Existing native lock owns supported writes. Ordinary history follows committed changes; restore requires verified original-state recovery. Private metadata writes/deletions read back before success; partial outcomes remain explicit. Duplicate/order rule differences stay visible and restorable. | [History/recovery and lock proof](HISTORY_LOCK_FIXES.md), [final draft/history review](INTEGRATION_REVIEW_FIXES.md), reviewed-state and asset/history fixtures. |
| Action authority/lifecycle — AR07, AR10 | Conflict-plugin deactivation is target-bound and requires native per-plugin authority. Native network-wide deactivation cancels exact queues/crons per current-network blog and restores context; initial/reactivated recurring cleanup starts after its full interval. Idle sites avoid false cancellation feedback. Uninstall removes exact owned queues, review records, notices and lifecycle metadata without shared cache flushes. | Action fixtures, 537 modeled network-lifecycle and 850 network-uninstall assertions; [optimization lifecycle details](OPTIMIZATION_FIXES.md). |
| Database cleanup — AR03, OPT-03/06 | Immediate cleanup is an independent native POST with its own nonce, capability, confirmed category scope and background queue. It cannot submit unrelated pending settings or alter the recurring schedule. Categories resume in bounded native ID chunks; results distinguish success, failure and cancellation. Optimization touches only eligible current-blog native core tables. | [Cleanup contract](OPTIMIZATION_FIXES.md#immediate-database-cleanup--ar03), native cleanup fixture, browser transactions and installed multisite proof. |
| Resources/CSP/beacons — OPT-01–05/11 | Fonts publish complete bytes through native same-directory rename. Remote assets require a usable response, complete URL identity and bounded requests. Failed preparation retains originals; enforcing CSP retains original scripts. Shared performance input rejects malformed/nonobject/oversized JSON before feature decoding; LRC additionally bounds accepted unique hashes. | [Optimization corrections](OPTIMIZATION_FIXES.md), resources/CSP fixtures and shared performance-hint boundary fixture. |
| CSS work — OPT-07–09 | Existing jobs have bounded retries/backoff, explicit manual reset and retained source identity. Stale completions remain rejected. Duplicate term invalidation is removed while public/global invalidation stays conservative. | [CSS contract and retry proof](OPTIMIZATION_FIXES.md), native settings/backend and actual sandboxed Linux worker proof. |
| Images/cold PHP — OPT-10/16 | Modern derivatives require at least 5% byte benefit; intentional skips are recorded and changed sources can retry. Original responsive fallback and external-file ownership remain. Per-render picture lookups reuse positive and negative derivative results. | [Image and memoization proof](OPTIMIZATION_FIXES.md); synthetic timings state both repeated-image benefit and unique-image validation cost. |
| Providers — PROVIDER-01–05 | Sucuri error context excludes credentials and raw replies. Cloudflare internal callbacks retain trusted cron ownership; manual actions retain native authorization. Exact target zone, zero TTL and partial-recovery ownership survive failures. | [84 intercepted provider assertions and recovery contract](PROVIDER_FIXES.md). Real accounts were not called. |
| Shared interface — SH01–07, ADM-01/02 | Native checked/disabled/radio states have one visible owner. Buttons stay stationary. Help is a disclosure; related destinations use a shared task footer. Compact navigation switches at 1100px in CSS and JavaScript. Change descriptions are computed once per update; Cancel refreshes categorized counts/parent/mixed states through the existing reset event. The maintained settings bundle has pinned reproducible minification. | [Shared control proof](CACHE_FIXES.md#shared-controls-and-layout), [110 categorized-reset browser assertions](INTEGRATION_REVIEW_FIXES.md), browser transactions/appearance/notices and native UI captures. |
| Task hierarchy | Import review appears before transfer controls and reuses translated Cloudflare credential-editor labels. Cleanup review stays beside deletion selections; scheduling is separate. Asset selection and errors stay with the editor; short rule lists remain visible. CSS setup precedes exceptions. The conditional font tool shares native card padding. Dashboard/provider/CDN evidence distinguishes configured, pending, unavailable, failed and ready states. | Native page/state matrix, [design guidelines](../DESIGN_GUIDELINES.md) and focused template/browser fixtures. |

## Save, Cancel and separate actions

Global Save owns pending native settings. Cancel restores the controls and dynamic CDN rows captured when the current document loaded, without a document request. It preserves the route, appearance, saved private asset draft and navigation focus. Unfinished asset editor input retains its existing warning. A loaded baseline is not a fresh read of changes another tab saved after this document loaded; a reload obtains that newer saved state.

Private draft Add/Remove, reviewed publication, inspection, provider purge, cleanup, import and history restore retain their separate native action boundaries. A private draft is not a saved global setting or visitor publication. Operational navigation must not silently save unrelated form values. Confirmed cleanup can permanently delete selected data; settings history cannot recover it. With JavaScript disabled, cleanup uses the saved categories with an explicit native confirmation.

Changed workflows keep field/review errors beside their owner. Standalone outcomes use the existing persistent themed tray and preserve severity and recovery content. No synthetic design score or claim that every notice belongs in a toast is used as acceptance.

## Reproducible maintained inputs

Run from the workspace root. The installed Node runtime owns bundle generation; `tools/package-lock.json` pins Terser 5.44.0. The readable `noobrocket-settings.js` remains the maintained input, and `noobrocket-settings.min.js` is its served generated output. Active GSAP warning handlers remain.

```powershell
npm ci --prefix tools --ignore-scripts --no-audit --no-fund
npm run generate:settings --prefix tools
```

Keep English gettext keys in source and translations in PO files. The updater supplements literal singular/plural extraction, preserves translations, validates placeholders, compiles all 36 MO catalogs and verifies compiled lookups against PO/fallback. It does not replace complete native gettext extraction. Install `polib==1.2.0` in the chosen Python environment once; this workspace already has an isolated translation environment.

```powershell
& .artifacts/translation-tools/Scripts/python.exe tools/update-translations.py
```

After changing maintained inputs, regenerate their artifacts and compare repeat-generation bytes. Do not patch only a minified file, place French dictionaries in product source or install dependencies during packaging. Current French singular/plural additions belong to `fr_FR`/`fr_CA` PO files.

## Verification record

| Gate | Recorded result and scope |
| --- | --- |
| Cache/policy/writer | 24 policy, 16 configuration, 13 writer, 8 header, 10 ownership, 8 preload and 32 preload-outcome assertions. Atomic fixture observed 1,600 complete native policy includes during replacements. Owned temporary files/native methods; no shared preview mutation. |
| Settings/provider/history | 78 settings/API, 29 CDN, 84 provider, 30 history-lock, 14 reviewed-state and 27 asset/history assertions, plus 32 final private metadata/history regressions. Native owners with intercepted storage/HTTP establish mechanics, not live API acceptance or real contention. |
| Optimization | 30 resource, 18 Chromium CSP, 27 cleanup, 25 CSS retry, 47 generator, 36 image, 6 picture memoization, 13 dashboard, 537 modeled network-lifecycle and 850 network-uninstall assertions. Details and fixture limits remain in [the optimization report](OPTIMIZATION_FIXES.md). |
| Import/shared beacon | 16 import and 11 performance-hint boundary assertions. Import fixture covers staged ownership and verified outcome; shared beacon fixture rejects input before feature decoding. |
| Shared browser controls | 248 checks over 16 width/theme/motion configurations. Native controls, disabled state, stationary bounds, menu and local overflow; [proof](CACHE_FIXES.md#shared-controls-and-layout). |
| Browser transaction/notice/appearance | Focused native-form Cancel/cleanup transaction fixture passed 71 assertions; appearance passed. The final notice gate passed 576 checks, including unmarked native history failures using the persistent error tray. Categorized-list browser fixture passed 110 assertions. Native installed interaction proof passed 89 checks over eight width/theme states, including served minified category-reset handlers, exact form/row restoration and no reload or operation requests. |
| Installed Linux WordPress | 266 assertions plus 10 preflight checks: native MariaDB/PHP 8.4, Apache/Nginx cache isolation, Asset Manager, CSS settings/backend, sandboxed Chromium worker and multisite isolation. Debug output empty; owned processes stopped. [Exact runtime and receipts](NATIVE_WORDPRESS_PROOF.md). |
| Maintained-source syntax | Final source passed 1,413 PHP and 37 JavaScript parses after late lifecycle, draft/reset/history, rendering and notification fixes. The baseline comparison records 106 changed product files. Parsing alone is not behavioral proof. |
| Native UI matrix | Refreshed 448 renders over 70 state configurations across all 14 routes, EN/FR, light/dark, desktop/narrow and selected tight widths; zero page errors or page overflow. Reviewed actual page/card screenshots, including narrow French comparisons, provider credentials, stale publication, conditional font tools and the corrected history tray. Six additional native workflow states confirm localized credential labels, card padding and scoped feedback. Screenshot counts are not human usability trials. |
| Generated assets/catalogs | Repeated settings minification is byte-identical. All 36 MO catalogs compile; 71,619 lookups match PO/fallback. All 104 new entries in each French catalog are translated: 98 singular and six plural entries. Runtime French remains in PO/MO only. |
| Protected preview preservation | Cleanup passed exact protected settings/history/draft/locale/domain-bookkeeping and all pre-existing session comparisons. Only the owned QA session and write-blocking fixture were removed. No baseline was restored over user state. |

Final evidence: [source syntax](../../.artifacts/batch-fixes-20261009/syntax-proof.json), [UI inventory](../../.artifacts/batch-fixes-20261009/inventory.json), [native interactions](../../.artifacts/batch-fixes-20261009/ui-interaction-proof.json), [workflow review](../../.artifacts/batch-fixes-20261009/ui-final-review-proof.json), [catalog proof](../../.artifacts/batch-fixes-20261009/translation-proof.json) and [preview preservation](../../.artifacts/batch-fixes-20261009/runtime-preservation.json). Earlier receipts remain available. See [acceptance commands](../../tests/README.md#batch-implementation--2026-10-09) for reproducible focused gates.

Final quiet receipts under `C:/Users/Guillaume/.codex/logs/commands`: `20261009-231843-357-231db18ab6dc4d85864beebf5ef84930.log` (syntax), `20261009-230914-768-83b02108fd024a3992ff372fc6eb2e50.log` (full UI refresh), `20261009-231459-823-2d9819bdfc624629b8eb625f0a7449f4.log` (final import/font output), `20261009-231825-081-d059cebcef674f2bbeab96d7b434ea90.log` (final history output), `20261009-231111-918-a3b6f29a79b241419c51abf8e5a6e7dd.log` (89 native interactions), `20261009-231750-505-9eedfb8ee8764207af1c71803986ad74.log` (576 notice checks), `20261009-231750-530-717b477902e0473ba90d49538f2971f3.log` (six final workflow states), `20261009-230841-537-72609658bdc4492a9a5dff22ee10f874.log` (catalogs), `20261009-230935-463-c321f2d7002645de9aba6bc4b7c37d68.log` (deterministic minification) and `20261009-231854-006-959644c6441649cf8e2eaaaf6b3f03c1.log` (owned-session cleanup).

## Measured boundaries and remaining release work

- Private User Cache is disabled/unavailable in early delivery; this is a safe bypass, not an implemented logged-in acceleration claim.
- Scheduler admission is bounded. The expiry filesystem walk still scales with the cache tree: the isolated 50k-URL/200k-file Windows profile took about 11–12 seconds. A fair continuation and installed-host visitor p95 need separate proof.
- The new index improves sparse pending/in-progress/failed access in native MariaDB fixtures. Dominant completed counts retain full scans; optional ID ordering did not improve. Older database execution and installed-host write/load costs remain release checks.
- Public taxonomy/global CSS invalidation remains conservative. Retained source identity does not detect unchanged-URL file edits without a new job; use versioned URLs or explicit CSS/page regeneration.
- Font misses still prepare synchronously under bounded download admission. Worker host budgets, GD/Imagick quality/capacity and native callback worst-case duration require installed-host measurement.
- Real provider accounts, commerce/builders, persistent-object-cache contention, mapped domains and minimum supported PHP/WordPress/database platforms remain compatibility checks.
- Current site FCP/LCP/CLS, cache coverage, production p95 and matched WP Rocket comparisons were not measured. Synthetic SQL/PHP/CSS improvements do not establish faster installed sites or universal superiority.
- Human task timing, full screen-reader/RTL and complete no-JavaScript tasks remain separate evidence. Automated checks do not establish a 95/100 beauty or usability rating.

Further work follows measured need in existing owners. No new cache service, SPA, transaction bus, font daemon or parallel generation framework was added. Packaging and live deployment remain separate requested tasks.

## Follow-up: slow local settings save

[Settings-save latency](SETTINGS_SAVE_LATENCY.md) records blocking preload/discovery removal, current saved-option ownership, focused queue proof and the single-worker local transport failure. Save-facing callbacks now enqueue existing worker actions. Worker verification remains intact; synchronous filesystem/provider costs remain separate.

[Inline warning correction](INLINE_WARNINGS_FIX.md) removes inherited purple pseudo-element paint from the opened combining/Safe Mode confirmations. Native theme, language, narrow-layout and interaction checks pass. Its cleanup records concurrent settings changes without restoring older user state.

[Dashboard card refinement](DASHBOARD_CARD_REFINEMENT.md) aligns paired Modern images/Object cache surfaces and footers, and groups the recorded homepage-check result/action/date. Native fixture/current-state checks pass; protected settings and sessions match, with ordinary cron progression recorded separately.

[Overview content review](OVERVIEW_CONTENT_REVIEW.md) replaces ambiguous configuration badges with useful operational states, exposes waiting work, aligns CSS diagnostics with Retry, and makes image/object-cache/PageSpeed scope clear. Existing action owners remain intact; native theme/language/layout and isolated data-scope proof are recorded separately from speed claims.

[Saved-draft preview navigation](ASSET_PREVIEW_NAVIGATION.md) opens visual previews through normal new-tab links while preserving the editor and pending settings. The native POST fallback and signed preview checks remain; mutation commands retain POST ownership.

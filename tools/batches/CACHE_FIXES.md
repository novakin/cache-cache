# Cache and shared-control batch fixes

Date: 2026-10-09. Implements the responsible-layer corrections for [CACHE-01–09](../audits/FULL_REVIEW_CACHE.md) and the assigned shared-control findings from [the card audit](../CARD_BY_CARD_AUDIT.md). Maintained source and focused fixtures changed. No package, deployment, external request or shared WordPress settings/database mutation.

## Cache behavior

| Finding | Correction | Existing owner |
| --- | --- | --- |
| CACHE-01 | Authenticated cache paths require native `wp_validate_auth_cookie()` and the matching current WordPress user. Early bootstrap cannot establish this identity, so it bypasses private delivery/publication. Anonymous fast hits remain. | [Cache buffer](../../noobrocket/inc/classes/Buffer/class-cache.php) |
| CACHE-02 | Explicit `Cache-Control: private` or `no-store` blocks outer cache publication. Header names/directives are case insensitive; multiple headers are inspected. HTML optimization retains its separate gate. | [Buffer tests](../../noobrocket/inc/classes/Buffer/class-tests.php) |
| CACHE-03 | Executable policy and drop-in updates use a same-directory temporary write, full-byte size check and atomic rename. Failure retains old bytes and cleans temporary files. A final completeness marker follows configuration filters; empty, partial, malformed and unmarked policies fail closed. Existing admin repair rewrites missing markers. | [File functions](../../noobrocket/inc/functions/files.php), [configuration](../../noobrocket/inc/classes/Buffer/class-config.php), [drop-in writer](../../noobrocket/inc/Engine/Cache/AdvancedCache.php) |
| CACHE-04 | Manual, homepage and expiry selection share an exact host/current-secret bucket boundary. Lookalike hosts, foreign secrets and symlink roots are excluded. Expiry recursion refuses nested symlinks. Homepage pagination stays inside the selected owned home directories. | File functions and [expiry controller](../../noobrocket/inc/Engine/Cache/PurgeExpired/PurgeExpiredCache.php) |
| CACHE-05 | Refresh enters the existing pending preload table, then uses ordinary scheduler staging/backpressure. Plain/gzip/mobile pairs make one refresh decision per URL per scan. Original stale bytes and the hard one-hour grace deadline remain. | [Preload subscriber](../../noobrocket/inc/Engine/Preload/Subscriber.php) and expiry controller |
| CACHE-06 | Admission uses native Action Scheduler count queries for pending and running actions rather than hydrating the backlog. Outdated rows use exact URL action membership, avoiding the old row-by-action nested comparison. Running-only work prevents premature completion; unavailable counts stop admission. | [Queue](../../noobrocket/inc/Engine/Preload/Controller/Queue.php), [URL controller](../../noobrocket/inc/Engine/Preload/Controller/PreloadUrl.php) |
| CACHE-07 | Native SQL evidence supports one `(status(32), is_locked, modified)` index. Fresh schema includes it; the existing table migration advances version `20261009`. Failed DDL keeps the previous version for retry; repeat application is a no-op. | [Preload table](../../noobrocket/inc/Engine/Preload/Database/Tables/Cache.php) |
| CACHE-08 | Discovery no longer claims completion from the inner optimization buffer. The outer writer returns a publication result and emits its completion hook only after cache bytes exist. Blocking bounded preload checks HTTP 200 plus every configured desktop/mobile variant. Redirects/errors, uncacheable responses, missing variants and refused writes cannot claim success. A missing discovery row causes no invalid status update. | Cache buffer, preload subscriber and URL controller |
| CACHE-09 | Automatic page discovery uses native URL resolution and exact scheme, host, effective port and site-path ownership. Userinfo, fragments and dot segments are refused. Explicit external sitemap sources/children remain supported through the native safe HTTP API. | [Discovery trait](../../noobrocket/inc/Engine/Preload/Controller/CheckExcludedTrait.php), [homepage crawler](../../noobrocket/inc/Engine/Preload/Controller/CrawlHomepage.php), [sitemap reader](../../noobrocket/inc/Engine/Preload/Frontend/FetchSitemap.php) |

**Private User Cache is unavailable in the early drop-in.** This batch deliberately does not add a second late-authenticated delivery pipeline. The corresponding root UI correction communicates that restriction. It must not be described as working logged-in caching. Native private/no-store responses also veto publication after authentication.

Configuration safety does not depend on a separate identity/session companion file. No new queue, catalog, background service, policy database or ORM was introduced. The existing compatibility action getter remains group scoped, but active product admission uses native counts instead.

## Native SQL decision

[The isolated runner](preload-sql-proof.sh) creates an unprivileged MariaDB datadir/socket under a new `/tmp/noobrocket-preload-sql.*` directory with networking disabled. [The fixture](../../tests/wordpress-preload-sql.php) installs disposable WordPress, loads the maintained table/query owners, captures their actual generated SQL and records native `EXPLAIN` and `ANALYZE FORMAT=JSON`. External HTTP and mail are intercepted. The owned database process is stopped; its diagnostic directory is retained.

MariaDB `11.8.6` with utf8mb4 was exercised at 1k/10k/100k rows. Dataset: 2% pending, 0.5% in progress, 5% failed, 92.5% completed, 0.2% locked. Each read uses 15 local warm samples; these are SQL timings, not page latency or production performance guarantees.

Final 100k-row result:

| Real query | Before median / p95 | After median / p95 | Native actual rows before → after |
| --- | --- | --- | --- |
| Unlocked in-progress count | 7.190 / 7.788 ms | 0.131 / 0.335 ms | 100,000 → 500 |
| Pending admission ordered by modified, limit 45 | 1.230 / 1.358 ms | 0.130 / 0.229 ms | 2,988 → 45 |
| Pending admission ordered by ID, limit 45 | 0.225 / 0.275 ms | 0.233 / 0.317 ms | 2,007 → 2,007; existing primary key retained |
| Pending count | 6.055 / 6.782 ms | 0.260 / 0.334 ms | 100,000 → 2,000 |
| In-progress count | 5.987 / 6.201 ms | 0.164 / 0.219 ms | 100,000 → 500 |
| Completed count | 6.985 / 7.779 ms | 7.041 / 7.655 ms | 100,000 → 100,000; full scan retained |
| Failed count | 6.020 / 6.158 ms | 0.497 / 0.615 ms | 100,000 → 5,000 |

The index is justified by sparse queue access and the default pending ordering. It does not improve the dominant completed count or the optional ID ordering. The 32-character prefix covers the existing short status names and keeps its utf8mb4 key well below the older 767-byte index budget; older MySQL/MariaDB execution was not tested.

Write measurements are single transactional local batches, including client round trips and rollback. At 1k rows, 1,000 status updates measured 64.80 → 69.89 ms and 1,000 inserted rows 6.88 → 8.08 ms. At 10k: 65.85 → 71.40 ms and 7.25 → 8.53 ms. At 100k: 77.92 → 72.60 ms and 10.26 → 10.88 ms. Earlier concurrent runs varied substantially; the index has real write/storage cost, and these numbers do not establish a write-speed improvement.

Proof: `.artifacts/cache-fixes-20261009/preload-sql-proof.json`. Receipt: `C:/Users/Guillaume/.codex/logs/commands/20261009-225157-593-ad32ef0e8a784c49873e0fefb24e48f6.log`. Eleven native assertions cover source schema, actual query capture, new-install index, old-version upgrade with 100k retained rows, repeat migration, refused DDL/version preservation and retry.

## Shared controls and layout

Changes reuse [the existing semantic CSS](../../noobrocket/assets/css/noobrocket-admin.css):

- SH01: native checked boxes have an action background and a visible white native marker. Custom switch/cleanup label ownership remains separate.
- SH02: the Save ID no longer inflates every primary selector's specificity. Disabled primary and purple actions have authoritative muted states.
- SH03: CSS mode radios suppress the inherited WordPress pseudo-dot and retain their own selected indicator and native grouped-keyboard behavior.
- SH05: buttons keep stationary hover/focus/active bounds and zero vertical margins; their action row owns spacing.
- SH06: standalone help shares the link treatment, with a minimum 44px target and underline on hover/focus. Root owns broader route/footer placement corrections.
- SH07: the existing compact menu switches at 1100px, leaving useful working width on laptops. WordPress bar offsets retain their native narrower breakpoints. Root owns the matching JavaScript query.
- Shared hierarchy: dashboard cards use natural heights. Short draft inventories use natural overflow; long inventories remain bounded. Root owns the template classification and editor workflow.

SH04 cancel/dirty tracking and the broader SH08 admin payload/profile decision belong to the root batch. This CSS work does not claim lazy route rendering or reduced production transfer/DOM cost.

[The browser fixture](../../tests/settings-controls-browser.mjs) uses actual WordPress forms CSS, packaged legacy/settings CSS and current maintained admin CSS/JavaScript. It makes no WordPress request. It passed 248 checks over 1440/1024/390/320px, light/dark and normal/reduced motion. Checks cover native confirmation boxes, disabled actions, radio indicator ownership, stationary bounds, row spacing, standalone help target/decoration, menu Enter/Escape/focus, short/long inventory overflow, no horizontal overflow and no runtime exceptions. Screenshots were visually inspected at desktop dark and 320px light, as well as desktop light during the preceding run.

Proof: `.artifacts/cache-fixes-20261009/controls/proof.json` and adjacent screenshots. Receipt: `20261009-224847-763-0544728b972e40919c7c8087f0824a83.log`. This isolated fixture does not serve the custom icon font; its synthetic cleanup glyph therefore cannot establish product icon correctness. Root's native page audit owns that proof.

## Focused verification and remaining limits

| Gate | Result and boundary |
| --- | --- |
| [Configuration](../../tests/wordpress-cache-config.php) | 16 safe/missing/empty/partial/malformed/preview checks; no WordPress bootstrap. |
| [Cache policy](../../tests/wordpress-cache-policy.php) | 24 atomic-failure, ownership, refresh-pair/deadline, early-private refusal, anonymous path and publication/veto assertions using owned temporary files. |
| [Cache writer](../../tests/wordpress-cache-write.php) | 13 actual filesystem/concurrent writer assertions. |
| [Atomic policy](../../tests/wordpress-cache-atomic.php) | 1,600 native includes during 120 slow replacement attempts; only complete old/new policies observed. Windows safe rename refusal is also permitted by the fixture. |
| [Response headers](../../tests/wordpress-cache-headers.php) | Eight isolated loopback HTTP privacy/optimization cases; owned PHP server cleaned. |
| [Cache ownership](../../tests/wordpress-cache-ownership.php) | Ten existing ownership assertions. |
| [Preload](../../tests/wordpress-preload.php) | Eight native-class layout/cache-hit/mobile/pause assertions; WordPress files read without booting its installation. |
| [Preload outcomes](../../tests/wordpress-preload-outcomes.php) | 32 native-owner assertions with in-memory HTTP/action/database boundaries. Final missing-row case uses warnings as errors. |
| Syntax | 28 affected PHP sources/fixtures parsed on PHP 8.3. Final native SQL fixture ran on WSL PHP. Product changes retain PHP 7.4-compatible syntax; PHP 7.4 execution is not claimed. |

Combined focused receipt: `20261009-224919-049-ea501b4e68624bf2a55f27253a260584.log`; final 32-outcome receipt: `20261009-225021-225-83b7ebd912c340158e84ce77bde6c07e.log`; 28-file syntax receipt: `20261009-224919-084-19475d03f98e4a7e80647b8b9b181b1c.log`; final refined PHP/SQL-shell syntax receipt: `20261009-225432-234-0d67e838eda34ac3bc8939a2ed7c1849.log`. Root owns consolidated isolated-runtime regression for stale refresh, WebP, full diagnostics and native server delivery. Mutable acceptance tests were not booted against the shared local preview.

The expiry filesystem walk remains proportional to the cache tree. Owned Windows temporary profiling at 1k/10k/50k URLs (four variants each) measured fresh scans 0.187/2.297/11.141 seconds and synchronized expired scans 0.146/1.668/12.126 seconds, with 2/4/8 MiB peak PHP memory. Refresh decisions fell to one per URL, but every eligible URL can still touch the existing pending table. `.artifacts/cache-fixes-20261009/scan-profile.json` records the boundary: no SQL, web-serving or production p95 was measured.

Scheduler action admission is bounded; filesystem traversal and pending-row touches are not. A time cutoff without a fair persistent continuation would starve tail directories. The measured follow-up is to reduce large-tree scan work inside the current expiry owner with fairness proof, rather than introduce a second scheduler/catalog service. Live server regeneration, public p95 latency, alternate database versions, native symlink behavior and a positive authenticated late-cache pipeline remain unproved by this batch.

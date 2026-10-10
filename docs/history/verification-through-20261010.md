# Historical verification record

Preserved from tests/README.md on 2026-10-10, with relative repository links adjusted. Superseded as the current contributor recipe by [tests/README.md](../../tests/README.md). All source/version claims, commands and outcomes below retain their original dated scope; they do not prove the later process implementation or development beta.2. Private artifact/log references are operator receipts, not clean-checkout prerequisites.

# Cache-Cache acceptance checks

Current package: **Cache-Cache 0.1.0-beta.1**, `dist/cache-cache-0.1.0-beta.1.zip`. A stable 1.0.0 has not been released; earlier 1.x development numbers below identify historical beta builds. The [2026-10-10 full-system reaudit](../../tools/FULL_SYSTEM_REAUDIT.md) records the maintained implementation. Earlier receipts remain historical; the package-specific proof below covers this beta.

## Beta package proof — 2026-10-10

The ZIP retains `noobrocket/noobrocket.php` for installed-plugin replacement. Plugin metadata, worker manifest/root lock entries, POT and all 36 PO/MO catalogs use `0.1.0-beta.1`; the inherited engine remains `3.23.5.1`. Dependency versions and historical archives remain unchanged.

The build contains 1,681 unique entries, 7,213,360 compressed bytes. Every archived byte matches the maintained source; CRC validation passes. Runtime data, upstream reference source and `node_modules` are excluded. SHA-256: `635b94d9d3deed6c6648fc9dc6d5534c975d65e0fada2748cffd9062b39a15da`. A checksum file sits beside the ZIP.

Windows PHP 8.3.35 syntax checks pass for all 1,471 plugin/test PHP files; Node syntax checks pass for 51 plugin/test scripts. All catalogs compile and pass 82,055 lookup checks. Standalone requirements, native deactivation and settings-import fixtures pass 15, 27 and 16 assertions respectively.

Six native acceptance phases (`activate`, `boot`, `hooks`, `release-update`, `branding`, `render`) pass against the extracted ZIP in a separate WordPress 7.1.3/SQLite installation, with HTTP blocked. The release-update phase models persisted `1.3.1` settings: the lower beta product number preserves optimization choices, refreshes bundled lists and purges obsolete caches without engine migrations. The final isolated WordPress debug log is empty. Initial runs exposed old brand expectations in two fixtures; expectations now match Cache-Cache. Failed logs remain retained.

Evidence is retained under `.artifacts/beta-package-20261010/` and the operator command logs. The existing preview is not used for lifecycle tests. No GitHub publication or live deployment occurs. This package check does not repeat browser design audits, target-host benchmarks, minimum-version checks or the Linux/multisite matrix. Historical proof below keeps its original scope.

Current implementation: [batch corrections and proof](../../tools/batches/BATCH_FIXES.md), [native Linux WordPress proof](../../tools/batches/NATIVE_WORDPRESS_PROOF.md) and [current acceptance commands below](#batch-implementation--2026-10-09). The [full-system assessment](../../tools/FULL_SYSTEM_REVIEW.md), [approved plan](../../tools/IMPROVEMENT_PLAN.md) and [documentation-only review checkpoint](#full-system-review--2026-10-09-documentation-only) preserve the original findings. Passing historical fixtures do not prove a later source revision.

The [independent UI/UX audit](../../tools/UX_UI_AUDIT.md) records the historical usability baseline separately from automated acceptance results. That read-only audit covered all 13 then-existing routes, both themes, English/French, populated assets, keyboard/navigation behavior and intercepted transaction scope. No settings mutation or product fix was executed for the audit. The later [improvement record](../../tools/UX_UI_IMPROVEMENTS.md) documents the current UX01–UX23 changes and focused proof; old passing checks do not negate the baseline findings.

Run against a disposable WordPress installation with NoobRocket installed as `noobrocket/noobrocket.php`. These tests activate, deactivate, uninstall, and change settings. Never use an existing site containing valuable data.

Create an empty `.noobrocket-acceptance-site` file in the disposable WordPress root. Use a local site at `http://127.0.0.1:8765` with administrator user ID 1.

```powershell
php tests/wordpress-acceptance.php /absolute/path/to/wordpress activate
php tests/wordpress-acceptance.php /absolute/path/to/wordpress boot
php tests/wordpress-acceptance.php /absolute/path/to/wordpress hooks
php tests/wordpress-acceptance.php /absolute/path/to/wordpress settings
php tests/wordpress-acceptance.php /absolute/path/to/wordpress import
php tests/wordpress-acceptance.php /absolute/path/to/wordpress render
php tests/wordpress-acceptance.php /absolute/path/to/wordpress cdn
php tests/wordpress-acceptance.php /absolute/path/to/wordpress minify-purge
php tests/wordpress-acceptance.php /absolute/path/to/wordpress release-update
php tests/wordpress-acceptance.php /absolute/path/to/wordpress health
php tests/wordpress-acceptance.php /absolute/path/to/wordpress abilities
php tests/wordpress-acceptance.php /absolute/path/to/wordpress branding
php tests/wordpress-acceptance.php /absolute/path/to/wordpress veto
php tests/wordpress-acceptance.php /absolute/path/to/wordpress deactivate
php tests/wordpress-acceptance.php /absolute/path/to/wordpress uninstall
php tests/wordpress-acceptance.php /absolute/path/to/wordpress collision
```

Focused fixtures; the current batch adds the standalone checks [below](#batch-implementation--2026-10-09):

```powershell
php tests/wordpress-external-contracts.php /absolute/path/to/wordpress
php tests/wordpress-cache-config.php
php tests/wordpress-bloat-object-cache.php
php tests/wordpress-preload.php /absolute/path/to/wordpress
php tests/wordpress-link-prerender.php /absolute/path/to/wordpress
node tests/preload-links-browser.mjs /absolute/path/to/chromium
php tests/wordpress-webp-cache.php /absolute/path/to/wordpress
php tests/wordpress-taxonomy-cache.php /absolute/path/to/wordpress
php tests/wordpress-cache-http.php /absolute/path/to/wordpress
php tests/wordpress-stale-refresh.php /absolute/path/to/wordpress
php tests/wordpress-assets.php /absolute/path/to/wordpress
php tests/wordpress-assets-ux.php /absolute/path/to/wordpress
php tests/wordpress-picture-delivery.php /absolute/path/to/wordpress
node tests/picture-delivery-browser.mjs /absolute/path/to/wordpress /absolute/path/to/php /absolute/path/to/chromium
php tests/wordpress-picture-generator.php
php tests/wordpress-overview.php
php tests/wordpress-cache-write.php
php tests/wordpress-cache-ownership.php
php tests/wordpress-requirements.php
php tests/wordpress-uninstall-network.php
php tests/wordpress-settings-import.php
node tests/settings-transactions-browser.mjs /absolute/path/to/chromium
php tests/wordpress-assets-second-pass.php
node tests/second-audit-catalog-browser.mjs /absolute/path/to/chromium /absolute/path/to/php
```

Settings import now uploads and validates a JSON file before showing changed saved values, with integration credentials hidden. Confirmation replaces only the reviewed proposal. The per-user review expires after ten minutes; another upload, changed saved settings or changed native validation requires a new review. Import excludes the cleanup submit action, asset rules and settings history. The standalone import fixture checks staging, confirmation, cancellation, native nonce/capability checks, credential redaction and stale or replaced reviews without loading WordPress. The Chromium fixture uses synthetic native forms to check cleanup scope, dirty section ownership, saved-state guidance, unfinished asset entry, numeric IDs and pending feedback with a delayed local response. The existing disposable-site `import` phase follows upload and confirmation; do not run it on a user's runtime.

External contract checks serve synthetic Cloudflare responses, block other HTTP, and preserve GeotargetingWP cookie names. Cache configuration checks use temporary files without loading WordPress; they cover a configuration removed after memoized path discovery and call the initialization gate directly before any failed-load preconditioning. The gate must load valid configuration before optional checks, including when every optional check is disabled. Successful loads remain consistent within the request; failed loads invalidate path discovery and stop initialization cleanly.

External contract checks also preserve BeRocket's runtime identifier, WP Recipe Maker's CSS prefix, Royal Elementor Addons' actual plugin basename and the literal external advertising domain. These names belong to other integrations and are not NoobRocket-owned prefixes.

The standalone image generator, concurrent cache writer, loader ownership, requirements notice and network-uninstall fixtures need no site argument. They use mocks or owned temporary files; the writer launches twenty real PHP processes. The network fixture covers 105 blogs and context restoration after injected failures. Image-generation fixtures cover source-byte replacement, output ownership, locking, bounded work and resumability; native host encoding is verified separately.

`wordpress-overview.php` loads no site and mocks every homepage request. It checks the probe's original request boundaries and expiry, code-based current-locale presentation with old-record compatibility, meaningful transport failure output, dated evidence, setup-only versus recent worker activity, empty CSS queues, related next actions, image prerequisites and resume wording, and native Overview hook ordering. Real scheduling and visitor performance remain outside this presentation fixture.

`wordpress-bloat-object-cache.php` runs without WordPress bootstrap or site writes. It checks opt-in frontend removal, block/embed/dependency preservation, admin and alternate-response bypasses, malformed option rejection, existing-provider detection, connection failures, isolated temporary-key probes and cleanup after read/write/delete failures. It never installs an object-cache drop-in or flushes another cache group.

Preload checks load WordPress classes without booting its database; they verify HTTP/HTTPS cache detection and separate mobile variants. WebP checks run conversion through the cache writer with temporary files, including inherited Rocket markers. Taxonomy checks use actual term APIs and disposable posts/terms, isolate cache/config files in a temporary directory, and restore preload rows. They prove removed and added archives, ancestors, pagination, query variants, and old/current post URLs are invalidated; unchanged, excluded, imported, nonpublic, nonqueryable, and unrelated-object relationships retain cache.

HTTP checks require the disposable site running at `http://127.0.0.1:8765` with working pretty permalinks. Run site-mutating fixtures serially. The HTTP fixture creates and removes a unique page and authenticated session, saves mobile options in independent WordPress requests, then restores settings and cron state. It checks cold/warm HTML delivery, gzip-only delivery, plain/gzip conditional 304 responses, separate mobile variants, authenticated/query bypasses, content invalidation, and expiry regeneration. Actual early-cache responses expose `Cache-Status: NoobRocket; hit`.

The stale-refresh fixture proves the supported configuration gate, generated early policy, Apache PHP routing, public HTTP/HTTPS and desktop/mobile/gzip retention, immediate TTL purge of query/user/legacy WebP variants, pending/running Action Scheduler deduplication and concurrent enqueue handling, failed refresh timestamp preservation, real native forced regeneration, hard cutoff before cron, immediate content/manual purge, and preview/query/cookie/authenticated bypasses. It verifies raw precompressed bytes, decoded HTML, encoding/Vary headers and explicit gzip refusal. It restores settings, pause/cron state, its created AS actions, page and session. An owned temporary MU plugin prevents asynchronous AS runners during the proof. `NOOBROCKET_STALE_TEST_ORIGIN=http://127.0.0.1:8877` reroutes its delivery probes through a dedicated loopback Nginx proxy forwarding to the marked PHP server on 8765; this proxy must send `X-NoobRocket-Proof-Route: php`, preserve the backend Host, disable direct page-cache rewrites and block direct page-cache directory URLs. This checks Nginx-to-PHP routing and the same early cutoff; it does not prove a production PHP-FPM configuration.

The 2026-10-09 stale-refresh run passed 35 main assertions against Windows PHP 8.3.35 / WordPress 7.1.3 / SQLite, plus six child assertions on native request arguments. The isolated nginx 1.26.3 proxy run passed 37 main assertions and the same six child assertions. It used a dedicated loopback listener on 8877 and the marked PHP server on 8765; production Nginx configuration and PHP-FPM remain unverified by this fixture. Full command logs are retained in the operator's Codex command-log directory; the dedicated Nginx configuration/logs are under `.artifacts/nginx-stale-refresh-proof/`.

## Local CSS fixtures

Local CSS defaults off. Generation requires Node.js 22 or later, PHP CLI, an explicit Chromium executable, and the pinned dependencies installed with `npm ci`. Run the worker on the same server as WordPress; Chromium's Linux sandbox stays enabled. These fixtures use the existing disposable-site marker and administrator user ID 1. Run the WordPress fixtures serially.

Install dependencies beside the generator being tested and the worker in the disposable WordPress plugin. A source junction makes these the same directory, so only one installation is needed:

```powershell
npm ci --prefix noobrocket/local-css
npm ci --prefix /absolute/path/to/wordpress/wp-content/plugins/noobrocket/local-css
```

Run the settings and backend checks without starting a browser:

```powershell
php tests/wordpress-local-css-settings.php /absolute/path/to/wordpress
php tests/wordpress-local-css.php /absolute/path/to/wordpress
php tests/wordpress-local-css-fonts.php /absolute/path/to/wordpress
```

The settings fixture is read-only and checks form/import/Abilities policy. The backend fixture uses temporary posts, terms, settings, job rows and cache paths to check enqueue/claim/publication, lease ownership, stale results, exclusions, cascade/media order, frontend eligibility and invalidation. It restores its saved state.

The font fixture calls the actual native font controller with deterministic cached CSS. It verifies provider ownership, native exclusions, ready/pending critical delivery, CSP/bypass state resets, explicit inline opt-out and unchanged used/off defaults. Network access is blocked; fixture state is restored.

For the HTTP/worker fixture, first start the disposable WordPress HTTP server at `http://127.0.0.1:8765` with working pretty permalinks. Pass the installed Chromium executable explicitly; the optional third argument selects the Node executable:

```powershell
php tests/wordpress-local-css-http.php /absolute/path/to/wordpress /absolute/path/to/chromium node
```

This fixture exercises anonymous HTTP requests, the actual Chromium worker and same-server PHP bridge, public generated CSS files, critical/used delivery, early-cache hits and cleanup. Pending or failed generation keeps the original CSS active.

The standalone Chromium fixture starts its own loopback servers and does not require WordPress. Pass the generator path followed by the Chromium executable:

```powershell
node tests/local-css-generator.mjs noobrocket/local-css/generator.mjs /absolute/path/to/chromium
node tests/local-css-interactions.mjs noobrocket/local-css/generator.mjs /absolute/path/to/chromium
node tests/benchmark.mjs /absolute/path/to/chromium
php tests/wordpress-local-css-worker.php /absolute/path/to/wordpress
php tests/wordpress-diagnostics.php /absolute/path/to/wordpress
```

It checks CSS selection and URL rebasing, hidden/dynamic states, desktop/mobile viewport samples, asset-origin restrictions, redirects, prerequisite failures and CSP fallback. See [worker setup and recovery](../../noobrocket/local-css/README.md) for production scheduling and safelists.

The collision phase leaves an `OtherCache` sentinel in `wp-content/advanced-cache.php`. Remove that test file before reactivating NoobRocket. Tests preserve WP Rocket sentinel settings, cache files, RUCSS cron events, shared plugin-family settings, and pending preload actions. They also assert that unsupported imported cloud settings stay disabled and vendor/cloud/telemetry callbacks are absent.

After reactivation and `boot`, run `restore` to leave NoobRocket active with CDN rewriting disabled for browser verification. `minify-purge` creates owned files and checks selected-extension deletion for plain/gzip files on native filesystem paths, unselected/unrelated file retention and another site directory's isolation. `release-update` checks that changing the public product version refreshes bundled compatibility lists and removes obsolete page/plain/gzip minification caches without rerunning engine migrations or resetting local optimization options. It models older persisted settings without calling this release's save callbacks and places the page sentinel under the actual hostname cache directory. `import` exercises the actual JSON upload/import handler and preserves site keys/version markers. `health` tests overdue/future preload events; `abilities` rejects cloud settings and writes local settings; `branding` checks plugin links, license upsells, and CLI registration. `veto` checks that an earlier WordPress filter can prevent cache drop-in/config ownership changes.

For HTTP auditing, copy `wordpress-http-audit.php` into the disposable site's `wp-content/mu-plugins/` before activation. It records destination host/path in `wp-content/http-audit.jsonl` and blocks remote HTTP requests while allowing localhost. This is test instrumentation, not part of NoobRocket.

## Verified 1.0.1 local run — 2026-10-08

Windows, portable PHP 8.3.35, WordPress 7.1.3, official SQLite Database Integration 3.0.2. Plugin loaded through a junction to the working source. PHP archive SHA-256 and WordPress archive checksum matched their official download metadata.

| Phase | Result |
| --- | --- |
| `activate`, `boot` | Activation/admin initialization pass without keys. Product 1.0.1 and engine 3.23.5.1 remain separate. No upstream globals. Own cache/config/drop-in generated. |
| `hooks` | Vendor update/license/cloud/telemetry service callbacks absent; inert database schema hooks retained. Local export REST registered; remote dynamic-list and upstream export routes absent. |
| `settings` | Imported cloud CSS, monitoring, analytics, and license credentials disabled/removed. WP Rocket settings untouched. |
| `import` | Actual export/JSON upload/import succeeds without warnings. Local optimization applies; site keys and product/engine versions preserved; cloud/account settings stripped. |
| `render`, `cdn` | Settings page renders. Manual HTTPS CNAME and zone save through registered WordPress sanitizer. Vendor CDN cannot be enabled. |
| `release-update` | Public version update replaces stale copied compatibility JSON and cached lists. Preload, CSS/JS minification, lazyload, and defer settings preserved; engine migration does not run. |
| `health`, `abilities`, `branding` | Overdue active preload event produces warning; future event accepted. Cloud options rejected; local option saved; capability check retained. No paid support/renewal links; CLI catalog uses NoobRocket. |
| `veto` | Earlier false filters remain false and prevent drop-in generation. |
| `deactivate`, `uninstall` | WP Rocket settings/cache, shared plugin-family option, both original RUCSS cron forms, pending original preload action, and another owner's cache drop-in preserved. |
| `collision` | Active WP Rocket and another owner's drop-in reject activation without replacing their data. |
| `restore` | NoobRocket active, CDN disabled, original preload action pending. Frontend HTTP 200 and fresh WordPress debug log empty. |

Anonymous frontend requests generated desktop/mobile HTML and gzip cache files under `wp-content/cache/noobrocket/pages/`. PHP HTTP audit recorded local requests and WordPress core destinations; no WP Rocket, GPLTimes, RocketCDN, or telemetry destination was requested.

PHP lint passed for all 1,389 plugin PHP files and all four PHP test files. External contract fixtures passed nine assertions; cache configuration fixtures passed seven. Initial test-fixture errors, reproduced regressions, and lifecycle warnings were preserved separately before the clean final run. Final WordPress debug log is empty.

SQLite and PHP's built-in server do not prove production MySQL/MariaDB behavior, Apache rewrite delivery, older supported WordPress/PHP versions, or compatibility with every third-party plugin. Browser checks are separate from this CLI suite.

Browser checks passed at 1440 × 960 and 390 × 844 with light and dark browser preferences. The manual CDN checkbox and CNAME survived a real form submission. Each form submits the CDN value once, no cloud polling runs, the page loads no external scripts, and the final console contains no errors or warnings. Local test data was restored after verification; the temporary PHP server was stopped.

The 1.0.1 follow-up also checked the French dashboard at 1440 × 960, plus dashboard, CSS explanation, and manual CDN layout at 390 × 844. Long translated text fits without horizontal overflow; console is clean. Anonymous frontend HTTP returns 200, creates own cache files, and exposes the NoobRocket performance comment without the upstream marketing URL. All 36 compiled translation catalogs parse; 41 French lookups pass; repeat translation generation is byte-identical.

## Verified 1.0.2 cache checks — 2026-10-08

Same disposable Windows/PHP 8.3.35/WordPress 7.1.3/SQLite runtime. Eight PHP fixture files now cover lifecycle, HTTP auditing, external contracts, cache configuration, preload, WebP, taxonomy relationships, and HTTP delivery. The 1.0.1 browser proof above remains historical; these checks verify cache behavior.

| Fixture | Result |
| --- | --- |
| `wordpress-acceptance.php` | All 16 lifecycle phases pass with product 1.0.2 and engine 3.23.5.1. Final restore leaves NoobRocket active and manual CDN rewriting disabled. |
| `wordpress-preload.php` | Seven assertions. HTTPS cache detection works from HTTP/CLI cron context; HTTP and HTTPS variants remain independent. Desktop plus mobile variants complete preload without redundant HTTP. |
| `wordpress-webp-cache.php` | 34 assertions across six scenarios. Converted/original/mixed/no-image pages select the correct variant; NoobRocket and inherited Rocket markers work, disappear from delivered/cached HTML, and agree with gzip and `.no-webp` state. |
| `wordpress-taxonomy-cache.php` | 87 real WordPress assertions. Replacement, append, and direct removal purge changed archives/ancestors/pagination/query/mobile files, old/current category permalinks, and preload rows. Unrelated child archives, no-op writes, imports, exclusions, unpublished posts, and unsupported object taxonomies retain cache. Disabled-hook baseline reproduces stale removed-category cache. |
| `wordpress-cache-http.php` | 20 assertions. Actual plain/gzip-only hits and both 304 paths expose the hit header. Authenticated requests and unknown queries bypass shared cache; mobile uses its own files. Content changes invalidate both variants; expired pages regenerate and become warm again. |

Programmatic mobile option saves now regenerate `advanced-cache.php` when mobile settings change, including when no settings form was submitted. Focused handler checks passed 30 assertions; the HTTP fixture also switches separate-mobile settings off/on through independent WordPress requests.

Final lint passed for all 1,389 plugin PHP files and all eight PHP test files. External contract checks passed nine assertions; cache configuration checks passed seven. The fresh final WordPress debug log is empty. Settings were restored and the temporary PHP HTTP server was stopped after verification.

Remaining limits: production MySQL/MariaDB, Apache rewrite delivery, older supported WordPress/PHP versions, broad third-party compatibility, concurrent cache writes, and 1.0.2 browser layout were not verified by these fixtures.

## Verified 1.1.0 local CSS checks — 2026-10-08

Same disposable Windows/PHP 8.3.35/WordPress 7.1.3/SQLite runtime, with an installed Chromium executable and Node.js worker dependencies. Product 1.1.0 and inherited engine 3.23.5.1 remain separate. Local CSS is opt-in and defaults off; the original cloud generation flags stay disabled.

| Fixture | Result |
| --- | --- |
| `wordpress-local-css-settings.php` | 39 assertions. Default/invalid modes, form/import policy, credential/cloud stripping, preserved minification/exclusions, literal safelist merge/replace/clear and native capability checks pass. Preinitialized WordPress hooks block mutating SQL and external HTTP before bootstrap; raw saved settings/rules/history rows remain unchanged. The original 37-check fixture's history side effect is documented in the [readability follow-up](#hierarchy-notice-readability-and-tools--2026-10-09-unpackaged). |
| `wordpress-local-css.php` | 121 backend assertions. Jobs, claims, publication, retries, lease ownership, stale-result rejection, artifact cleanup, variant isolation, cascade/media order, HTML attributes, exclusions, eligible frontend contexts, CSP fallback and purge invalidation pass. |
| `wordpress-local-css-http.php` | 55 real HTTP/Chromium/CLI assertions. Both local modes generate and publish actual browser results, expose readable public CSS files, preserve original cascade/media positions and inline styles, purge the pending page, and become real early-cache hits. Fixture data, settings and cron state are restored. |
| `local-css-generator.mjs` | 28 Chromium assertions. Rule selection, hidden menus, dynamic states, viewport samples, relative URLs, prerequisite failures, asset-origin allowlists, redirects and enforcing/report-only CSP behavior pass. |
| `wordpress-acceptance.php` | All 16 lifecycle phases pass at 1.1.0. Activation, import, local settings, abilities, product/engine separation, ownership vetoes, collision rejection, deactivation and uninstall isolation remain valid. Final restore leaves NoobRocket active with manual CDN rewriting disabled. |
| Cache regression fixtures | 20 real cache HTTP assertions, 87 taxonomy assertions, 34 WebP assertions, seven preload assertions, seven cache configuration assertions and nine external contract assertions pass against the current release. |

The worker also passed a stale-result proof: when both completion and failure marking are rejected after invalidation, it continues draining other jobs. PHP syntax checks passed for all 1,394 plugin files and all 11 PHP test files.

The real settings page was checked at 1440 × 960 and 390 × 844 with light and dark browser preferences. A real settings form save passed; the page had no horizontal overflow or console errors/warnings. The isolated UI harness answered WordPress's core Gravatar image request locally. Plugin settings, cron state and the temporary administrator session were restored exactly after the proof.

The final WordPress debug log is empty and the disposable PHP HTTP server is stopped. Fixture bootstrap bypasses warm homepage delivery so cached HTML cannot skip assertions. Earlier fixture failures, including an expired original preload sentinel, remain archived separately.

Performance benchmarks were not performed. Production MySQL/MariaDB, Apache/Nginx static cache delivery, multisite, Linux Chromium sandbox operation, older supported PHP/WordPress versions and broad third-party compatibility still need target-site verification. Future classes or application states absent from browser samples require safelisting; interactive pages need staging review before enabling used CSS.

## Verified 1.2.0 Windows release checks — 2026-10-08

Windows, PHP 8.3.35, WordPress 7.1.3 and SQLite Database Integration 3.0.2, using Chromium 148.0.7778.96 and Node.js 26.7.0. Product 1.2.0 remains separate from inherited engine 3.23.5.1.

| Fixture | Result |
| --- | --- |
| `wordpress-acceptance.php` | All 16 lifecycle phases pass. Activation, import, product updates, local settings, unavailable cloud options, ownership vetoes and uninstall/collision isolation remain valid. |
| `wordpress-local-css.php` | 126 real WordPress assertions, including cookie exclusion and dynamic/mandatory-cookie configuration isolation. |
| `wordpress-local-css-worker.php` | 31 assertions covering worker exclusivity, expiry, successor ownership, health, pause and failed-job retries. |
| `wordpress-diagnostics.php` | 42 assertions covering capability/nonce/POST controls, cache probes, queue recovery, pause/resume and bounded preload workload without premature timeout. |
| `wordpress-local-css-http.php` | 55 real HTTP/Chromium/PHP bridge assertions covering generation, publication, CSS delivery and warm page caching. |
| `local-css-generator.mjs` | 46 Chromium assertions including import cascade/conditions/assets, conservative native nesting, every-hop redirect validation and CSSOM mutation fallback. |
| `local-css-interactions.mjs` | 18 assertions covering menus, dialogs, tabs, AJAX, focus and synthetic WooCommerce-like cart states at desktop/mobile sizes. This is not an installed WooCommerce store. |
| Cache regression fixtures | 20 HTTP, 87 taxonomy, 34 WebP, eight preload, seven cache configuration, nine external contract and 37 local CSS settings assertions pass. |

PHP lint passed for all 1,396 plugin files and 14 PHP fixtures. The real worker dependency check passed through the selected site's PHP bridge and Chromium launch; Windows does not provide the Linux Chromium sandbox. A WebP fixture shutdown warning was traced to deleted temporary configuration; the fixture now restores native cache state before WordPress shutdown. Earlier failed checks and debug output remain archived under `.artifacts/` and the operator command logs.

French diagnostics passed 36 browser checks at 1440 × 960 and 390 × 844 with light/dark preferences. Eight screenshots were visually inspected. Controls meet 44 × 44 px, with no horizontal overflow or console warnings/errors. Settings, cron, worker/pause state, locale and sessions were restored exactly. All 36 compiled catalogs parse, 90 French lookups pass and all 73 translation artifacts are byte-identical on regeneration. The final disposable WordPress debug log is empty.

Explicit CLI site selection passed a real WordPress/Chromium check. Four incorrect roots (wrong scheme, path, query or credentials) were rejected before claims; the configured site's scheme is checked independently of the caller's HTTPS request.

Production environment proof and benchmarks are recorded separately below as they complete. These checks do not establish compatibility with every page builder, installed WooCommerce workflow or older supported PHP/WordPress release.

## Verified 1.2.0 Linux and multisite checks — 2026-10-08

Debian 13 under WSL, PHP 8.4.26 CLI/FPM, MariaDB 11.8.6, WordPress 7.1.3, verified official Node.js 22.23.3 and Chromium 154.0.8037.92. The runner used UID 1000 with Chromium's sandbox enabled, private database/socket and dedicated loopback-only Apache/Nginx configurations. Proof artifacts are retained under `.artifacts/linux-proof-1.2.0/` and the operator's native Linux proof directory.

Apache and nginx each passed 17 real static cache/isolation assertions. Single-site and multisite production preflights each passed 5/5 checks. The MariaDB backend passed 126 local CSS and 37 settings assertions. The actual Linux worker claimed, generated and published used CSS, with three publication checks.

The actual network fixture converted an already active plugin to network activation, created a subsite, and passed five activation/site-birth checks, nine queue/settings/worker-lease isolation checks and six invalid site-selection preservation checks. Both selected sites launched the real sandboxed worker. The test exposed and fixed repeated activation helper includes and options-table reads before WordPress finishes creating a site. The final WordPress debug log is absent (zero bytes).

Ten aggregate host-load samples during one bounded job peaked at 6.3% CPU busy; the idle sample peaked at 0.1%. These describe one disposable host run, include unrelated activity and do not establish production capacity or per-plugin CPU consumption. The runner stopped its verified service processes and retained evidence. Production rules, mapped domains, installed WooCommerce, page builders and older supported software versions still require their own verification.

The later benchmark protocol/report follow-up passed 30 assertions on Windows with Node.js 26.7.0 and Chromium 148.0.7778.96. It retains the existing cache-hit, runtime/config equivalence, browser-cache, paint and metric checks, and adds CLI report regressions for zero and near-zero baseline CLS. Comparison tables now show absolute CLS changes; raw comparison values remain unchanged. This supersedes the earlier 28-check benchmark fixture count without changing historical executed records. The synthetic fixture does not establish product performance. See [benchmark protocol and executed comparisons](../../tools/BENCHMARKS.md).

The native font integration follow-up passed 31 actual WordPress font-controller assertions, 37 read-only local CSS settings assertions, 126 backend assertions and 55 real HTTP/Chromium/CLI assertions. All four changed PHP files pass lint; the disposable WordPress debug log is empty. The temporary HTTP server stopped and the worker dependency junction was removed, preserving its dependency target. This proves local critical CSS respects native Google font ownership and later inline opt-outs; live font requests and loading shifts are verified separately.

## Verified Noobunbox deployment — 2026-10-08

NoobRocket 1.2.0 is active on `https://www.noobunbox.net`; WP Rocket is inactive and its original raw settings/autoload are preserved. The final 1,666-file archive is 6,439,159 bytes, SHA-256 `13cb7c65ee374fdecf6890f9551e0d2ed840ddc9cbe631f45441792b57c1008c`. Every installed release file matches the archive. The six-file update preserved optimization settings, ownership/modes, and installed worker dependencies, with private rollback copies and a graceful PHP-FPM reload. NoobRocket owns its early-cache loader and cache/config directories; `WP_CACHE` is enabled and the original protected configuration permissions are restored.

Theme 2.7.9 / `b2d7d3a` is deployed. Its JavaScript build, mobile menu width and hero typing layout fixes passed live UI checks. Four homepage/article desktop/mobile critical CSS variants publish 12 public artifacts with exact file/content hashes. Eight consecutive anonymous nginx HIT samples and the independent HTTP gate passed; main theme CSS is deferred with a complete noscript fallback and native inline local font CSS.

Live font requests on the four variants use only the site origin; no duplicate font downloads or failed resources occurred. All 12 final UI/loading screenshots passed at 390 × 844 and 1440 × 960, with menus, search, TOC, reading bar and comments checked, no overflow or script errors. Desktop no-input CLS was 0.003798 / 0.004104. Mobile had raw font shifts 0.013580 / 0.029560 that Chromium excluded through `hadRecentInput` despite zero recorded test inputs; this does not prove physical zero shift. Native duplicate font CSS and Google noscript/DNS/preconnect markup remain; the duplicate adds an estimated 421 compressed bytes to the captured head and did not duplicate actual font downloads. With JavaScript disabled, the Google noscript stylesheet is fetched while observed font files remain local.

The real host's nginx `open_file_cache_valid 60s` retained deleted page-cache inodes after a correct native purge. A server-scope `open_file_cache off` override now applies only to this site's owned include, covering both rewrite existence tests and static delivery. Routing, browser asset caching and file permissions remain unchanged; nginx syntax/reload passed. Fresh connections proved warm HIT, guarded deletion of only the desktop homepage plain/gzip files, immediate HTTP 200 MISS with current critical/local font styles, then native HIT in 2.056 seconds. Ready CSS jobs were preserved. This disables nginx descriptor/metadata caching for this vhost; OS and browser caches remain available.

An ordinary public article exposed the theme's pinned Prism 1.29.0 dependency on `https://cdnjs.cloudflare.com`. That exact origin was added alongside Google's two font origins, and scoped native recovery regenerated the affected job with zero failures. No arbitrary asset origins were added. The verified shared font CSS contains 48 font-face rules referencing 36 existing nonempty local font files, with no remote Google font URLs.

[Fresh PageSpeed report](https://pagespeed.web.dev/analysis/https-www-noobunbox-net/q1w32uone6?form_factor=desktop), captured at 18:06 Europe/Paris: desktop/mobile performance 100, accessibility/best practices/SEO 100. Desktop FCP 0.3 s, LCP 0.4 s, CLS 0.004; mobile FCP/LCP 0.9 s, CLS 0.034; TBT 0 ms on both. No render-blocking request warning. This is one lab report per device with Lighthouse 13.5.0, desktop custom throttling and mobile Slow 4G / Moto G Power. No field data was available. Theme and settings differ from the historical report and matched WP Rocket comparison; the result does not establish plugin speed superiority or every-page performance. Manual workers were stopped, preload temporarily paused and managed cron absent during measurement.

After measurements, the exact original preload option presence/value/autoload was restored and native sitemap discovery resumed, preserving pending URLs. Site-specific minute cron schedules now run WordPress cron and the local CSS worker as `www-data`. The worker uses Node.js 24.21.0, sandboxed Chromium 154, dedicated XDG directories without changing `HOME`, `flock`, nice level 10, two-job batches, a 120-second between-job budget and a 240-second external ceiling. WordPress cron has no global timeout. Installed scheduler/wrapper/logrotate hashes and ownership/modes passed verification; all five pre-existing cron files remain hash-identical. Private logs use daily/maxsize10M rotation, retaining seven rotations with compression and delaycompress. The first four completed scheduled cycles processed jobs 76–83 with zero failures and no worker stderr; no manual worker was invoked for this proof. Automatic generation remains enabled. Cron execution, completed batches and retained cron files are recorded in `.artifacts/releaseproof/noobunbox-final-cron-cycle-proof-20261008.json` and `.artifacts/releaseproof/noobunbox-final-existing-cron-proof-20261008.json`; the separate scheduler snapshot may capture the next normal batch in progress.

Archive, deployment, publication, public HTTP, purge, font cache, scheduler and PageSpeed evidence is retained under `.artifacts/releaseproof/`; UI/loading proof is retained in `.artifacts/live-critical-ui-font-proof-after-font-fix.json` and `.artifacts/live-no-input-critical-after-font-fix-loading-proof.json`. The separate final optimized benchmark is documented in [performance records](../../tools/BENCHMARKS.md).

## Verified 1.3.0 release checks — 2026-10-09

Status: native integration, syntax, release archive and disposable-runtime cleanup pass. No 1.3.0 live deployment has been performed. Historical 1.2.0 production/Linux records above describe their recorded revision; they do not automatically verify this candidate.

Runtime: disposable marked WordPress 7.1.3 with SQLite Database Integration 3.0.2, Windows PHP 8.3.35, Node.js 26.7.0 and Chromium 148.0.7778.96. Product 1.3.0 remains separate from engine 3.23.5.1. The following focused checks have passed after the owned-identifier migration:

| Fixture | Result |
| --- | --- |
| Acceptance lifecycle | All final lifecycle phases pass, including activation/boot, import/settings, render/local-help return contracts, minify purge, product update, deactivation/uninstall and collision isolation. The hook audit checks 503 NoobRocket class callbacks. |
| Acceptance `minify-purge` / `release-update` | Six minification-purge assertions and the product-update phase pass in focused and full native runs. Native Windows path handling removes selected plain/gzip CSS/JS while preserving unselected files and another site directory; update invalidation removes page/plain/gzip sentinels, preserves optimization settings and avoids engine migrations. |
| `wordpress-picture-generator.php` | 31 assertions covering bounded work, one final purge, source freshness despite equal timestamps, atomic owned replacement, legacy records, external ownership and publication races. |
| `wordpress-uninstall-network.php` | 533 assertions across 105 mock blogs, including complete enumeration, foreign data/event preservation and context restoration after injected failures. This is not a native MySQL network installation. |
| `wordpress-cache-write.php` | 13 assertions with 20 actual parallel PHP writers, complete plain/gzip pairing, held mutex, cleanup, compression limits and failed/terminated writer recovery. |
| `wordpress-cache-ownership.php` | 10 assertions covering owned/missing/empty and foreign loaders, product mentions and earlier vetoes. |
| `wordpress-requirements.php` | 15 assertions covering supported/unsupported runtimes, capability restriction, local recovery notice and absence of remote rollback hooks or credential reads. |
| `wordpress-cache-config.php` | Ten assertions including direct initialization after a discovered configuration disappears and refusal with all optional checks disabled. Existing loads/defaults, failed-load invalidation and asset-preview bypass remain covered. |
| `wordpress-cache-http.php` | 22 real WordPress/HTTP assertions, including raw compressed wire bytes, decoded equality, Vary, explicit gzip refusal, plain/gzip-only hits and both conditional-response branches. |
| `local-css-generator.mjs` | 47 Chromium assertions, including conservatively retained picture/source selectors absent from the unoptimized sample. |
| `local-css-interactions.mjs` | 18 interaction assertions at desktop/mobile sizes. Synthetic cart states do not prove an installed WooCommerce workflow. |
| `benchmark.mjs` | 30 synthetic protocol/report assertions. No current-release product superiority measurement. |
| External protocol fixture | 13 assertions, including four bundled JSON checks for real third-party identifiers. |
| Stale-refresh fixtures | 35 direct PHP assertions plus six native preload child assertions; 37 isolated Nginx proxy assertions plus six child assertions. Proxy routing/cutoff proof does not establish native PHP-FPM delivery. |
| Native cache regressions | Final run: 34 WebP assertions, 87 taxonomy assertions and eight preload assertions. Configuration, external protocols and 22 cache HTTP assertions above also pass in the final suite. |
| Native feature fixtures | Final run: 42 asset rules/history assertions, 65 picture delivery assertions and 38 bloat/object-cache assertions. |
| Native LocalCSS fixtures | Final run: 37 settings, 126 job/render/invalidation, 31 font integration, 31 worker/queue-control and 42 diagnostics assertions. |
| Native CSS HTTP bridge | 55 actual HTTP/Chromium/CLI assertions, with real generation/publication, public CSS delivery and early page-cache hits; fixture settings and scheduled events restored. |
| Native picture browser | 28 desktop/mobile and JavaScript-disabled fallback assertions; browser setup reruns and passes the 65 picture delivery assertions. |
| Native worker dependency check | Node.js 26.7.0, Chromium 148.0.7778.96 and the WordPress/PHP bridge are ready. Chromium sandbox remains unavailable on Windows. |

The image-generator, network-uninstall, cache-writer, loader-ownership and requirements fixtures total 602 assertions. Focused LocalCSS readability checks established unchanged executable tokens for the formatting refactor across `Frontend.php`, `Subscriber.php`, `ServiceProvider.php`, `Jobs.php` and `Diagnostics.php`, totaling 5,794. The later intentional `DONOTNOOBROCKETOPTIMIZE` rename changes one string token in `Frontend.php`'s 1,549-token stream; all remaining executable tokens stay unchanged. All five files pass focused PHP lint. Final full syntax checks pass for 1,435 PHP files and 40 JavaScript files; focused lints subsequently pass for `inc/classes/Buffer/class-tests.php` and `tests/wordpress-cache-config.php`, the only two PHP files changed after that full run.

The final naming pass also renamed the owned optimization opt-out constant throughout plugin/CSS fixtures without a legacy alias, cleaned runtime JS names in three assets and corrected installed root-package metadata. Real dependency URLs and external integration contracts remain. Custom integrations must define `DONOTNOOBROCKETOPTIMIZE` for the optimization opt-out in this release. Final fixture gates must use that spelling.

The Flight Deck admin UI passed real output checks for all 11 tabs in English and French at 1440 × 960 and 390 × 844 in light/dark modes. Axe 4.11 WCAG A/AA and 2.1 AA checks found zero violations; the recorded fixtures found no overflow, contrast failures, targets below 44 px or admin JavaScript errors. Additional keyboard/focus, radio, dirty-state, appearance, reduced-motion, responsive-width, malformed-hash and denied-localStorage checks pass. A real `options.php` POST persisted critical CSS mode and returned a clean form. Signed Asset Manager preview preserved required dependencies, left anonymous visitors unchanged and rejected unauthorized access. Settings, rules/history, locale, temporary post, sessions and owned MU fixtures were restored before the final native suite.

UI evidence: `.artifacts/settings-ui-proof.json`, `.artifacts/settings-fr-ui-proof.json`, `.artifacts/settings-native-post-proof.json`, `.artifacts/assets-ui-proof.json` and matching screenshots. The final admin read found no fresh PHP warnings/errors. The frontend proof also reproduced `AbortError: Transition was skipped` on control WordPress navigation without NoobRocket frontend JavaScript or failed assets; `.artifacts/frontend-transition-proof.json` records that browser/navigation limitation.

Translation proof compiles 36 catalogs, validates 50,936 singular/context/plural lookups and confirms byte-identical regeneration. All 1,098 extracted source labels have French translations; 174 were added directly to PO. Nine unsafe inherited entries retain their text as fuzzy entries and fall back to English. All 22,553 compiled message entries and catalog metadata are free of retired product branding. PO remains translation authority, with no target-language dictionary in the updater. Supplemental extraction does not establish exhaustive native gettext coverage. Evidence: `.artifacts/noobrocket-translations-proof.json`, `.artifacts/translation-metadata-proof.json` and the archived retired entries outside the package.

Final release gates:

| Gate | Current state |
| --- | --- |
| Native lifecycle/integration | PASS: `.artifacts/release-1.3-proof.ps1` covers lifecycle, minify-purge and release-update invalidation, settings/help contracts, config/preload/WebP/taxonomy/assets/picture/bloat/local CSS/fonts/worker/diagnostics/cache HTTP/stale refresh, actual CSS HTTP/Chromium bridge, picture browser and worker prerequisites. Final fresh WordPress debug log empty. Exit 0, duration 71,149 ms; log `20261009-080129-230-1d699439f3cd433f84fbf0aa7bf039c1.log`. |
| Final syntax | PASS: `.artifacts/verify-syntax.ps1` checks 1,435 PHP files and 40 JavaScript files; subsequent focused lints cover the two later-edited PHP files. Full log `20261009-075557-753-1c3bc102166c46b59287be6a9568a774.log`; focused lints in the packaging log below. |
| Packaging | PASS: `dist/noobrocket-1.3.0.zip`, 1,674 unique entries, 6,148,082 bytes, SHA-256 `751e0308842b1032f3583fbe647760ed71189abc1b4d1ccadc9fce00c867e223`. `python tools/build.py` and `.artifacts/verify-package.py` verify CRC and exact maintained source-to-ZIP entries/bytes, excluding `node_modules`, test artifacts and upstream reference. Log `20261009-080129-273-5c870d9c3d974312875941b6cd5beb51.log`. |
| Disposable runtime cleanup | PASS: verified owned PHP executable/command line for PID 52096, stopped that process and confirmed no port 8765 listener. The stale own PID 49736 file was removed without using it to terminate anything. Only the original `noobrocket-http-audit.php` MU fixture remains; temporary UI/browser/native fixture state and settings were restored. Final WordPress debug log is zero bytes. |

The ten-assertion focused configuration proof is retained in operator log `20261009-080108-422-c70bd6c1c2e4461b819527b3480b1d28.log`. These log filenames refer to the operator's Codex command-log directory. Reproducible final readability proof is `.artifacts/readability-before/LocalCSS/verify-final-tokens.php`; `token-proof-final.json` confirms 5,794 executable tokens, exactly one intended constant-name string difference and equality for every other executable token.

The exact initiating HTTP request for the earlier configuration-disappearance race was not isolated. The direct gate regression reproduces the ordering without preloading the missing file and proves initialization now refuses that state; the complete final native suite and fresh debug-log gate pass after the fix.

## Historical Flight Deck mockup follow-up — 2026-10-09 (unpackaged)

This follow-up changes the design source after the historical 1.3.0 release proof. The source version is 1.3.1 for asset freshness; no 1.3.1 ZIP is built. Packaging is stopped at the user's request. The local preview at `http://127.0.0.1:8765/wp-admin/options-general.php?page=noobrocket#dashboard` remains running for review, with user settings and sessions preserved. No live deployment occurs.

The [historical mockup follow-up](../../tools/CODE_REVIEW.md#historical-flight-deck-mockup-follow-up--2026-10-09) records this earlier revision's pages, shared interaction invariants, mockup adaptations and relevant design skills. Its grouped navigation, disabled-provider fallback, initially open inventory and collapsed history were subsequently changed; current proof is recorded below. Browser fixtures use the marked disposable WordPress 7.1.3 installation, PHP 8.3.35, SQLite and Chromium 148.0.7778.96. English/French contexts have request-scoped locale headers; they do not change the global or user locale. External requests are blocked. Settings/cleanup submissions are intercepted after native validation; these proofs establish the submitted form contract without deleting data or persisting draft options.

| Focused gate | Evidence and scope |
| --- | --- |
| Native settings render and syntax | 90 relevant PHP files and three maintained/generated JavaScript files pass syntax. The existing real WordPress `render` phase passes help, native CDN fields, single submitted CDN value, migration metadata and absent license-field assertions; rendered output is 120,555 bytes at that run. Operator log `20261009-085450-206-c1a756097a994c89a3faff74baca8b33.log`. Later focused edits require their final checks below. |
| Base route matrix | `.artifacts/ui-gap-review/matrix-proof.json`: 11 routes × English/French × light/dark × 1440/390 px, plus eight focused/open Appearance cases, totaling 96 records. Reflow at 320 px and 200% text also passes. Automated axe 4.11 WCAG 2, 2.1 and 2.2 A/AA tags report zero violations and zero unresolved incomplete findings. |
| Native form and navigation interactions | `.artifacts/ui-gap-review/interaction-proof.json`: 144 checks for deep links, Back, h1/skip focus, mode keyboard interaction, dirty/revert state, retained closed-rule values, native integration drafts, unsaved-action cancellation, busy/saving state and one cleanup submission. Native confirmation Cancel/Escape restores focus without a POST. Four settings/cleanup POSTs are intercepted; no draft settings are persisted. |
| Isolated responsible-layer regressions | `native-ui-flow-proof.json`: 42 assertions against maintained JavaScript and native browser forms, including discard confirmation before an admin-post action can send its request; `conditional-route-guard-proof.json`: 18 assertions for initial disabled deep links, stored routes, Back after disable, enabled routes, collapsed navigation ancestry and fallback. These isolated checks complement actual WordPress integration proof. |
| Advanced disclosures and setup copy | `field-disclosure-proof.json`: 52 read-only assertions for original native field names/values, parent-controlled exclusions, configured indicators, setup/fallback copy, prerequisites, import chooser and scope guidance. |
| Cache lifespan alignment | `cache-lifespan-finish-proof.json`: 24 assertions at 1440/390 px in both themes. The inherited select divider is gone; number/unit controls align, retain native labels/names and do not overflow. |
| Integration switch geometry | `switch-geometry-proof.json`: 40 state/theme/locale cases, 120 assertions. Thumb stays centered inside the track; labeled targets remain 44 px; conflicting legacy pseudo-text is hidden. Native checkbox state and focus remain available. The prior axe incomplete findings were traced to these pseudo-elements rather than waived. |
| Translation authority | `translation-proof.json`: 53 new French UI translations live directly in PO; all 1,124 extracted literal source labels have French translations. All 36 catalogs compile; 52,844 lookups and byte-identical rerun checks pass. The updater is unchanged and has no target-language dictionary. The other 35 catalogs use English fallback for the 53 new labels; their older translation coverage varies. Supplemental literal extraction does not establish exhaustive native gettext coverage. |

Final focused evidence:

| Gate | Result |
| --- | --- |
| Conditional delivery pages | `conditional-routes-proof.json`: 16 English/French desktop/mobile light/dark views, zero script errors, violations or failed assertions. Initial disabled deep links, stored routes and a draft disable fall back correctly. Four credential-form submissions use synthetic values and are intercepted; no external provider operation runs. `conditional-layout-finish-proof.json` adds 56 geometry assertions for readable textual h1, decorative logo, stacked mobile credentials and aligned helpers. |
| Populated Asset Manager | `assets-disclosure-proof.json`: eight locale/theme/size cases with 27 inventory rows and five history snapshots supplied by request-only native WordPress filters. Inventory opens after inspection and can close to reveal edit/publish immediately. History starts collapsed; native required-acknowledgement validation opens it and focuses the invalid checkbox. No metadata or option writes are needed for this fixture. |
| Heading hierarchy | The final base, conditional and populated matrices also run axe's explicit `heading-order` rule. Overview uses h1/h2/h3, and integration section/card headings use h2/h3. Zero reported violations or unresolved incomplete checks across those matrices. |
| Final source checks | 91 relevant PHP files and the three maintained/generated JavaScript files pass syntax; the owned session-cleanup script also passes PHP lint. The real WordPress native `render` phase passes after all PHP/JS changes, producing 120,742 bytes. Operator log `20261009-091308-319-a4e3fd5159ec44018798761abc60095e.log`. |
| Final alignment and cleanup | `integration-alignment-proof.json`: 12 final phone/tablet locale/theme cases and 60 measured headers pass after the last CSS correction. `final-proof.json` consolidates final runtime results. Owned QA MU fixture and current QA session token are removed; current settings, locale and other user sessions are preserved. Verified PHP process 9504 remains running and the local site returns HTTP 200. No package is built or live site changed. |

The 96 base cases, 16 conditional cases and eight populated Asset Manager cases total 120 rendered records. The final integration-block alignment correction additionally passes 12 focused cases at 390, 320 and 768 px in both themes and locales, measuring 60 headers. Titles and status controls share their left edge within 2 px; targets remain 44 px; overflow, script errors, axe WCAG/heading-order violations and incomplete findings are absent. These focused captures supersede the earlier mobile integration screenshots; the other route evidence is unaffected. Agent visual review compares all 13 routes with the approved refined mockup; it does not establish a production 100/100 UX rating. Human usability, screen-reader review, real operating-system select rendering and RTL presentation remain outside the tested matrix. External Cloudflare/Sucuri actions and actual data deletion are not executed during this design review. Backend/cache proof remains tied to the historical release revision above.

Remaining limits: no current-release PHP 7.4/older WordPress execution, native concurrent PHP-FPM/MySQL network proof, installed WooCommerce/page-builder matrix or Linux Chromium sandbox proof. Native image formats depend on the host editor; queue mocks cannot establish encoding support. Picture wrapping is opt-in and may affect selectors such as `.hero > img`; stage theme interactions. Stale refresh requires PHP delivery because static Nginx cannot enforce its cutoff. The matched historical benchmark and single target-site PageSpeed reports do not prove 1.3.0 is faster than WP Rocket or universally optimal. See [source review](../../tools/CODE_REVIEW.md) for the traced findings and boundaries.

## Independent audit improvements — 2026-10-09 (unpackaged)

The [resolution map](../../tools/UX_UI_IMPROVEMENTS.md#implemented-audit-resolutions) covers UX01–UX23 and the subsequent typography, description-width and CDN interaction corrections in the maintained 1.3.1 source. This revision follows the historical mockup proof above. All 14 routes pass the stated font/measure review, but the preceding UI 90/100, task UX 90/100 and combined 90/100 estimate is withdrawn: later user examples and deeper page states expose remaining design and interaction defects. No package, deployment, database cleanup, provider operation, real homepage probe or queue run is part of this UI verification. Request-scoped locale/filter fixtures supply representative records without persisting options or metadata. Native form submissions are intercepted before HTTP except isolated synthetic-browser responses and mocked PHP handlers.

| Current focused gate | Result and scope |
| --- | --- |
| Native transaction behavior | `tests/settings-transactions-browser.mjs`: 24 assertions pass against maintained JavaScript and synthetic native forms. Covers category/count/schedule and unrelated-draft review, dirty section ownership, saved-state operation choices, unfinished entry, positive numeric IDs, inactive-provider email validity, radio legend naming, native submitter preservation, delayed pending state, canceled-request recovery and removed saved-CDN field ownership/labels. Operator log `20261009-121116-447-688fc2aa8d8e41779dd4ac3342cd5294.log`. |
| Import handler | `tests/wordpress-settings-import.php` exercises upload staging, normalized proposal, reviewed confirmation, cancellation, replaced/expired reviews, saved-state/validation drift, native nonce/capability and credential redaction with mocks. `import-review-proof.json` adds four locale/theme/size layouts with native form ownership, changed values, hidden credentials, 44px review buttons and no submitted actions. This is not a real runtime import. |
| Overview evidence | `tests/wordpress-overview.php`: 37 standalone assertions pass for scoped anonymous request settings, transport/HTTP/cache-header codes, historical-record compatibility, viewing-locale messages, evidence dates, queue guidance and DOM order. Requests are mocked; no real homepage check runs. |
| Asset/history responsible layer | `tests/wordpress-assets-ux.php`: 21 read-only assertions pass for native target normalization, expert/legacy compatibility, dependency-result summary, closed initial inventory, create-rule entry, direct history, actual setting/rule differences, native acknowledgement and credential exclusions. Saved state remains unchanged. Operator log `20261009-113946-288-5a72ab33b9094669a91fa95b163c041f.log`. |
| Asset/history interaction | `assets-interaction-proof.json`: 30 English/French checks pass. Bounded inventory is 448px for 27 supplied rows; create carries context, native numeric validity rejects bad IDs, canceled departure/replacement preserves input, native post-type choices remain valid, site-wide targeting avoids hidden required fields, and history reviews differences. No HTTP mutation was attempted. |
| Navigation and focus | `navigation-state-proof.json`: eight compact menu/Appearance/scroll cases pass. `navigation-focus-proof.json`: four focus cases and two Back/Forward reading-position sequences pass. These fixtures report zero failures and script errors, without saving settings. |
| Final route and data states | `final-visual-proof.json`: 100 records across all 14 routes, English/French, light/dark and 320/390/768/1024/1440px; zero failures, script errors, small targets and axe violations. `asset-state-proof.json`: 30 empty/expanded/prepared/review records at 320/390/1440px; zero failures, violations or incomplete findings. Route-matrix contrast incompletes are reviewed separately below rather than counted as passes. |
| Corrected geometry | `final-heartbeat-proof.json`: seven desktop/phone/tablet cases confirm readable context rows, associated native selects and a 44px upload control. Fresh route/state captures show the corrected lifespan label/number/unit grouping and readable dark Target header. Earlier captures showing those defects are superseded. |
| Final typography and content measure | `typography-final-font-measure-proof.json`: 112 English/French light/dark route/state checks at 320/390/1440/1920px use the current system UI/code stacks, including existing/new inputs and placeholders. `final-content-geometry-proof.json`: 84 checks across all 14 routes at 390/1440/1920px, zero failures, page errors or overflow. Routine/data content caps are 960/1200px. The desktop LazyLoad paragraph uses 854px of its actual card rather than the earlier 566px column. Fresh real-size captures confirm the font/width reports are resolved. |
| Native CDN Add/Remove and review | `cdn-interaction-proof.json`: 30 checks pass with zero failures/errors. `final-cdn-focus-dirty-proof.json`: six request-only saved-CNAME cases at 320/390/768/1440px pass across both languages/themes. Remove is 44px; added/deleted rows dispatch native change, focus moves to a usable control, removal retains dirty section/field ownership, review uses human-readable translated CNAME/file labels, and Cancel preserves an unrelated Media draft. Zero settings saves, operational requests or external requests. |
| Final copy | `final-copy-proof.json`: ten final Sucuri, Media and CDN captures pass with zero failures, script errors or axe violations. Checks include the concise Sucuri field label/full-width format helper, associated help references and the French HTTP link spacing. Four Save-summary contrast incompletes use the unchanged manually reviewed color surfaces described below. |
| Notification behavior | Historical `toast-proof.json`: 38 locale/theme/width/no-JS records and six virtual-clock behaviors pass with zero failures, script errors or axe violations. Completion toasts preserve inline errors, actionable notices, cleanup receipts and unrelated notices. Checks cover dismiss, the earlier expiry/pause policy, synthetic document visibility and the dirty Save boundary. The current [readability follow-up](#hierarchy-notice-readability-and-tools--2026-10-09-unpackaged) supersedes automatic expiry. Two menu-overlap cases also pass. The visibility proof overrides `document.hidden` within the request; it is not an operating-system background test. |
| Contrast incompletes | Route, notification and final-copy matrices retain 16, 18 and four axe incomplete records respectively for fixed-surface overlap: 38 total. `contrast-manual-proof.json` passes 28 computed-color checks for their Save/worker/notice/code surfaces, minimum 6.02:1 against required 4.5:1. The four final-copy records use the unchanged reviewed Save-summary colors. These were manually checked; automated zero-incomplete is not claimed. The 30 asset/history states have no incomplete findings. |
| Source syntax | Final post-CDN/copy checks pass for 109 relevant PHP files and three maintained/generated JavaScript files. This is focused source proof, not a new backend release/lifecycle run. |
| Translation compilation | All 36 PO/MO catalogs compile to 1.3.1. Verification checks 1,247 extracted French literal source labels and 59,253 compiled lookups against PO text or English fallback; a rerun is byte-identical. Other-language coverage is not expanded by this work. Unsafe fuzzy entries retain safe fallback. Literal extraction does not establish exhaustive native gettext coverage. |
| Owned QA cleanup and preservation | `preservation-proof.json`: `PASS_CURRENT_STATE_CLEANUP` at 10:15:49 UTC. Fresh exact before/after database rows preserve current settings/rules/history, locales, private asset metadata and every other current session. All three original user-1 sessions remain. Only the owned QA session token and request fixture are removed. The original baseline reports settings/rules/history drift of unknown origin; `baseline-drift-proof.json` retains it, and no stale baseline settings are restored. Owned PHP preview process 9504 remains running; local homepage HEAD returns HTTP 200. |

Human usability timing, manual screen-reader review, native operating-system select rendering, RTL and actual external delivery/provider outcomes remain outside this proof. An agent rating or an automated pass does not establish a universal 95/100 product score or production performance gain.

## CDN validation and failure severity — 2026-10-09 (unpackaged)

Run the standalone native browser fixture with explicit installed executables, then the isolated PHP fixtures:

```powershell
node tests/cdn-settings-browser.mjs /absolute/path/to/chromium /absolute/path/to/php
php tests/wordpress-cdn-validation.php
php tests/wordpress-overview.php
php tests/wordpress-settings-import.php
```

CDN browser proof passes 162 assertions on the actual PHP row template, settings modules, tracker and styles at desktop/narrow widths in both themes. It checks inert templates, associated visible labels, Add/Remove focus and dirty ownership, native validation and retained/corrected errors, supported URLs and recovery from another settings page. Save uses keyboard activation in the isolated fixture; the local WordPress follow-up separately proves pointer Save and sticky-footer clearance. Server proof passes 29 assertions, including rejected rows retaining every saved CDN field, escaped native errors and sparse-row scope pairing. Overview passes 49 assertions, including red failed probe status versus unverified delivery and recorded hits. Import passes 14 transaction checks with the existing responsive comparison labels.

The [CDN follow-up record](../../tools/UX_UI_SECOND_PASS_FIXES.md#follow-up-cdn-structure-and-validation) documents eight local WordPress states and 97 assertions. Both the isolated fixtures and runtime proof preserve product settings and avoid provider/destructive operations. Scope does not include a new whole-app quality score.

## Validation notice routing — 2026-10-09 (unpackaged)

```powershell
node tests/settings-notices-browser.mjs /absolute/path/to/chromium
php tests/wordpress-cloudflare-settings-notices.php
```

The native browser fixture passes 330 assertions against maintained shell JavaScript, styles and PageManager. It exercises WordPress notice relocation before/after plugin code and canonical save-parameter cleanup, owning-page persistence, first-error focus without extra history, native dismissal, completion toasts, and desktop/narrow layouts in both themes. The Cloudflare fixture passes 18 assertions through the native subscriber, with the provider connection call replaced by a deterministic stub; disabled/unchanged connections are skipped and edited credentials retain validation. Neither fixture boots WordPress or writes runtime settings.

## Save cancellation recovery — 2026-10-09 (unpackaged)

Subsequent [common-width and appearance fixes](../../tools/UX_UI_SECOND_PASS_FIXES.md#follow-up-common-page-width-and-appearance-before-rendering) add `node tests/settings-appearance-browser.mjs /absolute/path/to/chromium`: 126 assertions and seven synthetic native Save redirects verify the palette while footer execution is withheld, preference persistence/fallback, live System changes and no-JS output. Appearance now loads in the head; the notice fixture includes that owner and still passes 330 assertions. The transaction/CDN gates remain at 48/162. Local WordPress rendering adds 430 scoped checks across 140 route/theme/viewport records and three held-footer states; no settings or provider operation is submitted. The linked record and cleanup receipt contain scope and preservation evidence.

`tests/settings-transactions-browser.mjs` now passes **48 assertions**. Genuine pointer clicks exercise later document/window submit cancellation; the old evaluation-only dispatch missed native event timing. Checks cover preserved drafts, removed busy/status markers, restored departure protection and single native retries with exact endpoint/submitter payload. A deterministic held synthetic response verifies Saving label/disabled consistency after navigation synchronization. The new cancellation assertion fails against the pre-fix source. JavaScript syntax and the **162-assertion CDN gate** also pass.

[Local WordPress proof](../.artifacts/save-state-fix/runtime-proof.json) passes **48 checks** across English desktop1440 and French390, both themes. It injects cancellation in its owned browser document, makes no operation request or settings save, and reports no script errors or horizontal overflow. Desktop light/mobile dark captures were visually reviewed. [Cleanup proof](../.artifacts/save-state-fix/preservation-proof.json) preserves exact settings/rules/history, locale rows, private asset metadata and all three pre-existing sessions; only owned QA state was removed. Source is active through the local plugin junction; no ZIP or live deployment. The original user's POST/network result remains unverified because the connector exposed no stuck tab. See the [fix record](../../tools/UX_UI_SECOND_PASS_FIXES.md#follow-up-save-cancellation-recovery).

The [notice ownership record](../../tools/UX_UI_SECOND_PASS_FIXES.md#follow-up-validation-notice-ownership) includes the separate local WordPress proof: 137 assertions across eight configurations, request-only synthetic errors/save evidence and zero provider/settings operations. It preserves stored settings and the user's settings-error transient. Scoped CDN accessibility checks do not establish whole-app conformance.

## Fresh page audit — 2026-10-09 (documentation only)

The [new audit of all 14 pages](../../tools/UX_UI_PAGE_AUDIT.md) records current unresolved defects and page-specific acceptance checks. Recommendations are not implemented, and the preceding 90/100 estimate remains withdrawn. Earlier passing matrices above prove their stated cases; they do not override the deeper findings.

Fresh coverage: 56 Asset/CDN/Heartbeat/History captures across English/French, desktop/mobile and both themes; 16 views of the other eight routes in English desktop/light and French mobile/dark; 28 Database/Tools states with 56 captures across both languages/themes. Database/Tools has zero script errors or operation requests, two `scrollable-region-focusable` violations on French mobile import review and six contrast-incomplete records. The [Tools focus follow-up](../.artifacts/ux-improvement/tools-dirty-focus-proof.json) confirms settled keyboard scrolling reveals Upload despite its initial overlap with the dirty footer; permanently unreachable/focus-obscured control failure is not established. No source tests were rerun for this documentation-only audit.

The separate [fresh cleanup receipt](../.artifacts/ux-improvement/reaudit-preservation-proof.json) records `PASS_CURRENT_STATE_CLEANUP`. Fresh baseline/current protected rows and other sessions were preserved, including three pre-existing user-1 sessions; only owned QA session/fixture artifacts were removed. Historical preservation/drift receipts remain intact. Preview PID 9504 remains running, homepage HTTP 200. No package, deployment, source implementation or real provider/destructive operation was produced.

## Second page review — 2026-10-09 (audit only)

Subsequent [SP01–SP20 implementation and verification](../../tools/UX_UI_SECOND_PASS_FIXES.md) supersedes the unresolved state described in this historical audit section. Current focused fixtures pass 92 catalog/dependency, 34 transaction and 26 asset/history assertions. The local rendered follow-up passes 272 assertions across 84 page baselines and 24 scoped accessibility states, with zero violations/errors/operation requests and eight inconclusive contrast records. This is scoped regression evidence, not a 95/100 rating. Packaging/deployment remain stopped.

The [second review of all 14 pages](../../tools/UX_UI_SECOND_PASS_AUDIT.md) records new functional/accessibility/state issues and revalidates prior page findings. Recommendations are not implemented. Corrected forms coverage is 122 states, 24 focused states and 14 catalog-event states; provider/asset coverage is 92 main plus 40 detail states; Database/Tools/History has 88 states, 138 captures, six native keyboard cases and two focus follow-ups. Root shell proof adds 10 viewport/theme cases and 140 route checks. Matrices cover both themes and English/French; extra widths are scoped individually in the report.

Native warning acceptance and exclusion label clicks change fields without updating Save; category keyboard/name/imagery and dark contrast defects are confirmed. Native Chromium can Tab/arrow-scroll long History/Tools reviews, despite 20 repeated axe explicit-focusability findings; Tools' focus indicator is absent in both themes. The three-page matrix retains 22 contrast-incomplete records, not passes. Menu Label in Name checks explicitly run axe's experimental rule and fail six compact French cases; earlier helper tags omitted WCAG2.1/2.2 A tags. No universal accessibility result is claimed.

Empty-inventory behavior and oldest-snapshot retention use isolated existing-method/native-class probes with no WordPress bootstrap/database. No actual settings save, publication, provider purge, import or cleanup ran. No product code changed or source syntax tests reran for this audit. [Second-pass preservation](../.artifacts/ux-improvement/second-pass-preservation-proof.json) confirms the fresh baseline/current protected state and three pre-existing user-1 sessions retained, only owned QA token/fixture/private state removed, historical receipts retained and no stale rollback. All readers/commands are closed; PHP preview 9504 remains alive, homepage HTTP 200. Packaging/deployment remain stopped.

## Asset Manager layout and publication review — 2026-10-09 (unpackaged)

```powershell
node tests/settings-transactions-browser.mjs /absolute/path/to/chromium
php tests/wordpress-assets-ux.php /absolute/path/to/wordpress
php tests/wordpress-assets-second-pass.php
```

The transaction fixture now passes **60 synthetic browser assertions**, including trusted Discard cancellation, pending-operation review before confirmation, retained drafts/departure guards and one operation-owned POST. It never submits to WordPress. The native UX fixture passes **35 read-only assertions** for independent preview forms, unique visible field IDs, unchanged/changed/empty/rejected/stale states, exact rule deltas including duplicate occurrences and reordering, disabled stale publication and preserved acknowledgement. It compares saved settings/rules/history/private metadata before and after. The isolated responsible-layer fixture still passes **26 assertions** without WordPress/database/network.

[Local rendered proof](../.artifacts/asset-layout-fix/render-proof.json) passes **934 checks in 44 states** across English/French, both themes and 320/390/768/1024/1440px. Checks cover shared task frames, aligned responsive fields, full-width mobile URLs, bounded inventory, native scope/expert choices, prefill/focus, publication review and canceled Discard. Zero layout failures, script errors or operation requests. Sixteen scoped accessibility states report zero violations/incompletes for the configured tags and heading-order rule; this is scoped evidence, not full WCAG or whole-app conformance. Integrated and detail captures were visually reviewed in desktop/mobile and both themes.

[Implementation and limits](../../tools/UX_UI_SECOND_PASS_FIXES.md#follow-up-asset-manager-task-layout) records the remaining manual-ID and no-JavaScript discard-confirmation gaps. French labels live in PO; all 36 catalogs compile and preserve existing translations. [Cleanup proof](../.artifacts/asset-layout-fix/preservation-proof.json) confirms exact product state, locale/private metadata and all four pre-existing QA-account sessions preserved; only owned QA state was removed. The plugin source is junction-linked into the running local preview. No package, push, live deployment, real publication or settings save occurred. No new UI/UX rating is claimed.

## Cancel unsaved optimization settings — 2026-10-09 (unpackaged)

`node tests/settings-transactions-browser.mjs /absolute/path/to/chromium` now passes **70 assertions**. The added Cancel cases cover confirmation refusal, retained invalid/structural/credential edits and unfinished rule input, focus/departure protection, saved private draft preservation, canonical GET/hash reload including an already-canonical URL, and disabled/recovered Cancel during native Save. Accepted Cancel tests wait for the actual page load plus network idle; `history.replaceState` alone is not proof of a reload. Only a synthetic server receives the existing POST cases.

[Local WordPress verification](../.artifacts/settings-cancel-fix/verification.json) passes **137 checks in eight locale/theme/viewport states** at EN1440/768 and FR390/320. Canonical values and dynamic CDN rows restore, visible/canonical synthetic credentials restore, current section/theme persist, and stored private drafts remain. Zero operation requests, script errors, overflow, scoped footer axe violations or incomplete findings. [Eight repeated reloads](../.artifacts/settings-cancel-fix/stability-proof.json) preserve exact editor state. Detail captures were inspected at desktop/narrow output sizes; PHP/JS syntax and **62,365 compiled translation lookups** pass.

[Behavior and limitations](../../tools/UX_UI_SECOND_PASS_FIXES.md#follow-up-cancel-pending-optimization-settings) explains all-section scope, explicit unfinished-input loss warning and disabled Cancel once a submission starts. [Owned cleanup](../.artifacts/settings-cancel-fix/preservation-proof.json) verifies unchanged product settings/rules/history, locale/private metadata and all four pre-existing sessions. Only owned QA state was removed. Source is active locally; no package or live deployment.

## PageSpeed Insights — 2026-10-09 (unpackaged)

Run `php tests/wordpress-pagespeed.php /absolute/path/to/wordpress` against a marked disposable installation. The fixture mocks HTTP with a synthetic key and restores its option rows/autoload, current user, sessions and request state. Run site-mutating fixtures serially. The current proof passes **116 assertions** covering capabilities, POST/nonces, separate credential storage, empty secret rendering, settings/export/history isolation, Mobile/Desktop request parameters, score scaling including zero/unavailable values, metric sanitation, invalid/API/runtime/transport errors, retained previous results, storage failures, localhost/private rejection, public IPv6 acceptance and concurrent/stale lock ownership. Existing shared transaction checks still pass **70 synthetic browser assertions**.

Two explicitly authorized real Google API requests verified retrieval for `https://www.noobunbox.net/` using an environment-only credential. Desktop returned Performance/Accessibility/Best practices/SEO **100/100/100/100**; mobile returned **99/100/100/100**. Both returned HTTP 200 and Lighthouse 13.5.0. Desktop lab FCP/LCP were **376/418.5 ms**; mobile **1509/1809 ms**. Checks began at 14:50 and 14:56 UTC. These are individual lab runs, not field measurements, plugin comparisons or stable score guarantees. Sanitized evidence: [desktop](../.artifacts/pagespeed-api-check/desktop.json) and [mobile](../.artifacts/pagespeed-api-check/mobile.json). Neither raw Google responses nor the real key were stored.

All 36 PO/MO catalogs compile and **64,453 message lookups** match PO translations or English fallback. New French UI/backend messages are maintained in the France/Canada PO catalogs. A source/docs/tests/PageSpeed-artifact scan found no real Google API key. Standalone rendered-view checks covered light/dark and 1440/768/390/320px, with no clipping/overflow; async failures preserve results and allow retry. Real WordPress UI proof is recorded separately below. No package, push or live deployment.

[Real WordPress UI proof](../.artifacts/pagespeed-api-check/runtime-ui-report.json) passes **272 checks across 12 states** (English/French, light/dark, 1440/768/390px), with zero browser errors and zero scoped axe violations. Checks cover separate form ownership, empty secret rendering, keyboard controls, duplicate-request prevention, loading/failure/retry, preserved previous results and unchanged pending settings. Native key-action review cancellation retains typed input and pending global edits. Six additional setup/validation/storage-error states verify disabled unconfigured checks, opened setup, focused visible errors and accurate field validation semantics. The duplicate disclosure arrow was removed. Chromium's actual glyph-font inspection confirms Segoe UI for paragraph and URL text; the fixture's English month names reflect its missing WordPress core French language pack, not missing plugin translations.

[Owned cleanup](../.artifacts/pagespeed-api-check/runtime-preservation.json) verifies unchanged saved options, private asset metadata, locales and all four pre-existing sessions. The owned session, request fixture and private files were removed. Runtime HTTP was simulated for UI proof and external requests were blocked; the two real API probes above establish external score retrieval separately. Source is active in the local preview through its junction; production PHP timeout/proxy configuration remains untested. This is scoped PageSpeed evidence, not a whole-app UI/UX score.

## Shared notifications and link states — 2026-10-09 (unpackaged)

Standalone NoobRocket native notices, Settings API save/import outcomes, Diagnostics, Asset Manager, Settings history and PageSpeed outcomes use the existing notification tray. Severity is explicit. Diagnostics stores operation/probe codes and translates them when viewed. Field validation and publication recovery guidance remain contextual. This historical revision kept persistent errors/warnings/recovery and an eight-second completion timer with pointer/focus/document-visibility pauses. The [readability follow-up](#hierarchy-notice-readability-and-tools--2026-10-09-unpackaged) supersedes that timer: outcomes persist until dismissal or navigation. Native actions, lists, readonly controls and dismissal preferences are preserved. Temporary scroll space is added only when a focused final control cannot clear the tray through normal scrolling, then cleared after dismissal or navigation. Native recovery-button focus uses semantic colors; its dark contrast improved from 2.57 to 7.50.

Focused source checks pass **560 synthetic notification/browser checks**, **72 standalone Overview assertions**, **42 WordPress Diagnostics assertions** and **70 shared transaction checks**. The Diagnostics fixture's [outer raw-row comparison](../.artifacts/notification-ui-proof/diagnostics-preservation.json) confirms exact owned options/transients/cron/settings values and autoload were preserved. The [PageSpeed outcome follow-up](../.artifacts/notifications-ui/psi-outcomes.json) passes **99 checks** for shared success/error placement, safe message rendering, retained results/pending edits, final-control clearance, resize, native hidden-notice cleanup and tray-absent fallback. The previous PageSpeed storage-error focus evidence predates this change: invalid key input stays inline and focused; a storage-operation failure is now announced in the tray without stealing focus.

[Real WordPress notification proof](../.artifacts/notification-fix/runtime-ui-report.json) passes **1,014 checks** across eight English/French, light/dark, 1440/390px states and two no-JavaScript states. Twelve fixture scenarios cover all severities, real Diagnostics markup, persistent native recovery, cleanup list/readonly receipt, existing dismissal and contextual provider validation. Top-of-page NoobRocket notices converge on the same tray. All scoped axe runs have zero violations and browser runs have zero script errors or operation requests. Axe retains eight contrast-incomplete records, one per state; **384 computed text-contrast checks** include composited translucent backgrounds and settled keyboard-only/hover recovery states, with minimum **5.41:1**. Captures were reviewed at their actual desktop/mobile sizes. These results do not establish manual screen-reader, touch or live-provider behavior.

[Owned runtime cleanup](../.artifacts/notification-fix/runtime-preservation.json) confirms exact current protected option rows, domain bookkeeping, private metadata, locales and all four pre-existing sessions were unchanged. The owned session/MU fixture/private credential files were removed. No stale baseline was restored; no product action, package or live deployment ran. Earlier failing focus evidence remains in `runtime-ui-before-focus-fix.json` and command logs.

Text links also use consistent normal/visited/hover/focus rules that override native notice specificity without changing buttons/navigation. [Link proof](../.artifacts/text-link-fix/proof.json) passes **290 checks** in eight real locale/theme/viewport states, minimum contrast **5.43:1**. Its earlier [runtime receipt](../.artifacts/text-link-fix/runtime-preservation.json) records protected-option drift of unknown origin while preserving domain/private metadata/locales/sessions; no stale settings were restored. The current notification baseline above passes independently. Translation verification now compiles 36 PO/MO catalogs, verifies **64,561 lookups**, and confirms a byte-identical rerun; severity labels are translated in France/Canada PO files.

## Dashboard placement — 2026-10-09 (unpackaged)

`php tests/wordpress-overview.php` passes **98 assertions**. Added checks cover the visible cache timestamp outside closed evidence, worker disclosure inside CSS generation, automatic opening for a required/problematic worker, independent diagnostic form ownership and native Images/Object-cache/PageSpeed hook order. `php tests/wordpress-bloat-object-cache.php` passes **38 assertions**; the provider check remains unchanged. Changed PHP files pass syntax checks.

[Complete WordPress layout proof](../.artifacts/dashboard-layout/verification.json) passes **282 checks in 24 states**: English/French, light/dark, 1440/1024/390/320px, plus unconfigured PageSpeed, populated results, invalid-key feedback and worker setup states. It verifies all four cards and native action forms, equal paired desktop widths/bottoms, narrow DOM order, visible focus, keyboard disclosure, empty secret input, preserved results and expanded content without overflow. Zero scoped axe violations or incomplete findings, browser errors or operation requests. Public Google calls and plugin operations were blocked; populated grades are request-only fixtures rather than new measurements.

The review uses native dashboard rendering with current saved state. A header-gated local fixture prevents database writes and consumption of pending feedback, blocks external HTTP and supplies only the additional state cases. [Before layout](../.artifacts/dashboard-layout/before-layout.json) and [revised layout](../.artifacts/dashboard-layout/revised-layout.json) record dimensions; focused screenshots were inspected in both themes. [Preservation receipt](../.artifacts/dashboard-layout/runtime-preservation.json) records cleanup of the owned session/fixture and exact protected-state comparison. See [design findings and decisions](../../tools/UX_UI_IMPROVEMENTS.md#dashboard-placement-follow-up--2026-10-09). No whole-app rating, package or live deployment is claimed.

All 36 PO/MO catalogs compile; **64,669 message lookups** match their PO translations or English fallback. The three added labels have French translations in France/Canada PO files. Existing API credential/export/history behavior remains outside the layout change.

## Browser prerendering — 2026-10-09 (unpackaged)

`wordpress-link-prerender.php` passes **119 assertions** (113 main and six isolated constant-context cases). It loads native WordPress utility/core classes without `wp-load.php`, database writes or network access. It checks strict opt-in normalization, defaults, saves/imports, abilities and settings-history eligibility; typed before-handler JSON; independent prefetch/prerender flags; public-context guards; native prefixed paths, third-party path hooks and selector opt-outs; explicit native configuration veto; read-state restoration after a throwing hook; and preservation of native ownership when the option is disabled. Older-core absence is simulated before loading modern speculation functions; this does not establish a full older WordPress runtime result. Existing import **14** and preload **8** checks also pass.

`preload-links-browser.mjs` passes **180 assertions** across maintained and minified JavaScript. It covers hover/focus/press intent, cancellation, throttling, one-candidate replacement, exact origin/directory boundaries, raw/decoded exclusions, bare and nonempty query/fragment markers, file types, downloads, target/base targets, credentials, opt-out ancestors, native relative URLPatterns/selectors, capability fallback and inactive malformed patterns. Separate Chromium processes without DevTools prove actual prerender requests under nonce CSP, `document.prerendering`, activation and reuse of one document request. Each run closes its owned browser, server and temporary profile.

[Actual WordPress frontend proof](../.artifacts/speculation-rules/frontend-verification.json) passes **53 assertions** over five HTTP cases and six source/minified browser states on WordPress 7.1.3 / PHP 8.3.35. It checks the actual emitted configuration, native exclusions, native opt-out, preserved default core behavior when disabled and suppression of the broader core rule while prerendering is enabled. Browser checks use the emitted HTML/configuration and abort subsequent network requests; actual activation is proved by the isolated native-browser fixture above. The runtime caught `wp_localize_script()` converting booleans to strings, which prevented the strict frontend flag from enabling prerendering. Configuration now uses `wp_add_inline_script()` with type-preserving JSON before the handler.

[Settings UI proof](../.artifacts/speculation-rules/ui-verification.json) passes **194 checks in 12 states**: English/French, light/dark and 1440/390/320px. It verifies independent toggles, linked labels/help, keyboard activation/disclosure, retained exclusion input, visible focus clear of the Save boundary, native Save/Cancel ownership and clean state after reverting the toggle. Expanded content has no horizontal overflow, script errors, mutation requests or scoped axe violations. Full-page and focused viewport captures are retained; fixed controls in full-page screenshots are interpreted alongside viewport captures. France/Canada PO files translate seven added messages. All **36 PO/MO catalogs** compile and **64,921 lookups** match the authoritative PO or English fallback.

The request-only local MU fixture blocks database writes, external PHP HTTP and WordPress cron. Its narrowly allowed native `users/me` GET prevents a fixture-induced admin REST error while keeping write protection. [Cleanup evidence](../.artifacts/speculation-rules/runtime-preservation.json) confirms exact preservation of protected settings, drafts/history, locales, private metadata, domain bookkeeping and all four pre-existing sessions. The owned session, MU fixture and private state files were removed; no stale baseline was restored. No package or live deployment ran. Manual screen-reader/touch testing, full older WordPress/PHP execution, live analytics behavior and measured navigation speed gains remain outside these checks.

## Hierarchy, notice readability and Tools — 2026-10-09 (unpackaged)

The [design guidelines](../../tools/DESIGN_GUIDELINES.md) and [implementation record](../../tools/UX_UI_IMPROVEMENTS.md#hierarchy-notice-readability-and-tools--2026-10-09) define this pass. The local plugin source is served through the existing WordPress junction. Fixtures use an owned session and header-gated request state, block database writes/external PHP HTTP/cron, and do not consume existing pending notices.

| Proof | Result and boundary |
| --- | --- |
| Focused syntax | 17 changed/relevant UI PHP files, the corrected local CSS test and four maintained/generated JavaScript files pass syntax checks. |
| Existing source contracts | 98 Overview, 94 catalog/dependency browser, 71 transaction, 119 prerender PHP, 14 import, 39 corrected read-only local CSS, 26 isolated asset/history and 35 read-only WordPress asset/history checks pass. |
| Notice lifecycle | 405 appearance and 564 notification fixture checks pass. [Native WordPress proof](../.artifacts/notice-readability/notice-runtime-proof.json) adds 96 checks for delayed bootstrap, readable fallback, severity, placement and persistence. It invokes the native notice renderer; no purge action runs. |
| Full route matrix | [Final proof](../.artifacts/notice-readability/after-design-verification.json): 2,882 checks, 168 ordinary captures over 14 routes × English/French × light/dark × 1440/390/320px, plus 12 Tools file-selection probes. Zero script errors, page overflow or operation requests. Twelve external resource GETs were blocked. |
| Controls and hierarchy | Native form/control ownership, visible main choices, configured disclosures, 44px operational switch targets, visible off knobs, keyboard disclosure, invalid-field reveal, Heartbeat value retention, cleanup counts/scope and Cancel pass. Maintained/minified settings bundles are both exercised. |
| Tools | [40 read-only ownership checks](../.artifacts/notice-readability/tools-redesign-proof.json) retain native export/import boundaries. The final matrix additionally checks row/surface padding, button containment and text fit before/after selecting a local file in all 12 states. Selection is cleared; no upload, import or export is submitted. |
| History | Twelve rendered one-setting summaries verify independent English/French singular grammar. `_n()` owns both count phrases; isolated fixtures also exercise source plural handling. |
| Contextual translation | [23 focused Cloudflare checks](../.artifacts/notice-readability/cloudflare-context-proof.json) verify the exact `_x()` helper context in English desktop and French 390/320px, both themes. Six screenshots are refreshed; no script errors or actions submitted. Six disallowed requests are blocked. |
| Catalogs | All 36 PO/MO catalogs compile during this pass. The final France/Canada contextual correction recompiles those two catalogs and verifies 3,674 compiled lookups. Labels remain in gettext catalogs. |
| Accessibility | [36 scoped native axe runs](../.artifacts/notice-readability/accessibility-proof.json): zero violations or unavailable states, with three contrast-incomplete occurrences recorded. [Separate measurements](../.artifacts/notice-readability/accessibility-contrast.json) pass: textarea text 12.96–14.61:1; placeholders/helpers 6.02–7.41:1. This does not establish whole-app manual accessibility conformance. |
| Visual review | [Independent findings](../.artifacts/notice-readability/visual-review.json) cover all 14 routes and focused Tools states. Root additionally inspected fresh Tools desktop light/dark and French 320px dark captures. Findings include the card-edge clipping that page-overflow checks missed; focused fixes and recaptures are retained. |

French screenshot dates retain English month names in the request-forced locale; source uses `wp_date()` and the local fixture/core language coverage remains a limit. Native file chooser wording follows browser/OS locale. Human task timing, manual screen-reader/touch testing, RTL and actual provider/destructive outcomes remain outside this pass. No whole-app rating or speed gain is inferred.

[Initial cleanup](../.artifacts/notice-readability/runtime-preservation-before-history-recovery.json) detected a real test mistake: the original local CSS fixture directly applied `pre_update_option_noobrocket_settings`, invoking the native snapshot subscriber despite never saving settings. The added snapshot's timestamp matches the 19:56:10 test command. It displaced the oldest entry under the five-entry limit; the other four entries, saved settings, rules, option identity and autoload stayed unchanged. The header-gated browser write guard did not cover that CLI test.

The fixture now installs native read-only query/HTTP hooks before WordPress bootstrap and compares raw settings/rules/history rows. Its corrected 39 checks pass. [Targeted recovery](../.artifacts/notice-readability/runtime-history-recovery.json) removes the proven QA entry and recovers the displaced entry, using an atomic expected-value check; four retained entries and all other runtime options remain untouched. The failed receipt and command logs remain available. No full runtime baseline is restored.

[Final cleanup receipt](../.artifacts/notice-readability/runtime-preservation.json) passes exact comparison of protected option rows, domain bookkeeping, locales, private asset metadata and all four pre-existing sessions after that targeted recovery. This pass's session, MU fixture and private QA files are removed. No package, push or live deployment runs.

## Dashboard navigation beside actions — 2026-10-09 (unpackaged)

The Preloading, CSS, Modern images and PageSpeed setup partials separate native operational buttons from related navigation. Required CSS worker setup remains beside its prerequisite message. Existing gettext labels, operation values, independent forms, disabled controls, hash destinations and external-link attributes are retained. Shared navigation footers use resting chevrons, hover/focus underlines and 44px targets.

`wordpress-overview.php` passes its existing **98 assertions** without site bootstrap or external requests. All three changed PHP partials pass syntax checks. [Native browser proof](../.artifacts/dashboard-navigation/verification.json) passes **284 checks in 16 states**: English/French × light/dark × 1440/390/320px, plus four worker-needed/paused cases. It checks destination URLs, native form ownership, keyboard focus order, hover/focus, keyboard disclosure, action-row separation and wrapping. There are zero script errors, action requests, clipped controls, page overflow, scoped axe violations or incomplete results. Setup is expanded for PageSpeed; no key, queue, cache or generation operation runs. This focused proof does not retest Back or activate external destinations.

Actual-size captures cover status, images and PageSpeed setup. Independent visual review and root inspection confirm the revised hierarchy in both themes and narrow French states. The fixture uses a unique header-gated session and MU file; it blocks database writes, external HTTP and cron while preserving pending feedback. [Owned cleanup](../.artifacts/dashboard-navigation/runtime-preservation.json) records exact saved-state/session comparison. No package, push or live deployment runs; no UX score or speed improvement is inferred.

## Dashboard footer help — 2026-10-09 (unpackaged)

The dashboard no longer repeats Server worker setup as a detached footer link. Its local-optimization caption remains. Setup is still available inside CSS generation's Server worker disclosure, beside a required-worker prerequisite and in CSS delivery setup instructions. This addresses the footer duplication in historical OV01 and control-ledger item 19. The [design guidelines](../../tools/DESIGN_GUIDELINES.md#feedback-and-action-ownership) require specialist help to stay with its owning task.

The changed dashboard partial passes PHP syntax checks; existing `wordpress-overview.php` passes **98 assertions**, including required-worker guidance and separate action ownership. [Focused native output](../.artifacts/dashboard-footer/verification.json) passes **37 checks** over English 1440px and French 320px, both themes, with zero clipping, browser errors or operation requests. [Visual review](../.artifacts/dashboard-footer/visual-review.json) and cropped footer screenshots document the absence of the detached link and retention of contextual help. [Owned cleanup](../.artifacts/dashboard-footer/runtime-preservation.json) passes exact saved-state/session preservation. No settings or operational action is submitted; no package or live deployment runs.

## Card-by-card audit — 2026-10-09 (unpackaged)

The [current audit](../../tools/CARD_BY_CARD_AUDIT.md) covers all 14 routes, every inventoried task card and nested/conditional workflow. Detailed [settings](../../tools/audits/CARD_AUDIT_SETTINGS.md), [workflow](../../tools/audits/CARD_AUDIT_WORKFLOWS.md) and [overview/provider](../../tools/audits/CARD_AUDIT_OVERVIEW_PROVIDERS.md) assessments contain retained strengths, issues, priorities, proposals, acceptance and current source/visual references. This is audit evidence, not an implementation gate or a usability score.

| Evidence | Result and limit |
| --- | --- |
| [Final inventory](../.artifacts/card-audit-20261009/inventory.json) | 448 route/render records across 70 configurations, 1,526 card image tiles. All 14 baseline routes cover English/French, light/dark and 1440/390px; default/expanded and representative conditional cases are separate. Selected French 320px cases supplement the matrix. Direct visual review concentrates on English desktop/French narrow, with supplementary cross-locale inspection and geometry. |
| [Shared frontend probe](../.artifacts/card-audit-20261009/shared-proof.json) | Ten shell states across 320/390/768/1024/1440px, both themes; 28 interaction records. Menu access/Escape works in sampled compact states. Native expert/publication checked markers disappear on white surfaces; disabled stale Publish remains blue; hover/focus moves Inspect by 2px even with reduced motion. Cancel causes actual HTTP 200 document reloads in all ten states; final route/theme are preserved. |
| Collection safety | Final collectors report zero page script errors, document overflow or local operation requests. Unique request headers gate SQL-write/HTTP/cron guards; only a created QA session is used. Pending real notices are suppressed without being consumed. Synthetic grades, credentials, inventory and review cases are not actual provider/performance results. |
| Current source findings | Publication/history reviewed-state gaps are traced through their handlers without executing replacement transactions. Confirmed state-rendering/layout problems remain open. Historical passing checks do not close current SH01–SH04 findings. |
| [Cleanup receipt](../.artifacts/card-audit-20261009/runtime-preservation.json) | PASS: exact protected-state comparison; only the owned QA session/fixture/private files removed. Saved settings, published rules, history, private drafts, locale and all existing sessions are preserved. No stale baseline was restored. |

Capture harness issues were diagnosed at their owning layer and corrected: a metadata callback recursively resolved the current user, two fixture exclusion keys were wrong, and supplemental import-bottom captures were needed for the bounded comparison. Shared probes corrected an initial wrong selector and distinguished actual reload responses from same-document navigation. Initial failed logs remain under `C:/Users/Guillaume/.codex/logs/commands`; these harness failures are not plugin defects. The final import fixture may generate a nonpersisted Settings saved toast during normalization; this is explicitly excluded from product findings, and fresh card crops hide the tray without changing geometry.

No publication, restoration, cleanup, purge, queue run, Google request or persistent settings save occurred. Manual screen-reader/touch use, native OS picker rendering, RTL, human task timing and complete no-JavaScript task behavior remain unverified. Only audit documents and temporary evidence changed; no maintained plugin edits, package, commit, push or live deployment.

## Full-system review — 2026-10-09 (documentation only)

The [full review](../../tools/FULL_SYSTEM_REVIEW.md) and [ordered plan](../../tools/IMPROVEMENT_PLAN.md) consolidate current [architecture](../../tools/audits/FULL_REVIEW_ARCHITECTURE.md), [cache](../../tools/audits/FULL_REVIEW_CACHE.md), [visitor optimization](../../tools/audits/FULL_REVIEW_OPTIMIZATIONS.md), [provider](../../tools/audits/FULL_REVIEW_PROVIDERS.md) and card findings. Native isolated failure probes are review evidence, not completed regression fixes. No shared WordPress bootstrap or provider mutation was used for the additional code probes.

| Fresh verification | Result and scope |
| --- | --- |
| [Syntax receipt](../.artifacts/full-review-20261009/syntax-proof.json) | PASS: 1,413 PHP and 37 JavaScript files, zero parse errors. Includes bundled PHP dependencies; uses PHP 8.3.35 and the installed Node runtime. Not minimum-version or behavior proof. |
| Existing cache fixtures | PASS: concurrent writer 13, configuration 10, preload 8 assertions. New companion-session, response-privacy, incomplete-policy and host-selection defects reproduced separately. See cache report for all logs and scope. |
| Existing architecture fixtures | PASS: import 14, asset/history 26, network uninstall 638 assertions. New private-review, restore-review, pre-commit history/cleanup, API normalization/outcome and delegated plugin-deactivation gaps reproduced with in-memory/recording doubles. |
| Maintained CDN validation | FAIL, exit 1 before assertions: undefined `noobrocket_sanitize_prerender_options` in fixture bootstrap. A temporary review-only shim reached 29 existing assertions; it does not repair or pass the maintained command. AR08 remains open. |
| Existing optimization fixtures | PASS: bloat/object cache 38; picture generator 31; link PHP 113 + 6; CSS generator 47; CSS interactions 18; link source/minified browser 180. Synthetic interaction fixtures are not installed commerce/page-builder verification. |
| Additional optimizer probes | Reproduced failed font publication, HTTP 503 asset persistence, foreign-table optimization, LRC stored count 40 versus limit 20 and real Chromium delay failure under enforcing CSP. Passing probe assertions establish these defects. SVG 128×64 control passed; suspected SVG defect was rejected. |
| Admin appearance browser | PASS, 405 checks including seven synthetic Save redirects. Log: `20261009-214027-610-9f260af8f1684f62aa6e1c6727470d23.log`. |
| Admin transaction browser | PASS, 71 checks. Log: `20261009-214027-629-062a7e8b792c4ee48863f7776297d6d8.log`. |
| CDN browser | PASS, 162 checks. Log: `20261009-214027-619-a575fbf992dc4931a3ed094a27f6962b.log`. |
| Provider/action source trace | Five provider findings; no external HTTP/probe. Separate PageSpeed/image forms retain POST/capability/nonce ownership. Current remote API compatibility/availability unverified. |
| Worker dependency advisories | `npm audit --omit=dev --json --prefix noobrocket/local-css`: exit 0, zero registry-reported advisories. Read-only; no install/update. Full PHP dependency advisory scan unavailable. |
| Native UI | Reused the still-current card evidence above. Synthetic browser passes do not close native checked/disabled/radio/Cancel defects. Protected preview state remains preserved. |
| [Source preservation](../.artifacts/full-review-20261009/source-preservation.json) | All 1,673 initially inventoried paths/bytes/SHA-256 match. Six pre-existing dot-path vendor/reference files were omitted by the initial inventory; their hashes cannot be compared. Complete package: 1,679 files / 24,743,776 bytes. No product edit performed. Review documentation/evidence are outside that tree. |
| [Review document proof](../.artifacts/full-review-20261009/document-proof.json) | New report links/anchors and all 14 page entries checked. This validates document structure, not UX quality. |

Logs remain under `C:/Users/Guillaume/.codex/logs/commands/`; domain reports identify exact receipts and command/probe boundaries. [Review evidence](../.artifacts/full-review-20261009/) retains isolated probes and machine-readable results. Cache-specific temporary-file/HTTP probes are also retained under `.artifacts/full-review-cache/`; their owned server was stopped. No product fix was made to hide failing checks.

No fresh site-speed/load comparison, PHPStan/PHPUnit run, production SQL EXPLAIN, real concurrent database failure test, minimum PHP/WordPress matrix or current-source Linux/server/integration release proof is claimed by this historical review checkpoint. Historical proof remains historical. The active preview's settings, history, drafts, notices, locales and existing sessions were not changed. No archive, commit, push or live deployment was performed.

## Batch implementation — 2026-10-09

The approved corrections are recorded in [BATCH_FIXES.md](../../tools/batches/BATCH_FIXES.md). Detailed [cache](../../tools/batches/CACHE_FIXES.md), [optimization](../../tools/batches/OPTIMIZATION_FIXES.md), [provider](../../tools/batches/PROVIDER_FIXES.md), [settings/API](../../tools/batches/SETTINGS_DOMAIN_FIXES.md), [history/lock](../../tools/batches/HISTORY_LOCK_FIXES.md) and [final integration](../../tools/batches/INTEGRATION_REVIEW_FIXES.md) reports identify exact fixture scope, counts, receipts and remaining limits. This section supplements earlier commands; historical records above retain their original source/runtime scope.

The following checks use native plugin owners with mocks, intercepted boundaries or owned temporary files. They do not bootstrap or mutate the user's active WordPress runtime. Some read official core classes from `.artifacts/wordpress/`; browser checks require the pinned local worker dependencies and an explicit Chromium executable.

```powershell
php tests/wordpress-cache-policy.php
php tests/wordpress-cache-headers.php
php tests/wordpress-cache-atomic.php
php tests/wordpress-cache-config.php
php tests/wordpress-cache-write.php
php tests/wordpress-cache-ownership.php
php tests/wordpress-preload-outcomes.php
php tests/wordpress-settings-domain-api.php
php tests/wordpress-provider-boundaries.php
php tests/wordpress-history-lock.php
php tests/wordpress-reviewed-state.php
php tests/wordpress-settings-import.php
php tests/wordpress-private-draft-persistence.php
php tests/wordpress-plugin-deactivation.php
php tests/wordpress-performance-hint-boundary.php
php tests/wordpress-database-cleanup.php
php tests/wordpress-database-lifecycle.php
php tests/wordpress-optimizer-resources.php
php tests/wordpress-local-css-retries.php
php tests/wordpress-picture-generator.php
php tests/wordpress-dashboard-state.php
php tests/wordpress-uninstall-network.php
node tests/settings-controls-browser.mjs /absolute/path/to/chromium
node tests/settings-transactions-browser.mjs /absolute/path/to/chromium
node tests/settings-appearance-browser.mjs /absolute/path/to/chromium
node tests/settings-notices-browser.mjs /absolute/path/to/chromium
node tests/cdn-settings-browser.mjs /absolute/path/to/chromium /absolute/path/to/php
node tests/second-audit-catalog-browser.mjs /absolute/path/to/chromium /absolute/path/to/php
node tests/delay-csp-browser.mjs /absolute/path/to/php /absolute/path/to/chromium
```

Run focused checks for the changed owner first. Preserve failed logs and inspect decisive output; do not rerun unrelated suites to inflate totals. In this workspace use `.artifacts/tools/php-8.3.35/php.exe` for PHP. Site-argument fixtures earlier in this document still require their separately marked disposable WordPress root. The shared `127.0.0.1:8765` preview is not disposable merely because it is local.

### Native Linux SQL and WordPress proof

The native SQL runner creates its own private MariaDB directory/socket with networking disabled, loads actual query/table owners, records EXPLAIN/ANALYZE and verifies creation/migration/refusal/retry. It needs existing MariaDB/PHP and an official local WordPress archive. Run as an unprivileged account; no existing service or database is reused.

```sh
sh tools/batches/preload-sql-proof.sh /absolute/workspace /absolute/wordpress.zip
```

The existing production runner needs already-installed PHP-FPM 8.4, MariaDB, Apache, Nginx, Node and Chromium. It copies maintained source into a new owned directory, uses serial loopback HTTP on port 8875, keeps Chromium sandboxing and stops its owned processes. Its optional fifth argument identifies the package.json beside already-installed worker dependencies. It does not package or deploy the plugin.

```sh
sh tools/linux-production-proof.sh /absolute/workspace /absolute/wordpress.zip /absolute/node /absolute/chromium /absolute/local-css/package.json
```

Current [native verification](../../tools/batches/NATIVE_WORDPRESS_PROOF.md) passed 266 WordPress assertions and 10 preflight checks on PHP 8.4.26/MariaDB 11.8.6, including both web servers, actual sandboxed CSS work and multisite isolation. An artifact-only runner addition covered 38 Asset Manager read-only assertions without broadening the maintained shared-preview guard. The debug log was empty and owned processes stopped. Intercepted provider checks remain a different gate; this native proof does not call real accounts or certify every server/platform configuration.

### Frontend inputs and catalogs

Terser 5.44.0 is pinned in `tools/package-lock.json`. Regenerate the served bundle from maintained `noobrocket-settings.js`; preserve active GSAP dependencies. Keep translations in PO and compile MO through the existing updater, which validates singular/plural placeholders and compiled lookups. `polib==1.2.0` is required in the selected Python environment.

```powershell
npm ci --prefix tools --ignore-scripts --no-audit --no-fund
npm run generate:settings --prefix tools
& .artifacts/translation-tools/Scripts/python.exe tools/update-translations.py
```

Compare repeat-generation bytes after an input change. The translation updater supplements literal extraction; it is not a full gettext extraction replacement. No dependency install belongs in packaging.

### Native UI and preserved preview state

Review all 14 routes and meaningful conditional workflows at 1440/390px in English/French and light/dark, with selected 320px/laptop cases. Native captures and interaction probes use an owned QA session and write-blocking fixture; operations are intercepted. Verify Cancel restores the current document's controls/CDN rows in place, categorized selection state and focus, without a document request. Verify immediate cleanup submits its own confirmed categories/nonce and never pending global settings. Import review precedes transfer; private draft, saved settings and visitor publication remain distinct.

Final cleanup must remove only that owned instrumentation/session and compare exact protected settings/history/draft/locale/pre-existing sessions. It must not restore a baseline over later user state. Final current-source syntax, catalog, capture and preservation receipts are tracked in [the batch record](../../tools/batches/BATCH_FIXES.md); earlier successful checkpoints do not certify later edits.

No numeric UX score, universal speed claim, matched current-site FCP/LCP comparison, live provider proof or full older-platform/commerce/accessibility matrix follows from fixture totals. Expiry traversal still scales with the filesystem; private early User Cache remains unavailable. The batch produces source and proof, with packaging and deployment left as separate requested tasks.

### Settings-save preload latency

The focused fixture executes native settings events, the after-save callback, service registration and queue methods against isolated boundaries. It rejects preload HTTP and sleep in the save-facing path and covers current saved options, native filters, disabled/paused state, deduplication and mixed-save callback priorities.

```powershell
php tests/wordpress-settings-preload.php
php tests/wordpress-preload-outcomes.php
```

Current proof: 21 settings assertions, 32 preload outcomes, four PHP parses and 89 read-only native panel interactions pass. [Cause, changes and evidence limits](../../tools/batches/SETTINGS_SAVE_LATENCY.md) distinguish source behavior from transport timing and complete installed-site save latency. Do not benchmark settings POSTs against the shared preview; use a disposable site.

### Opened inline confirmation warnings

[Warning correction and evidence](../../tools/batches/INLINE_WARNINGS_FIX.md) covers the shared component after opening JavaScript-combining and Delay Safe Mode confirmations. The native read-only probe passed 267 checks over 14 theme/language/width/direction states, including opt-in, prerequisites, Cancel, focus, contrast and targets. Both edited templates parse. Confirmations use `type="button"`; no settings POST belongs to the probe. A concurrent settings/history difference was retained and documented rather than overwritten with the audit baseline. Owned private QA state was removed after diagnosis.

### Dashboard card refinement

```powershell
php tests/wordpress-overview.php
php tests/wordpress-dashboard-state.php
```

The follow-up [overview content review](../../tools/batches/OVERVIEW_CONTENT_REVIEW.md) adds selected-mode queue/Retry isolation over in-memory SQLite, image scan/scheduling states, and object-cache/PageSpeed rendering contracts. These checks do not bootstrap or mutate the shared preview:

```powershell
php tests/wordpress-overview-css-scope.php
php tests/wordpress-overview-images.php
php tests/wordpress-overview-supporting.php
```

[Scoped proof and limits](../../tools/batches/DASHBOARD_CARD_REFINEMENT.md): 117 overview assertions, 13 dashboard assertions, three PHP parses and 446 native browser checks over 32 configurations. The browser matrix checks paired edges/footers, natural expansion, narrow reflow, recorded check hierarchy and independent operation ownership in both themes and English/French. No UI operation or settings POST was issued. A cron-only baseline difference was retained; other protected settings/history/draft/locale/private metadata/sessions matched exactly. No numeric app-wide design score follows from these checks.

### Saved-draft preview navigation

Run the isolated signed-link test, browser navigation test and nearest transaction/review gates. The browser test requires an explicit Chromium executable; it uses a synthetic local endpoint, not WordPress or live operations.

```powershell
php tests/wordpress-asset-preview-links.php
php tests/wordpress-assets-second-pass.php
php tests/wordpress-reviewed-state.php
node tests/asset-preview-links-browser.mjs C:/path/to/chrome.exe
node tests/settings-transactions-browser.mjs C:/path/to/chrome.exe
```

[Preview behavior and proof](../../tools/batches/ASSET_PREVIEW_NAVIGATION.md) documents real new-tab links, native POST fallback, preserved pending controls, GET-only preview authorization and continued POST-only mutation ownership. Both dedicated tests pass 57 assertions; the browser fixture also covers named-form-property collisions, field Enter, native reset and invalid middle-click. Native UI review blocks preview operation requests; actual browser navigation is covered by the synthetic endpoint and server validation by the native isolated PHP owners.

### Optimization reaudit — 10 October 2026

[Current fixes and evidence](../../tools/audits/REAUDIT_OPTIMIZATION_20261010.md) cover valid Delay JS attributes, exact minifier/CSS-import/image-dimension origins, intrinsic module/nonce/SRI/native/configured exclusions and complete fallback when a selected combining resource fails. Run these isolated checks without a WordPress site argument:

```powershell
php tests/wordpress-optimizer-resources.php
php tests/wordpress-minify-boundaries.php
node tests/delay-csp-browser.mjs C:/path/to/php.exe C:/path/to/chrome.exe
node tests/minify-boundaries-browser.mjs C:/path/to/php.exe C:/path/to/chrome.exe
```

The minifier fixture generates real optimized bytes with the native minifier and owned temporary files; mocked HTTP never reaches an external provider. Its Chromium companion intercepts every request and proves generated external/inline execution, original ordering, external and inline module imports, nonce/SRI preservation including inline nonce-only CSP, native inline opt-outs, supported self-source CSP and all-original recovery after optimizer-time 503/transport/empty-source failures. Neither fixture bootstraps or mutates the shared preview. Syntax/browser success does not establish production speed or every installed-site policy and runtime combination.

## Full-system reaudit — 2026-10-10 (unpackaged)

[Current review](../../tools/FULL_SYSTEM_REAUDIT.md) links the cache/lifecycle, optimization and UI/brand reports. Cache fixes reuse native normalization, cache-file readiness and current-network deactivation scope; homepage activation now queues existing async work and respects saved enabled/paused state. Run focused isolated regression owners without a WordPress site argument:

```powershell
php tests/wordpress-preload-outcomes.php
php tests/wordpress-settings-preload.php
php tests/wordpress-cache-lifecycle.php
php tests/wordpress-database-lifecycle.php
php tests/wordpress-settings-domain-api.php
php tests/wordpress-settings-import.php
php tests/wordpress-provider-boundaries.php
php tests/wordpress-private-draft-persistence.php
php tests/wordpress-assets-second-pass.php
```

Final evidence: 877 PHP and 51 JavaScript files parse; 448 native page renders cover all 14 routes and supported themes/locales/sizes without page overflow or operation requests; 112 scoped axe states report zero violations with remaining contrast nodes checked separately. Cache-Cache branding, no-JavaScript navigation/actions, provider alignment and native count grammar have their own focused UI proof. All 36 catalogs compile with 82,055 verified lookups. Shared Save/appearance/preview-operation tests use synthetic endpoints; real settings mutation is never part of read-only preview review.

Cleanup preserved every protected product row, private metadata, locale and all four existing sessions. The exact comparison differed only in normal cron timestamp progression; event configurations remained identical. The scoped preservation proof records that difference without restoring a baseline. Owned QA session, fixture and private files were removed.

This run does not prove production visitor timings, external provider accounts, the minimum PHP/WordPress versions, installed multisite Action Scheduler behavior, assistive-technology/RTL interaction or image quality. Logs, matrix captures, before snapshots and sanitized preservation evidence remain task artifacts; credentials and owned session/baseline payloads are not published.


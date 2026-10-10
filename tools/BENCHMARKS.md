# Performance and production checks

The benchmark reuses the worker's pinned Playwright dependency. Install it with `npm ci` in `noobrocket/local-css`, use Node.js 22 or later, and provide an installed Chromium executable. Tools stay outside the installable WordPress ZIP.

## Controlled product comparison

Copy [benchmark.example.json](benchmark.example.json). Replace its placeholders with an immutable staging snapshot, actual server and software versions, equivalent optimization settings, canonical page URLs, and generation/preload completion state. Use one machine, browser executable, site snapshot and configuration for both runs. Retain database/uploads/theme/plugin snapshots outside the benchmark.

Prepare each product manually on that staging snapshot. Activate only one page-cache plugin, restore identical content and settings, finish CSS generation and preload, and confirm cache-hit instrumentation. The runner does not switch plugins, purge cache, submit WordPress settings, or contact vendor APIs. Add an explicit staging response header when a server serves cached HTML; do not infer hits from a marketing footer or a fast response. Both products must use the same evidence configuration. NoobRocket PHP hits already return `Cache-Status: NoobRocket; hit`; static Apache/Nginx delivery needs server-side instrumentation.

```bash
node tools/benchmark.mjs run --config staging.json --product "WP Rocket" --version 3.23.5.1 --browser /usr/bin/chromium --out .artifacts/benchmarks/wp-rocket.json
node tools/benchmark.mjs run --config staging.json --product NoobRocket --version 1.2.0 --browser /usr/bin/chromium --out .artifacts/benchmarks/noobrocket.json
node tools/benchmark.mjs compare --baseline .artifacts/benchmarks/wp-rocket.json --candidate .artifacts/benchmarks/noobrocket.json --out .artifacts/benchmarks/comparison.json
```

Output includes raw samples, product versions, normalized config, Node/OS/architecture, Chromium version, timestamps, medians, p75/p95, and a sibling Markdown report. Existing output files are preserved. Comparisons reject different benchmark protocols, configuration, browser/runtime versions, groups, or sample counts. Site and hardware identity are operator declarations; the runner cannot prove that a supplied snapshot or settings were restored correctly. Alternate product order and repeat complete runs when drawing performance conclusions; five samples describe variance but do not establish statistical significance.

Comparison tables report CLS as an absolute score change. A percentage against a near-zero CLS baseline can be misleading. Raw comparison JSON retains the exact medians, differences and ratios for inspection.

Each sample warms server cache, then measures a proven HTTP cache hit and two browser visits. Fresh browser means a new isolated browser context. Repeat browser means the same context revisits the page with its normal browser cache. Neither label means cold server cache. Native HTTP TTFB includes connecting/TLS from request creation to first body byte; browser TTFB measures navigation `responseStart - requestStart`, which excludes connection setup. Do not compare those two columns as equivalent timings.

Browser metrics include buffered LCP, CLS using the maximum session window, transferred external CSS, and inline CSS bytes. CSS network bytes include Chromium's response overhead. The browser remains unthrottled; mobile uses a mobile user agent, touch and mobile viewport, not a simulated slow phone. Zero repeat CSS bytes means browser cache served the assets. A no-store site may still transfer them again.

Optional staging page `interactions` support `click`, `hover`, and `press` with a selector; `press` also needs `key`. `expectations` require `selector` and `visible`, with optional expected text. These can open menus, dialogs, tabs, or disposable cart states after initial LCP collection. Only configure interactions safe for disposable staging data. Scripted event latency is a lab measurement and is not field INP. Browser errors, failed resources, redirects, unproven cache hits and failed expectations stop a controlled run.

## Read-only public observation

For a user-authorized public URL, set `observationOnly: true`, `staging: false`, omit cache evidence when unavailable, and use no interactions. This records response timings and browser metrics without claiming cache hits. Browser/network errors are retained in raw samples. Public observations cannot be fed into a controlled product comparison. Differences in deployment, browser, network, CDN and traffic remain uncontrolled.

When the site owner authorizes a controlled public cutover and both products have equivalent verified settings and hit instrumentation, use `readOnly: true`, `staging: false`, mandatory `cacheEvidence`, and no interactions. Run both products with the same config. The runner changes nothing on the site. Results still depend on public traffic/network and do not establish statistical significance or superiority across sites.

The initial 2026-10-08 observation of `https://www.noobunbox.net/` is stored under `.artifacts/benchmarks/`. Its historical report does not prove a product speed advantage. An earlier attempted controlled baseline incorrectly declared CSS generation disabled while WP Rocket cloud used CSS remained enabled; that report is marked invalid for comparison.

The subsequent authorized public-site cutover produced the matched `noobunbox-matched-css-off-{wp-rocket,noobrocket,comparison}-20261008.{json,md}` reports. Both products used theme 2.7.8 (`3ebd914`), equivalent common settings with all CSS generation off, the same browser/runtime/config and mandatory nginx static-cache HIT evidence. Each product passed 36 samples covering homepage/article, desktop/mobile, HTTP and fresh/repeat browser phases, without controlled browser/network failures. Operator checks confirmed settings and cache variants before measurement. WP Rocket's original option was restored exactly while inactive; NoobRocket retained its own keys and versions.

Fresh-browser LCP medians ranged from 1040 to 1084 ms for WP Rocket and 1052 to 1068 ms for NoobRocket. CSS transfer differed by only 4–5 response-overhead bytes. Browser TTFB varied between sequential public runs. Homepage mobile CLS remained high in both products (fresh medians 0.497 and 0.477; repeat 0.379 in both). These three-sample groups support near-equivalence on this warmed static-cache profile, not statistical significance or general superiority. Later critical-CSS settings and theme fixes are separate profiles and cannot be substituted into this matched comparison.

### Final optimized public profile — 2026-10-08

The separate `noobunbox-optimized-critical-noobrocket-20261008.{json,md}` run records NoobRocket 1.2.0, deployed ZIP SHA-256 `13cb7c65ee374fdecf6890f9551e0d2ed840ddc9cbe631f45441792b57c1008c`, theme 2.7.9 (`b2d7d3ac42e5b0c33653ad3783b4949ed5825041`), local critical CSS, native local fonts, image dimensions and automatic font preload. All four target variants and 12 public CSS assets were verified before measurement. Generation workers were stopped, NoobRocket preload paused and local-CSS cron absent throughout the run. All 36 samples passed static-cache HIT and browser/resource checks. This is a standalone profile; the changed theme, settings, artifact and nginx override prevent an identical-conditions comparison with the earlier WP Rocket pair.

Fresh-browser medians, with three samples per row:

| Page / viewport | Browser TTFB ms | LCP ms | CLS | External CSS bytes | Inline CSS bytes |
| --- | ---: | ---: | ---: | ---: | ---: |
| Homepage / desktop | 51.4 | 1016 | 0.030 | 9154 | 82222 |
| Article / desktop | 52.3 | 960 | 0.119 | 39617 | 265375 |
| Homepage / mobile | 52.6 | 1100 | 0 | 9154 | 82189 |
| Article / mobile | 56.0 | 928 | 0 | 36620 | 259716 |

Article desktop CLS slightly exceeds the 0.1 target in this run; repeat-browser median was 0.029. Mobile zero is Chromium's standard CLS filtering, not proof of no physical layout movement. The separate no-input homepage proof recorded raw font shifts of 0.01358 and 0.02956 flagged `hadRecentInput=true` despite zero recorded inputs. Its desktop scores were 0.003798 and 0.004104. Timing, viewport and filtering differences make those independent runs distinct evidence. Inline CSS bytes include existing page CSS, generated critical CSS and native font definitions; they cannot all be attributed to the generator or interpreted as compressed HTML transfer. Public network/traffic and three-sample variance remain uncontrolled; field INP, production capacity and general product superiority are unproved.

The final site include disables nginx `open_file_cache` at server scope. An observed 60-second inherited file-cache validity window had kept deleted HTML cache inodes serving after native purges; a location-only override would leave the server rewrite file-existence test stale. The scoped override preserves routing and browser asset-cache directives while giving up nginx descriptor/metadata caching for this vhost; OS and browser caches remain available. The fresh-connection purge proof observed HIT, removal of only the owned desktop homepage plain/gzip files, MISS HTTP 200 containing current critical CSS/local fonts, then static HIT in 2.056 seconds. It preserved the four ready CSS jobs.

The operator's independent fresh PageSpeed Insights homepage run `q1w32uone6` scored 100 for both desktop and mobile. Desktop reported FCP 0.3 s, LCP 0.4 s and CLS 0.004; mobile reported FCP 0.9 s, LCP 0.9 s and CLS 0.034. These are one homepage Lighthouse lab run under different conditions from the local browser benchmark; they are not field Core Web Vitals, article scores or evidence that every page scores 100.

## Same-host CPU measurement

Remote browser timing cannot measure WordPress server CPU. Run the Linux host sampler on the WordPress/worker host during an idle baseline and again during the same bounded worker workload:

```bash
node tools/server-load.mjs --duration 30 --interval 1000 --out idle-host.json
node tools/server-load.mjs --duration 30 --interval 1000 --out worker-host.json &
node noobrocket/local-css/worker.mjs --wordpress /var/www/staging --php /usr/bin/php --browser /usr/bin/chromium --once --limit 2
wait
```

The sampler records aggregate CPU busy percentage, available/total memory and load averages from `/proc`. It includes unrelated host activity and does not attribute CPU to a plugin or process. Match worker job count, source content, browser, concurrency and background traffic before comparing load. Keep `--limit` and worker scheduling bounds set on production; successful completion is separate from an acceptable server-load budget.

## Read-only environment preflight

Run as the worker's unprivileged service account on staging. WordPress boot performs its normal initialization; this tool does not write settings, generate cache, publish CSS or issue HTTP requests.

```bash
node tools/production-check.mjs --wordpress /var/www/staging --php /usr/bin/php --browser /usr/bin/chromium --require-linux yes --require-mysql yes --require-multisite no --out production-preflight.json
```

The PHP inventory checks WordPress/plugin activation, connected database and actual database version, PHP version, cache-directory permission, owned cache drop-in and WP_CACHE. The Node runner performs a real Chromium launch/render and verifies Linux runs did not disable sandbox flags. Requirements fail explicitly when the selected environment is unavailable. Use `--require-multisite yes` and `--site-url https://staging.example.com/subsite/` for a network site. Site selection uses normal WordPress bootstrap and verifies the selected stored home URL. Preflight alone does not prove isolation, cache rewrite delivery, worker generation, or installed WooCommerce compatibility.

## Disposable Linux production fixture

[linux-production-proof.sh](linux-production-proof.sh) requires existing Debian-style PHP 8.4 CLI/FPM, MariaDB, Apache, Nginx, unzip, Node 22+, Chromium and worker dependencies. It installs nothing and must run unprivileged. Use a verified WordPress archive. The runner creates a unique marked directory, private MariaDB datadir/socket, native filesystem copy of the current plugin, dedicated Apache/Nginx/FPM configs and logs. It binds only `127.0.0.1:8875`, runs servers serially, and terminates only its verified processes. It retains evidence files. Set `NOOBROCKET_PROOF_DIR` to a persistent directory when `/tmp` is cleared between WSL boots. An optional fifth argument selects the dependency package.json when installed outside the plugin source.

```bash
NOOBROCKET_PROOF_DIR="$PWD/.artifacts/linux-runtime" sh tools/linux-production-proof.sh "$PWD" "$PWD/.artifacts/downloads/wordpress-7.1.3.zip" /path/to/node /usr/bin/chromium
```

Keep the private database on a native Linux filesystem. The fixture covers generated Apache rewrite delivery, a minimal explicit Nginx static-cache configuration, anonymous/private/password/authenticated/query/cart-cookie isolation, real MariaDB settings/job behavior, and sandbox-enabled Chromium. Its Nginx configuration tests one controlled desktop cache variant; adapt production rules for HTTPS, mobile, WebP, exclusions and multisite rather than copying the fixture configuration into a live server.

[local-css-interactions.mjs](../tests/local-css-interactions.mjs) checks actual generated used CSS at desktop/mobile sizes for hidden menus, opened dialogs, tab activation, dynamically inserted AJAX content, focus and WooCommerce-like cart classes. It uses a synthetic browser fixture; an installed WooCommerce store and specific page builders require their own staging interactions. Existing WordPress cache fixtures cover separate mobile variants and content invalidation.

```bash
node tests/benchmark.mjs /path/to/chromium
node tests/local-css-interactions.mjs noobrocket/local-css/generator.mjs /path/to/chromium
```

Record executed checks separately from unsupported environments. No checklist or preflight turns an unexecuted environment into a pass.

Executed on 2026-10-08 with Debian 13, PHP 8.4.26, MariaDB 11.8.6, WordPress 7.1.3, Node 22.23.3 and sandbox-enabled Chromium 154.0.8037.92. The fixture passed 17 Apache and 17 Nginx cache/privacy assertions, both single-site and secondary-site preflights (5/5 each), 37 settings and 126 backend local-CSS assertions, and three publication assertions after the real Linux worker generated one used-CSS job with zero failures. The actual network passed five activation/site-birth, six preparation, nine queue/settings/worker-lease isolation, and six invalid site-selection preservation assertions. WordPress debug output was empty after all phases. Evidence is retained in `.artifacts/linux-proof-1.2.0/`; the native runtime directory and command log are recorded in `execution.json`.

The host sampler recorded two idle and ten worker-window samples; aggregate peak CPU was 0.1% idle and 6.3% while generating the small fixture. This is an executed functionality/load observation, not a capacity limit or performance claim for a production site. The interaction fixture passed 18 assertions, and the benchmark protocol/report fixture passed 30, including zero and near-zero CLS comparison display. Installed WooCommerce, specific page builders and other production server configurations remain separate validation targets.

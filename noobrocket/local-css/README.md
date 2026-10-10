# Local CSS generation

Cache-Cache can generate critical CSS or used CSS on the same server as WordPress. This feature is off by default and needs Node.js 22 or later, PHP CLI, Chromium, and permission to run a scheduled command. Ordinary PHP-only shared hosting cannot run this worker. The worker follows the plugin's beta version; dependencies remain pinned in package-lock.json.

## Install and run

Install Node.js and Chromium through your server's normal package management. Use an unprivileged service account with access to WordPress, its database configuration, and NoobRocket's cache directory. Chromium's Linux sandbox remains enabled; do not run the worker as root.

Chromium also needs writable user configuration/cache directories. Service accounts such as `www-data` may require dedicated directories owned by that account and `XDG_CONFIG_HOME`/`XDG_CACHE_HOME` environment variables in the worker command. Verify these with `--check`; an unwritable browser profile can cause a crashpad startup failure.

Install the pinned worker dependencies from the plugin directory:

~~~bash
cd /var/www/site/wp-content/plugins/noobrocket/local-css
npm ci
~~~

Check the actual WordPress/PHP bridge and Chromium launch before enabling generation:

~~~bash
node worker.mjs --wordpress /var/www/site --php /usr/bin/php --browser /usr/bin/chromium --check
~~~

`--check` exits after checking the environment. It does not process queued pages, start a persistent worker or install cron. The dashboard records this as a setup check; run or schedule the generation command below to process jobs.

Select **Local critical CSS** or **Local used CSS** under **Settings → Cache-Cache → CSS & JavaScript**. Add selector substrings for menus, dialogs, AJAX content and other classes that appear only after interaction. Visit or preload public pages to queue generation, then run:

~~~bash
node worker.mjs --wordpress /var/www/site --php /usr/bin/php --browser /usr/bin/chromium --once --limit 10 --max-seconds 120 --delay-ms 1000
~~~

Replace the paths with your installation paths. `--once` exits when no eligible jobs remain; `--limit` caps claimed jobs. `--max-seconds` stops between jobs after the time budget, allowing the current job to finish. `--delay-ms` controls the pause between jobs and defaults to 1000 milliseconds. Without `--once`, the worker polls every five seconds. A per-site lease allows one worker at a time; abandoned leases expire after ten minutes.

For automatic generation, add a cron entry for the service account. This example handles up to two jobs per invocation; the built-in site lease prevents overlapping workers:

~~~cron
* * * * * /usr/bin/node /var/www/site/wp-content/plugins/noobrocket/local-css/worker.mjs --wordpress /var/www/site --php /usr/bin/php --browser /usr/bin/chromium --once --limit 2 --max-seconds 120 --delay-ms 1000
~~~

The worker visits pages anonymously. It uses a private PHP CLI bridge to claim and publish jobs through the same WordPress installation. The bridge refuses web requests. No application password, API key, vendor account or remote generation service is needed.

For multisite, configure local CSS separately on each site and run one invocation per enabled site using its exact home URL:

~~~bash
node worker.mjs --wordpress /var/www/site --site-url https://example.com/subsite/ --php /usr/bin/php --browser /usr/bin/chromium --once --limit 2 --max-seconds 120
~~~

`--site-url` selects the site before WordPress bootstraps and refuses a mismatched home URL before accessing jobs or leases. Queues, worker leases, settings and generated CSS files belong to that site. Schedule sites separately and account for their combined server load. Verify network and mapped-domain behavior on your staging installation.

Only same-origin browser requests are allowed by default. Add each required stylesheet, script or font CDN origin explicitly; otherwise generation fails and pages retain original CSS:

~~~bash
node worker.mjs --wordpress /var/www/site --php /usr/bin/php --browser /usr/bin/chromium --once --limit 10 --asset-origin https://cdn.example.com --asset-origin https://fonts.example.com
~~~

Same-origin page redirects and asset redirects to explicitly trusted origins are supported. Every redirect hop is checked before fetching it; page navigation must retain the original scheme, host and port even when another origin is trusted for assets. Redirected stylesheet assets resolve against their final source directory. Canonical URLs remain preferable.

## Behavior and recovery

Critical CSS prioritizes the first viewport while preserving original full stylesheets asynchronously and inside noscript. Pages with an enforcing Content Security Policy keep their blocking originals because this delivery path uses inline styles and an inline load handler.

With Host Fonts Locally enabled, Google font stylesheets accepted by the native font controller stay outside the local CSS job. A completed critical page uses that controller's cached local font CSS inline, avoiding remote font-face definitions in the critical output. Used mode retains native linked font delivery. Native font exclusions are honored; unsupported font tags stay eligible for the existing local CSS path. Pending results, enforcing CSP and excluded requests do not force inline font CSS. A later `noobrocket_host_fonts_locally_inline_css` filter can keep linked delivery.

Used CSS removes unused ordinary top-level rules from each processed external stylesheet, preserving its place in the cascade. Original inline styles, exclusions, conditional blocks, fonts, keyframes, custom properties, hidden elements and state selectors remain conservative. Native nested rule subtrees are retained whole. Browser-loaded imports are expanded in place with their layer, supports and media conditions, original source validation and relative asset bases; imported contents are retained whole. This favors compatibility over the smallest possible output.

Unavailable imports, import cycles or excessive depth/count, imported namespaces, invalid late imports, changed sources and missing prerequisites leave original CSS active. An import skipped by the sampled browser, such as an unsupported supports condition, can require this fallback.

Results stay separate for desktop/mobile when separate mobile caching is enabled. Otherwise generation unions desktop and mobile samples. No browser sample can predict all future application states; safelist dynamic selector substrings before enabling used CSS on interactive pages.

Local CSS skips authenticated, private, password-protected, query and cache-excluded requests. It also skips sites configured with dynamic or mandatory cache cookies because anonymous browser samples cannot represent their personalized variants. Page caching and other optimization settings continue to use their existing behavior.

Failed jobs retain original CSS and retry after five minutes. Abandoned claims become eligible after ten minutes. Changes that invalidate a claim reject its late result. Content, taxonomy, theme, settings and cache purges invalidate results and reuse the existing page cache/preload paths.

The dashboard's **Diagnostics and recovery** panel shows queue counts, recent CSS failures, worker versions/state and the last report time. **Check cache delivery** warms the anonymous homepage and checks the PHP `Cache-Status` header; absence of that header does not prove that static server or proxy caching failed. Queue counts do not count all cache files.

Use **Retry failed CSS jobs** for an immediate retry, or **Regenerate CSS and page cache** after stylesheet changes. **Pause CSS generation** stops new claims while current work may finish. **Pause preloading** preserves queued URLs; resume continues existing work and sitemap discovery when Preload Cache is enabled. Adaptive preload batches cap new batches at five URLs while a fresh worker report indicates CSS generation is running.

To disable local CSS, select **Original CSS** and save. Worker output reports job IDs and failure reasons; retain server cron output for troubleshooting. The dashboard sanitizes displayed failures. Worker resource samples cover the Node.js process, not total Chromium or host load.

The WordPress ZIP excludes node_modules and browser binaries. Rerun npm ci after installing a plugin update, then restart any persistent worker. The inherited cloud CSS options remain disabled.

Compare performance on the same staging snapshot and settings before choosing a mode. Repository operators can use `tools/BENCHMARKS.md` for local benchmarks, server-load sampling and production checks; these development tools are outside the WordPress ZIP. Cache-Cache's local diagnostics do not enable Performance monitoring or any remote monitoring account.

=== Cache-Cache ===
Contributors: noobrocket
Tags: cache, performance, minify, lazyload
Requires at least: 5.8
Requires PHP: 7.4
Stable tag: 0.1.0-beta.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/old-licenses/gpl-2.0.html

Free WordPress caching and local performance optimization. No account or activation key required.

== Description ==

Cache-Cache is a standalone beta plugin with page caching, preload, asset optimization, lazy loading, database maintenance, Heartbeat controls, and custom CDN rewriting. Bundled dependencies retain their license notices. A stable 1.0.0 has not been released.

Local critical CSS and used CSS generation are available with the bundled Node.js/Chromium worker on your server. Paid cloud generation, vendor CDN subscriptions, vendor performance monitoring, account management, telemetry, and vendor updates are disabled. Updates are installed manually using a Cache-Cache ZIP.

Optional PageSpeed Insights checks in Overview use your own Google API key. Enable the API in Google Cloud and follow the key setup link in the card. Manually check Mobile or Desktop Lighthouse scores for your public homepage. The server stores the key separately from optimization settings; exports and settings history exclude it. No key is bundled and no checks run automatically. Results are lab measurements, not real-user field data. Localhost and private sites cannot be checked.

Local diagnostics show cache delivery, preload/CSS queue status, worker reports and recent failures, with retry, regeneration and pause/resume controls. Optional local CSS supports browser-loaded imports, native nesting and validated redirects, with conservative retention and original CSS fallback. Same-server worker setup is documented in local-css/README.md.

== Installation ==

1. Deactivate other page cache plugins.
2. Upload the Cache-Cache beta ZIP, then activate Cache-Cache.
3. Open Settings > Cache-Cache and save your options.

Cache-Cache refuses to overwrite another cache plugin's advanced-cache.php. It uses its own settings, database tables and cache paths. The noobrocket plugin directory and technical identifiers are retained so existing installations can be replaced without creating a second plugin.

== Changelog ==

= 0.1.0-beta.2 =
* Prepared contributor guidance, portable verification and GitHub workflows.
* Removed unusable inherited Composer commands and automatic dependency-rewriting hooks; retained bundled runtime dependencies.
* This source version follows the frozen beta.1 package. No beta.2 release has been published.

= 0.1.0-beta.1 =
* First explicitly numbered Cache-Cache beta. Earlier 1.x development numbers below were beta identifiers, not stable releases.
* Updated package branding, worker metadata and translation catalogs; retained installed plugin identifiers and engine version 3.23.5.1.
* Included current cache, resource optimization, settings transaction and dashboard corrections from the maintained source.

= Historical beta 1.3.1 (unpackaged) =
* Grouped Flight Deck navigation, compact choices and advanced-rule disclosures improve every settings page in light and dark modes.
* Settings use one explicit save workflow, with unsaved-change protection and native validation across hidden pages.
* Database cleanup has a separate review step, preserves the native submission and requires confirmation before deletion.
* Updated local setup guidance, import/export scope, Asset Manager feedback and French translations.

= Historical beta 1.3.0 =
* Added native picture delivery and background AVIF/WebP generation with original fallbacks and one shared HTML cache.
* Added page asset rules, private preview, dependency preservation and reversible settings history inside NoobRocket.
* Added bounded background cache refresh, conditional WordPress asset cleanup and existing object-cache health checks.
* Rebuilt the admin interface around Flight Deck, with responsive layouts, light/dark appearance and translated controls.
* PHP cache hits now serve precompressed gzip bytes directly; cache writes publish complete files under per-variant locks.
* Fixed new-module autoloading, network uninstall cleanup, image regeneration and local CSS interoperability.
* Translations are maintained in PO catalogs and compiled into MO files.

= Historical beta 1.2.0 =
* Added local diagnostics for cache delivery, preload/CSS queues, worker health and failures, with retry, regeneration and pause/resume controls.
* Added one-worker-per-site leases, runtime dependency checks, job/time/delay bounds and coordinated adaptive preload batching.
* Added explicit multisite worker selection through --site-url with site-scoped jobs, leases and generated files.
* Fixed network activation of an already loaded plugin, new-site initialization before options tables exist, and Apache cache delivery on custom ports.
* Added conservative CSS imports/native nesting support, validated redirect chains, interactive state selectors and CSSOM mutation detection.
* Local CSS now preserves original delivery for personalized cookie variants and requests excluded from public caching.
* Local CSS cooperates with native Google font hosting; completed critical pages use cached local font CSS without retaining remote font-face URLs.
* Added repository tools for reproducible staging benchmarks, server-load sampling and production compatibility checks; remote Performance monitoring remains disabled.

= Historical beta 1.1.0 =
* Added opt-in local critical CSS and used CSS generation with a same-server Node.js/Chromium worker.
* Preserved stylesheet cascade order, original inline styles, conditional rules, dynamic states, and selector safelists.
* Added durable claims, retry leases, stale-result rejection, and cache/preload invalidation without cloud accounts.
* Original CSS remains active for pending, failed, unsupported or excluded pages; critical mode retains original CSS under enforcing CSP.

= Historical beta 1.0.2 =
* HTTPS cache detection now follows each preload URL, including cron and CLI callers.
* Term assignment/removal invalidates affected archives and old/new post URLs using existing preload paths.
* Restored WebP cache variant selection after the branding change, with inherited marker compatibility.
* Programmatic mobile cache settings now regenerate the early-cache loader, including CLI and Abilities saves.
* PHP cache hits expose the standard Cache-Status response header, including conditional responses.

= Historical beta 1.0.1 =
* Finished active product branding and removed paid support and license renewal links.
* Fixed JSON import, release compatibility-list refresh, preload health warnings, and unavailable cloud options in Abilities.
* Restored Cloudflare and GeotargetingWP external protocol names.
* Cache initialization now stops cleanly when a discovered configuration file disappears.
* Updated translation catalogs and French free edition guidance.

= Historical beta 1.0.0 =
* Free standalone edition with isolated plugin identity, data, cache paths, and translations.
* Removed account validation and disabled unsupported cloud services.
* Adapted settings dashboard and custom CDN controls.

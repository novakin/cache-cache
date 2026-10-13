# Operations and recovery

This guide owns diagnosis, server delivery and safe rollout/rollback. [Packaged help](../noobrocket/docs/index.html) owns user-facing settings help; [worker documentation](../noobrocket/local-css/README.md) owns local CSS installation, scheduling and worker recovery. [Benchmarks](../tools/BENCHMARKS.md) owns controlled performance measurements.

## Install and diagnose

Use an owned staging copy and backup before enabling optimizations. Deactivate other page-cache plugins first: WordPress has one shared `advanced-cache.php`. Install Cache-Cache under its preserved `noobrocket/` directory and open **Settings → Cache-Cache**. No activation key/account is needed. Cloud services stay disabled; optional PageSpeed uses the user's separate API key.

Start from the failing layer:

| Symptom | Inspect first | Evidence limit |
| --- | --- | --- |
| Page cache appears disabled | Active plugin, filesystem permissions, owned drop-in/config, exclusion and response headers | A setting or queue count does not prove delivered cache hits. |
| No PHP cache header | First anonymous homepage response, cookies/query, server static rules/proxy | Static delivery can work without PHP's `Cache-Status` header. |
| Preload remains pending | Saved enabled/paused state, cron/Action Scheduler, failure response and usable variants | Completed queue rows do not establish all-site coverage. |
| Local CSS stays original | Selected mode, worker `--check`, queue/lease state, failures and explicit asset origins | Original delivery is the intended unavailable/failed-result fallback. |
| Image generation remains queued | Cron and native GD/Imagick encoding support, per-batch progress | The toggle does not prove AVIF/WebP files exist or improve visual quality. |
| Save is slow | Actual request duration and synchronous callbacks, filesystem work and font preparation | Do not infer latency from admin markup size. Measure before refactoring. |

The dashboard's cache probe makes one anonymous homepage request and reports that response; it is not a whole-site benchmark or a purge-success test. The request may create page cache even when automatic preloading is off. A cold response without `Cache-Status: NoobRocket; hit` remains unconfirmed, rather than being reported as a MISS; a later separate check may confirm a HIT. Completed domain and homepage purge hooks discard the saved check, so Overview returns to Not checked instead of retaining an older HIT. This reset does not prove deletion succeeded. Object-cache diagnostics use the configured provider and an isolated temporary key, without a global flush. Provider action failure must remain distinct from an unsaved configuration or successful settings save.

The one-hour query-result TTL applies to newly written result keys; an upgrade does not retroactively expire older zero-TTL entries in a persistent object cache.

Database cleanup starts its transient-name keyset at the empty string; a numeric cursor can skip underscore-prefixed names under database collation. It counts stored transient values, excluding timeout companions and its own running/result state. Cleanup runs in the background; reload the Database page after completion to refresh its counts. WordPress and plugins can recreate temporary values immediately, so a later nonzero count does not establish deletion failure. With an external object cache, cleanup invalidates each selected cache key and removes its stored current-site timeout/value pair; an absent cache key or timeout is harmless, while SQL deletion errors remain failures. Arbitrary cache-only entries are not enumerated or flushed. Multisite cleanup preserves shared network transients and other sites' rows.

Before deleting an auto-draft, trashed post or spam/trashed comment, cleanup reads its current category directly from the site database. Objects that no longer match after a save, restore or approval, and objects already removed, are skipped without increasing deletion or failure counts; failed category reads remain failures and retain the object. The cursor advances across skips so later eligible items still run. Native deletion vetoes and revision handling remain in place. This check does not serialize concurrent editing: a change after the category read can still race with deletion, so avoid restoring or editing selected content during destructive cleanup.

## Apache, Nginx and stale refresh

PHP cache delivery evaluates generated site policy. Apache may use generated direct HTML rules; Nginx needs explicitly maintained server configuration matching the effective site/cache paths and exclusions. Do not blindly reuse another site's Rocket rules. Verify authenticated, query, cookie, mobile and HTTPS behavior against representative routes. Protect config/PHP files from public static exposure; public generated CSS/images have separate delivery needs.

PHP-emitted enforcing and report-only CSP headers exclude that response from page-cache publication because HTML files and early cache hits cannot replay headers sent by WordPress. The separate optimizers retain their existing CSP fallbacks. Purge existing page cache after deploying this admission correction; already published files can otherwise bypass the new gate. Verify repeated anonymous responses retain the policy and create no page file, then verify an ordinary public page still caches. Server/CDN policies added after PHP require equivalent policy delivery on both cached and uncached responses; PHP cannot inspect them. Rolling back the code restores the previous admission behavior and needs another page-cache purge when PHP owns the policy.

When deploying the nested dynamic-cookie correction, regenerate the owned early configuration and clear the affected page cache. Existing configuration files still contain the older reduced declaration list until regeneration; newly restored cookie groups select distinct filenames. Verify requests that differ only in each declared group after deployment.

Optional stale refresh requires PHP page delivery. Disable direct page-cache HTML rewrites in Nginx before enabling it and keep the normal PHP handler/assets. Apache omits its direct HTML rewrite while enabled. Reverse proxies/CDNs must not serve stale HTML beyond the PHP cutoff. The supported policy requires automatic preload, nonzero lifespan, legacy WebP variants off and no mandatory/dynamic cache cookies; unsupported variants expire normally. Run WordPress cron regularly. Failed refresh retains the original timestamp, and delayed cron cannot extend the one-hour PHP grace.

Modern images use `<picture>` sources with original fallback; the same HTML serves AVIF/WebP-capable and other browsers. Accept-based HTML variants are unnecessary for this mode. Check theme selectors affected by a picture wrapper and complete responsive derivative families. Other tools' legacy WebP cache variants remain a separate compatibility path.

### Site-scoped Nginx AVIF caching

Check direct derivative responses as well as image generation. A media location that matches WebP but omits AVIF can serve a correct `image/avif` response without `Cache-Control` or `Expires`. Browser caching is a server-delivery concern; changing the plugin's optimization settings does not repair that location.

For the Noobunbox vhost, `/etc/nginx/sites-enabled/01.www.noobunbox.net` includes its existing media rules before `common/php8.5.conf`. Keep the PNG/JPEG sidecar negotiation and WebP location unchanged. Add the following site-local block **after** that PHP/security include, before the server's closing brace:

```nginx
location ~* \.avif$ {
    try_files $uri $uri/ /index.php?$args;
    etag on;
    expires 90d;
    include /etc/nginx/rocket-nginx/conf.d/default/global.*.conf;
    include /etc/nginx/rocket-nginx/conf.d/default/media.*.conf;
}
```

This example uses that site's existing security-header includes and front-controller fallback. Nginx takes the first matching regex location: placing AVIF after the hidden-path denials preserves those denials, while `try_files` preserves missing-file routing. Appending `avif` to the earlier media matcher would change both behaviors. Confirm that the effective `mime.types` includes `image/avif avif;`; adapt paths and fallback to another site's own configuration rather than installing this example globally. Record the site owner and this local amendment before regenerating any inherited Rocket-Nginx configuration.

Before an authorized deployment, record the resolved vhost path, ownership/mode and original checksum; retain an exact private copy outside the public document root. Prove the location order on an owned disposable Nginx listener with existing hidden-path files, missing AVIF paths with query strings, direct PNG/WebP files, PNG/JPEG sidecar negotiation and an unaffected sibling vhost. A front-controller stub proves Nginx routing, not native WordPress execution.

Apply only the reviewed vhost change. Run `nginx -t` before reloading Nginx; a failed syntax check must restore the original file without reloading the candidate. After reload, verify an existing AVIF returns 200, `Content-Type: image/avif`, `Cache-Control: max-age=7776000`, an `Expires` header and an ETag; an `If-None-Match` request should return 304. Verify security headers, hidden-path denial, missing-file fallback and unchanged PNG/WebP/negotiation responses against the pre-change evidence. No plugin settings save, worker run or page-cache purge is required for this response-header change.

For rollback, first confirm the live vhost still matches the deployed candidate, then restore the exact owned original file, run `nginx -t` and reload Nginx. Recheck the original headers and routing. If a write/reload reply is lost, reconnect and inspect the source checksum and runtime responses before retrying; do not overwrite a concurrent edit. Longer asset caching helps repeat delivery, but a cold-load LCP or Speed Index improvement still needs matched measurements.

Image generation needs PHP GD or Imagick with AVIF/WebP encoding support, writable uploads and WordPress cron. Node.js, npm and Chromium are CSS-worker requirements, not image requirements. The image dashboard reports queued discovery and media items, last batch and historical successful encodes; encoder availability alone does not establish generated files. A scheduled batch over five minutes late warrants checking WordPress cron. Traffic-driven cron can be delayed on quiet sites; use the host's regular WordPress cron setup for reliable progress.

Local CSS uses a separate Node.js/Chromium command. A passed `--check` records environment readiness but neither starts generation nor installs a schedule. For queued pages with no recent active worker report, check the actual process and service-account cron command in the [worker guide](../noobrocket/local-css/README.md). Off or paused generation shows worker history without an active setup warning; original styles remain available while results are pending.

Automatic font preloading needs a completed nonempty hint row for the page and device variant. Enabling the option alone does not immediately add font links. A successful new collection invalidates that public page's existing anonymous cache files through the native nonrecursive purge owner, retaining the collected hints, authenticated buckets, child pages and unrelated caches; the next page render can emit its preloads. Duplicate, empty, failed or disabled collections do not trigger this invalidation. The shared guest nonce does not permit invalidation of foreign/sibling sites, query/action URLs, rejected cache paths or private/password-protected posts. Existing hint rows collected by an older release can require an authorized single-page cache refresh after upgrade. Font-hosting changes clear obsolete hint URLs for recollection.

Font collection retains each loaded face's URL, style and weight. Hosted families no longer reuse their first loaded URL for every face; visible regular text must not inherit an unrelated italic font preload. Existing completed hints are not rewritten by a source update. Use the normal owned website-cache clear and an eligible ordinary visit to recollect incorrect hints; refreshing only HTML publishes the existing hint list. This changes collection and priority, not the theme's font families or appearance, and does not establish a PageSpeed improvement.

The shared beacon script URL includes the selected asset's modification time, with the product version as fallback. Changed readable or minified runtime bytes therefore receive a new browser-cache URL after deployment. An owned HTML cache refresh is still required to publish that URL to cached pages; do not remove unrelated assets or replay old settings.

The shared beacon intentionally samples mobile viewports no wider than 393 pixels and no taller than 830 pixels. A 412-pixel Lighthouse visit can use already collected mobile hints but cannot collect them itself; use an ordinary eligible mobile visitor when preparing that variant. Preserve the sampling limits when measuring another viewport, compare matching delivered HTML/cache state, and measure Speed Index and layout shifts before keeping a font-loading change.

## Rollout

1. Record plugin/product engine versions, saved configuration, delivery mode and a staging backup. Exported settings/history exclude credentials; preserve those through the site's secure backup mechanism.
2. Validate the immutable ZIP/checksum and update staging under the same plugin path. Product updates purge obsolete page/minification caches while preserving choices; engine changes may perform separate migrations.
3. Check anonymous cached/bypassed responses, representative interactions, active integrations and affected themes/locales. Start optional worker/image/browser optimizations conservatively and inspect resource usage/failures.
4. Deploy only when authorized. Reinstall worker dependencies with `npm ci` after updating plugin code and restart persistent workers. Record final version, proof and any server configuration change.

## Recovery boundaries

The public `noobrocket_clean_domain()` helper is exposed before plugin initialization completes. Calls made before its API and language dependencies load return `false` without deleting files or firing purge hooks. Call after initialization to perform the purge; early calls are not queued. Ready initialization/upgrader paths remain supported before the final `noobrocket_loaded` hook, and importing and repeated-purge guards retain their behavior.

URL cache purge resolves selected entries and wildcard search directories within each owned host/user bucket. Paths resolving outside that bucket are rejected; recursive deletion unlinks directory links without visiting their targets. Content, archive and Performance Hints invalidation applies the same boundary to the selected directory, its files and pagination. Purge paths follow native writer naming: literal dot segments are normalized, Unicode names are encoded, percent escapes retain their meaning and hexadecimal case is canonicalized. This preserves escaped spaces, slashes and double-encoded paths rather than selecting a different decoded directory. Per-URL public-helper success requires an actual deletion and no refused selected variant. Missing cache and refused deletion both retain the existing false result and Ability error response. Public purge hooks, Unicode cache naming and valid wildcard selection remain supported. These checks do not lock directories against concurrent filesystem replacement.

Preload discovery upgrades its table with a nullable fixed URL key and unique index. Existing row IDs, statuses, locks, access times and duplicates stay intact; no full-table data backfill runs. One-time DDL can wait for active database work and build the index; verify its duration on staging for unusually large queues. A failed or partial migration retains the prior version marker and refuses unprotected new discovery until the normal upgrade hook retries successfully. Existing rows remain readable. Code rollback can leave the additive column/index in place: older readers and `add_item()` callers omit the nullable key and remain compatible, but can again insert duplicates. Concurrent old-release requests are outside the new discovery guard. Completion still recovers those rows; do not delete the table as a recovery shortcut.

| Operation | Restores | Does not restore |
| --- | --- | --- |
| Replace plugin with a prior verified beta | Plugin code and bundled assets | Deleted data, provider-side state or necessarily migrated schema. |
| Settings history/import | Reviewed allowlisted configuration/rules | Credentials, site keys, database cleanup, schema or a deleted attachment. |
| Site/database backup recovery | Data/files covered by the backup at that point | Later edits absent from the backup or external provider state. |

For a CSS regression, select **Original CSS**, save and purge affected page cache; pause the worker while diagnosing. Failed/pending jobs already preserve originals. For image-layout regression, disable modern delivery and purge pages so original markup is served; keep derivative files while cached pages may reference them. Do not remove original media or another tool's derivatives.

For code rollback, retain a verified prior package and backup, pause generation/preload if relevant, replace code under `noobrocket/`, and use owned purge/regeneration controls. Product-version changes invalidate `pages/` and `min/` through the updater; they do not promise to reverse engine/schema migrations or delete all generated font/CSS/image outputs. Disable the affected optimizer before regenerating its owned results. Purge corresponding proxy/CDN caches through the site's authorized operator path.

Stylesheet-background publication uses separate source-query pointers and immutable CSS files; legacy array-only mapping records are left untouched. Older code can still read its own records after code rollback. Keep generated revisions while cached HTML references them. Use the normal owned domain purge to clear generated background CSS and affected HTML together when disabling, regenerating or rolling back this optimizer; do not manually remove individual revisions from a live cache. Same-URL stylesheet changes require this invalidation path rather than a content scan on every page render.

Deactivation preserves settings and relinquishes owned early-cache configuration. Uninstall removes settings/tables/cache and is not rollback. It verifies each directory against its resolved parent before descending, including configured cache/configuration roots, and preserves redirected directory entries such as symlinks and Windows junctions. Their targets remain untouched; preserved entries and their parent directories can remain after uninstall. If another plugin owns `advanced-cache.php`, do not overwrite it manually to recover Cache-Cache. Inspect effective paths/ownership before cleanup; preserve independently active multisite sites and restore blog context after failures.

Deploying the multisite mobile-loader correction also requires regenerating the owned `advanced-cache.php` with `noobrocket_generate_advanced_cache_file()` in a fresh WordPress request. Replacing plugin source alone leaves the installed loader unchanged. Verify an anonymous mobile request uses the enabled site's mobile cache after a mobile-disabled sibling saves settings.

# Operations and recovery

This guide owns diagnosis, server delivery and safe rollout/rollback. [Packaged help](../noobrocket/docs/index.html) owns user-facing settings help; [worker documentation](../noobrocket/local-css/README.md) owns local CSS installation, scheduling and worker recovery. [Benchmarks](../tools/BENCHMARKS.md) owns controlled performance measurements.

## Install and diagnose

Use an owned staging copy and backup before enabling optimizations. Deactivate other page-cache plugins first: WordPress has one shared `advanced-cache.php`. Install Cache-Cache under its preserved `noobrocket/` directory and open **Settings → Cache-Cache**. No activation key/account is needed. Cloud services stay disabled; optional PageSpeed uses the user's separate API key.

Start from the failing layer:

| Symptom | Inspect first | Evidence limit |
| --- | --- | --- |
| Page cache appears disabled | Active plugin, filesystem permissions, owned drop-in/config, exclusion and response headers | A setting or queue count does not prove delivered cache hits. |
| No PHP cache header | Two anonymous homepage requests, cookies/query, server static rules/proxy | Static delivery can work without PHP's `Cache-Status` header. |
| Preload remains pending | Saved enabled/paused state, cron/Action Scheduler, failure response and usable variants | Completed queue rows do not establish all-site coverage. |
| Local CSS stays original | Selected mode, worker `--check`, queue/lease state, failures and explicit asset origins | Original delivery is the intended unavailable/failed-result fallback. |
| Image generation remains queued | Cron and native GD/Imagick encoding support, per-batch progress | The toggle does not prove AVIF/WebP files exist or improve visual quality. |
| Save is slow | Actual request duration and synchronous callbacks, filesystem work and font preparation | Do not infer latency from admin markup size. Measure before refactoring. |

The dashboard's cache probe checks anonymous homepage delivery; it is not a whole-site benchmark. Object-cache diagnostics use the configured provider and an isolated temporary key, without a global flush. Provider action failure must remain distinct from an unsaved configuration or successful settings save.

The one-hour query-result TTL applies to newly written result keys; an upgrade does not retroactively expire older zero-TTL entries in a persistent object cache.

Database cleanup starts its transient-name keyset at the empty string; a numeric cursor can skip underscore-prefixed names under database collation. It counts stored transient values, excluding timeout companions and its own running/result state. Cleanup runs in the background; reload the Database page after completion to refresh its counts. WordPress and plugins can recreate temporary values immediately, so a later nonzero count does not establish deletion failure. With an external object cache, cleanup invalidates each selected cache key and removes its stored current-site timeout/value pair; an absent cache key or timeout is harmless, while SQL deletion errors remain failures. Arbitrary cache-only entries are not enumerated or flushed. Multisite cleanup preserves shared network transients and other sites' rows.

Before deleting an auto-draft, trashed post or spam/trashed comment, cleanup reads its current category directly from the site database. Objects that no longer match after a save, restore or approval, and objects already removed, are skipped without increasing deletion or failure counts; failed category reads remain failures and retain the object. The cursor advances across skips so later eligible items still run. Native deletion vetoes and revision handling remain in place. This check does not serialize concurrent editing: a change after the category read can still race with deletion, so avoid restoring or editing selected content during destructive cleanup.

## Apache, Nginx and stale refresh

PHP cache delivery evaluates generated site policy. Apache may use generated direct HTML rules; Nginx needs explicitly maintained server configuration matching the effective site/cache paths and exclusions. Do not blindly reuse another site's Rocket rules. Verify authenticated, query, cookie, mobile and HTTPS behavior against representative routes. Protect config/PHP files from public static exposure; public generated CSS/images have separate delivery needs.

Optional stale refresh requires PHP page delivery. Disable direct page-cache HTML rewrites in Nginx before enabling it and keep the normal PHP handler/assets. Apache omits its direct HTML rewrite while enabled. Reverse proxies/CDNs must not serve stale HTML beyond the PHP cutoff. The supported policy requires automatic preload, nonzero lifespan, legacy WebP variants off and no mandatory/dynamic cache cookies; unsupported variants expire normally. Run WordPress cron regularly. Failed refresh retains the original timestamp, and delayed cron cannot extend the one-hour PHP grace.

Modern images use `<picture>` sources with original fallback; the same HTML serves AVIF/WebP-capable and other browsers. Accept-based HTML variants are unnecessary for this mode. Check theme selectors affected by a picture wrapper and complete responsive derivative families. Other tools' legacy WebP cache variants remain a separate compatibility path.

Image generation needs PHP GD or Imagick with AVIF/WebP encoding support, writable uploads and WordPress cron. Node.js, npm and Chromium are CSS-worker requirements, not image requirements. The image dashboard reports queued discovery and media items, last batch and historical successful encodes; encoder availability alone does not establish generated files. A scheduled batch over five minutes late warrants checking WordPress cron. Traffic-driven cron can be delayed on quiet sites; use the host's regular WordPress cron setup for reliable progress.

Local CSS uses a separate Node.js/Chromium command. A passed `--check` records environment readiness but neither starts generation nor installs a schedule. For queued pages with no recent active worker report, check the actual process and service-account cron command in the [worker guide](../noobrocket/local-css/README.md). Off or paused generation shows worker history without an active setup warning; original styles remain available while results are pending.

## Rollout

1. Record plugin/product engine versions, saved configuration, delivery mode and a staging backup. Exported settings/history exclude credentials; preserve those through the site's secure backup mechanism.
2. Validate the immutable ZIP/checksum and update staging under the same plugin path. Product updates purge obsolete page/minification caches while preserving choices; engine changes may perform separate migrations.
3. Check anonymous cached/bypassed responses, representative interactions, active integrations and affected themes/locales. Start optional worker/image/browser optimizations conservatively and inspect resource usage/failures.
4. Deploy only when authorized. Reinstall worker dependencies with `npm ci` after updating plugin code and restart persistent workers. Record final version, proof and any server configuration change.

## Recovery boundaries

URL cache purge resolves selected entries and wildcard search directories within each owned host/user bucket. Paths resolving outside that bucket are rejected; recursive deletion unlinks directory links without visiting their targets. Per-URL success requires an actual deletion and no refused selected variant. Missing cache and refused deletion both retain the existing false result and Ability error response. Public purge hooks, Unicode cache naming and valid wildcard selection remain supported. These checks do not lock directories against concurrent filesystem replacement.

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

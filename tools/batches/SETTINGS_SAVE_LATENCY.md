# Settings save latency — 2026-10-09

## Confirmed cause

An optimization change purges page cache and emits `noobrocket_options_changed`. The admin preload subscriber previously warmed the homepage within that request. Its verified HTTP request allows a 20-second timeout and then a 500 ms delay. Enabling preload could also execute initial discovery within the settings request; its homepage fallback allows a 10-second HTTP timeout.

The local preview used PHP's single-worker development server. It cannot answer its own homepage request while handling the settings POST. Moving warm-up to a queue removes that work from the save callback, but a background runner can still occupy that single worker and delay the redirected admin GET.

The current preview has Cloudflare and Sucuri synchronization disabled and a small page-cache tree. Provider HTTP and a large page-cache purge therefore do not explain this local failure. History lock expiry is not a waiting period.

## Source change

- `Preload/Admin/Settings.php` queues homepage warm-up and domain-change discovery through existing Action Scheduler actions.
- `Preload/Subscriber.php` queues initial discovery when preload becomes enabled.
- The admin settings owner reads the current saved preload option through its existing filtered helper. Mixed saves that disable preload and change optimization settings cannot admit new warm-up from a stale option snapshot.
- Service registration drops the unused synchronous controllers and option snapshot from this owner.

Pause/disable behavior, queue deduplication, discovery ownership and worker response/publication verification remain intact. Workers still perform real HTTP; that work belongs outside the settings save callback. Filesystem invalidation and configuration writes remain synchronous. Enabled external provider purges can also add their own latency.

## Evidence

- [Settings-facing preload fixture](../../tests/wordpress-settings-preload.php): 21 assertions, including native events, after-save callback, service registration, callback priorities, option filters, zero preload HTTP and a sleep trap.
- Existing preload outcome fixture: 32 assertions, preserving worker publication/discovery/failure behavior.
- Four changed/new PHP files parse successfully.
- [Isolated single-worker transport proof](../../.artifacts/settings-save-20261009/loopback-proof.json): direct request 3.4 ms; self-request timed out after 1,515 ms against a controlled 1,500 ms deadline. It loads no WordPress and changes no shared site state.
- [Native panel interaction proof](../../.artifacts/settings-save-20261009/ui-interaction-proof.json): 89 checks across eight width/theme states after the constructor/service changes; settings writes were blocked.
- Independent read-only review found no actionable regression in the changed preload owners.

These checks establish callback behavior and the single-worker transport failure. They do not measure the user's complete before/after settings POST, redirected GET or production-server save latency.

## Concurrent local preview

The preview now runs standard Nginx FastCGI routing with four existing PHP-CGI 8.3.35 workers. Its origin, source junction, PHP INI, SQLite database and browser cookies stay the same. All listeners bind to loopback. No Windows service or database migration was added.

The [isolated runtime proof](../../.artifacts/concurrent-preview/probe-f6be90cfb23f4dd7b493ce0fab1fb851/proof.json) confirms distinct PHP processes, both SQLite extensions, static/PHP/front-controller routes and missing-PHP 404. A nested loopback completed in 1.8 ms; an independent request completed in 2 ms while another worker slept for two seconds. Those values describe the tiny transport fixture, not WordPress save latency.

The original PID 9504, executable, command line and port ownership were checked before stopping that preview. The replacement serves the maintained plugin asset and the real WordPress homepage with HTTP 200. A second [89-check native panel run](../../.artifacts/settings-save-20261009/concurrent-ui-interaction-proof.json) passes on the new runtime. Nginx and PHP-CGI error logs are empty after these checks.

[Cleanup proof](../../.artifacts/settings-save-20261009/runtime-preservation.json) confirms exact protected settings/history/draft/domain rows, locales, private metadata and all pre-existing sessions. Only the owned audit session and write-blocking fixture were removed; no baseline was restored.

[Start, stop, rollback and download provenance](../../.artifacts/concurrent-preview/README.md) belong to this local preview. PHP-CGI workers have no process supervisor; fatal worker failures require stop/start. Four workers remove the demonstrated single-worker loopback stall but do not guarantee latency under arbitrary workload. SQLite remains a development database. No package, commit, push or live deployment was performed.

Additional receipts: `20261009-233542-553-3f5325011d624ed297d3dab24c775c5d.log` (isolated concurrent routing), `20261009-233624-110-a5549ea4e2854aa0961df1d3a7db93bc.log` (coordinated preview switch output), `20261009-233702-638-4c0bca9179c9469fb8cb0bd059f14838.log` (native panel on concurrent preview) and `20261009-233808-952-cdb42c69dacf49d784ea5afd82389c0e.log` (exact preservation cleanup).

The first switch launcher held inherited standard handles after the servers started. Only that verified, completed-switch launcher was stopped; the working preview stayed running. The helper now uses native hidden Windows detached launch and each daemon's native error log. An [isolated launcher proof](../../.artifacts/concurrent-preview/launcher-885594e3784e4b28bc357ce3f5c844f3/launcher-proof.json) confirms the quiet start command returns in 1.281 seconds with all five listeners alive and unlimited CGI requests; a separate stop removes only owned processes. The caller's original CGI environment value is restored. Receipt: `20261009-234059-592-204216cb051c408c96a482ad7ddd1ad2.log`.

## Follow-up: two pending URLs

The dashboard counts stored URL statuses, not Action Scheduler actions. At 21:49 UTC, two local, unlocked URLs were pending while Action Scheduler had no queued/running preload actions. The recurring `noobrocket_preload_process_pending` admission event was approximately 15.8 hours overdue. The preview's `DISABLE_WP_CRON=true` prevented ordinary WordPress requests from spawning it; an async action runner cannot admit URL rows on its own.

The artifact-only WordPress config now sets `DISABLE_WP_CRON=false`. This restores normal request-driven WordPress cron without a new scheduler or plugin setting. Read-only preflight found no old trash/auto-draft deletion candidates, completed privacy requests or privacy-export files. Existing HTTP-audit ownership still blocks external requests. Native overdue maintenance hooks can now resume alongside caching.

An uncached WordPress GET triggered normal cron. The recurring admission timestamp advanced; two native URL actions completed. At 21:52:05 UTC the inventory had three completed URLs and zero pending, running or failed URLs, with no active claims or recent preload scheduler errors. The independent [read-only recovery snapshot](../../.artifacts/settings-save-20261009/queue-recovery.json) confirms the same counts at 21:52:49 UTC. Nginx and CGI error logs remained empty. No URL rows were manually marked complete or deleted to change the counts.

Cron remains request-driven: the preview must be running and receive an uncached WordPress request. A fully cached homepage can bypass WordPress bootstrap; an ordinary admin/login request permits a tick. This local correction is not a production cron configuration recommendation. To restore the former local behavior, change only `DISABLE_WP_CRON` back to `true`; pending admission will then require an existing external cron runner.

Quiet command receipts remain under `C:/Users/Guillaume/.codex/logs/commands`: `20261009-233012-507-866dee861bc14eb6bbe269757906c396.log` (21 settings assertions), `20261009-232940-101-81c7ca1cbb8d448f8f3f2ca17f01fe3a.log` (32 preload outcomes), `20261009-233053-216-74405afed87c4a858c509e1c4b554415.log` (syntax), `20261009-232610-245-99433d29773d499e9057cf0d40fdcadd.log` (isolated transport) and `20261009-233146-323-75db476f518a408aa224b3ae0c2a0161.log` (native panel).

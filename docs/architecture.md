# Architecture and ownership

Cache-Cache uses the inherited WordPress hook/subscriber/container architecture. [Plugin.php](../noobrocket/inc/Plugin.php) registers service providers and activates subscribers through [Event_Manager](../noobrocket/inc/classes/event-management/class-event-manager.php). Trace registration, not filenames alone: some inherited cloud/license/monitoring classes remain present while their service paths are disabled. No new provider, context or interface is required merely because a feature is added.

The unused SaaS admin provider is no longer registered. Retained legacy services have concrete callers: settings and local CDN still depend on License `User`/`UserClient`; BYO CDN subscribers and its driver factory still use HostedCDN query/subscription services; local Beacon uses Support data, while preload uses its mobile detector. `TrackingTrait` remains used by Abilities/CDN and emits a public integration hook. The vendor sender subscriber is not activated, analytics stays disabled by the free-edition policy, and retained files alone do not establish remote requests. Removing these shared services requires an explicit compatibility refactor rather than deleting directories by name.

## Request and page-cache path

```mermaid
flowchart TD
  Request[Anonymous page request] --> Loader[WordPress advanced-cache.php]
  Loader --> Config[Per-site early configuration]
  Config --> Eligibility[Early request eligibility and variants]
  Eligibility --> Hit[Complete eligible disk cache hit]
  Eligibility --> WP[WordPress renders on bypass or miss]
  WP --> Buffer[Optimization and response admission]
  Buffer --> Files[Complete owned cache-file publication]
  Purge[Content/settings/manual invalidation] --> Files
  Purge --> Preload[Existing preload queue]
  Preload --> Request
```

| Responsibility | Maintained owner | Contract |
| --- | --- | --- |
| Bootstrap, product constants and activation | `noobrocket/noobrocket.php`, `inc/Plugin.php`, `inc/functions/` | Preserve plugin path, data identifiers and shared drop-in ownership. |
| Early loader and per-site configuration | `inc/Engine/Cache/AdvancedCache.php`, `Config/`, `inc/classes/Buffer/class-config.php` | Only an explicitly owned `advanced-cache.php` may be replaced. Early config is executable policy. Shared-root cleanup enumerates every site across networks, restores switched blog context in `finally` and stops when the directory listing is unavailable. |
| Early eligibility, response admission, variants and publication | `inc/classes/Buffer/class-tests.php`, `class-cache.php`, `class-abstract-buffer.php` | Public anonymous responses only; complete files, scheme/device/cookie/query rules and privacy headers remain authoritative. |
| URL/content invalidation | `inc/Engine/Cache/Purge.php`, `UrlValidation/`, `inc/functions/files.php` | Purge affected archives/pagination and old/new URLs; preserve unrelated cache owners/sites. |
| Expiry and optional stale refresh | `inc/Engine/Cache/PurgeExpired/` and early buffer policy | Full-tree scanning remains proportional to owned files; appending deleted paths avoids repeated accumulated-list copies. Purge hook paths remain unique. PHP enforces the stale hard cutoff; direct static HTML cannot. |
| Preload discovery, admission and completion | `inc/Engine/Preload/Frontend/`, `Controller/`, `Database/`, `Cron/` | Native queue normalization, pending/running deduplication and usable required variants determine completion. Activation queues work. |

The early hit bypasses normal plugin boot. PHP hits expose `Cache-Status: NoobRocket; hit`; static Apache/Nginx delivery uses server rules and does not execute that header path. Publication locks prevent competing file writers from publishing partial output; they do not serialize all cold WordPress renders.

Preload admission computes an adaptive total target within the configured maximum; without duration evidence the default target is five. Native database rows retain that total-target contract, while pending/running Action Scheduler work independently limits available slots. Matching active work consumes capacity once. Load-control caps, stale-row recovery and the existing request grace remain authoritative even when no new work can be admitted.

Discovery methods share one query-owner implementation and supply a SHA-256 key protected by a native unique index. A full `varchar(2000)` URL cannot fit older utf8mb4 unique-index limits; the fixed ASCII key indexes every normalized URL byte without a prefix collision. Insert conflicts re-read the exact URL: update discovery uses the winning row, while create-only discovery retains its existing false-on-already-present contract. A key collision without an exact URL match fails without changing another row. Unfinished schema migration refuses new discovery; successful migration records readiness only after the key exists.

The nullable key leaves installed rows, including old duplicates, untouched. Normal discovery finds those rows before attempting insertion; completion and deletion continue to visit every matching row beyond BerlinDB's default 100-row limit. Membership bypasses stale cached ID lists and checks exact URL bytes after the indexed database prefilter, preserving case-sensitive and accented paths. Update discovery writes only supplied status/lock/access fields instead of restoring cached values. Completion refreshes the modification time; an uncached scalar check distinguishes identical stored completion from a failed native write. Update/deletion failures do not prevent later matching rows from being attempted. Required cache variants still gate completion. Direct `add_item()` integrations and older readers may still insert unkeyed rows; this is the maintained discovery invariant, not a destructive legacy-row consolidation or a change from task counts to unique-page counts.

## Settings and admin transactions

```mermaid
flowchart LR
  Form[Native settings form] --> Validation[Shared settings validation]
  API[CLI / Abilities callers] --> Validation
  Import[Reviewed import proposal] --> Validation
  Validation --> Settings[noobrocket_settings]
  Settings --> History[Bounded allowlisted snapshots]
  Settings --> Policy[Cache policy and invalidation]
  Draft[Administrator private asset draft] --> Publish[Revision-checked publication]
  Publish --> Rules[noobrocket_asset_rules]
  Rules --> History
```

`inc/Engine/Admin/Settings/Page.php`, `Settings.php`, `Render.php` and views own native page structure and fields. `inc/admin/options.php` applies shared domain checks and save effects; `inc/admin/admin.php` owns staged import/review. Imports preserve site keys/version markers and reject stale proposals. `inc/Engine/Abilities/Options/` owns the supported option allowlist.

`inc/Engine/Optimization/Assets/Rules.php`, `Admin.php`, `Frontend.php` and `Snapshots.php` own dependency-safe asset rules, private inspection/drafts, publication and five-entry settings history. Saved settings, private drafts and public asset publication are separate operations. History excludes credentials and restores allowlisted settings/rules; it cannot recover deleted database rows. Native locking and revision checks belong to those owners.

The shared settings JavaScript, admin styles, views and enqueues own Save/Cancel, navigation, appearance and feedback. [Design guidelines](../tools/DESIGN_GUIDELINES.md) are the sole design contract. [PageSpeed.php](../noobrocket/inc/Engine/Admin/PageSpeed.php) owns the optional manual external lab request and its separately stored key/results; lab scores are not cache coverage.

## Optimizers, workers and queues

| Area | Owner and boundary |
| --- | --- |
| Buffer transformations | `inc/Engine/Optimization/Buffer/`, `Minify/`, `DelayJS/`, `DeferJS/`; preserve original output when selected resources cannot be safely transformed. |
| Local critical/used CSS | `inc/Engine/Optimization/LocalCSS/Jobs.php`, `Frontend.php`, `Subscriber.php`, `Diagnostics.php`; existing per-site used-CSS table holds separately named local queues. Fingerprints/claims/leases reject stale publication. |
| Same-server CSS execution | `noobrocket/local-css/worker.mjs`, `generator.mjs` and PHP bridge; unprivileged Chromium samples public pages. Explicit origins constrain browser requests. See [worker setup](../noobrocket/local-css/README.md). |
| Modern images | `inc/Engine/Media/Picture/Generator.php` and `Subscriber.php`; bounded cron generation with owned outputs and AVIF/WebP/original picture delivery. Original files remain available. |
| Image/iframe lazy loading | `inc/Engine/Media/Lazyload/Subscriber.php` and `assets/js/lazyload-bootstrap.js`; original configuration/runtime delivery remains the default, with explicit opt-in to start the filtered async runtime from actual DOM targets. |
| Stylesheet background lazy loading | `inc/Engine/Media/Lazyload/CSS/Subscriber.php` and `Common/Cache/FilesystemCache.php`; publish immutable revisioned CSS before its source-specific mapping pointer. Hits reuse the published URL without hashing the CSS again. |
| Font preparation | `inc/Engine/Media/Fonts/`; local font ownership stays with the font controller. Cold rendering schedules existing Action Scheduler work and retains provider delivery. Background preparation has a bounded per-sheet budget, two delayed retries and per-page hint/cache invalidation. |
| Next-page preparation | `inc/Engine/Preload/Links/` and its enqueued script; intent mode remains default. Optional native document rules admit finite exact destinations through the same exclusions, with conservative/moderate/eager browser hints. Footer JSON starts inert; browser policy/capability checks precede activation. |
| Providers and object cache | `inc/Addon/`, `inc/Engine/CDN/`, `inc/Engine/Cache/ObjectCache/`; saved authorized provider operations and existing-provider diagnostics, without installing a second object-cache drop-in. |

WordPress cron/Action Scheduler and existing native queues own scheduling. Local CSS has one lease per site; mutating fixtures run serially. Multisite workers explicitly select a site before accessing its queue/settings/files. Do not replace these paths with a general scheduler to solve a bounded defect.

Stylesheet-background generation hashes the completed CSS once. Same-directory temporary writes and atomic renames publish complete CSS, then a pointer containing the exact source URL, revision and mapping. Each source query variant has a separate pointer outside the legacy mapping filename. A render retains its selected revision so overlapping publishers cannot mix its CSS and custom-property mapping. Ready hits validate the pointer and readable, nonempty CSS; they do not reread or hash the full stylesheet. Same-URL source changes still need existing invalidation. Old revisions remain until owned domain purge because cached HTML can reference them. Generated URL mapping normalizes disk/content paths through WordPress before removing the content-directory prefix, including mixed Windows separators. A missing real head or unsuccessful publication preserves the original stylesheet; recovery tags use the shared real-tag boundary parser. See [operations](operations.md) for rollback.

Local CSS reuses the optimizer's structural tag walker for stylesheet/base selection and the real closing-head prerequisite. Comments, quoted attributes, raw-text content and nested templates cannot supply stylesheet links or base URLs. Explicit `noptimize` blocks remain excluded, and retained byte offsets preserve original nonselected markup during replacement. This prevents inert link bytes from creating a manifest the browser worker cannot satisfy; it adds no parser dependency or whole-page DOM serialization.

Document prerender rules retain native CSS predicates for target, download, nofollow and ancestor/provider opt-outs. URL components are bound literally through browser `URLPattern`, rather than translating the site's regular-expression exclusions into a different grammar. Relevant link/base mutations refresh destinations; unrelated DOM changes do not rescan anchors. Unavailable document-rule support falls back to the existing intent handler. Source script attributes and CSP nonce survive replacement. Browser fixtures prove parsing, admitted/excluded candidates and a trusted-interest request; Chromium's DevTools restriction prevents claiming completed document-mode activation. Detached intent activation is separately verified.

The footer emits the original bundled configuration and filtered runtime tag. The subscriber's buffer callback at priority 19, after media conversion at 18, retains that delivery unless `noobrocket_lazyload_gate_runtime` explicitly returns true; its default is false. Opted-in supported runtime markup is wrapped in an inert template followed by the bootstrap. Unbuffered output retains eager delivery. Enforcing HTTP CSP visible to PHP or meta CSP retains original delivery even when opted in. PHP cannot detect a policy added later by Nginx/CDN, so opt-in requires the site owner to ensure the final response policy permits the additional inline bootstrap; otherwise the inert runtime cannot start. This source feature adds no settings UI or live settings/deployment change.

When opted in, the bootstrap checks evaluated `window.lazyLoadOptions` selectors and containers after browser parsing, rather than counting footer targets before buffer conversion. Empty/eager-only pages retain one temporary child-list observer for the first AJAX or YouTube thumbnail target. It disconnects when the runtime is inserted; the unchanged library scans again after fetching and retains its existing instance observers. Native images, responsive pictures, inline backgrounds, iframes, exclusions and noscript delivery remain with their existing owners. Stylesheet background loading under `Media/Lazyload/CSS/` has a separate runtime.

Only a single empty-body async external tag without explicit type/defer is gated. Its parser-created script is cloned with filtered attributes preserved, avoiding Trusted Types HTML/URL assignment sinks. Inline, multiple, typed, deferred or non-async filtered markup, unavailable bootstrap assets and browsers without MutationObserver retain eager runtime insertion. Later delay/defer rewrites are published immediately so their owners can discover the script; an emptied template after combination needs no further insertion. Configuration and script-tag hooks keep their order; the readable bootstrap is inlined through the existing PHP minifier. On gated pages, the `LazyLoad` global and initialization events become available when targets first require the runtime. The existing background instance and its observers are not consolidated; that requires the dependency reconstruction scope in [development](development.md). No measured Speed Index improvement is established.

## Persistent ownership and recovery

Defaults are `wp-content/noobrocket-config/` and `wp-content/cache/noobrocket/` with `pages/`, `min/`, `busting/`, `critical-css/` and `used-css/` below the cache root. Constants allow existing configured paths; inspect the effective runtime before deleting anything. `noobrocket_settings`, asset rules, private user metadata and bounded history have distinct owners. Database tables retain the `nbr_` family and site prefix.

Product updates refresh compatibility lists and invalidate page/minification caches; engine migrations run when the independent engine marker changes. Deactivation preserves settings; uninstall is destructive and site/network-aware. [Operations](operations.md) distinguishes code rollback, settings restore and database recovery. [Source inventory](source-inventory.md) tracks confirmed open findings separately from historical fixes and verification limits.

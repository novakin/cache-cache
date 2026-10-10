# Source inventory and current boundaries

Date: 2026-10-10. Public product: Cache-Cache. Technical identifiers: `NoobRocket` / `noobrocket`.

## Curated repository inputs

| Location | Ownership | Treatment |
| --- | --- | --- |
| `noobrocket/inc/`, `views/` | Maintained WordPress engine, feature owners and templates | Commit intentional source. Extend the responsible owner before adding structure. |
| `noobrocket/assets/` | Maintained scripts/styles and served generated assets | Commit inputs and required generated outputs together. |
| `noobrocket/languages/` | POT/PO message catalogs and compiled MO | English gettext keys in source; translations in catalogs. |
| `noobrocket/vendor/`, `inc/Dependencies/`, `inc/classes/dependencies/` | Bundled runtime dependencies and prefixed distributions | Commit current runtime inputs. Dependency reconstruction is not yet a verified release pipeline. |
| `noobrocket/local-css/` | Same-server Node/Chromium worker and pinned npm manifests | Commit source and lock file; install `node_modules` separately. |
| `noobrocket/docs/` | Packaged user help | Ship with plugin; keep developer process guidance outside this directory. |
| `tests/` | Maintained fixtures and explicit verification catalog | Commit tests/helpers; isolate mutation and generated evidence. |
| `tools/` | Build/check tools, maintained generators, dated reviews and proof summaries | Commit intentional scripts/docs; exclude installed dependencies and private artifacts. |
| Root guides, `docs/`, `.github/` | Current contributor contract, operations, architecture and GitHub process | Commit current guidance and small workflows/templates. |

The curated import excludes `.artifacts/`, `dist/`, installed `node_modules`, private captures, credentials, cache/config output and duplicate reference distributions. Local copies remain intact. The beta ZIP is a release artifact rather than a source-control input.

`tools/prepare-github.py` creates a new checkout from upstream release `v3.23.5.1` and copies these inputs. It stages a reviewable migration; it does not commit or publish. The downloaded shallow checkout retains the release commit identity; the GitHub fork provides the existing upstream ancestry. Local history depth is not a claim that every historical blob was downloaded.

## Product findings and disposition

These findings distinguish resolved owner defects, retained dependencies and remaining work. Focused regressions establish their scope; none proves a production loading improvement.

| Finding | Current status | Responsible path / next proof |
| --- | --- | --- |
| Expiry scanning walks the owned cache tree; a historical 50k-URL Windows run took about 11–12 seconds. | Bookkeeping optimized; traversal remains proportional to files | Append expired paths without repeated array copying and report each directory once. Owned-file regressions cover variants, expiry/stale deadlines, deletion failure and publication races. Host timing remains variable; a faster live scan is not established. |
| Cold local-font preparation uses a synchronous request budget. | Resolved at the font owner | Existing Action Scheduler queues unique sheet/page preparation. Cold HTML keeps provider delivery; successful publication clears that page's font hints and HTML. Failed work has two bounded delayed retries. |
| Config cleanup retrieves site IDs with WordPress's default site-query limit. | Resolved | Configuration cleanup and shared stylesheet-background directory preservation enumerate all sites across networks. A modeled 107-site fixture covers sites above 100, ownership, unavailable listings and normal/exceptional blog restoration. |
| Combined JavaScript insertion matches lowercase `</body>` before removing originals. | Resolved | The insertion boundary recognizes real tags case-insensitively, ignoring comments, quoted attributes, raw text and nested templates. Unfinished tags/attributes consume EOF and cannot supply an insertion point. Missing body retains all original scripts. Actual optimizer/browser fixtures cover fallback, CSP and module boundaries. |
| Delay runtime insertion assumes a head element. | Resolved | Missing real head retains original executable scripts. Runtime insertion uses literal string placement, preserving dollar sequences; browser fixtures cover headless and enforcing CSP output. |
| Hosted font replacement can omit original link attributes. | Resolved | Linked delivery changes only href, preserving media, disabled, alternate and handler attributes. Conditional comments and integrity-constrained links remain original; only safe all-media links can inline. Cold or failed preparation preserves provider markup. |
| Dormant cloud/license/account/tracking classes remain in the distribution. | Caller assessment complete; shared dependencies retained | Unused SaaS admin provider registration removed. License, HostedCDN, TrackingTrait and Support retain concrete settings/CDN/Abilities/Beacon/preload callers. The sender subscriber remains inactive and analytics disabled. See the architecture caller boundaries. |
| Document speculation rules and eagerness selection are not exposed. | Deferred product proposal | Current handler dynamically prepares one destination after intent. Native document rules need separate acceptance and exclusions. |

The expiry measurement used 10,000 owned page directories with four desktop/mobile HTTPS plain/gzip variants, three Windows/PHP 8.3.35 samples per revision. Expired-scan medians were 8,959.027 ms before and 8,452.930 ms after, a 5.6% reduction. Filesystem work remains dominant; fresh-tree results varied, and no Linux, live-host or visitor loading gain is claimed.

The [latest full-system review](../tools/FULL_SYSTEM_REAUDIT.md) retains its dated reviewed behavior and limits. This inventory records the later cache/network and font fixes plus caller assessment; earlier audits retain their original findings and proofs.

## Focused loading review, 2026-10-10

Current image/iframe source preserves original configuration and runtime delivery by default. `noobrocket_lazyload_gate_runtime` permits explicit opt-in to the maintained DOM bootstrap only when the final response policy allows it, including CSP added downstream by Nginx/CDN. Opt-in delays `LazyLoad` globals/events until targets require the runtime; known enforcing PHP/meta CSP retains original delivery. Library/background instance observers remain unchanged; consolidation needs a separate dependency reconstruction scope. This source change does not alter live settings or deploy the plugin, and establishes no measured site-speed gain.

The noobunbox.net review used anonymous homepage/article loads at mobile and desktop sizes, fixed mobile network/CPU throttling, cold browser caches and actual public resource bytes. Six samples before and six after the narrow lazy-load patch returned HTTP 200 without browser exceptions or broken images after scrolling. Live lazy loading was disabled throughout; this rollout establishes compatibility, not a speed improvement. The installed enabled-path proof used native WordPress with request-local options and controlled body markup, then fetched the actual public runtime/images in Chromium. Saved settings remained unchanged.

| Priority | Confirmed mechanism | Smallest next action and proof |
| --- | --- | --- |
| 1 | Three stored font preloads use old local paths while hosted CSS uses current hashed paths. Byte-identical pairs duplicate 61,508 font body bytes per captured cold page. Installed/current font owners match. `Media/PreloadFonts/Frontend/Controller.php` emits stored URLs; `Media/Fonts/Filesystem.php` owns current font URLs. | Implemented hint invalidation on font CSS clearing, plugin upgrade and successful page preparation. Native WordPress proof checks both device rows and actual page-cache invalidation without a visitor filesystem scan. Existing cached HTML needs an authorized rollout/purge; live data was not changed. |
| 2 | The site's theme independently preloads the original PNG hero; Cache-Cache correctly preloads its AVIF picture source. Both responses contain identical AVIF content, duplicating 8,442 body bytes on the reviewed article. The theme's `inc/performance.php::noobunbox_cp_preload_featured_image()` owns the original preload. | Remove the theme's unconditional preload while retaining eager/high-priority hero markup. Verify responsive/format fallback and plugin-disabled delivery before a separate theme deployment. No picture synchronization regex change is needed. |
| 3 | The article's initial path contains five blocking stylesheets and blocking jQuery/Migrate, including comment styling. This is an active integration cost, not a proven Cache-Cache defect. | Rehearse existing dependency-aware JS deferral and local critical CSS on an equivalent disposable article. Verify comment submission, replies, validation, keyboard use and layout before changing live settings. |

The maintained stylesheet-background runtime now discovers initial targets immediately, coalesces relevant mutations, scans only unpublished mappings and disconnects after publication. Its readable and served bytes have browser regression proof and a reproducible generator. The bundled image lazy-load dependency remains unchanged. Neither that runtime nor cold-font preparation was demonstrated to delay the sampled live pages. Private captures, exact runtime receipts and rollback information remain local artifacts rather than curated repository inputs.

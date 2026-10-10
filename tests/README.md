# Verification

This is the current test recipe. [catalog.json](catalog.json) is the complete fixture inventory: profile, command, prerequisites, mutation scope and purpose. [Workflow acceptance](../docs/workflow-acceptance.md) records executed revisions/environments. [Historical verification](../docs/history/verification-through-20261010.md) preserves the former long README and beta.1 receipts; those outcomes do not prove the current source.

## Portable entry point

Run from the repository root. Python 3.10+ coordinates native tools; PHP CLI, Node.js 22+, a POSIX shell and Chromium are required only by the selected group. Use PHP 8.3 for broad development fixtures; selected product-floor lifecycle checks support PHP 7.4, while other fixtures use newer APIs. Install pinned generator dependencies first:

```sh
python -m pip install -r tools/requirements.txt
npm ci --prefix tools
python tools/check.py fast --list
python tools/check.py docs
python tools/check.py syntax
python tools/check.py fast
python tools/check.py generated
```

`--php PATH`, `--node PATH` and `--shell PATH` select executables; repeat `--fixture NAME` for focused fixtures in `fast`, `wordpress` or `browser`. Other profiles reject fixture selections. `PROFILE --list` prints that group's available names. Profiles fail on missing prerequisites/unknown selections; skipped or unavailable execution is not a pass. Output is bounded; keep detailed failure receipts private and sanitized summaries in acceptance records.

| Profile | Scope | Important prerequisites/boundaries |
| --- | --- | --- |
| `docs` | Local documentation links and repository configuration | Python; historical private receipt references are not public setup requirements. |
| `syntax` | Plugin/test/tool/CI PHP, JavaScript, Python and shell parsing, including shipped PHP dependencies | Applicable executables and `sh` (or `--shell PATH`); syntax does not replace static analysis or runtime tests. Workflow YAML was checked locally with actionlint; the current CI does not run that validator. |
| `fast` | Existing standalone regression fixtures using mocks/owned temporary files | PHP/Node as catalogued; no WordPress site, provider credentials or valuable runtime. |
| `generated` | Settings bundle and catalog consistency in a disposable copy | Pinned tool dependencies; validation does not rewrite the checkout. |
| `wordpress` | Native lifecycle/domain fixtures plus explicitly catalogued core-class models, serial per site | Native checks require a copied plugin, marked disposable site and separate database; focused `modeled-core` readers need a core copy without bootstrapping it. |
| `browser` | Chromium fixtures for settings/optimizer/worker behavior | `npm ci --prefix noobrocket/local-css`, explicit Chromium and any fixture-specific WP/PHP arguments. Synthetic form checks do not establish native page layout. |
| `server` | Owned Linux/MariaDB/Apache/Nginx/PHP-FPM/worker/multisite rehearsal | Linux dependencies, verified WordPress archive, unprivileged execution; owned service cleanup. |
| `package` | Existing ZIP structure/integrity and optional exact source equality | `--zip FILE`; `--compare-source` only for a ZIP intended to match the checkout. |

## Native WordPress safety

Install WordPress into an explicitly disposable root with its own database/site URL. Copy `noobrocket/` to `wp-content/plugins/noobrocket/`; a source junction is rejected. Create `.noobrocket-acceptance-site` only after confirming the installation may be mutated/destroyed. An existing localhost preview is not an acceptable lifecycle target.

```sh
python tools/check.py wordpress --wordpress /absolute/path/to/disposable-wordpress
```

The default group runs non-HTTP native activation/settings/update/branding proof serially, with activation first. Destructive phases such as `acceptance:deactivate`, `acceptance:uninstall` or `acceptance:collision` require explicit selection. The ordered destructive sequence is `acceptance:veto`, `acceptance:deactivate`, `acceptance:uninstall`, `acceptance:collision`, then `acceptance:restore`, following the initial activation phases. Restore removes only the exact owned `OtherCache` fixture contents, reactivates Cache-Cache and disables fixture CDN settings; it does not remove an unrelated drop-in. Inspect the catalog before selecting any phase.

The runner installs an owned temporary MU HTTP guard before WordPress boot and removes it after the sequence, including failures. It preserves synthetic fixture responses registered before the terminal `PHP_INT_MAX` guard and blocks real requests, including localhost, by default. Deliberate mocks use a lower priority, including `PHP_INT_MAX - 1`. A blocked request records a private marker without its URL; the completed check fails even when the fixture ignores `WP_Error`. Marker write failures fail immediately. Fixture HTTP guards disable Action Scheduler's automatic async loopback at shutdown; these checks exercise their own queue/worker actions without incidental dispatch. Explicit fixed-port HTTP scenarios first prove that port 8765 serves the selected copied site using a unique temporary probe file; only that verified origin is then permitted. A protected preview on that port fails the probe before site mutations. [wordpress-http-audit.php](wordpress-http-audit.php) remains optional diagnostic instrumentation that records destinations without query strings and allows localhost unless an explicit origin is configured; it does not replace the runner's stricter boundary. Its unexpected blocks also reach the runner's private marker. Do not install instrumentation into an existing site. Fixtures do not constitute provider-account testing.

HTTP/stale/local CSS fixtures additionally require the configured loopback server and pretty permalinks, plus any browser/worker argument listed in the catalog. Run all site-mutating checks serially. Parallel environments need distinct databases, paths and ports. Preserve owned failure logs; do not restore shared runtime rows to an old baseline.

## Browser and server proof

```sh
npm ci --prefix noobrocket/local-css
python tools/check.py browser --chromium /absolute/path/to/chromium --wordpress /absolute/path/to/wordpress-core
```

Keep Linux Chromium sandboxing enabled and run unprivileged. The default browser group needs an explicit WordPress core copy for native class readers without site bootstrap. Browser fixture prerequisites differ: some start synthetic loopback servers; others load WordPress classes or exercise the same-server generator. A focused fixture without `wordpress-core` can omit `--wordpress`. Select through the catalog instead of assuming every browser check needs a live site. [Worker documentation](../noobrocket/local-css/README.md) owns installation, origins, scheduling and recovery.

The `server` profile delegates to [linux-production-proof.sh](../tools/linux-production-proof.sh):

```sh
python tools/check.py server --wordpress-zip /path/to/verified-wordpress.zip --php /path/to/php --node /path/to/node --chromium /path/to/chromium
```

The Linux script reads `PHP_FPM` and optional `APACHE_MODULE_DIR` from the environment; the runner sets `PHP_CLI` from `--php`. It creates owned disposable MariaDB/site/server paths and cleans only owned processes. Each Apache/Nginx listener proves it serves that site's unique temporary file before the audit permits its exact loopback origin. The harness checks the audit's sanitized blocked-request marker before reporting success, so ignored errors in direct PHP fixtures or web requests fail the completed rehearsal. CI site setup also rejects blocked requests during installation. Both installers supply their chosen pretty-permalink structure through a scoped option filter during `wp_install`, remove the filter in `finally`, then save the real option; this avoids core's speculative loopback discovery before a listener is authorized. Real server permalink delivery is checked after origin verification. Repository CI uses verified WordPress archives and isolated jobs; production Nginx configuration is not proved by a synthetic Windows proxy.

## Focused lazy-load proof

```sh
python tools/check.py fast --fixture lazyload-bootstrap --php /path/to/php
python tools/check.py browser --fixture lazyload-bootstrap-browser --php /path/to/php --chromium /path/to/chromium
python tools/check.py wordpress --fixture wordpress-picture-delivery --wordpress /path/to/disposable-wordpress --php /path/to/php
python tools/check.py browser --fixture picture-delivery-browser --wordpress /path/to/disposable-wordpress --php /path/to/php --chromium /path/to/chromium
```

The bootstrap fixtures use actual footer/buffer subscriber output and shipped library bytes with modeled WordPress boundaries and intercepted browser responses. They do not bootstrap a site or contact providers. Default empty/target pages retain original HTML and work under a final CSP header added after PHP, including a later AJAX target. Optimization scenarios explicitly enable the default-false `noobrocket_lazyload_gate_runtime` filter; actual use requires final Nginx/CDN response policy permission for its inline bootstrap and accepts delayed `LazyLoad` globals/events. Known enforcing PHP/meta CSP still retains original delivery when opted in. The fixtures check opted-in empty/eager-only pages, dynamic targets, YouTube thumbnail timing, filtered configuration/runtime attributes, CSP/Trusted Types fallbacks, shipped DelayJS activation, fallback markup and bootstrap observer cleanup. Combine removal/relocation is modeled output; the fixture does not execute native combining. The existing native picture fixtures separately cover production responsive conversion, library activation and JavaScript-disabled fallback; both require a marked copied-plugin site and serial execution. These checks establish behavior and opted-in avoided runtime work, not a PageSpeed score or measured site-speed gain; no settings or deployment changes are performed.

## Meaningful proof and limits

For stylesheet-background publication, run `fast --fixture stylesheet-background-publication`, `browser --fixture stylesheet-background-publication-browser --php /path/to/php --chromium /path/to/chromium`, and `wordpress --fixture wordpress-stylesheet-background-publication --wordpress /path/to/disposable-wordpress --php /path/to/php` through `tools/check.py`. The modeled fixture executes actual publication owners with real owned files and injected write/interleaving failures. The browser fixture serves actual PHP output, generated CSS and runtime bytes at 1365×900 and 390×844, including repeated navigation and JavaScript-disabled recovery. The native fixture uses the registered extraction/transformation/tag pipeline on a marked copied-plugin site; it does not need a listener or real provider requests. Modeled interleaving is not simultaneous-process proof, and browser-cache reuse is not a measured whole-site speed improvement.

`local-css-stylesheet-boundaries` exercises actual local CSS selection and completed delivery with modeled WordPress/job storage and one owned temporary stylesheet. `local-css-stylesheet-boundaries-browser` supplies that owner's manifest to the actual used/critical generator at 1365×900 and 390×844 on an owned loopback listener. The checks cover inert template/raw-text/attribute bytes, real base/head boundaries, exact offsets and existing exclusions. They neither bootstrap WordPress nor establish generation on a production page or a site-loading gain.

Trace failures to native owners and add a regression that fails for the defect while preserving surrounding behavior. Test helpers/instrumentation are catalogued but are not automatically runnable tests. A modeled fixture can exercise real plugin classes without booting WordPress; label it accordingly. Do not advertise absent upstream PHPUnit/PHPCS/PHPStan suites or call parsing static analysis.

UI changes require real output review for affected light/dark, EN/FR, desktop/narrow and keyboard/error/dirty states using [design guidelines](../tools/DESIGN_GUIDELINES.md). Synthetic browser controls and automated accessibility checks do not prove screen-reader behavior or all production layouts. The current CI fixtures are one part of that acceptance.

Performance needs matched settings/environment and cold/hit state, sample count and variability; see [BENCHMARKS](../tools/BENCHMARKS.md). PHP 7.4/WordPress 5.8 and a recent compatible pair must execute before a passing matrix is claimed. Historical logs, a successful workflow file parse and an assigned QA task are not current runtime proof.

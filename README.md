# Cache-Cache

Cache-Cache is a free, standalone WordPress page-cache and performance plugin derived from the WP Rocket `3.23.5.1` engine. It needs no vendor account, activation key, subscription or license validation. It is still **beta**: the existing local package is `cache-cache-0.1.0-beta.1.zip`; development source advances to `0.1.0-beta.2`. Stable `1.0.0` has not been released. Product and engine versions are independent.

The maintained plugin lives in `noobrocket/`. The technical `NoobRocket` namespace, `noobrocket` directory/text domain, options, hooks and routes preserve installed settings and integrations. The WordPress panel displays **Cache-Cache** and uses the supplied brand assets. [Release rules](docs/releases.md) explain immutable packages, beta numbering and upstream intake.

## Features and boundaries

| Area | Available behavior |
| --- | --- |
| Page cache | Anonymous public-page disk caching, preload/purge, scheme/device variants, response privacy checks, complete file publication and optional PHP-enforced stale refresh. |
| CSS and JavaScript | Minification, JavaScript defer/delay and optional same-server critical/used CSS generation with conservative original delivery on unavailable/failed results. |
| Media and resources | Lazy loading, image dimensions, local fonts, opt-in native AVIF/WebP generation and `<picture>` delivery with original fallback. |
| Navigation | Hover-based link preloading and opt-in intent or native document Speculation Rules prerendering with public-route exclusions. |
| Site administration | Dependency-safe Asset Manager with private drafts/preview/publication, five-entry allowlisted settings history, reviewed JSON import/export, database cleanup and Heartbeat controls. |
| Delivery and diagnostics | Custom CDN hostnames, saved Cloudflare/Sucuri actions, existing object-cache diagnostics, worker/queue recovery and optional manual PageSpeed lab checks. |

Hosted CSS, Rocket Insights, vendor account/licensing, telemetry, remote rollback and automatic vendor updates remain disabled. No update server is configured. Some inherited inactive classes remain; their presence does not enable a service. PageSpeed is an optional separate API using the user's own key; keys are excluded from history/export and never returned to the browser. [Enable the API](https://console.cloud.google.com/apis/library/pagespeedonline.googleapis.com) and [create a key](https://console.cloud.google.com/apis/credentials), then configure it in Overview. Restrict it to the API and applicable server outbound IP; these manual scores are Lighthouse lab results, not field data or proof of comparative speed.

Local CSS requires Node.js 22+, PHP CLI, Chromium and scheduling permission on the WordPress server/VPS; ordinary PHP-only hosting cannot run it. [Worker setup](noobrocket/local-css/README.md) is canonical. Modern image encoding depends on host GD/Imagick support. Opt-in browser preparation spends bandwidth/memory and executes scripts before navigation; exclude private/action routes and verify visit-dependent scripts. Intent mode remains the default. Optional native document rules expose conservative, moderate and eager browser hints; source-visible rules start inert until browser capability, connection and destination checks allow activation.

## Install a beta

Declared requirements are **WordPress 5.8+** and **PHP 7.4+**. Check the [current verification record](docs/workflow-acceptance.md) for actually executed environments; the declared floor alone is not a passed matrix.

1. Back up the site and test on an owned staging copy.
2. Deactivate other page-cache plugins, including WP Rocket. WordPress has one `advanced-cache.php` slot; Cache-Cache refuses another owner's drop-in.
3. Upload the existing Cache-Cache beta ZIP through **Plugins → Add New → Upload Plugin**, then activate it.
4. Open **Settings → Cache-Cache**, choose optimizations and save. Configure optional server/worker dependencies before enabling their modes.

The package retains `noobrocket/noobrocket.php` to replace the installed plugin rather than create a second copy. Settings are stored in `noobrocket_settings`; default config/cache roots are `wp-content/noobrocket-config/` and `wp-content/cache/noobrocket/`. WP Rocket settings are not automatically imported. Integrations using upstream PHP classes or `rocket_*` hooks need adaptation to the preserved `NoobRocket`/`noobrocket_*` interface.

Deactivate before switching cache plugins. Deactivation preserves settings; uninstall removes owned settings/tables/cache and is destructive. History does not reverse database cleanup. [Operations and recovery](docs/operations.md) distinguishes code rollback, settings restore and database recovery, including PHP delivery requirements for stale refresh.

## Contributor guides

| Guide | Owns |
| --- | --- |
| [AGENTS.md](AGENTS.md) | Shared engineering/agent contract. |
| [CONTRIBUTING.md](CONTRIBUTING.md) | Issue, branch, proof, current PR acceptance and review process. |
| [Development](docs/development.md) | Clean checkout setup, commands, generated output and optional roles. |
| [Architecture](docs/architecture.md) and [source inventory](docs/source-inventory.md) | Actual owners, persistent contracts, maintained inputs and open findings. |
| [Tests](tests/README.md) and [fixture catalog](tests/catalog.json) | Executable verification, prerequisites, mutation scope and limits. |
| [Design guidelines](tools/DESIGN_GUIDELINES.md) | Sole UI/design contract. |
| [Operations](docs/operations.md), [worker guide](noobrocket/local-css/README.md), [packaged help](noobrocket/docs/index.html) | Operator diagnosis/recovery, worker setup and user settings help. |
| [Releases](docs/releases.md), [benchmarks](tools/BENCHMARKS.md), [workflow acceptance](docs/workflow-acceptance.md) | Beta/upstream process, performance evidence and current process proof. |
| [Historical evidence](docs/history/README.md) | Dated audits/receipts separated from current guidance. |

Start checks from the repository root:

```sh
python -m pip install -r tools/requirements.txt
npm ci --prefix tools
python tools/check.py docs
python tools/check.py fast
```

Bundled runtime dependencies are checked in. Do not run Composer install/update over them; [development](docs/development.md) explains ownership and reconstruction limits. Native/lifecycle fixtures use marked disposable sites, never a contributor's existing preview. Prior audit scores and local Lighthouse results do not establish that Cache-Cache is faster than WP Rocket.

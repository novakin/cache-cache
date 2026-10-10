# Development

This guide owns checkout setup, source/generated ownership and optional agent roles. [AGENTS.md](../AGENTS.md) owns the shared contract; [tests](../tests/README.md) owns fixture scope and prerequisites; [source inventory](source-inventory.md) separates maintained inputs from dependencies and private runtime data.

## Clean checkout setup

Work from the repository root. Use Python 3.10+, PHP 8.3 CLI for broad development fixtures, Node.js 22+, npm and a POSIX shell for shell parsing. PHP 7.4/WordPress 5.8 are declared product minimums; selected lifecycle fixtures support that pair, while some modeled fixtures use newer PHP/core APIs. A declaration is not proof that the current revision passes the pair. The [acceptance record](workflow-acceptance.md) owns actually executed environments. Chromium is needed for browser/local CSS work, not ordinary documentation edits.

```sh
python -m pip install -r tools/requirements.txt
npm ci --prefix tools
python tools/check.py docs
python tools/check.py syntax
python tools/check.py fast
python tools/check.py generated
```

Pass `--php /path/to/php`, `--node /path/to/node` or `--shell /path/to/sh` when executables are not on PATH. On Windows, Git for Windows supplies `sh.exe`, for example `--shell "C:/Program Files/Git/bin/sh.exe"`. `python tools/check.py fast --list` prints that profile's fixture catalog; substitute the needed profile. `--fixture NAME` can select a focused fixture within its profile. See `--help` for actual command arguments. A missing prerequisite fails visibly; it must not be called a pass.

Runtime PHP dependencies ship in `noobrocket/vendor/` and prefixed `noobrocket/inc/Dependencies/`. The existing manifest does not reproduce the complete prefixed distribution from a clean Composer download. Use the bundled files for this checkout. Do not run Composer install/update over them. Changes to dependencies require a separate reproducible reconstruction task, license review and package/runtime proof. Composer check aliases delegate to the repository runner; they do not supply missing upstream PHPUnit, PHPCS or PHPStan suites.

Install worker dependencies only for browser/local CSS work:

```sh
npm ci --prefix noobrocket/local-css
python tools/check.py browser --chromium /path/to/chromium --wordpress /absolute/path/to/wordpress-core
```

Keep Chromium's Linux sandbox enabled and run it unprivileged. The default browser group needs an explicit WordPress core copy for native class readers, but does not bootstrap that copy as a site. Individual fixtures without that prerequisite can omit `--wordpress`. For actual worker installation, scheduling and recovery, use [the canonical worker guide](../noobrocket/local-css/README.md).

## Native WordPress and server checks

Create a separate installation with its own database and site URL; copy `noobrocket/` to `wp-content/plugins/noobrocket/`. Do not use a source junction or an existing preview. Mark only an intentionally disposable installation with `.noobrocket-acceptance-site`. Fixtures may activate/deactivate/uninstall, save options, create posts/jobs/files or change roles. Backups do not make a valuable runtime an acceptable fixture target.

```sh
python tools/check.py wordpress --wordpress /absolute/path/to/disposable-wordpress
```

The default native group runs serially. Destructive lifecycle phases are explicitly selected; HTTP fixtures additionally need their documented listener/permalink setup. [The catalog](../tests/catalog.json) records each fixture's prerequisites and mutation scope. The [CI setup script](../.github/scripts/setup-wordpress.sh) copies the plugin, installs the HTTP boundary and bootstraps WordPress using the job-owned MariaDB service from [ci.yml](../.github/workflows/ci.yml). Its fixed disposable credentials are for that isolated service only; never point it at an existing database/server. The [archive downloader](../.github/scripts/download-wordpress.sh) verifies official checksums and needs a new destination directory.

Server rehearsal uses Linux with owned MariaDB/Apache/Nginx/PHP-FPM processes and a verified WordPress archive; [test setup](../tests/README.md) explains the entry point. `PHP_CLI`, `PHP_FPM` and optional `APACHE_MODULE_DIR` select the installed PHP binaries/module path for the server script. Nothing requires this workstation's `.artifacts/` directory.

The hosted server job uses Ubuntu 24.04 so PHP 8.3/FPM is available from the standard package archives. Minimum-version native checks remain on their separate runner; changing the server OS does not change the declared product floor.

## Maintained and generated ownership

| Input/owner | Output or rule |
| --- | --- |
| `noobrocket/inc/`, `views/`, `assets/css/`, readable `assets/js/` | Maintained plugin code and presentation. Trace actual enqueues before editing an asset. |
| `noobrocket/assets/js/noobrocket-settings.js` | `node tools/generate-settings-assets.mjs` regenerates its tracked `.min.js`; other assets follow their existing owner. |
| `noobrocket/assets/js/lazyload-css.js` | `node tools/generate-lazyload-css.mjs` regenerates its served `.min.js` using the existing pinned Terser tool dependency. |
| English gettext messages and `noobrocket/languages/*.po` | `python tools/update-translations.py` updates POT/MO and verifies placeholders/lookups; inspect PO changes. |
| `noobrocket/local-css/package.json` and lockfile | Pinned worker dependencies. `node_modules` and browser binaries are local-only. |
| Bundled `vendor/` and `inc/Dependencies/` | Shipped runtime inputs. Do not let implicit install scripts overwrite them. |
| `tools/build.py` | Installable ZIP with one `noobrocket/` root. Building a changed beta uses a new version; preserve published ZIPs. |

`check.py generated` runs generators in a disposable copy and compares the required tracked outputs. It does not repair source. Run the owning generator intentionally when inputs change, then inspect the diff. `check.py package --zip /path/to/package.zip` verifies an existing archive; add `--compare-source` only when that archive is meant to match the current source. An older immutable beta is allowed to differ from development.

Catalog text and the generated settings bundle use LF on every platform. The translation writer requests LF explicitly; `.gitattributes` keeps those generated files consistent even with Windows `core.autocrlf` enabled. Other imported source bytes are preserved. Compiled MO files remain binary and are verified separately; the checker never hides drift by normalizing outputs.

## CI selection and evidence

[CI](../.github/workflows/ci.yml) runs docs/syntax/fast/generated checks on every PR. The [scope selector](../.github/scripts/ci-scope.py) chooses WordPress, browser and server groups from the actual diff; infrastructure changes and explicit full rehearsal broaden the selection. The final **Required checks** aggregate evaluates each applicable result and records irrelevant heavy groups as skipped. Tokens are read-only; checkouts do not persist credentials, and ordinary jobs do not publish beta assets or deploy.

The WordPress matrix uses PHP 7.4/WordPress 5.8 for selected native acceptance/lifecycle proof, and PHP 8.3/WordPress 7.1.3 for the broader catalog. Full source/dependency syntax runs in the PHP 8.3 fast job. The floor job executes native plugin paths and their loaded dependencies; it does not parse every bundled library or run every modeled fixture on old core. Browser fixtures verify actual maintained script/style bytes with synthetic/native-class boundaries; affected real WordPress page sizes/themes/locales remain part of UI review. Linux server proof covers its owned MariaDB/Apache/Nginx/PHP-FPM/worker/multisite installation. Workflow configuration alone does not prove these jobs executed or that GitHub enforces a merge gate; see [current acceptance](workflow-acceptance.md).

## Optional roles

Use roles where another context improves the task, without a mandatory pipeline or fixed agent count.

| Role | Responsibility | Required output |
| --- | --- | --- |
| Investigator | Trace callers, registration, reproduction and invariants; diagnose the failing layer. | Relevant paths, evidence and the smallest responsible fix. |
| Implementation owner | Make the coherent change; update owning docs/artifacts and execute proof. | Intentional diff, actual checks and remaining limits. |
| Fresh reviewer/verifier | Inspect the final diff and affected behavior; use cache/UI expertise where relevant. | Reviewed revision, concrete findings, proof scope and verdict. |

Agent analysis is distinct from human approval. An author-account comment does not prove another person reviewed it. Material code/base changes require fresh affected proof/review. Keep one current acceptance record in the PR; [CONTRIBUTING.md](../CONTRIBUTING.md) owns the full recipe.

## Contract dry runs

For a wrong preload completion, trace `inc/Engine/Preload/Controller/PreloadUrl.php` (`is_already_cached`/`is_cache_file_ready`) and `Database/Queries/Cache.php` (`make_status_complete`) to native cache-file readiness and stored URL normalization. Select `python tools/check.py wordpress --fixture wordpress-preload-outcomes --wordpress /absolute/path/to/wordpress-core` before any browser suite. This focused fixture reads native core classes with modeled boundaries and no site bootstrap; a native HTTP regression needs the separate marked site. Check HTTP/HTTPS and device variants, case-sensitive paths and unavailable files. Do not invent a second queue.

For a narrow Cloudflare control defect, trace `inc/Engine/Admin/Settings/Page.php`, its rendered provider fields, `inc/admin/ui/enqueue.php` and the relevant shared rules in `assets/css/noobrocket-admin.css`/`noobrocket-settings.css`. Apply [design guidelines](../tools/DESIGN_GUIDELINES.md); inspect affected EN/FR light/dark desktop/narrow output and keyboard focus. A focused `settings-controls-browser` fixture verifies shared controls but does not replace native visual review. Use a disposable/synthetic proof, block provider actions and leave unrelated saved settings untouched. UI validation does not authorize deployment.

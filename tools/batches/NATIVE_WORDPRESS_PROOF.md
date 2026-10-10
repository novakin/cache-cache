# Batch native WordPress verification

## Result

The current maintained source passed **266 native WordPress assertions and 10 production preflight checks** on a new disposable Linux/MariaDB runtime. WordPress debug output remained empty. The owned database, Apache, Nginx and FPM processes stopped after verification. The shared Windows preview and its database/settings were not changed.

The first run exposed a genuine missing-row preload warning and stale Asset Manager fixture expectations. The preload owner now returns safely when discovery has no row. The Asset Manager fixture now checks the counted collapsed asset chooser, the compact unchanged draft with no live publication form, and separate required previews with unique IDs when publication changes exist. Existing duplicate, order, stale draft, explicit acknowledgement, credential exclusion and exact saved-state preservation checks remain.

## Runtime and inputs

- WSL Debian, native PHP 8.4.26, MariaDB 11.8.6, Apache, Nginx and PHP-FPM 8.4.
- Existing Node.js 22.23.3 and Chromium 154.0.8037.92. Chromium sandbox remained enabled.
- Existing official WordPress 7.1.3 core archive. Its SHA1 matched `https://wordpress.org/wordpress-7.1.3.zip.sha1`: `13eb1d042b1ca39224e44906cd84fe203112210c`.
- Maintained `noobrocket/` source copied with tar into a new native filesystem directory. No plugin package, build, installation of prerequisites, deployment or provider call was used.
- Disposable runtime: `/home/guillaume/noobrocket-proof/noobrocket-production.Nh1V9RJQ`. Its private MariaDB socket used `--skip-networking`; HTTP servers used only `127.0.0.1:8875`, serially.
- Existing worker dependencies came from `.artifacts/local-css-worker/package.json` through the runner's explicit fifth argument.

The runner was [linux-production-proof.sh](../linux-production-proof.sh), with an artifact-only insertion to run [wordpress-assets-ux.php](../../tests/wordpress-assets-ux.php). That inserted fixture copy replaced its hardcoded `127.0.0.1:8765` with the disposable runtime's `127.0.0.1:8875`. The maintained test's shared-preview guard was not broadened. Existing local CSS fixtures ran against the marked disposable root and its configured site URL.

## Checks

| Gate | Result |
| --- | --- |
| Native Apache public/private cache isolation | 17 assertions |
| Native Nginx public/private cache isolation | 17 assertions |
| Asset Manager/history rendering and normalization | 38 read-only assertions; saved settings, rules, history and private metadata exactly unchanged |
| Local CSS settings and ability normalization | 39 read-only assertions |
| Local CSS database jobs, rendering and invalidation | 126 assertions |
| Actual sandboxed Linux CSS worker | One job processed; zero failures; 3 publication assertions |
| WordPress network activation and site creation | 5 assertions |
| Network/main and subsite preparation | 6 assertions |
| Actual multisite queue/settings/worker isolation | 9 assertions |
| Invalid site selection preservation | 6 assertions |
| Single-site and multisite production preflight | 5/5 checks each |
| WordPress debug log | Zero bytes |
| Owned process cleanup | No matching runtime process remained |

Host sampling records the short idle and worker observations in the retained JSON files. These samples describe this fixture only; they are not a throughput benchmark or a performance comparison with another plugin.

## Evidence and limits

Quiet command log: `C:/Users/Guillaume/.codex/logs/commands/20261009-230611-759-263d9ce51a714794bd9bf3caca9c6686.log`. Retained local evidence: `.artifacts/batch-fixes-20261009/native-proof/{execution,apache-preflight,multisite-preflight,host-load-idle,host-load-worker}.json`. `execution.json` records exact matching SHA256 values for all eight tested provider, settings, history, rules and preload owners.

This proof covers the explicit disposable server configuration, actual native WordPress hooks/database and sandboxed worker. It does not establish every production Nginx/Apache configuration, installed WooCommerce behavior, live provider API compatibility or visual quality. Provider operations remain separately verified through intercepted boundary fixtures; UI screenshots and interaction checks remain separate gates. No package or external deployment was produced.

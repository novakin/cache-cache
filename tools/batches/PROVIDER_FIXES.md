# Provider boundary fixes

Date: 2026-10-09. Implemented in maintained source. No package, deployment, real provider call or shared WordPress setting change.

Scope: [PROVIDER-01–05](../audits/FULL_REVIEW_PROVIDERS.md). Existing Cloudflare/Sucuri owners, request hooks, capabilities and feedback remain responsible. No new queue, service, option or provider abstraction.

| Finding | Correction | Owner |
| --- | --- | --- |
| PROVIDER-01 | Sucuri failures log action, error category and safe HTTP status. Credential-bearing URLs, raw replies and transport messages are excluded from logs and feedback, including malicious provider echoes. | [Sucuri subscriber](../../noobrocket/inc/Addon/Sucuri/Subscriber.php) |
| PROVIDER-02 | Internal purge callbacks reread saved integration settings, authenticate the configured target and require the existing Cache Everything Page Rule. They work without an interactive user. Manual endpoints retain nonce/capability checks. | [Cloudflare subscriber](../../noobrocket/inc/Addon/Cloudflare/Subscriber.php) |
| PROVIDER-03 | Existing string backup now stores validated JSON with the zone and all three changed settings. Zero TTL survives. Missing/corrupt/unbound/cross-zone restoration has visible error feedback and sends no setting mutations. | Cloudflare subscriber |
| PROVIDER-04 | Submitted credentials, fresh target validation, reads, backup and patches use one explicit zone. Pending original recovery values survive partial application/restoration and later re-enable. Active optimal settings prevent silently switching the connection to another zone. | Cloudflare subscriber and [Cloudflare owner](../../noobrocket/inc/Addon/Cloudflare/Cloudflare.php) |
| PROVIDER-05 | Zone names must be valid hostnames and match the normalized site host exactly or at a dot-delimited subdomain boundary. Substring/lookalike matches fail. | Cloudflare owner |

## Recovery and ownership

The three provider mutations retain individual success/error results. A partial remote application is visible; it does not replace the original backup. Re-enabling after an incomplete restore reuses the valid same-zone original snapshot rather than capturing partially applied settings.

Only a complete successful restoration clears its backup. `Subscriber::clear_restored_backup()` reads current stored settings and requires exact equality with the just-restored snapshot before writing `cloudflare_old_settings = ''` through the existing options API. A private owner guard allows this nested metadata update through the pre-update callback. Unchanged provider toggles prevent repeated remote mutations. A refused metadata write retains recovery values and adds explicit feedback. An intervening settings change prevents the metadata write entirely.

Legacy CSV lacks reliable zone provenance. It is retained but cannot authorize an automatic restore or overwrite. Existing installations with such unresolved metadata require operator verification of the original zone/provider state before replacement; this batch does not infer prior ownership from the current zone. No previous one-year TTL is invented.

Explicit target checks bypass the old unscoped connection transient. This costs a fresh zone validation request for each internal operation; unchanged default manual connection caching remains. Page Rule detection remains the existing supported automatic-sync prerequisite. Support for modern Cache Rules is not claimed.

## Verification

| Gate | Result |
| --- | --- |
| [Native provider boundaries](../../tests/wordpress-provider-boundaries.php) | 84 assertions passed using real plugin Client/Endpoints/Cloudflare/Subscriber classes and intercepted HTTP. No external request, WordPress bootstrap or shared settings write. |
| [Cloudflare settings notices](../../tests/wordpress-cloudflare-settings-notices.php) | Existing 18 assertions passed. |
| PHP syntax | Three changed product files and new fixture pass PHP 8.3 syntax checks; source retains PHP 7.4-compatible syntax. |

Provider regression covers zero-user cron callbacks, manual nonce/capability rejection, disabled/invalid/unsupported integration gates, A-to-B target/credential ownership despite stale injected options, zero/nonzero TTL restoration, hidden backup tampering, missing/corrupt/legacy/cross-zone backups, failed fresh backup, mixed patch results, partial restore/re-enable/retry, nested metadata replay prevention, intervening settings ownership, refused metadata writes, and Sucuri credential echoes across each failure branch.

Logs: `C:/Users/Guillaume/.codex/logs/commands/20261009-221953-482-7c8290a3e9d94a78ae58e9d4f3435977.log` (final provider boundaries, including zero-user cron), `20261009-221852-386-f0adea5166ae450eab2d8d2ac9e3bb84.log` (notices), and `20261009-221852-395-91ea54c9c3e84d4695bf8a8651753af5.log` (syntax).

No current Cloudflare/Sucuri account/API compatibility, provider permissions, network latency, live cache purge, PHP 7.4 execution or production deployment was tested. Fake HTTP proves request intent, ownership and failure handling; it does not establish live API acceptance of TTL or legacy setting names.

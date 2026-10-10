# Provider and administrator action boundary review

Date: 2026-10-09. Status: source-only cross-check; product source unchanged. No external HTTP, provider change, shared WordPress operation or new probe was performed.

Scope: administrator-triggered PageSpeed checks and key storage, Cloudflare/Sucuri purge and provider settings, and modern-image library actions. This is a bounded supplement to the integrated review, not a proof of current provider API compatibility. Source references below are relative to `noobrocket/inc/` unless an asset/view path is named.

## Ownership and active paths

| Boundary | Existing owner and activation | Authorization and result boundary |
| --- | --- | --- |
| PageSpeed | `Engine/Admin/PageSpeed.php`; registered in the admin subscriber list at `Plugin.php:212`. Separate key form and AJAX analysis of the configured public homepage. | Key changes and runs require POST, `noobrocket_manage_options` and separate nonces. Key/result storage is independent of optimization settings and snapshots. Google HTTP/status/payload and database persistence outcomes are checked. |
| Cloudflare manual purge | `Addon/Cloudflare/Subscriber.php:71`; the integration's subscribers are registered only when saved `do_cloudflare` is enabled (`Plugin.php:381–385`). | Manual endpoint verifies nonce at `Subscriber.php:317`; `purge_cache_no_die()` checks `noobrocket_purge_cloudflare_cache` at line 260. Connection/purge errors become user-specific feedback. |
| Cloudflare internal synchronization | Existing local domain/post/home/file purge hooks at `Subscriber.php:67–70`. | Requires integration registration, valid provider connection and recognized active Cache Everything Page Rules. This is trusted internal synchronization of an already-authorized local cache change. A current interactive user's capability is the wrong gate for cron-driven synchronization; see PROVIDER-02. |
| Cloudflare settings | Provider subscriber listens to `pre_update_option_...` and `update_option_...` at `Subscriber.php:73–80`; ordinary settings form uses WordPress `settings_fields` in `views/settings/page.php:46–47`. | Mutation methods require `noobrocket_manage_options` and compare the changed option. Concrete remote changes are described at `Engine/Admin/Settings/Page.php:1831`. Restoration and target ownership have gaps below. |
| Sucuri | Common subscriber registered at `Plugin.php:323`. Manual purge at `Addon/Sucuri/Subscriber.php:49`; internal synchronization at lines 43–48. | Manual action verifies nonce and `noobrocket_purge_sucuri_cache` at lines 82/86. Automatic synchronization requires saved `sucury_waf_cache_sync` and runs at most once per request at lines 60–73. Valid credentials are required before the request. |
| Modern-image backfill | `Engine/Media/Picture/Generator.php`, common subscriber at `Plugin.php:313`. Footer renders a distinct action form, linked by the card's `form` attribute. | POST, `noobrocket_manage_options` and dedicated nonce at `Generator.php:117–120`. `queue_library()` honors saved enabled state and obtains the existing filesystem lock; it does not submit pending optimization fields. Encoding stays in cron. |

## Confirmed source findings

These findings describe reachable source behavior under the stated gates. They were not reproduced against installed WordPress or a real provider. Priorities indicate consequence and implementation order, not a severity score for the entire application.

### PROVIDER-01 — Sucuri error logs retain API credentials — P1

Owner: `Addon/Sucuri/Subscriber.php`. `clean_firewall_cache()` passes credentials as `k` and `s` at lines 178–183. `request_api()` builds a query-bearing API URL at lines 249–252. Transport, empty-response, invalid-JSON and failed-status branches include the complete URL in logger context at lines 280, 296, 311 and 324; several also include the raw response/body. `Logger/Logger.php:112–113` writes errors only when debug logging is enabled. `Logger/HTMLFormatter.php:34–38` formats the provided context without credential removal.

Active gates: valid saved Sucuri key, a manual authorized purge or enabled internal synchronization, a relevant failed-response branch, and debug enabled for that request. This review does not claim that credentials were actually recorded in the user's current logs, or that those logs are publicly accessible.

Smallest fix: remove the query-bearing URL and raw provider payload from these existing log calls. Keep the provider name, action, transport/error category and safe HTTP status. If the endpoint is useful, log its fixed origin/path only. No generic new logging/redaction service is needed.

No-operation acceptance: with fake credentials and an intercepted failing transport, invalid JSON, empty reply and failed provider status, neither log context nor rendered feedback contains either credential or its URL-encoded form; useful error feedback remains. Use a capturing logger/HTTP fixture and make zero real provider requests.

### PROVIDER-02 — Cloudflare automatic purge is skipped in cron contexts — P1

Owner: `Addon/Cloudflare/Subscriber.php`. `auto_purge()`, `auto_purge_by_url()` and `purge_url()` reject a request without the current user's purge capability at lines 153, 189 and 228. Those methods are registered on internal local-purge hooks at lines 67–70, not just on manual endpoints. A concrete existing caller is modern-image cron completion: `Engine/Media/Picture/Generator.php:167` calls `noobrocket_clean_domain()`, which emits `noobrocket_after_clean_domain` in `functions/files.php:961`. Normal cron runs without an interactive authorized user, so this gate skips Cloudflare invalidation after local image HTML becomes ready.

Active gates: saved Cloudflare integration enabled; anonymous cron/internal operation purges the local cache; valid provider connection and recognized active Cache Everything Page Rules would otherwise allow synchronization. Modern Cache Rules recognition was not assessed or verified with Cloudflare. Do not generalize this finding to installations without edge HTML caching.

Smallest fix: retain nonce/capability checks on `purge_cache()`/`purge_cache_no_die()` and all other manually reachable mutations. Make the three existing internal synchronization callbacks depend on configured integration, target ownership and connection/cache policy, rather than an interactive user. Reuse their existing calls and hooks; do not add a second purge queue or remove manual authorization globally.

No-operation acceptance: dispatch the internal hook with no current user and configured fake connection/Page Rule; expected owned-zone purge is captured once. Disabled integration, invalid connection and unsupported cache policy send no purge. An unauthorized manual request still sends zero requests. Fixtures intercept all provider calls; no real edge cache is cleared.

### PROVIDER-03 — Cloudflare restoration discards a previous zero TTL — P2

Owner: `Addon/Cloudflare/Subscriber.php`. `save_cloudflare_old_settings()` obtains the previous provider values at lines 597–599, then calls `array_filter()` before positional comma serialization. `Cloudflare::get_settings()` includes `browser_cache_ttl` at `Addon/Cloudflare/Cloudflare.php:351–355`. A previous numeric zero is removed. When optimal settings are disabled, `save_cloudflare_auto_settings()` uses the fourth positional value or defaults to `31536000` at `Subscriber.php:465`, so it cannot faithfully restore that zero.

Active gates: integration registered; authorized settings save toggles optimal settings on, backup contains zero; a subsequent authorized save toggles it off. The documented action attempts to restore prior zone values (`Engine/Admin/Settings/Page.php:1831`). Current remote acceptance/semantics of individual Cloudflare values were not validated in this source-only review.

Smallest fix: preserve zero and positional values when writing the existing backup; validate the backup before restoration. If values are missing or corrupt, show that restoration could not be completed instead of silently inventing previous values. Keep the existing storage unless a separately justified migration is necessary.

No-operation acceptance: fake provider state with TTL zero, nonzero TTL and each valid loader/cache value survives enable/disable unchanged. Missing/corrupt backup yields visible incomplete restoration and no invented one-year TTL. Capture provider intents without making requests.

### PROVIDER-04 — Cloudflare save paths mix submitted and cached zone identity — P2

Owners: `Addon/Cloudflare/Subscriber.php`, `Cloudflare.php` and the existing options service. The subscriber validates submitted zone arguments at lines 492, 537 and 593 and changes authentication from submitted values at lines 613–615. However, `Cloudflare::get_settings()` and the provider patch methods obtain the zone from their injected `Options_Data` at `Cloudflare.php:312,181,221,261,284`. `classes/admin/class-options-data.php:23–24,50,81` reads its constructor snapshot; it does not automatically refresh after a WordPress option update. `Addon/Cloudflare/ServiceProvider.php:55–60` injects the options dependency into that provider object.

`auto_purge()` explicitly refreshes the subscriber's own options at `Subscriber.php:157–159`. The provider-setting save/backup methods have no equivalent explicit target update, and refreshing one injected options instance is not a sound ownership contract for another object's target. A settings save that changes credentials/zone and a provider toggle can therefore validate the submitted zone while backing up or patching the old zone, or an empty previous zone. This is a source ownership finding; actual save-hook ordering and provider responses were not reproduced here.

Active gates: integration already registered, authorized settings save, submitted credentials/zone differs from the request snapshot, and a provider-setting transition invokes backup/read/patch. A valid cached connection is not sufficient proof that a new target was checked; `check_connection()` reuses a connection transient (`Cloudflare.php:47–59`). Normal unchanged-zone toggles do not establish this defect's outcome.

Smallest fix: pass the validated submitted zone explicitly through the existing backup/read/patch methods for that transaction. Bind the restoration backup to the zone whose values it represents and reject cross-zone restore. Keep existing provider classes and WordPress settings ownership; no adapter layer or new orchestration framework is needed. Surface connection failure/partial provider updates instead of treating local setting persistence as remote success.

No-operation acceptance: a fake transaction changes zone A to B alongside a provider toggle; validation, backup and patch intents all target the intended zone B. An existing backup for A cannot restore onto B. Invalid new credentials/connection send no mutations; mixed patch results remain distinguishable from complete success. Use intercepted provider methods and disposable in-memory settings, with zero external changes.

### PROVIDER-05 — Cloudflare domain matching accepts unrelated substring zones — P2

Owner: `Addon/Cloudflare/Cloudflare.php:99`. `is_auth_valid()` accepts a returned zone name when `strpos()` finds it anywhere in the site's hostname. For example, `box.net` is a substring of `noobunbox.net` but is not that site's zone. This weakens the target check before purge/settings operations.

Active gates: an authorized operator provides credentials/zone for which the provider returns such a name; `check_connection()` reaches fresh validation instead of returning its cached result. This is an administrator target-verification defect, not evidence of an unauthenticated exploit.

Smallest fix: normalize both hostnames and require exact equality or a proper dot-delimited subdomain suffix. Keep the existing domain-mapping behavior and reject invalid returned names. No public-suffix library or generic hostname policy subsystem is needed for this check.

No-operation acceptance: `example.com` and `www.example.com` match zone `example.com`; `notexample.com`, `example.com.other.net` and `noobunbox.net` versus `box.net` do not. Exercise native validation with a fake endpoint and inspect no purge/patch is authorized for rejected targets; no DNS/provider call is required.

## Retained PageSpeed and modern-image boundaries

- PageSpeed key form and mutation: `Engine/Admin/PageSpeed.php:62–97`; independent footer form, POST/capability/nonce, explicit save/remove operations, key validation, non-autoload storage and verification of persistence. The saved key is not populated into the rendered replacement field (`views/settings/partials/pagespeed.php:53`), and it is sent to Google in a header, not the query URL (`PageSpeed.php:183`).
- PageSpeed run: `PageSpeed.php:100–112`; POST/capability/nonce; only mobile/desktop strategies; configured public homepage, no user-supplied target. Existing option lock rejects concurrent checks and ownership-aware release runs in `finally` (`:165–172,217–228`). Fixed HTTPS Google endpoint, zero redirects, empty cookies, 90-second HTTP timeout and sanitized response errors (`:175–195`). Scores/numeric metrics and result persistence are verified (`:197–215`). Browser uses a 110-second abort and restores busy controls in `assets/js/noobrocket-admin.js:687–723`. PHP/FPM/proxy timeout capacity was not measured; no successful Google call is claimed.
- Modern-image action: `Engine/Media/Picture/Generator.php:59–66,83–103,116–123`; distinct form, dedicated nonce, POST/capability checks and saved configuration. It queues work instead of encoding inside the action or HTML output. Existing cron uses four encodes and a twenty-second soft deadline (`:126–156`), with filesystem locking; the deadline is checked between encodes, not a hard OS process limit. Native encoder behavior/quality on production Linux was not tested here.
- Sucuri manual results distinguish transport/invalid-body/failed-status outcomes from an accepted purge (`Addon/Sucuri/Subscriber.php:90–105,275–340`), with an explicit five-second timeout (`:264`). Cloudflare client checks transport, JSON and API success/result (`Addon/Cloudflare/API/Client.php:120–146`) and inherits WordPress HTTP defaults rather than defining a custom timeout (`:33–37,176`). Neither provider client was tested against today's live API contract.

## Sequence and limits

1. Remove Sucuri credential logging and correct internal/manual Cloudflare authorization ownership.
2. Preserve restoration values and give each Cloudflare transaction one explicit validated target; then tighten zone-name matching.
3. Add the described intercepted regression cases and verify UI feedback for failure/partial application before any provider staging test.

No new queue, generic security framework, persistent worker or provider abstraction is proposed. Improvements should stay in the responsible existing owners and preserve independent action forms. No provider API compatibility audit, real account permission test, Cache Rules support proof, network latency/load benchmark, production image encoder check or live deployment is included. Passing existing suites does not cover these newly identified provider failure/ownership cases by implication.

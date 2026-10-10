# NoobRocket upstream release review — 2026-10-09

No missing fix was found among the release changes checked below. NoobRocket 1.3.1 declares engine version 3.23.5.1, and the reviewed source contains both fixes from that release, all five 3.23.5 security fixes, and the preceding 3.23.4/3.23.3.3 fixes. The version constant alone does not establish release parity.

## Official release and source evidence

- Highest numbered published GitHub release: [v3.23.5.1](https://github.com/wp-media/wp-rocket/releases/tag/v3.23.5.1), published 2026-10-02, marked **prerelease** by GitHub.
- GitHub's `/releases/latest` endpoint returns [v3.23.3.3](https://github.com/wp-media/wp-rocket/releases/tag/v3.23.3.3), published 2026-08-27, marked **not prerelease**. The endpoint was checked again during this review. These are different definitions of “latest.”
- Exact release tag resolves to commit [`8e89a5917009777d51669c9f3e76cf8f85401a6b`](https://github.com/wp-media/wp-rocket/tree/8e89a5917009777d51669c9f3e76cf8f85401a6b), fetched into `.artifacts/upstream/wp-rocket`. Review used `git show v3.23.5.1:<path>`; it did not substitute the downloaded `develop` working tree for release source.
- The downloaded `develop` checkout remains at `3a96b66db41791fb1f4bb1e62dfe658ec4807454`. Its retrieval receipt is `.artifacts/upstream/retrieval.json`; release notes are `.artifacts/upstream/releases.json`.
- Maintained plugin identity and engine declaration: [noobrocket.php](../noobrocket/noobrocket.php), lines 21–22. Preserved `base-wp-rocket/` remains unchanged.

## Changes checked

| Release change | NoobRocket evidence | Result |
| --- | --- | --- |
| **3.23.5.1:** cast `COOKIE_DOMAIN` to string, [#8961](https://github.com/wp-media/wp-rocket/pull/8961) | [UserCacheKeySubscriber.php](../noobrocket/inc/Engine/Cache/UserCacheKeySubscriber.php):177 casts the constant before passing it into the typed cookie setter. | Present. |
| **3.23.5.1:** keep Elementor service registered, [#8963](https://github.com/wp-media/wp-rocket/pull/8963) | [ThirdParty/ServiceProvider.php](../noobrocket/inc/ThirdParty/ServiceProvider.php):29,55–57,79 includes `elementor_subscriber` in the always-registered service list, provider advertisement and registration. Event subscription remains resolver-gated. | Present; provider file matches exact tag after namespace/product-name normalization. |
| **3.23.5:** prevent unexpected ATF/LCP value types when pattern matching fails | [ATF Frontend/Controller.php](../noobrocket/inc/Engine/Media/AboveTheFold/Frontend/Controller.php):100–108,180–191 checks for `null` replacements and retains the original HTML. | Present. |
| **3.23.5:** encode RocketCDN button URL in inline JavaScript | Renamed [HostedCDN/AdminPageSubscriber.php](../noobrocket/inc/Engine/CDN/HostedCDN/AdminPageSubscriber.php):203 uses `wp_json_encode()` for the URL. | Present in retained hosted-provider source; normally dormant in the free edition. |
| **3.23.5:** sanitize beacon image `srcset` before storage | [ATF AJAX/Controller.php](../noobrocket/inc/Engine/Media/AboveTheFold/AJAX/Controller.php):168–179,414–457 validates candidates, rejects disallowed URL characters/schemes and validates descriptors before assignment. | Present; sanitization/parser block matches exact tag. |
| **3.23.5:** strict Lazy Render Content location-hash validation | [LRC AJAX/Controller.php](../noobrocket/inc/Engine/Optimization/LazyRenderContent/AJAX/Controller.php):74–83 requires a string matching 32 lowercase hexadecimal characters. | Present; `add_data()` block matches exact tag after hook-name normalization. |
| **3.23.5:** harden per-user cache-key validation | [Buffer/class-cache.php](../noobrocket/inc/classes/Buffer/class-cache.php):689–711 checks cookie shape, numeric future expiration and constant-time HMAC equality. Lines 613–616 bypass caching when the companion cookie fails validation. | Present; validator block matches exact tag. |
| **3.23.4:** reduce service-lookup overhead, [#8731](https://github.com/wp-media/wp-rocket/issues/8731) | Plugin IDs are memoized in [PluginResolver.php](../noobrocket/inc/ThirdParty/Plugins/PluginResolver.php):21,30–37. [Plugin](../noobrocket/inc/ThirdParty/ServiceProvider.php):45–57, [hosting](../noobrocket/inc/ThirdParty/Hostings/ServiceProvider.php):29–44 and [theme](../noobrocket/inc/ThirdParty/Themes/ServiceProvider.php):23–38 providers advertise precomputed service lists; only resolved compatibility services are registered. | Present; all four provider/resolver files match exact tag after namespace/product-name normalization. No performance benchmark was run. |
| **3.23.4:** percent-encoded LCP URL fatal error, [#8735](https://github.com/wp-media/wp-rocket/issues/8735) | [ATF Frontend/Controller.php](../noobrocket/inc/Engine/Media/AboveTheFold/Frontend/Controller.php):157–165 uses matching `#` quoting/delimiters. Lines 212–230 selectively decode non-ASCII byte runs while retaining encoded ASCII characters. | Present; `set_fetchpriority()` and decoder block match exact tag. |
| **3.23.4:** new CDN modes and settings migration, [#8693](https://github.com/wp-media/wp-rocket/issues/8693), [#8703](https://github.com/wp-media/wp-rocket/issues/8703) | [CdnStateBridge.php](../noobrocket/inc/Engine/CDN/CdnStateBridge.php):52–57,67–86 retains mode-state reconciliation and upgrade backfill. [free.php](../noobrocket/inc/free.php):61–62 forces BYO CDN mode; hosted-provider controls are not registered in the maintained admin subscriber list. | Intentional product difference: NoobRocket exposes local/BYO CDN, not RocketCDN Free/Pro subscription modes. Full upstream CDN product/UI parity is not claimed. |
| **3.23.4:** remove hosted-CDN notices/status on one.com, [#8852](https://github.com/wp-media/wp-rocket/issues/8852) | [OneCom.php](../noobrocket/inc/ThirdParty/Hostings/OneCom.php):33–38 retains filters hiding CTA, status, badge, banner and notices. | Present in compatibility source; hosted-provider UI is already dormant in the free edition. |
| **3.23.3.3:** cache-clear fatal when `deleted_post` has no callback, [#6759](https://github.com/wp-media/wp-rocket/issues/6759) | [Cloudflare.php](../noobrocket/inc/ThirdParty/Plugins/CDN/Cloudflare.php):539,551–565 checks hook existence, callback-array type and priority existence before iterating callbacks. | Present; reviewed guard agrees with exact release source. |

Release notes: [3.23.5](https://github.com/wp-media/wp-rocket/releases/tag/v3.23.5), [3.23.4](https://github.com/wp-media/wp-rocket/releases/tag/v3.23.4), [3.23.3.3](https://github.com/wp-media/wp-rocket/releases/tag/v3.23.3.3).

## Reachability and intentional differences

ATF and Lazy Render Content are local beacon features, not removed cloud-service paths. [Plugin.php](../noobrocket/inc/Plugin.php):297–301,349–354 registers their providers and performance-hint subscribers. [PerformanceHints AJAX/Subscriber.php](../noobrocket/inc/Engine/Common/PerformanceHints/AJAX/Subscriber.php):35–40 includes the anonymous beacon actions. Their normal context checks still apply.

HostedCDN classes remain registered as container services, but hosted admin/API subscribers are omitted from the active subscriber lists in [Plugin.php](../noobrocket/inc/Plugin.php):210–230,309–364. [free.php](../noobrocket/inc/free.php):56–62 disables upstream cloud CSS, monitoring, analytics and hosted-CDN options. [noobrocket.php](../noobrocket/noobrocket.php):29–32 sets upstream service endpoints to empty strings. These deliberate free-edition differences must not be reported as accidentally missing security or caching fixes.

## Verification and limits

Read-only review used official release metadata, the exact release-tag commit, focused source tracing and normalized text comparisons for the listed provider files and method blocks. Normalization changed only branding/namespace/hook names and line endings; it was evidence for those blocks, not a whole-plugin equivalence test.

No product source, settings, runtime, preserved base, package or live system changed. No application tests, exploit tests or speed benchmarks ran. This review covers the release entries listed above; it does not certify every historical release, every vendor dependency, all NoobRocket-specific changes or equality with current `develop`.

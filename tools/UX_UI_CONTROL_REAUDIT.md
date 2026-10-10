# NoobRocket control audit ledger

Date: 2026-10-09. Current local WordPress output; audit only. Read with the [design re-audit](UX_UI_DESIGN_REAUDIT.md). Source/control-family reviews: [pages](../.artifacts/design-reaudit/page-findings.md), [interaction](../.artifacts/design-reaudit/interaction-findings.md), [design system](../.artifacts/design-reaudit/system-findings.md).

This ledger inventories **306 distinct English control instances** across all 14 destinations in the exercised site and fixtures. Repeated Prepare rule/Restore actions remain separate rows. It covers labels, native type, submission ownership, dependency visibility and rendered enabled/disabled states. Source review covers dynamic templates beyond these instantiated rows. Rendering or an enabled state is not proof that its operation completed, its design is good, or its accessibility conforms.

All captured control instances use local WordPress. Both themes and French 390/320px layouts were reviewed through the supporting matrix. Actual provider, cleanup, restore, import, publication and diagnostic operations were not executed. One deliberate discard request was blocked; export used a static mock attachment. Small native checkbox inputs use associated labels as their hit area; measuring the 16px input alone would falsely report a target-size defect. Visually hidden canonical credential inputs are submission proxies, not missing user controls.

Main evidence: [runtime inventory](../.artifacts/design-reaudit/runtime-inventory.json) and [dependency/scope states](../.artifacts/design-reaudit/state-followup.json). The extractor uses native action attributes because a form input named action shadows the DOM form.action property. It uses browser visibility checks rather than positive layout rectangles for closed disclosures.

## Shared shell and transient controls

| Controls | Audit outcome |
| --- | --- |
| 14 navigation links and icons | Destination names, active state, deep links and native pointer focus reviewed. Desktop sidebar width and route-dependent content width lack one coherent rail. |
| Appearance: System, Light, Dark | Owns display preference, not optimization settings. Native labelled select; retain independent ownership. |
| Settings mobile opener | Native button, expanded state, Tab/Escape and Back checked at 320×568. No reproduced navigation defect. |
| Skip to settings | Native skip link and focusable main destination exist. |
| Save optimization settings; pending summary and section links | One settings owner and dirty summary exist. Enter and export guards still fail. Partial rejection may still emit generic saved success; server feedback path source-reviewed. |
| Cleanup: Cancel; Save settings and run cleanup; changed-settings disclosure | Deletion scope is explicit, but action still saves unrelated optimization changes. Final cleanup was not activated. |
| Pending operation: Cancel; Save settings first; Continue; pending-editor/settings disclosures | Review distinguishes editor input/settings and restores. Download routing is wrong; saved-draft discard bypasses review. |
| Toast dismissal and persistent error dismissal | Native error ownership and toast pause/dismiss behavior source-reviewed. Information notices still inherit warning colors; partial save needs truthful feedback. |

## Per-page inventory

| Page | Distinct instantiated controls |
| --- | ---: |
| Overview | 19 |
| Preload | 10 |
| Cache rules | 24 |
| CSS & JavaScript | 68 |
| Media | 19 |
| Asset Manager | 72 |
| CDN | 17 |
| Integrations | 10 |
| Cloudflare | 17 |
| Sucuri | 8 |
| Heartbeat | 5 |
| Database | 11 |
| Settings history | 16 |
| Tools | 10 |

## Overview — #dashboard

Status severity and recovery priority diverge; setup/action links repeat. Diagnostic forms correctly remain independent.

| Instance | Label | Native control | Owner | Observed rendering |
| --- | --- | --- | --- | --- |
| item 1 | Clear cache | a | Saved-state operation/download | Both themes |
| item 2 | Check homepage cache | submit | Independent operation form | Both themes |
| item 3 | Pause preloading | submit | Independent operation form | Both themes |
| item 4 | Preload Cache | a | Navigation/help | Both themes |
| item 5 | Preload queue counts | summary | Local disclosure | Both themes |
| item 6 | Set up scheduled worker runs | a | Navigation/help | Both themes |
| item 7 | Preload Cache | a | Navigation/help | Both themes |
| item 8 | CSS settings | a | Navigation/help | Both themes |
| item 9 | Pause CSS generation | submit | Independent operation form | Both themes |
| item 10 | CSS queue counts | summary | Local disclosure | Both themes |
| item 11 | Worker setup and scheduling | a | Navigation/help | Both themes |
| item 12 | Worker runtime details | summary | Local disclosure | Both themes |
| item 13 | Recovery actions | summary | Local disclosure | Both themes |
| item 14 | Regenerate CSS and page cache | submit | Independent operation form | Both themes |
| item 15 | Queue or resume media library | submit | Independent operation form | Both themes |
| item 16 | Media settings | a | Navigation/help | Both themes |
| item 17 | Image queue and encoder details | summary | Local disclosure | Both themes |
| item 18 | Object-cache setup and troubleshooting | a | Navigation/help | Both themes |
| item 19 | Server worker setup | a | Historical footer navigation; removed locally 2026-10-09, see [follow-up](../tests/README.md#dashboard-footer-help--2026-10-09-unpackaged) | Both themes at audit time |

## Preload — #preload

Oversized checkbox rows and repeated progress links; cache warming and hover prefetch must retain distinct labels.

| Instance | Label | Native control | Owner | Observed rendering |
| --- | --- | --- | --- | --- |
| item 1 | Help | a | Navigation/help | Both themes |
| item 2 | Overview | a | Navigation/help | Both themes |
| manual_preload | Activate Preloading | checkbox | Global settings form | Both themes |
| item 4 | Exclude URLs | summary | Local disclosure | Both themes |
| item 5 | More info | a | Navigation/help | Both themes |
| preload_excluded_uri | Exclude URLs | textarea | Global settings form | Both themes |
| item 7 | Help | a | Navigation/help | Both themes |
| item 8 | More info | a | Navigation/help | Both themes |
| preload_links | Enable link preloading | checkbox | Global settings form | Both themes |
| item 10 | View preload progress and homepage cache evidence | a | Navigation/help | Both themes |

## Cache rules — #advanced_cache

Enter can activate an unrelated hidden JS setting. Collapsed cards shift horizontal insets on opening; saved Configured badges do not follow pending values.

| Instance | Label | Native control | Owner | Observed rendering |
| --- | --- | --- | --- | --- |
| item 1 | Help | a | Navigation/help | Both themes |
| item 2 | preloading | a | Navigation/help | Both themes |
| purge_cron_interval | Clear cache after | number | Global settings form | Both themes |
| purge_cron_unit | Cache lifespan unit | select-one | Global settings form | Both themes |
| item 5 | When to use a shorter lifespan | summary | Local disclosure | Both themes |
| item 6 | Why? | a | Navigation/help | Both themes |
| cache_stale_refresh | Refresh expired pages in the background | checkbox | Global settings form | Both themes |
| item 8 | Background refresh requirements | summary | Local disclosure | Both themes |
| item 9 | Never Cache URL(s) | summary | Local disclosure | Both themes |
| item 10 | Help | a | Navigation/help | Both themes |
| cache_reject_uri | No extracted label; inspect native markup | textarea | Global settings form | Both themes |
| item 12 | Never Cache Cookies | summary | Local disclosure | Both themes |
| item 13 | Help | a | Navigation/help | Both themes |
| cache_reject_cookies | No extracted label; inspect native markup | textarea | Global settings form | Both themes |
| item 15 | Never Cache User Agent(s) | summary | Local disclosure | Both themes |
| item 16 | Help | a | Navigation/help | Both themes |
| cache_reject_ua | No extracted label; inspect native markup | textarea | Global settings form | Both themes |
| item 18 | Always Purge URL(s) | summary | Local disclosure | Both themes |
| item 19 | Help | a | Navigation/help | Both themes |
| cache_purge_pages | No extracted label; inspect native markup | textarea | Global settings form | Both themes |
| item 21 | Cache Query String(s) | summary | Local disclosure | Both themes |
| item 22 | Help | a | Navigation/help | Both themes |
| item 23 | Cache for query strings | a | Navigation/help | Both themes |
| cache_query_strings | No extracted label; inspect native markup | textarea | Global settings form | Both themes |

## CSS & JavaScript — #file_optimization

Unreadable purple confirmation overlay, implicit-submit acknowledgment, unannounced warning reveal, repeated setup/help. Native radio choices and catalog labels are appropriate.

| Instance | Label | Native control | Owner | Observed rendering |
| --- | --- | --- | --- | --- |
| item 1 | Help | a | Navigation/help | Both themes |
| minify_css | Minify CSS files | checkbox | Global settings form | Both themes |
| item 3 | CSS minification exclusions | summary | Local disclosure | Both themes |
| exclude_css | Excluded CSS Files | textarea | Global settings form | Both themes |
| item 5 | More info | a | Navigation/help | Both themes |
| noobrocket-css-off | Original CSS Keep your existing stylesheets. | radio | Global settings form | Both themes |
| noobrocket-css-critical | Critical CSS Prioritize styles for the first screen. | radio | Global settings form | Both themes |
| noobrocket-css-used | Used CSS Generate styles used by each page. | radio | Global settings form | Both themes |
| item 9 | View generation status | a | Navigation/help | Both themes |
| item 10 | Setup and requirements | summary | Local disclosure | Both themes |
| item 11 | Local CSS setup instructions | a | Navigation/help | Both themes |
| item 12 | CSS selector safelist | summary | Local disclosure | Both themes |
| local_css_safelist | CSS selector safelist | textarea | Global settings form | Both themes |
| remove_dashicons | Remove unused Dashicons | checkbox | Global settings form | Both themes |
| remove_embed_assets | Remove unused WordPress embed assets | checkbox | Global settings form | Both themes |
| remove_block_library | Remove unused block styles | checkbox | Global settings form | Both themes |
| item 17 | Help | a | Navigation/help | Both themes |
| minify_js | Minify JavaScript files | checkbox | Global settings form | Both themes |
| minify_concatenate_js | Combine JavaScript files | checkbox | Global settings form | Both themes; disabled and enabled |
| item 20 | More info | a | Navigation/help | Both themes |
| item 21 | Activate combine JavaScript | submit | Global settings form | Both themes |
| item 22 | Inline JavaScript exclusions | summary | Local disclosure | Both themes |
| item 23 | More info | a | Navigation/help | Both themes |
| exclude_inline_js | Excluded Inline JavaScript | textarea | Global settings form | Both themes |
| item 25 | JavaScript minification exclusions | summary | Local disclosure | Both themes |
| exclude_js | Excluded JavaScript Files | textarea | Global settings form | Both themes |
| item 27 | More info | a | Navigation/help | Both themes |
| defer_all_js | Load JavaScript deferred | checkbox | Global settings form | Both themes |
| item 29 | More info | a | Navigation/help | Both themes |
| item 30 | Deferred JavaScript exclusions | summary | Local disclosure | Both themes |
| item 31 | More info | a | Navigation/help | Both themes |
| exclude_defer_js | Excluded JavaScript Files | textarea | Global settings form | Both themes |
| delay_js | Delay JavaScript execution | checkbox | Global settings form | Both themes |
| item 34 | More info | a | Navigation/help | Both themes |
| item 35 | One-click exclusions | summary | Local disclosure | Both themes |
| delay_js_exclusions_selected-third_parties-analytics-toggle | Analytics & Trackers Selected exclusions: 0 | button | Global settings form | Both themes |
| delay_js_exclusions_selected-third_parties-analytics-all | Select all | checkbox | Global settings form | Both themes |
| delay_js_exclusions_selected-third_parties-analytics-item-0 | Google Analytics | checkbox | Global settings form | Both themes |
| delay_js_exclusions_selected-third_parties-analytics-item-1 | Google Optimize | checkbox | Global settings form | Both themes |
| delay_js_exclusions_selected-third_parties-analytics-item-2 | Google Tag Manager | checkbox | Global settings form | Both themes |
| delay_js_exclusions_selected-third_parties-analytics-item-3 | HubSpot | checkbox | Global settings form | Both themes |
| delay_js_exclusions_selected-third_parties-ad_networks-toggle | Ad Networks Selected exclusions: 0 | button | Global settings form | Both themes |
| delay_js_exclusions_selected-third_parties-ad_networks-all | Select all | checkbox | Global settings form | Both themes |
| delay_js_exclusions_selected-third_parties-ad_networks-item-0 | Amazon Ads | checkbox | Global settings form | Both themes |
| delay_js_exclusions_selected-third_parties-ad_networks-item-1 | Google AdSense | checkbox | Global settings form | Both themes |
| delay_js_exclusions_selected-third_parties-ad_networks-item-2 | Venatus Media | checkbox | Global settings form | Both themes |
| delay_js_exclusions_selected-third_parties-ad_networks-item-3 | Yandex Ads | checkbox | Global settings form | Both themes |
| delay_js_exclusions_selected-third_parties-payment_processors-toggle | Payment Processors Selected exclusions: 0 | button | Global settings form | Both themes |
| delay_js_exclusions_selected-third_parties-payment_processors-all | Select all | checkbox | Global settings form | Both themes |
| delay_js_exclusions_selected-third_parties-payment_processors-item-0 | Stripe | checkbox | Global settings form | Both themes |
| delay_js_exclusions_selected-third_parties-other_services-toggle | Other Services Selected exclusions: 0 | button | Global settings form | Both themes |
| delay_js_exclusions_selected-third_parties-other_services-all | Select all | checkbox | Global settings form | Both themes |
| delay_js_exclusions_selected-third_parties-other_services-item-0 | Cloudflare Turnstile | checkbox | Global settings form | Both themes |
| delay_js_exclusions_selected-third_parties-other_services-item-1 | Google Maps | checkbox | Global settings form | Both themes |
| delay_js_exclusions_selected-third_parties-other_services-item-2 | Google Recaptcha | checkbox | Global settings form | Both themes |
| delay_js_exclusions_selected-third_parties-other_services-item-3 | LeadConnector Chatbox | checkbox | Global settings form | Both themes |
| delay_js_exclusions_selected-third_parties-other_services-item-4 | Refari | checkbox | Global settings form | Both themes |
| delay_js_exclusions_selected-third_parties-other_services-item-5 | Reviews.io | checkbox | Global settings form | Both themes |
| delay_js_exclusions_selected-third_parties-other_services-item-6 | Trustindex | checkbox | Global settings form | Both themes |
| delay_js_exclusions_selected-third_parties-other_services-item-7 | Trustindex - Load Rich Snippets | checkbox | Global settings form | Both themes |
| delay_js_exclusions_selected-third_parties-other_services-item-8 | Typeform | checkbox | Global settings form | Both themes |
| delay_js_exclusions_selected-third_parties-other_services-item-9 | Typekit | checkbox | Global settings form | Both themes |
| delay_js_exclusions_selected-third_parties-other_services-item-10 | Vidalytics - Autoplay | checkbox | Global settings form | Both themes |
| delay_js_exclusions_selected-third_parties-other_services-item-11 | Wistia | checkbox | Global settings form | Both themes |
| item 65 | Delayed JavaScript exclusions | summary | Local disclosure | Both themes |
| delay_js_exclusions | Excluded JavaScript Files | textarea | Global settings form | Both themes |
| delay_js_execution_safe_mode | Safe Mode for Delay JavaScript Execution | checkbox | Global settings form | Both themes |
| item 68 | ACTIVATE SAFE MODE | submit | Global settings form | Both themes |

## Media — #media

Generous single-option gaps and duplicated Help/More info. Generation settings and library actions remain split between pages.

| Instance | Label | Native control | Owner | Observed rendering |
| --- | --- | --- | --- | --- |
| item 1 | Help | a | Navigation/help | Both themes |
| item 2 | More Info | a | Navigation/help | Both themes |
| lazyload | Enable for images | checkbox | Global settings form | Both themes |
| lazyload_css_bg_img | Enable for CSS background images | checkbox | Global settings form | Both themes |
| lazyload_iframes | Enable for iframes and videos | checkbox | Global settings form | Both themes |
| lazyload_youtube | Replace YouTube iframe with preview image | checkbox | Global settings form | Both themes |
| item 7 | Excluded images or iframes | summary | Local disclosure | Both themes |
| item 8 | More info | a | Navigation/help | Both themes |
| exclude_lazyload | Excluded images or iframes | textarea | Global settings form | Both themes |
| image_delivery | Generate and serve AVIF and WebP images | checkbox | Global settings form | Both themes |
| item 11 | Open media generation status | a | Navigation/help | Both themes |
| item 12 | Help | a | Navigation/help | Both themes |
| image_dimensions | Add missing image dimensions | checkbox | Global settings form | Both themes |
| item 14 | More info | a | Navigation/help | Both themes |
| item 15 | Help | a | Navigation/help | Both themes |
| auto_preload_fonts | Preload fonts | checkbox | Global settings form | Both themes |
| item 17 | More info | a | Navigation/help | Both themes |
| host_fonts_locally | Self-host Google Fonts | checkbox | Global settings form | Both themes |
| item 19 | More info | a | Navigation/help | Both themes |

## Asset Manager — #assets

Private/public workflow needs adjacent preview and publication delta. Discard lacks saved-draft protection. Native dark checkbox appearance differs; posts/exceptions still require IDs.

| Instance | Label | Native control | Owner | Observed rendering |
| --- | --- | --- | --- | --- |
| noobrocket-preview-url | Page URL | url | Independent operation form | Both themes |
| submit (item 2) | Inspect / preview page | submit | Independent operation form | Both themes |
| item 3 | Go to draft editor | a | Navigation/help | Both themes |
| item 4 | Browse inspected assets (27) | summary | Local disclosure | Both themes |
| item 5 | Source and dependencies | summary | Local disclosure | Both themes |
| item 6 | Prepare rule for twentytwentyfive-style | a | Navigation/help | Both themes |
| item 7 | Source and dependencies | summary | Local disclosure | Both themes |
| item 8 | Prepare rule for wp-block-template-skip-link | a | Navigation/help | Both themes |
| item 9 | Source and dependencies | summary | Local disclosure | Both themes |
| item 10 | Prepare rule for core-block-supports | a | Navigation/help | Both themes |
| item 11 | Source and dependencies | summary | Local disclosure | Both themes |
| item 12 | Prepare rule for global-styles | a | Navigation/help | Both themes |
| item 13 | Source and dependencies | summary | Local disclosure | Both themes |
| item 14 | Prepare rule for wp-block-library | a | Navigation/help | Both themes |
| item 15 | Source and dependencies | summary | Local disclosure | Both themes |
| item 16 | Prepare rule for wp-emoji-styles | a | Navigation/help | Both themes |
| item 17 | Source and dependencies | summary | Local disclosure | Both themes |
| item 18 | Prepare rule for wp-block-navigation-link | a | Navigation/help | Both themes |
| item 19 | Source and dependencies | summary | Local disclosure | Both themes |
| item 20 | Prepare rule for wp-block-columns | a | Navigation/help | Both themes |
| item 21 | Source and dependencies | summary | Local disclosure | Both themes |
| item 22 | Prepare rule for wp-block-spacer | a | Navigation/help | Both themes |
| item 23 | Source and dependencies | summary | Local disclosure | Both themes |
| item 24 | Prepare rule for wp-block-column | a | Navigation/help | Both themes |
| item 25 | Source and dependencies | summary | Local disclosure | Both themes |
| item 26 | Prepare rule for wp-block-post-template | a | Navigation/help | Both themes |
| item 27 | Source and dependencies | summary | Local disclosure | Both themes |
| item 28 | Prepare rule for wp-block-post-date | a | Navigation/help | Both themes |
| item 29 | Source and dependencies | summary | Local disclosure | Both themes |
| item 30 | Prepare rule for wp-block-post-content | a | Navigation/help | Both themes |
| item 31 | Source and dependencies | summary | Local disclosure | Both themes |
| item 32 | Prepare rule for wp-block-paragraph | a | Navigation/help | Both themes |
| item 33 | Source and dependencies | summary | Local disclosure | Both themes |
| item 34 | Prepare rule for wp-block-post-title | a | Navigation/help | Both themes |
| item 35 | Source and dependencies | summary | Local disclosure | Both themes |
| item 36 | Prepare rule for wp-block-heading | a | Navigation/help | Both themes |
| item 37 | Source and dependencies | summary | Local disclosure | Both themes |
| item 38 | Prepare rule for wp-block-template-part | a | Navigation/help | Both themes |
| item 39 | Source and dependencies | summary | Local disclosure | Both themes |
| item 40 | Prepare rule for wp-block-pattern | a | Navigation/help | Both themes |
| item 41 | Source and dependencies | summary | Local disclosure | Both themes |
| item 42 | Prepare rule for wp-block-group | a | Navigation/help | Both themes |
| item 43 | Source and dependencies | summary | Local disclosure | Both themes |
| item 44 | Prepare rule for wp-block-navigation | a | Navigation/help | Both themes |
| item 45 | Source and dependencies | summary | Local disclosure | Both themes |
| item 46 | Prepare rule for wp-block-page-list | a | Navigation/help | Both themes |
| item 47 | Source and dependencies | summary | Local disclosure | Both themes |
| item 48 | Prepare rule for wp-block-site-title | a | Navigation/help | Both themes |
| item 49 | Source and dependencies | summary | Local disclosure | Both themes |
| item 50 | Prepare rule for admin-bar | a | Navigation/help | Both themes |
| item 51 | Source and dependencies | summary | Local disclosure | Both themes |
| item 52 | Prepare rule for dashicons | a | Navigation/help | Both themes |
| item 53 | Source and dependencies | summary | Local disclosure | Both themes |
| item 54 | Prepare rule for wp-img-auto-sizes-contain | a | Navigation/help | Both themes |
| item 55 | Source and dependencies | summary | Local disclosure | Both themes |
| item 56 | Prepare rule for admin-bar | a | Navigation/help | Both themes |
| item 57 | Source and dependencies | summary | Local disclosure | Both themes |
| item 58 | Prepare rule for hoverintent-js | a | Navigation/help | Both themes |
| noobrocket-rule-type | Asset type | select-one | Independent operation form | Both themes |
| noobrocket-rule-handle | Registered handle | text | Independent operation form | Both themes |
| noobrocket-rule-scope | Disable on | select-one | Independent operation form | Both themes |
| noobrocket-rule-page | Choose a page | select-one | Independent operation form | Both themes |
| noobrocket-rule-post-type | Choose a post type | select-one | Independent operation form | Both themes |
| noobrocket-rule-template | Choose a template | select-one | Independent operation form | Both themes |
| noobrocket-rule-expert | Use an ID or registered value | checkbox | Independent operation form | Both themes |
| noobrocket-rule-value | Post ID | text | Independent operation form | Both themes |
| noobrocket-rule-exceptions | Exception post IDs | text | Independent operation form | Both themes |
| submit (item 68) | Add rule to draft | submit | Independent operation form | Both themes |
| submit (item 69) | Discard draft | submit | Independent operation form | Both themes |
| item 70 | I have tested the preview and want these rules applied to visitors. | checkbox | Independent operation form | Both themes |
| submit (item 71) | Publish rules | submit | Independent operation form | Both themes |
| item 72 | Review settings history or restore a previous state | a | Navigation/help | Both themes |

## CDN — #page_cdn

Hostname rows have visible labels and 44px actions. Add hostname is emphasized more strongly than its local editing role; collapsed verification differs in inset.

| Instance | Label | Native control | Owner | Observed rendering |
| --- | --- | --- | --- | --- |
| item 1 | Help | a | Navigation/help | Both themes |
| cdn | Enable CDN rewriting | checkbox | Global settings form | Both themes |
| noobrocket-cname-saved-0 | Hostname | text | Global settings form | Both themes |
| noobrocket-cname-saved-0-scope | Files to serve | select-one | Global settings form | Both themes |
| item 5 | Remove this CNAME | button | Global settings form | Both themes |
| item 6 | Add hostname | button | Global settings form | Both themes |
| noobrocket-cname-saved-1 | Hostname | text | Global settings form | Both themes |
| noobrocket-cname-saved-1-scope | Files to serve | select-one | Global settings form | Both themes |
| item 7 | Excluded files | summary | Local disclosure | Both themes |
| item 8 | Remove this CNAME | button | Global settings form | Both themes |
| cdn_reject_files | Excluded files | textarea | Global settings form | Both themes |
| item 9 | Add hostname | button | Global settings form | Both themes |
| item 9 | Verify saved delivery | summary | Local disclosure | Both themes |
| item 10 | Excluded files | summary | Local disclosure | Both themes |
| cdn_reject_files | Excluded files | textarea | Global settings form | Both themes |
| item 12 | Verify saved delivery | summary | Local disclosure | Both themes |
| item 13 | http://assets.qa.invalid/wp-content/plugins/noobrocket/assets/css/noobrocket-admin.css | a | Navigation/help | Both themes |

## Integrations — #addons

Five switches have associated labels and 44px label targets. Heading/task hierarchy differs from normal cards; current WebP and modern-image dependency remains explicit.

| Instance | Label | Native control | Owner | Observed rendering |
| --- | --- | --- | --- | --- |
| cache_logged_user | Add-on status | checkbox | Global settings form | Both themes |
| item 2 | User cache guidance | a | Navigation/help | Both themes |
| varnish_auto_purge | Add-on status | checkbox | Global settings form | Both themes |
| item 4 | Learn more | a | Navigation/help | Both themes |
| cache_webp | Add-on status | checkbox | Global settings form | Both themes; disabled prerequisite |
| do_cloudflare | Add-on status | checkbox | Global settings form | Both themes |
| item 7 | Compatibility details | summary | Local disclosure | Both themes |
| item 8 | Configure connection | a | Navigation/help | Both themes |
| sucury_waf_cache_sync | Add-on status | checkbox | Global settings form | Both themes |
| item 10 | Configure connection | a | Navigation/help | Both themes |

## Cloudflare — #cloudflare

Three credential controls and visibility action are native and labelled. Pending-off text incorrectly claims saved state is off; purge section floats outside cards; option names obscure provider effects.

| Instance | Label | Native control | Owner | Observed rendering |
| --- | --- | --- | --- | --- |
| item 1 | Open integration settings | a | Navigation/help | Both themes |
| item 2 | Help | a | Navigation/help | Both themes |
| cloudflare_api_key_mask | Global API key | password | Global settings form | Both themes |
| item 4 | Show Global API key | button | Global settings form | Both themes |
| item 5 | Find your API key | a | Navigation/help | Both themes |
| cloudflare_email | Account email | email | Global settings form | Both themes |
| cloudflare_email | Account email | text | Global settings form | Intentionally hidden canonical credential proxy |
| cloudflare_zone_id_mask | Zone ID | text | Global settings form | Both themes |
| item 8 | Find your Zone ID | a | Navigation/help | Both themes |
| item 9 | Help | a | Navigation/help | Both themes |
| cloudflare_devmode | Development mode | checkbox | Global settings form | Both themes |
| item 11 | Learn more | a | Navigation/help | Both themes |
| cloudflare_auto_settings | Optimal settings | checkbox | Global settings form | Both themes |
| cloudflare_protocol_rewrite | Relative protocol | checkbox | Global settings form | Both themes |
| item 14 | Learn more | a | Navigation/help | Both themes |
| item 15 | Clear Cloudflare cache | a | Navigation/help | Both themes |
| item 15 | Clear Cloudflare cache | button | Global settings form | Both themes; disabled prerequisite |

## Sucuri — #sucuri

Credential prerequisite/visibility remain explicit. Pending-off copy misstates saved state; purge section lacks a shared task surface.

| Instance | Label | Native control | Owner | Observed rendering |
| --- | --- | --- | --- | --- |
| item 1 | Open integration settings | a | Navigation/help | Both themes |
| item 2 | Help | a | Navigation/help | Both themes |
| sucury_waf_api_key | Firewall API key | password | Global settings form | Both themes |
| item 4 | Show Firewall API key | button | Global settings form | Both themes |
| item 5 | Find your API key | a | Navigation/help | Both themes |
| item 6 | Learn more | a | Navigation/help | Both themes |
| item 7 | Clear Sucuri cache | a | Navigation/help | Both themes |
| item 7 | Clear Sucuri cache | button | Global settings form | Both themes; disabled prerequisite |

## Heartbeat — #heartbeat

Context choices hide correctly when master is off and retain saved choices. Two cards split one dependency; nested 36rem content leaves unused width.

| Instance | Label | Native control | Owner | Observed rendering |
| --- | --- | --- | --- | --- |
| item 1 | Help | a | Navigation/help | Both themes |
| control_heartbeat | Control Heartbeat | checkbox | Global settings form | Both themes |
| heartbeat_admin_behavior | WordPress admin | select-one | Global settings form | Both themes |
| heartbeat_editor_behavior | Post editor | select-one | Global settings form | Both themes |
| heartbeat_site_behavior | Public site | select-one | Global settings form | Both themes |

## Database — #database

Frequency remains visible/enabled while schedule is off. Category counts consume oversized rows. Cleanup still submits unrelated pending optimization settings.

| Instance | Label | Native control | Owner | Observed rendering |
| --- | --- | --- | --- | --- |
| item 1 | Help | a | Navigation/help | Both themes |
| database_revisions | Revisions | checkbox | Global settings form | Both themes |
| database_auto_drafts | Auto Drafts | checkbox | Global settings form | Both themes |
| database_trashed_posts | Trashed Posts | checkbox | Global settings form | Both themes |
| database_spam_comments | Spam Comments | checkbox | Global settings form | Both themes |
| database_trashed_comments | Trashed Comments | checkbox | Global settings form | Both themes |
| database_all_transients | All transients | checkbox | Global settings form | Both themes |
| database_optimize_tables | Optimize Tables | checkbox | Global settings form | Both themes |
| schedule_automatic_cleanup | Schedule Automatic Cleanup | checkbox | Global settings form | Both themes |
| automatic_cleanup_frequency | Frequency | select-one | Global settings form | Both themes |
| item 11 | Review and run cleanup | submit | Global settings form | Both themes |

## Settings history — #history

Explicit restore review is retained. Short values create needless keyboard stops; record cards/native checkboxes differ from shared task controls.

| Instance | Label | Native control | Owner | Observed rendering |
| --- | --- | --- | --- | --- |
| item 1 | Restore scope and limits | summary | Local disclosure | Both themes |
| item 2 | Review changes before restoring | summary | Local disclosure | Both themes |
| item 3 | I have reviewed these changes and want to restore this snapshot. | checkbox | Independent operation form | Both themes |
| submit (item 4) | Restore snapshot | submit | Independent operation form | Both themes |
| item 5 | Review changes before restoring | summary | Local disclosure | Both themes |
| item 6 | I have reviewed these changes and want to restore this snapshot. | checkbox | Independent operation form | Both themes |
| submit (item 7) | Restore snapshot | submit | Independent operation form | Both themes |
| item 8 | Review changes before restoring | summary | Local disclosure | Both themes |
| item 9 | I have reviewed these changes and want to restore this snapshot. | checkbox | Independent operation form | Both themes |
| submit (item 10) | Restore snapshot | submit | Independent operation form | Both themes |
| item 11 | Review changes before restoring | summary | Local disclosure | Both themes |
| item 12 | I have reviewed these changes and want to restore this snapshot. | checkbox | Independent operation form | Both themes |
| submit (item 13) | Restore snapshot | submit | Independent operation form | Both themes |
| item 14 | Review changes before restoring | summary | Local disclosure | Both themes |
| item 15 | I have reviewed these changes and want to restore this snapshot. | checkbox | Independent operation form | Both themes |
| submit (item 16) | Restore snapshot | submit | Independent operation form | Both themes |

## Tools — #tools

Flat, wider transfer blocks diverge from task cards. Export disables edit protection after download. Pending/long import review has poor priority and nested scrolling.

| Instance | Label | Native control | Owner | Observed rendering |
| --- | --- | --- | --- | --- |
| item 1 | Download settings | a | Saved-state operation/download | Both themes |
| upload | Settings export file | file | Independent operation form | Both themes |
| item 2 | Cancel import | submit | Independent operation form | Both themes |
| item 3 | Upload and review changes | submit | Independent operation form | Both themes |
| item 4 | Settings history | a | Navigation/help | Both themes |
| upload | Settings export file | file | Independent operation form | Both themes |
| item 5 | Asset Manager | a | Navigation/help | Both themes |
| item 5 | Upload and review changes | submit | Independent operation form | Both themes |
| item 6 | Settings history | a | Navigation/help | Both themes |
| item 7 | Asset Manager | a | Navigation/help | Both themes |

## Boundaries and uncommon states

Source-reviewed uncommon controls include the Mobile Cache/Google Fonts one-time Tools actions, retry/resume diagnostics when their queued state requires them, and partial server-save feedback. Upload-directory failure and selected-file states have a separate [Tools state probe](../.artifacts/design-reaudit/tools-states.json). Those rare controls are not silently counted as successfully exercised transactions.

WordPress submit_button() currently repeats id=submit on four Asset Manager controls and five History controls. Labels and forms remain distinct in the exercised output; no wrong submission or ARIA collision was reproduced. Use unique IDs during component consolidation. This is markup correctness, not a claimed accessibility failure.

Screen-reader speech, RTL, 200% browser zoom, other browsers, real slow-network operations, all locale catalogs and user task-timing studies remain outside this audit. No numerical quality score or finished-design claim follows from this ledger.

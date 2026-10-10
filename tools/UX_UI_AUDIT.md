# NoobRocket independent UI/UX audit

Date: 2026-10-09. Product assessed: local NoobRocket 1.3.1 in WordPress 7.1.3. Assessment concerns the current rendered interface and its task behavior. The mockup is not an acceptance criterion. No product changes, packaging, deployment, or live-site actions were performed for this audit.

**Historical baseline:** subsequent implementation and proof are recorded in [the improvement record](UX_UI_IMPROVEMENTS.md). The [first fresh page audit](UX_UI_PAGE_AUDIT.md) and [second review of all 14 pages](UX_UI_SECOND_PASS_AUDIT.md) assess the later interface and supersede preceding completion judgments. Findings below describe this original audit's source, not every current defect.

## Verdict

The interface has a consistent visual foundation, but it is not finished as a usable product. The largest weaknesses concern understanding the scope of an action, finding the right destination, completing setup, and recovering from a change. Several problems are confirmed behavior or layout defects, rather than aesthetic preferences.

The app looks orderly because it repeats cards, borders, and generous spacing. That consistency also flattens priority. It gives routine explanations, zero counts, and consequential choices similar prominence. Users must connect settings, prerequisites, operations, and verification across different pages. Making it resemble a mockup more closely would not resolve those problems.

The blue palette and system font are suitable for a WordPress administration tool. Dark mode does not need a wholesale palette replacement. The larger design work is structural: clearer transactions, stronger task hierarchy, shorter paths, and better use of screen space. This audit does not assign a numerical quality score; an expert review cannot substantiate “100/100.”

## Method, assumptions, and evidence

Three independent reviewers examined visual design, live interaction, and task/source behavior. They were instructed not to use earlier mockup or completion judgments. The consolidated assessment reconciles duplicate findings and checks decisive fresh screenshots, measurements, and responsible source paths.

Skills applied:

- **UI/UX Pro Max:** accessibility, form feedback, touch interaction, responsive layout, and navigation guidance.
- **design-system:** semantic theme roles, component states, spacing, and consistent hierarchy.
- **frontend-design:** product purpose, meaningful visual structure, concise copy, and critique of repetitive presentation.

Navigation searches did not return directly applicable database guidance after one narrower retry. Navigation recommendations therefore use the skill's general guidance and independent judgment. No new design system was generated or treated as an authority. The stack remains native WordPress PHP templates, CSS, and JavaScript.

Assumed audiences:

- **WordPress site owner:** understands pages, plugins, and site speed; may not know handles, post IDs, PHP headers, or have SSH access. Needs useful optimization without breaking visitors' pages.
- **VPS operator:** understands cache layers, cron, PHP, and Nginx. Needs exact effects, trustworthy verification, and a clear rollback path.

These audiences are design assumptions, not research findings. Their likely difficulties are judgments, not measured error rates.

Fresh visual coverage includes every one of the 13 routes in English at 1440px and French at 390px, in light and dark themes. Additional captures cover 320px, 768px, and 1024px. Interaction checks cover both languages, both themes, desktop/mobile, draft-enabled provider routes, validation, cleanup review, navigation, and saving scope. Deeper Tab-order, browser Back, and form-scope checks used English.

Asset volume was supplied through a request-only fixture: 27 synthetic inventory rows and five history records. This proves the populated layout at that volume, not usability of every production inventory. Cloudflare and Sucuri were exposed through unsaved native integration toggles. Audit requests blocked mutations before reaching WordPress. No actual cleanup, import, publication, provider change, or worker run was executed.

Evidence is retained under [.artifacts/independent-ux-audit](../.artifacts/independent-ux-audit/): [visual review](../.artifacts/independent-ux-audit/visual-audit.md), [interaction review](../.artifacts/independent-ux-audit/interaction-audit.md), [task/source review](../.artifacts/independent-ux-audit/task-audit.md), [visual measurements](../.artifacts/independent-ux-audit/visual-measurements.json), [deep interaction evidence](../.artifacts/independent-ux-audit/deep-interaction-evidence.json), and [navigation evidence](../.artifacts/independent-ux-audit/navigation-feedback-evidence.json). The task review references some earlier captures; findings below use fresh screenshots where visual evidence is needed.

**Severity:** High means a consequential transaction cannot be adequately reviewed. Medium means recurring task friction, preventable mistakes, or a confirmed interaction/layout defect. Low means secondary clarity or feedback. No finding establishes that the whole app is unusable. **Confidence** distinguishes observed behavior/source from predicted user impact. Recommendations are directions for later design work, not implemented changes.

## Highest priorities

| Priority | Findings | Why first |
|---|---|---|
| Transaction clarity | UX01, UX02, UX03, UX21 | Cleanup, provider configuration, global saving, and import must reveal what they change. |
| Setup and recovery | UX04, UX05, UX09, UX10, UX19, UX23 | Users need to find, configure, verify, and undo the right feature. |
| Asset workflow | UX06, UX07, UX08, UX15 | Inspection should help create a rule rather than bury the editor or require identifier translation. |
| Keyboard and long-page behavior | UX12, UX13 | Navigation order and browser Back should support reading and comparison. |
| Layout and priority | UX11, UX14, UX16, UX17, UX18 | Responsive bugs and repetitive presentation consume space without improving decisions. |
| Remaining feedback and copy | UX20, UX22, additional observations | Improve pending states and explain actual WordPress effects. |

## Findings

### UX01 — High: cleanup review omits deletion scope and unrelated settings

**Observed:** “Review and run cleanup” opens a generic permanent-deletion warning. It lists neither selected categories/counts nor other pending settings. Selecting Auto Drafts and changing Heartbeat on another page produced an intercepted native POST with `database_auto_drafts=1`, `heartbeat_admin_behavior=disable`, and 59 fields. No POST reached WordPress.

**Impact:** The final destructive checkpoint does not let users review the transaction. Cleanup can also commit an unrelated optimization draft through the shared settings form. Cancel-first focus and the backup warning are useful, but do not disclose scope.

**Direction:** Review selected categories/counts and any pending schedule or unrelated settings. Resolve those drafts explicitly, or isolate the cleanup transaction at the responsible form layer. Settings history cannot recover deleted database data.

**Evidence/confidence:** High confidence in dialog and submission scope; deletion and server outcomes untested. [Fresh dialog](../.artifacts/independent-ux-audit/en_US-1440-light-cleanup-review.png), deep evidence test “Cleanup submits unrelated optimization draft”; [page.php](../noobrocket/views/settings/page.php:41), [database section](../noobrocket/views/settings/page-sections/database.php:24).

### UX02 — High: Cloudflare “Optimal settings” hides concrete zone changes

**Observed:** The toggle promises better speed and compatibility without listing its effects. The backend sets cache level to `aggressive`, disables `rocket_loader`, and sets browser cache TTL to 31,536,000 seconds: one year. Disabling the option attempts to restore stored previous values.

**Impact:** An operator approves an external configuration change without seeing its scope or restore behavior. This is a disclosure gap; the audit does not establish that these settings are inappropriate for the site.

**Direction:** List the three changes, affected zone, save boundary, and restoration behavior beside the option. Present provider result evidence when available. Clear copy is needed, not another generic approval dialog.

**Evidence/confidence:** High confidence in UI/source; actual provider execution/restoration untested. [Fresh Cloudflare screen](../.artifacts/independent-ux-audit/visual-captures/en_US-cloudflare-1440-light.png), [Page.php](../noobrocket/inc/Engine/Admin/Settings/Page.php:1835), [Subscriber.php](../noobrocket/inc/Addon/Cloudflare/Subscriber.php:451).

### UX03 — Medium: global Save has unclear ownership across pages

**Observed:** Changing only Heartbeat, then opening Asset Manager, displays “You have unsaved changes” and Save settings above the Asset Manager heading. At 390px, that card occupies y215–325. It identifies no changed section. The asset forms below use separate draft and publication semantics.

**Impact:** Users must remember which page they changed and distinguish global settings saving from adding a private rule and publishing rules. The form boundary is technically correct, but visually unclear.

**Direction:** Name changed sections and the action's scope. Place the global pending-settings notice where it cannot appear to save asset rules. Preserve separate native forms.

**Evidence/confidence:** High confidence in cross-page state and placement; mistaken user interpretation is judgment. [Fresh mobile state](../.artifacts/independent-ux-audit/en_US-390-light-global-save-assets-top.png), [positions](../.artifacts/independent-ux-audit/save-scope-position-evidence.json), [page.php](../noobrocket/views/settings/page.php:41).

### UX04 — Medium: navigation hides destinations and splits related work

**Observed:** Secondary routes appear only when their parent group is active. Primary links have no expansion indicator. Cache opens Preload; Maintenance opens Database, which has no separate navigation label; Delivery opens Content Delivery. User Cache and WebP Compatibility are under Maintenance → Integrations. Cloudflare/Sucuri activation is there, while configuration is under Delivery.

**Impact:** Users cannot scan the full destination set. Default page names differ from selected group names, and provider setup requires a category change. Cache/media settings are harder to locate from their subject matter.

**Direction:** Expose group structure with visible destinations or an explicit expansion affordance. Label default destinations. Keep provider activation/configuration together, or provide an immediate, explicit configuration link. Revisit grouping using real findability tasks.

**Evidence/confidence:** High confidence in structure; taxonomy and discoverability impact are judgments. [Fresh navigation](../.artifacts/independent-ux-audit/visual-captures/en_US-dashboard-1440-light.png), [Integrations](../.artifacts/independent-ux-audit/visual-captures/en_US-addons-1440-light.png), [navigation.php](../noobrocket/views/settings/navigation.php:8), [admin CSS](../noobrocket/assets/css/noobrocket-admin.css:82).

### UX05 — Medium: disabled provider links silently redirect to Overview

**Observed:** Direct visits to `#cloudflare` or `#sucuri` when their integrations are disabled rewrite the hash to `#dashboard`. There is no reason or prerequisite message. Draft-enabling the native toggle makes the route accessible without saving.

**Impact:** A bookmark or help link loses its intended destination. Users must infer which integration to enable and where to find it.

**Direction:** Explain the unavailable destination and link to its prerequisite, either on a prerequisite screen or with a clear redirect notice. Retain the existing disabled-route guard.

**Evidence/confidence:** High confidence from both languages. [Route inventory](../.artifacts/independent-ux-audit/route-inventory.json), [interaction evidence](../.artifacts/independent-ux-audit/interaction-evidence.json), [PageManager](../noobrocket/assets/js/noobrocket-settings.js:2756).

### UX06 — Medium: populated asset inventory overwhelms the next workflow step

**Observed:** Inventory opens automatically whenever an inspection URL exists. With 27 rows, French mobile Asset Manager measures 10,356px. Editing begins near y8,574 and publishing near y9,911. Desktop editing begins near y1,981. The inventory disclosure can be collapsed, but the populated default is expanded.

**Impact:** Inspection makes the next step harder to reach. A useful result table dominates the workflow before users can act on it. Page height includes WordPress chrome and depends on data; height alone is not the defect.

**Direction:** Keep a count/status summary and a bounded initial inventory. Make editing reachable without traversing every result. Preserve access to all records and the meaningful inspect/edit/publish sequence.

**Evidence/confidence:** High confidence in this populated layout; no user timing measured. [Fresh populated mobile page](../.artifacts/independent-ux-audit/visual-captures/fr_FR-assets-390-light.png), [collapsed comparison](../.artifacts/independent-ux-audit/visual-captures/fr_FR-assets-390-light-inventory-collapsed.png), [assets.php](../noobrocket/views/assets.php:50).

### UX07 — Medium: creating asset rules requires manual translation into internals

**Observed:** Inventory rows have no create-rule action. The editor asks for a handle, generic “Scope value,” numeric post IDs, template filenames, and comma-separated exception IDs. A datalist helps with handles. All four scope explanations remain visible together. `abc` passes native validity for the default numeric page scope.

**Impact:** Users must connect inspection to the form manually and translate pages into identifiers. Preventable entry errors reach a server round trip. Backend acceptance of invalid input was not tested or alleged.

**Direction:** Start a draft rule from an inventory row, retaining its type/handle and inspected-page context. Prefer recognizable page/template choices where practical, with an expert identifier path. Show scope-specific help and native constraints/input semantics.

**Evidence/confidence:** High confidence in controls and client validity; targeting error frequency unknown. [Fresh editor](../.artifacts/independent-ux-audit/visual-captures/en_US-assets-1440-light.png), [assets.php](../noobrocket/views/assets.php:150), interaction test “asset rule invalid numeric ID passes client validity.”

### UX08 — Medium: unfinished asset input lacks settings-equivalent exit protection

**Observed:** Entering a handle/scope value and clicking WordPress Posts navigates away without warning. Ordinary settings drafts have departure protection. Submitted private drafts are a different state and were not altered.

**Impact:** Users can lose unfinished rule entry while looking up content or a post ID. The protection model changes between adjacent workflows.

**Direction:** Preserve unfinished editor input or warn when leaving with changed input. Apply this to unsubmitted forms, not to every navigation action.

**Evidence/confidence:** High confidence in tested departure behavior. [Deep evidence](../.artifacts/independent-ux-audit/deep-interaction-evidence.json), test “Unsubmitted asset editor external navigation”; [asset form](../noobrocket/views/assets.php:150), [settings departure handling](../noobrocket/assets/js/noobrocket-admin.js:79).

### UX09 — Medium: general recovery is buried and snapshots lack meaningful differences

**Observed:** General settings history is collapsed at the bottom of Asset Manager. Tools links to the top of that workflow. Snapshots show time, supported-setting count, and rule count, but not changed values. The supported subset and five-entry limit are explained. Restore retains the current state first.

**Impact:** Recovering a CSS or media change requires finding a different feature and choosing a timestamp without knowing which values will revert.

**Direction:** Give history a direct destination and links from settings. Summarize differences and show restoration scope before the existing confirmation. Preserve current-state capture and credential exclusions.

**Evidence/confidence:** High confidence in presentation/source; real restore untested. [Fresh Asset Manager](../.artifacts/independent-ux-audit/visual-captures/en_US-assets-1440-light.png), [Tools](../.artifacts/independent-ux-audit/visual-captures/en_US-tools-1440-light.png), [assets.php](../noobrocket/views/assets.php:257), [Snapshots.php](../noobrocket/inc/Engine/Optimization/Assets/Snapshots.php:104).

### UX10 — Medium: CSS choices precede visible readiness and setup guidance

**Observed:** Critical CSS and Used CSS are selectable before worker readiness is shown beside them. Node.js, Chromium, and same-server scheduling requirements sit inside closed setup help. Worker status lives on Overview and detailed commands live in the worker README.

**Impact:** The site owner can select a feature they cannot provision. The operator must assemble a sequence across settings, status, and documentation. Safe original styles reduce breakage risk, but not setup ambiguity.

**Direction:** Put one prerequisite sentence, recorded worker status, and a setup link beside the modes. Distinguish “mode saved” from “generation available” and “pages generated.” Keep detailed commands in documentation.

**Evidence/confidence:** High confidence in current presentation; worker execution/shared-hosting feasibility untested. [Fresh CSS screen](../.artifacts/independent-ux-audit/visual-captures/en_US-file_optimization-1440-light.png), [Page.php](../noobrocket/inc/Engine/Admin/Settings/Page.php:491), [select.php](../noobrocket/views/settings/fields/select.php:26).

### UX11 — Medium: Overview exposes facts without a clear interpretation or next action

**Observed:** A failed probe shares the general panel treatment of ordinary states. Transport failure renders delivery result `0`; last check shows only a time. Critical CSS shows “Waiting” with all job counts zero. Server worker shows “Ready / Reported” from an older report. `ready` reports are exempt from the five-minute stale classification. Healthy/idle metadata occupies space before general Recovery actions.

**Impact:** Users must decide whether the system is configured, currently active, or merely reporting historical evidence. “Check cache” can sound broader than its two anonymous homepage requests and PHP header check. Static/proxy delivery may omit that header; a probe failure does not prove visitor caching is broken.

**Direction:** Name the homepage/probe scope. Use meaningful failure labels and date/elapsed evidence. Distinguish recorded environment readiness from observed worker activity. Explain an empty CSS queue and place the next relevant action with each attention state. Compress healthy detail without hiding evidence.

**Evidence/confidence:** High confidence in source/current state; comprehension impact is judgment, production performance untested. [Fresh Overview](../.artifacts/independent-ux-audit/visual-captures/en_US-dashboard-1440-light.png), [mobile Overview](../.artifacts/independent-ux-audit/visual-captures/fr_FR-dashboard-390-light.png), [diagnostic template](../noobrocket/views/settings/partials/local-css-diagnostics.php:5), [Diagnostics.php](../noobrocket/inc/Engine/Optimization/LocalCSS/Diagnostics.php:143).

### UX12 — Medium: Overview keyboard order contradicts visual order

**Observed:** CSS displays Site status before Modern images, while the DOM places Modern images first. At 390px, Tab goes from Check cache to Queue / resume library at document y1,881, then back to Pause preloading at y857. Desktop reproduces the reversed order.

**Impact:** Keyboard focus jumps down past the status panel and then back up. DOM reading order also differs from visual priority. A manual screen-reader session was not conducted, so this is not a complete assistive-technology finding.

**Direction:** Align DOM and visual order. Preserve visible focus and route-heading focus management.

**Evidence/confidence:** High confidence from live Tab sequences. [Deep evidence](../.artifacts/independent-ux-audit/deep-interaction-evidence.json), test “Overview DOM/visual/Tab order”; [CSS order rules](../noobrocket/assets/css/noobrocket-admin.css:115).

### UX13 — Medium: long-page navigation and browser Back lose reading context

**Observed:** At the CSS page bottom, desktop sidebar links have scrolled away. Mobile's menu button is at viewport y−1,750. Save and navigation are static. Reading CSS at scroll y500, moving to Media, and pressing Back returns CSS at y0 at both tested widths.

**Impact:** Comparing settings requires repeated scrolling and relocation. The problem grows with long forms and populated inventories. Save is available at form bottom, but unavailable near earlier edits without scrolling.

**Direction:** Preserve route reading position on Back. Keep a compact navigation affordance reachable on long pages. Evaluate persistent save access without covering mobile content or creating competing actions.

**Evidence/confidence:** High confidence in tested route sequence/geometry. [Desktop bottom](../.artifacts/independent-ux-audit/en_US-1440-light-css-bottom.png), [mobile bottom](../.artifacts/independent-ux-audit/en_US-390-light-css-bottom.png), [navigation evidence](../.artifacts/independent-ux-audit/navigation-feedback-evidence.json).

### UX14 — Medium: specificity defeats intended responsive padding

**Observed:** Live content has `noobrocket-Content isNotFull`. Its padding remains `25px 32px 34px` at 390px, 768px, and 1024px. The base `.isNotFull` selector outranks responsive selectors. Intended mobile horizontal padding is 17px, so 30px of potential content width is lost.

**Impact:** Text wraps sooner, controls have less room, and long forms grow. This is an implementation defect with direct layout consequences, not a preference for denser spacing.

**Direction:** Resolve selector ownership/specificity at the existing stylesheet layer. Recheck actual route classes at 320/390/768/1024px.

**Evidence/confidence:** High confidence from computed styles/source. [Media mobile](../.artifacts/independent-ux-audit/visual-captures/fr_FR-media-390-light-viewport.png), [measurements](../.artifacts/independent-ux-audit/visual-measurements.json), [base CSS](../noobrocket/assets/css/noobrocket-admin.css:92), [tablet CSS](../noobrocket/assets/css/noobrocket-admin.css:285), [mobile CSS](../noobrocket/assets/css/noobrocket-admin.css:304).

### UX15 — Medium: French asset scope select overflows its card at 320px

**Observed:** At 320px, the French scope select extends from x49 to x305. Its content cell ends at x271 and outer card at x288. It therefore exceeds the outer card by 17px. English intrudes 14px into padding but stays within the card. Both languages fit at 390px. Document `clientWidth` and `scrollWidth` remain 320px.

**Impact:** A translated native control visibly breaks its container at a supported narrow width. This is internal card overflow, not whole-page horizontal scrolling.

**Direction:** Let native selects shrink within available form width, and recheck after correcting padding. Test longest option labels rather than only English defaults.

**Evidence/confidence:** High confidence from measured geometry. [French screenshot](../.artifacts/independent-ux-audit/fr_FR-320-asset-select-geometry.png), [geometry](../.artifacts/independent-ux-audit/asset-select-geometry-evidence.json), [form](../noobrocket/views/assets.php:185).

### UX16 — Medium: repeated form spacing weakens association and exaggerates complexity

**Observed:** Desktop Media checkbox rows span 968px; “Enable for images” has about 836px between the label's last glyph and checkbox. Similar state placement recurs elsewhere. Many cards repeat title, help link, explanation, large row, and more explanation. Mobile Heartbeat reaches 1,328px for one switch and three selects. Zero-count database choices receive the same large treatment as actionable categories.

**Impact:** Users scan across wide gaps and scroll through repetitive structure. Text, state, and action are less closely associated than their meaning requires. The 18px checkbox glyph is not a touch-target failure: its clickable label row is at least 44px high.

**Direction:** Tighten the common state column or cap row width. Use concise routine rows and reveal secondary detail. Preserve readable text, necessary warnings, and full clickable targets. Do not reduce height by shrinking controls or hiding prerequisites.

**Evidence/confidence:** High confidence in measurements/repetition; preferred density is judgment. [Desktop Media](../.artifacts/independent-ux-audit/visual-captures/en_US-media-1440-light.png), [mobile Heartbeat](../.artifacts/independent-ux-audit/visual-captures/fr_FR-heartbeat-390-light.png), [detail measurements](../.artifacts/independent-ux-audit/visual-details.json), [label CSS](../noobrocket/assets/css/noobrocket-admin.css:183).

### UX17 — Medium: Integrations uses promotional space and state-independent copy

**Observed:** Large centered logos/symbols separate switch headers from explanations. Purpose statements and supporting paragraphs overlap. Five integration choices produce a 2,985px French mobile page. Enabled User Cache still says the user “must activate this add-on.”

**Impact:** Decoration and repeated explanation compete with checking state and configuring a service. Copy can suggest that an enabled feature remains unfinished.

**Direction:** Bring name, state, compact identity, and control together. Lead with one purpose sentence. Make activation guidance state-aware or neutral, with direct configuration steps.

**Evidence/confidence:** High confidence in current presentation; resulting task cost is judgment. [Fresh Integrations](../.artifacts/independent-ux-audit/visual-captures/fr_FR-addons-390-light.png), [tablet](../.artifacts/independent-ux-audit/visual-captures/fr_FR-addons-768-light.png).

### UX18 — Medium: mobile shell prioritizes appearance over task space

**Observed:** WordPress and NoobRocket each show a hamburger menu. NoobRocket's Appearance selector occupies a permanent second row. The app header uses about 124px below the 46px WordPress bar; route titles start around y211. The app menu has an accessible name, but no visible scope label.

**Impact:** The first viewport devotes substantial space to a secondary preference. Two visually similar menus represent different scopes. This is a hierarchy/discoverability concern, not an unlabeled-control violation.

**Direction:** Make app navigation visibly identifiable. Move appearance into that navigation or a compact control if testing supports it. Keep theme choice accessible and recheck long French headings.

**Evidence/confidence:** High confidence in geometry; preferred header treatment is judgment. [First viewport](../.artifacts/independent-ux-audit/visual-captures/fr_FR-media-390-light-viewport.png), [320px viewport](../.artifacts/independent-ux-audit/visual-captures/fr_FR-heartbeat-320-light-viewport.png), [header CSS](../noobrocket/assets/css/noobrocket-admin.css:292).

### UX19 — Medium: configure-then-operate offers discard or interruption

**Observed:** Independent forms/admin actions warn about unsaved settings with “Discard them and continue?” Cancel preserves the draft, but there is no save-and-continue path. Provider clear-cache actions sit beside unsaved credentials. Media tells users to save image delivery, then queue the library from Overview, without a direct next-step link.

**Impact:** A natural configure/test sequence requires canceling, saving, navigating, and retrying. Accepting discard can run an operation against the old persisted provider configuration. Departure protection is valuable; its workflow is incomplete.

**Direction:** Make the saved-state requirement visible. Provide save-and-continue where safe, or “Save settings first” for operations depending on changed configuration. Link image setup to its queue action. Do not silently execute an operation with draft credentials.

**Evidence/confidence:** High confidence in guard/source and cancel behavior; actual provider/queue execution untested. [admin JS](../noobrocket/assets/js/noobrocket-admin.js:90), [Cloudflare action](../noobrocket/views/settings/page-sections/cloudflare.php:30), [Media copy](../noobrocket/inc/Engine/Admin/Settings/Page.php:913).

### UX20 — Low: diagnostic submit lacks a local pending state

**Observed:** After product submit handlers run, Check cache retains its label and enabled state; its form/button has no `aria-busy`. A final audit listener canceled submission before HTTP. Global settings saving has a separate busy state.

**Impact:** Slow operations may feel ambiguous. The browser's own navigation indicator can provide feedback; this audit did not measure an actual delayed request, repeated submission, or completion response.

**Direction:** Add local pending text/state where operations have a material wait, preserving normal form submission and error recovery. Verify a real delayed response before broadening this change.

**Evidence/confidence:** High confidence in pre-HTTP state, limited confidence in real waiting experience. [Navigation/feedback evidence](../.artifacts/independent-ux-audit/navigation-feedback-evidence.json), test “Check cache submit feedback before HTTP”; [admin JS](../noobrocket/assets/js/noobrocket-admin.js:107).

### UX21 — Medium: import replaces settings without showing changed values

**Observed:** Tools accurately warns that import replaces live saved settings. The upload then submits directly, with no import-specific values preview or replacement confirmation. Import preserves three current cache keys plus version markers, but can replace supported optimization settings and provider credentials. It does not import asset rules/history. Its scope is broader than history restoration.

**Impact:** Users cannot verify which current values an old file will replace. A warning describes the operation class, but not the actual change.

**Direction:** Show a concise change summary before replacement, including credential changes without revealing secret values. Reuse existing import validation and policy normalization. Keep export's current privacy/scope warnings.

**Evidence/confidence:** High confidence in source and warning; no import executed. [Fresh Tools](../.artifacts/independent-ux-audit/visual-captures/en_US-tools-1440-light.png), [import form](../noobrocket/views/settings/fields/import-form.php:27), [import handler](../noobrocket/inc/admin/admin.php:361), [edition policy](../noobrocket/inc/free.php:44).

### UX22 — Medium: Heartbeat disable copy omits core WordPress effects

**Observed:** Copy warns that disabling Heartbeat may break plugins/themes. The selected context actually deregisters Heartbeat. Current WordPress uses it for post-lock refresh, login-expiry notices, and Heartbeat-dependent classic-editor autosave.

**Impact:** A user choosing Disable sees a broad third-party warning, but lacks concrete information about normal editing behavior. This does not establish that every block-editor autosave path is lost.

**Direction:** Describe consequences by context next to Disable. Keep Reduce as a distinct choice and explain what it changes. Verify editor-specific outcomes before stronger claims.

**Evidence/confidence:** High confidence in current source dependencies; actual editing outcomes untested. [Fresh Heartbeat](../.artifacts/independent-ux-audit/visual-captures/en_US-heartbeat-1440-light.png), [Page.php](../noobrocket/inc/Engine/Admin/Settings/Page.php:1475), [HeartbeatSubscriber.php](../noobrocket/inc/Engine/Heartbeat/HeartbeatSubscriber.php:58), WordPress `wp-admin/js/post.js:169`, `wp-includes/js/wp-auth-check.js:161`, and `wp-includes/js/autosave.js:803` in the local runtime.

### UX23 — Medium: CDN setup ends at saving rather than delivery verification

**Observed:** The page correctly explains static URL rewriting and provider ownership. CNAME instructions end with saving. There is no sample rewritten asset URL, delivery result, or verification step. Hostname sanitization can remove invalid entries; syntactic validity does not establish asset delivery.

**Impact:** Users may confuse accepted configuration with functioning delivery. No CDN outage or broken asset was observed in this audit.

**Direction:** Provide a sample rewritten URL and a clear verification step/result, including what to check when the provider rejects an asset. Keep the current bring-your-own-provider boundary.

**Evidence/confidence:** High confidence in page/source; real delivery untested. [Fresh CDN screen](../.artifacts/independent-ux-audit/visual-captures/en_US-page_cdn-1440-light.png), [CNAME field](../noobrocket/views/settings/fields/cnames.php:21), [Page.php](../noobrocket/inc/Engine/Admin/Settings/Page.php:1427), [Settings.php](../noobrocket/inc/Engine/Admin/Settings/Settings.php:572).

## Every-page assessment

All rows include English desktop and French mobile visual review in both themes. Conditional provider screens use unsaved local draft activation. Heights below include WordPress chrome/footer and current conditional content; they indicate volume, not a universal defect or a performance measurement.

| Page / route | Useful foundation | Main gaps and recommended direction | Desktop / FR mobile dark height |
|---|---|---|---|
| Overview / `dashboard` | Real probes, subsystem queues, pause/recovery, provider description. | Explain attention states and evidence freshness; align DOM order; put next actions beside problems. UX11–13, UX20. | 1,419 / 2,729px |
| Preload / `preload` | Distinct page/preload and link behavior; concise control set. | Cache parent name obscures default destination. Verification requires returning to Overview. Make configuration-to-status path explicit. UX04, UX11, UX19. | 1,025 / 1,150px |
| Cache rules / `advanced_cache` | Exclusions, cookie/query rules, and lifespan fit expert work; disclosures exist. | Secondary destination hidden initially. Broad rules need clear examples and change scope; preserve validation that already opens the invalid route. UX03–04, UX13–14, UX16. | 1,265 / 1,609px |
| CSS & JavaScript / `file_optimization` | Original CSS fallback, mutually exclusive modes, advanced exclusions. | Prerequisites/readiness separated from choice; long repetitive form; lost Back position and remote Save/navigation. UX10, UX13–14, UX16, UX19. | 2,166 / 2,804px |
| Media / `media` | Original image fallback and local generation boundary; editable lazy-load exclusions. | Save/queue split; wide checkbox association; fonts are an adjacent performance subject but weakly signposted here. Tighten routine rows and link next actions. UX14, UX16, UX19. | 1,685 / 2,117px |
| Asset Manager / `assets` | Meaningful inspection, private draft, preview, publication, protected dependencies. | Inventory overwhelms editor; raw IDs/handles; unfinished input loss; separate save semantics; buried history; narrow French select overflow. UX03, UX06–09, UX15. | 3,234 / 10,356px |
| Content Delivery / `page_cdn` | Accurate BYO provider explanation and rewriting scope. | Location label mismatch; save lacks delivery verification; state is separated from a meaningful test. UX04, UX14, UX16, UX23. | 1,025 / 1,118px |
| Database / `database` | Per-category counts, backup warning, Cancel-first dialog and Escape behavior. | No distinct Database nav label; repeated zero-count rows; review omits transaction scope. UX01, UX04, UX14, UX16. | 1,809 / 2,196px |
| Heartbeat / `heartbeat` | Context-specific controls and a reduced-frequency option. | Excessive explanation/spacing for four controls; disable consequences incomplete. UX14, UX16, UX22. | 1,025 / 1,328px |
| Integrations / `addons` | Dependency explanations and native activation toggles. | Cache/media options under Maintenance; provider configuration elsewhere; promotional space; enabled-state copy; dark logos. UX04–05, UX17–18. | 1,834 / 2,985px |
| Tools / `tools` | Export privacy/scope warning and explicit import replacement warning. | Import offers no values review; recovery link opens an unrelated workflow top. Native file chooser reflects browser locale. UX09, UX21. | 1,025 / 1,147px |
| Cloudflare / `cloudflare` | Normal labels, credential fields, separate provider action, restore logic. | Hidden external scope; split activation; silent disabled-link fallback; email uses generic text input; draft/action boundary. UX02, UX04–05, UX19. | 1,397 / 1,793px |
| Sucuri / `sucuri` | Simple credential/action boundary and readable dedicated-page branding. | Activation and configuration split; silent unavailable link; action with unsaved configuration needs saved-state guidance. UX04–05, UX19. | 1,025 / 1,118px |

## Task journeys

| Task | Current path and breakpoints | What the next design must let users understand |
|---|---|---|
| Configure and verify caching | Cache opens Preload; advanced rules are secondary; result is on Overview and covers only a homepage/PHP probe. | Which path is configured, which layer served the tested request, and what the evidence does or does not establish. |
| Enable local CSS | Choose mode; discover worker prerequisites in disclosure; open README; check Overview; interpret Waiting/Ready. | Prerequisites, saved mode, worker readiness/activity, generated-page state, and next useful action. |
| Generate existing images | Enable delivery in Media; save; find Overview library queue; monitor completion. | Difference between enabling future delivery and converting an existing library; direct next step and progress scope. |
| Inspect and publish an asset rule | Inspect page; traverse inventory; copy handle; identify scope/IDs; add private rule; preview; publish. | The exact asset and pages affected, private versus visitor state, necessary dependency protection, and preview limitations. |
| Recover a bad optimization | Find Tools link; open Asset Manager; locate history; choose time/counts; restore supported subset. | Which values will revert, which are excluded, and how the current state is retained. |
| Clean the database | Select categories/counts; review generic warning; submit all settings including other drafts. | Exact deletion categories/counts, schedule implications, unrelated changes, and backup requirement. |
| Connect a provider | Enable under Integrations; find conditional Delivery route; enter credentials/options; save; run operation. | Actual provider changes, zone/account context, saved-state requirement, result, and restore behavior. |
| Import/export | Export saved JSON with privacy warning; import a filename-constrained file; direct replacement. | Saved versus draft state, credentials included, asset/history exclusions, changed values, and verification after replacement. |

These paths were assessed through UI/source and safely intercepted interactions. Successful mutations or real visitor performance were not tested.

## Theme, typography, accessibility, and copy

### Light and dark

Both themes use coherent semantic surface/text/action/focus/success/warning/danger roles in the existing stylesheet. The dark palette is a complete theme. There is no evidence that replacing navy/blue would resolve the major findings.

Fresh calculations from current solid-color tokens give these contrast ratios:

| Pair | Light | Dark |
|---|---:|---:|
| Main text on surface | 14.61:1 | 12.96:1 |
| Helper text on surface | 6.02:1 | 7.41:1 |
| Helper text on background | 5.56:1 | 8.58:1 |
| White primary-action text | 6.20:1 | 4.67:1 |

These pairs exceed the 4.5:1 normal-text threshold. [Calculation evidence](../.artifacts/independent-ux-audit/contrast-samples.json) records the current token pairs and method. This is a sample, not certification of every rendered state, logo, border, or control. Dark Cloudflare/Sucuri marks on Integrations lose optical definition compared with their dedicated pages' existing light plates. The Tools divider also appears brighter than the shared border language. Both are lower-priority finish issues. [Dark Integrations](../.artifacts/independent-ux-audit/visual-captures/en_US-addons-1440-dark.png), [dark Tools](../.artifacts/independent-ux-audit/visual-captures/en_US-tools-1440-dark.png).

### Typography and component hierarchy

The system font fits the host product and avoids a needless font dependency. Body/help text is generally 14px with generous line height; mobile editable inputs use 16px. A larger reading size could help mobile prose, but 14px is not automatically a WCAG failure. Reducing repeated prose and fixing width is more consequential than changing fonts.

Card consistency is useful, but too many sections share the same weight. Distinguish task-critical warnings, ordinary configuration, historical evidence, and expert detail through content structure and placement. Do not solve this with more badges, animations, or decoration. The rocket and Flight Deck identity can remain while the workflow changes.

### Keyboard, forms, and assistive technology

Verified strengths: route changes focus the heading; negative lifespan on a hidden route opens it and focuses the invalid input; missing import file focuses the required chooser; cleanup focuses Cancel, supports Escape, and returns focus; mobile menu Escape returns focus; clean Save is disabled; reverting a setting restores clean state; hash navigation preserves native settings drafts; canceling a discard prompt preserves them.

The Overview order defect is confirmed in UX12. Long-page access is covered in UX13. Heading outlines in hash-navigation screenshots are focus feedback, not stray decorative borders. Preserve focus visibility while improving hierarchy.

**Additional semantic gap:** Several field explanations have no programmatic association with their control. The checkbox template renders descriptions outside the label without an ID/`aria-describedby` link. A user navigating directly between controls may not receive that explanation. Associate necessary instructions with fields and verify the result in a manual screen-reader session. This is a source-backed gap; it is not a demonstrated failure in every reading mode or a complete WCAG conformance finding. [Checkbox template](../noobrocket/views/settings/fields/checkbox.php:36), [route control inventory](../.artifacts/independent-ux-audit/route-inventory.json).

Cloudflare Account email is a text input and credentials are optional in native client validity. This does not prove the server accepts invalid credentials. Use appropriate input semantics and feature-dependent validation rather than adding a second validation framework.

### Language and copy

French expansion exposes layout weaknesses, particularly UX15. English text in the native French file chooser follows browser locale; it is not an app translation defect.

**Additional locale defect:** French Overview can show an English cached probe failure. Diagnostics stores an already translated message in a site-wide transient, then renders it for another viewing locale. The French catalogue contains a translation. Store a result code and translate on display, or otherwise bind presentation to the current administrator's locale. Keep translation text in PO files. [Fresh French Overview](../.artifacts/independent-ux-audit/visual-captures/fr_FR-dashboard-390-dark.png), [Diagnostics.php](../noobrocket/inc/Engine/Optimization/LocalCSS/Diagnostics.php:98), [probe display](../noobrocket/views/settings/partials/local-css-diagnostics.php:58).

“Help,” “More info,” and “Learn more” recur beside visible explanations. Prefer specific destinations where they improve scanning. Broken destinations were not established in this audit. Copy should describe concrete effects, saved-state requirements, and next actions rather than promise a generic performance grade.

## Strengths to preserve

- Real operational evidence, queue counts, pause controls, and recovery actions. Do not replace these with an unexplained speed score.
- Original CSS/image fallbacks and accurate explanations of provider ownership and unsupported boundaries.
- Private asset drafts, explicit publication, preview scope, required-dependency protection, and retention of current state before restore.
- Native WordPress forms, capability/nonce boundaries, settings validation, and separate operation forms.
- Visible focus, meaningful labels, 44px associated interaction rows, existing disclosures, and tested Cancel/Escape behavior.
- Coherent semantic themes, readable text, and a restrained local identity.

## Later design work and acceptance evidence

No fixes are authorized by this audit turn. Recommendations should inform a subsequent design task; they do not mandate a particular mockup or framework.

1. **Make consequential actions reviewable.** Address UX01–03 and UX21. Users must identify deletion scope, provider effects, pending settings, and imported replacements before committing.
2. **Connect setup, verification, and recovery.** Address navigation, conditional destinations, CSS readiness, provider setup, CDN verification, and history. Keep one clear path for the owner and an explicit expert path for the operator.
3. **Repair confirmed interaction/layout defects.** Align Overview DOM order; preserve reading position; fix responsive specificity and narrow translated controls; protect unfinished asset entry.
4. **Reduce task friction and repeated presentation.** Bound inventory; connect inventory to rule creation; tighten state association; simplify routine forms/integrations and mobile chrome. Preserve warnings and target sizes.
5. **Verify the resulting product.** Recheck empty/populated, enabled/disabled, clean/dirty, pending/failed, and conditional states across both themes and English/French. Use 320/390/768/1024/1440px, keyboard, screen reader, and real touch hardware.

Success should be measured by representative task completion and users' explanation of effects before acting. Suggested tasks: find Database, connect a disabled provider, determine why CSS has not generated, create a page-specific draft from inspection, select the right rollback, explain cleanup scope, and predict import changes. Record errors, assistance, and completion time; do not infer success from screenshots alone.

## Limits and preservation

This is a comprehensive local expert UI/UX audit, not user research, a complete WCAG audit, a security audit, or a production speed comparison. Real provider outcomes, import/export round trips, destructive results, worker execution, anonymous preview representativeness, every translation, and real-device behavior remain unverified. No task findings imply that NoobRocket is faster or slower than another plugin.

No product source was edited by this audit. Only audit documentation/evidence and isolated temporary QA support were used. Packaging remains stopped; the historical 1.3.0 archive does not represent the current design. The local preview remains available to the user.

After all runtime readers finished, the owned MU fixture, private browser state, baseline file, and newly created QA session were removed. Exact protected settings, asset rules, history, WPLANG, all user locale rows, and the QA account's three pre-existing sessions were preserved. The user's current CSS/image settings were not reset. No other account was written. PHP preview PID 9504 remained running. [Preservation receipt](../.artifacts/independent-ux-audit/preservation-proof.json) records the scope and result without secrets.

The consolidated report received independent visual and task/source review. Coordinates, page coverage, severity qualifications, and linked source claims were checked; citation/wording corrections were applied. Local-link validation found every audit target present, with 23 numbered findings and 13 page assessments. These documentation checks do not resolve the product defects or replace the remaining research described above.

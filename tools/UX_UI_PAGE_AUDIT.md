# NoobRocket page-by-page UI/UX audit

Date: 2026-10-09. Product: local NoobRocket 1.3.1. Status: audit complete; recommendations below are **not implemented**. This report supersedes preceding completion judgments for the current interface. The [original audit](UX_UI_AUDIT.md) and [improvement record](UX_UI_IMPROVEMENTS.md) retain their historical evidence. Packaging and deployment remain stopped.

**Second-pass update:** [all 14 pages were reviewed again](UX_UI_SECOND_PASS_AUDIT.md), with expanded catalogs, warning acceptance, dense cleanup, long comparisons and real native draft activation. That review finds additional saving/accessibility/state defects. It reproduces the provider activation transition and clarifies that current Chromium can natively scroll the review regions, while Tools focus remains invisible. The recommendations in both reports remain unimplemented.

## Verdict

The interface still needs structural work. Asset Manager, CDN, Heartbeat and Settings history have confirmed hierarchy, spacing or interaction problems. Deeper review also found a keyboard defect in Tools, misleading database deletion wording and incomplete first-time provider setup flows. Successful font, overflow and regression checks did not establish finished UI/UX.

The system font and paired light/dark palettes remain suitable. The remaining work concerns action accuracy, label/control proximity, information order, task ownership and density. The mockup is not an acceptance criterion. The preceding 90/100 estimate remains withdrawn; this audit assigns no replacement score and does not establish the requested 95/100 target.

## Method and evidence boundary

Three reviewers inspected rendered pages and responsible PHP/CSS/JavaScript layers. The consolidated review reconciles their findings and checks selected screenshots directly. Skills applied: UI/UX Pro Max for interaction, accessibility and forms; design-system for component hierarchy, spacing and states; frontend-design for task composition and concise copy. A focused `error summary validation` search returned applicable guidance on linked, focusable summaries and associated inline errors. The implementation stack remains native WordPress templates, CSS and JavaScript; no new framework or design system is proposed.

| Fresh review surface | Coverage | Evidence |
| --- | --- | --- |
| Asset Manager, CDN, Heartbeat, Settings history | 56 captures; English 1440px and French 390px; both themes; empty/populated, editor, disclosure and unsaved settings states | [Four-page proof](../.artifacts/ux-improvement/deep-four-route-audit-proof.json), [captures](../.artifacts/ux-improvement/deep-four-route-captures/) |
| Overview, Preload, Cache rules, CSS & JavaScript, Media, Integrations, Cloudflare, Sucuri | 16 route views plus selected bottom captures; English 1440px/light and French 390px/dark | [Eight-page proof](../.artifacts/ux-improvement/perpage-reader-audit-proof.json) |
| Database and Tools | 28 states, 56 captures; English/French, 1440/390px, both themes; cleanup review, scheduling, file selection, import review and unrelated unsaved edits | [Database/Tools proof](../.artifacts/ux-improvement/database-tools-audit-proof.json), [detail proof](../.artifacts/ux-improvement/database-tools-audit-detail-proof.json), [captures](../.artifacts/ux-improvement/database-tools-audit-captures/) |
| Tools keyboard follow-up | Native Tab traversal and settled scrolling with a dirty settings footer | [Focus proof](../.artifacts/ux-improvement/tools-dirty-focus-proof.json) |

Guarded browser contexts blocked operation requests and external traffic. Populated inventories, history and import proposals used request-only fixtures. No settings save, real import, cleanup, publication, provider change, diagnostic probe or worker run was executed. CDN sanitization findings are source-confirmed; they are not a submitted runtime reproduction. Earlier all-route captures cover additional widths/themes but are historical evidence, including stale Sucuri wording.

The Database/Tools matrix records two `scrollable-region-focusable` violations, one French mobile import review per theme, and six contrast-incomplete records. Incomplete results are not passes; overlap/background ambiguity limits their automated interpretation. These tests do not establish universal accessibility, human task success, native OS select behavior, RTL support or production-site performance.

**Priority:** P1 means a false action promise, concealed rejection, unusable setup boundary or confirmed keyboard defect. P2 means repeated layout, sequence, comprehension or task friction. P3 means secondary polish. Confidence is high for measured output and source behavior; predicted user impact remains an expert judgment. The priorities below are implementation order, not defect counts or a numerical score.

## Page inventory

| Page / route | Current assessment | First correction |
| --- | --- | --- |
| Overview / `dashboard` | Useful diagnostics; repeated setup destinations weaken priority | Keep worker setup in one authoritative place |
| Preload / `preload` | Single-toggle help appears before the controls; repeated next step | Pair each main toggle with its own help |
| Cache rules / `advanced_cache` | Current grouping works; only secondary reading friction found | Keep current grouping; place lifespan advice with its field |
| CSS & JavaScript / `file_optimization` | Local CSS explanations compete between mode choices and detailed setup | Separate requirements from dated diagnostic detail |
| Media / `media` | Image Dimensions and Image Delivery use different content order | Use title, control, then its help for single-control sections |
| Asset Manager / `assets` | Misleading prepare action, weak stage hierarchy and disconnected editor fields | Correct action wording before restructuring the editor |
| CDN / `page_cdn` | Silent rejected input, zero intro gap and unlabeled visible row scope | Show recoverable field errors and fix header/row structure |
| Integrations / `addons` | Improved compact cards; Cloudflare setup copy remains inaccurate | Replace domain instruction with Zone ID instruction |
| Cloudflare / `cloudflare` | Draft activation exposes an operation before usable saved credentials | Gate the operation on saved configuration prerequisites |
| Sucuri / `sucuri` | Same setup/action ambiguity; redundant action heading | Gate the operation, then align action vocabulary |
| Heartbeat / `heartbeat` | Labels and selectors remain far apart; sole-toggle help precedes its control | Pair labels and selectors at a compact measure |
| Database / `database` | Review scope works; deletion wording and repetitive density still fail | Distinguish auto drafts from authored drafts |
| Settings history / `history` | Restore review works; collapsed entries are oversized and weakly ordered | Compact the date/change summary before the disclosure |
| Tools / `tools` | Mobile import review has a keyboard defect and fragmented comparisons | Make review scrolling focusable, then stack comparisons |

## 1. Overview

**Task:** understand saved optimization state, identify work needing attention and run a clearly scoped operation.

- **OV01 · P2 · observed/source-confirmed:** worker setup appears in Local CSS generation, Server worker and the page footer. Multiple similar destinations compete with queue and status actions. Keep the authoritative setup action in Server worker; Local CSS can link there when setup is required. Remove the redundant footer setup link. Keep generation requirements and recovery information available.
- **Retain:** homepage checks explain their two-anonymous-request/PHP-header scope and qualify failures. Worker environment reports distinguish a past setup check from current scheduling/activity. Dated evidence and queue counts are useful.

**Owner/evidence:** [Local CSS diagnostics](../noobrocket/views/settings/partials/local-css-diagnostics.php:113), [second setup destination](../noobrocket/views/settings/partials/local-css-diagnostics.php:142), [Overview footer](../noobrocket/views/settings/page-sections/dashboard.php:25), [desktop output](../.artifacts/ux-improvement/perpage-reader-en_US-1440-light-dashboard.png).

**Footer follow-up — 2026-10-09:** the detached Overview footer setup link is removed locally. Worker help remains in the CSS generation task, with immediate prerequisite guidance when required and setup/scheduling instructions inside Server worker. This addresses the footer duplication identified by OV01; [focused proof](../tests/README.md#dashboard-footer-help--2026-10-09-unpackaged) records this change without claiming other historical findings are closed.

**Acceptance:** one primary setup destination; operational actions retain their saved-state scope; a failed check never claims that visitor caching is necessarily broken.

## 2. Preload

**Task:** enable automatic cache preparation or link preloading, then configure applicable exclusions.

- **PL01 · P2 · observed/source-confirmed:** Preload Cache and Preload Links introduce their help before their main checkboxes. This differs from the control-then-help pattern elsewhere. Move each description into its owning field registration: `manual_preload` and `preload_links`. Preserve the dependent cache exclusions and their disclosure. Do not reorder shared multi-option introductions globally.
- **PL02 · P2 · observed:** Overview navigation appears within help and again below the cards. Keep one next-step link beside the relevant Preload Cache help.
- **Retain:** separate cache and link preloading concepts, native toggles and available exclusions.

**Owner/evidence:** [Preload sections](../noobrocket/inc/Engine/Admin/Settings/Page.php:983), [owning cache field](../noobrocket/inc/Engine/Admin/Settings/Page.php:1007), [owning links field](../noobrocket/inc/Engine/Admin/Settings/Page.php:1035), [page footer](../noobrocket/views/settings/page-sections/preload.php:24), [mobile output](../.artifacts/ux-improvement/perpage-reader-fr_FR-390-dark-preload.png).

**Acceptance:** title, main control, its explanation, then dependencies; descriptions stay associated through the native field helper/`aria-describedby` path; one queue/status next step.

## 3. Cache rules

**Task:** set cache lifetime and background refresh, then add narrowly scoped exceptions.

- **CR01 · P3 · observed:** short-lifespan advice interrupts the two primary controls. Place it within lifespan help so lifetime and background refresh remain easy to scan together.
- **Retain:** the group introduction applies to multiple controls and should remain before the group. Lifespan already pairs label, number and unit; zero meaning remains visible. Five advanced disclosures and current 16px card gaps form a coherent hierarchy. No fresh page-specific functional defect was established.

**Owner/evidence:** [Lifespan field](../noobrocket/views/settings/fields/cache-lifespan.php), [lifetime/refresh registration](../noobrocket/inc/Engine/Admin/Settings/Page.php:1105), [advanced rules](../noobrocket/inc/Engine/Admin/Settings/Page.php:1112), [desktop output](../.artifacts/ux-improvement/perpage-reader-en_US-1440-light-advanced_cache.png).

**Acceptance:** preserve the functioning grouping and exception disclosures; keep lifetime guidance adjacent to its control without concealing zero behavior.

## 4. CSS & JavaScript

**Task:** choose compatible CSS/JavaScript optimizations and understand local CSS generation requirements.

- **CJ01 · P2 · observed/source-confirmed:** Local CSS mode has three explanatory blocks before the setup disclosure. Requirements, fallback and a dated environment report receive similar prominence. Keep the requirement and fallback visible beside the mode choice; place the recorded environment report inside Setup and requirements. Retain a status link to Overview.
- **Retain:** native Original/Critical/Used radios, explicit same-server Node.js/Chromium requirement and distinction between saved mode, worker readiness and completed generation.

**Owner/evidence:** [Local CSS select template](../noobrocket/views/settings/fields/select.php:44), [desktop output](../.artifacts/ux-improvement/perpage-reader-en_US-1440-light-file_optimization.png), [mobile output](../.artifacts/ux-improvement/perpage-reader-fr_FR-390-dark-file_optimization.png).

**Acceptance:** users can see the selected mode, necessary prerequisite and fallback without traversing diagnostic detail; the report remains dated and never implies current worker execution.

## 5. Media

**Task:** configure lazy loading, image delivery, dimensions and font behavior; find image generation after saving.

- **ME01 · P2 · observed/source-confirmed:** Image Delivery renders title, checkbox, help; Image Dimensions renders title, introduction, checkbox. Move Image Dimensions help onto the `image_dimensions` field. The inconsistency comes from registration placement, not the checkbox template. Keep the LazyLoad introduction because it covers three controls.
- **ME02 · P2 · observed:** Image Delivery explains saving and queueing the existing library, then repeats that direction after the Fonts section. Keep a single Overview next-step link directly below Image Delivery help.
- **Retain:** corrected full-width helpers, system control font, local AVIF/WebP explanation and original-image fallback.

**Owner/evidence:** [Dimensions section help](../noobrocket/inc/Engine/Admin/Settings/Page.php:809), [field registration](../noobrocket/inc/Engine/Admin/Settings/Page.php:926), [Media footer](../noobrocket/views/settings/page-sections/media.php:24), [desktop output](../.artifacts/ux-improvement/perpage-reader-en_US-1440-light-media.png), [mobile output](../.artifacts/ux-improvement/perpage-reader-fr_FR-390-dark-media.png).

**Acceptance:** both single-control image sections use the same order; the multi-control LazyLoad group retains its introduction; generation guidance stays with Image Delivery.

## 6. Asset Manager

**Task:** inspect assets, prepare and save a private rule, preview it, then publish reviewed rules.

- **AM01 · P1 · observed/source-confirmed:** inventory says **Create draft rule**, but the action only prefills and focuses the editor. The private draft remains unchanged until **Add rule to draft**. Rename the inventory action and accessible label to **Use in draft editor** or **Prepare rule**. Reuse the current handler; do not silently make this action save a rule.
- **AM02 · P2 · measured:** several ordinary French mobile editor text/select row labels sit **41.1px above their controls**: asset type, handle, scope and exceptions. URL and chooser labels have smaller gaps. A generic 44px label height plus stacked-table padding separates related content. Remove touch-height treatment from ordinary text/select labels and tighten stacked rows. Keep interactive controls and checkbox labels comfortably targetable.
- **AM03 · P2 · observed/measured:** the desktop editor occupies a **1008px** card while most controls are **126–350px** wide. Stage headings use weight 400 while field labels use 600. Long explanations place the first task action at y503 desktop/y669 French mobile and the editor at y878/y1134. These coordinates describe reviewed states, not universal page heights. Strengthen stage headings, shorten primary instructions and give the whole editor a deliberate compact measure while keeping inventory tables wide. Avoid narrowing helper text inside an oversized empty card. Remove the redundant Target/Choose a page hierarchy.
- **AM04 · P2 · source-confirmed:** rejected input is preserved, but its error notice appears at the page top, far from the editor. Add a direct editor/field link to the existing notice or return focus to an appropriate error summary. Keep the input available for correction; do not turn validation into an expiring toast.
- **Retain:** collapsed bounded inventory, dependency information, native page/post-type/template choices, unfinished-input protection, private draft/publication separation and the preview return link. An empty draft can intentionally remove published rules; do not disable publication merely because the draft is empty.

**Owner/evidence:** [Inventory action](../noobrocket/views/assets.php:72), [prepare handler](../noobrocket/assets/js/noobrocket-admin.js:250), [stage structure](../noobrocket/views/assets.php:36), [editor heading](../noobrocket/views/assets.php:87), [editor CSS](../noobrocket/assets/css/noobrocket-admin.css:321), [mobile row CSS](../noobrocket/assets/css/noobrocket-admin.css:460), [validation recovery](../noobrocket/inc/Engine/Optimization/Assets/Admin.php:95), [desktop editor](../.artifacts/ux-improvement/deep-four-route-captures/en_US-1440-light-assets-editor.png).

**Acceptance:** inventory wording accurately promises preparation; adding saves only the private draft; preview/publication keep separate actions. Labels stay visually adjacent at 390px, stage headings lead field labels and failed validation leads directly back to recoverable input. Test filled and intentionally empty publication states.

## 7. CDN

Follow-up: CD01–CD04 are [implemented and verified locally](UX_UI_SECOND_PASS_FIXES.md#follow-up-cdn-structure-and-validation). The findings below preserve the original audit evidence; CDN now retains the shared settings-card structure.

**Task:** connect configured CDN hostnames to static files, save them and verify saved rewriting.

- **CD01 · P1 · source-confirmed:** nonempty invalid hostnames return false in sanitization and are silently removed by `array_filter`. The CDN enabled setting can remain enabled. No invalid Save was submitted during the audit. Use the existing settings-error path to identify the rejected row and keep rejected input recoverable for correction. Preserve server validation; do not store an invalid hostname as active configuration. Blank unused rows can still be omitted normally.
- **CD02 · P2 · measured/source-confirmed:** the page introduction ends exactly where the first card starts: **0px separation**. The introduction sits outside the section header that owns bottom spacing. Put the title and introduction under coherent header ownership, with a normal 16px separation before the settings card. Do not add a global margin that changes every field helper.
- **CD03 · P2 · observed:** page introduction, section introduction and CNAME help repeat provider/CNAME/save instructions before the fields. Consolidate them into a short page purpose plus field-specific guidance.
- **CD04 · P2 · observed/source-confirmed:** row hostname and file scope have accessible names but lack corresponding visible row labels. Show Hostname and Files to serve labels or clear column headings, including stacked mobile rows. Retain unique row associations.
- **Retain:** 44px Remove buttons with visible icons, native change/focus behavior, saved-row dirty ownership and saved-delivery verification with recovery guidance. The saved-state sample must remain distinct from pending edits.

**Owner/evidence:** [Sanitization](../noobrocket/inc/Engine/Admin/Settings/Settings.php:572), [existing error mechanism](../noobrocket/inc/Engine/Admin/Settings/Settings.php:343), [enabled-state sanitization](../noobrocket/inc/Engine/Admin/Settings/Settings.php:359), [page header](../noobrocket/views/settings/page-sections/page-cdn.php:31), [section copy](../noobrocket/inc/Engine/Admin/Settings/Page.php:1412), [CNAME rows](../noobrocket/views/settings/fields/cnames.php:38), [new-row template](../noobrocket/views/settings/fields/cnames.php:73), [desktop output](../.artifacts/ux-improvement/deep-four-route-captures/en_US-1440-light-page_cdn-clean.png).

**Acceptance:** invalid nonempty input produces an actionable, associated error and remains available to correct; active settings remain valid. Header/card separation is visible in both themes. Added and saved rows have the same visible/accessible labels and preserve focus/dirty tracking after removal.

## 8. Integrations

**Task:** decide which installed service integrations apply, enable one and reach its configuration.

- **IN01 · P2 · source-confirmed:** Cloudflare help requests a **domain**, but its configuration requires a **Zone ID**. Correct the source instruction and PO translations to describe the actual required credential/identifier.
- **IN02 · P3 · observed:** Varnish uses two paragraphs for one conditional effect. Combine the site prerequisite and cache purge behavior into one concise instruction.
- **Retain:** compact identity, state and purpose; direct provider configuration destinations. Heading, toggle and help order is already coherent here.

**Owner/evidence:** [Cloudflare integration instruction](../noobrocket/inc/Engine/Admin/Settings/Page.php:1605), [Zone ID field](../noobrocket/inc/Engine/Admin/Settings/Page.php:1822), [Varnish help](../noobrocket/inc/Engine/Admin/Settings/Page.php:1662), [desktop output](../.artifacts/ux-improvement/perpage-reader-en_US-1440-light-addons.png).

**Acceptance:** integration copy matches its destination's fields in English/French; enabling a draft leads to configuration without implying that a usable connection has been saved.

## 9. Cloudflare

**Task:** save the required Cloudflare configuration, understand concrete zone changes and run a saved-state cache action.

- **CF01 · P1 · observed/source-confirmed:** the rendered configuration view exposes an active purge action beside empty credentials. Source confirms configuration visibility follows the draft integration toggle without a saved-prerequisite gate on the action. Consequently, first-time draft activation can expose an operation before usable saved credentials; that transition was not independently reproduced in this fresh capture matrix. Existing saved-state explanations do not make the button usable. Gate the operation on saved integration/required-field prerequisites and say what must be saved first. Presence of fields is not proof that remote authentication succeeds; do not invent a Connected status without evidence.
- **CF02 · P2 · observed:** Zone ID lacks retrieval help while API key has it. Add a concise Find your Zone ID link beneath the field, using a verified provider help destination when implemented.
- **CF03 · P3 · judgment:** Relative protocol is a secondary compatibility choice in the separate Cloudflare settings card. Consider disclosing its detail to emphasize the more consequential Optimal settings changes. Keep actual zone changes visible.
- **Retain:** exact zone-change disclosure and restoration limits, saved-state operation review, provider prerequisites and clear credential fields. No provider call was made.

**Owner/evidence:** [Draft provider visibility](../noobrocket/assets/js/noobrocket-settings.js:2795), [purge action](../noobrocket/views/settings/page-sections/cloudflare.php:44), [Zone ID](../noobrocket/inc/Engine/Admin/Settings/Page.php:1821), [Relative protocol](../noobrocket/inc/Engine/Admin/Settings/Page.php:1849), [desktop output](../.artifacts/ux-improvement/perpage-reader-en_US-1440-light-cloudflare.png).

**Acceptance:** disabled, first-time draft-enabled, saved incomplete and saved configured states give distinct instructions. Operations use saved configuration; draft fields never imply readiness. Exact provider effects stay reviewable before saving.

## 10. Sucuri

**Task:** save a firewall API key, then clear the provider cache using saved configuration.

- **SU01 · P1 · observed/source-confirmed:** the rendered configuration view exposes an active cache action with an empty key. Source confirms the same draft-toggle visibility path as Cloudflare, so unsaved activation can reveal an operation before a usable saved key exists; that first-time transition was not independently reproduced in the fresh captures. Apply the same saved-configuration prerequisite pattern, using native Sucuri fields and actions.
- **SU02 · P2 · observed:** the second heading repeats Sucuri rather than identifying the task. Use **Sucuri cache** and **Clear Sucuri cache** to align vocabulary with Cloudflare.
- **Retain:** the current **Firewall API key** label and key-format help are correct. Earlier captures showing the longer label are stale. Keep key privacy and saved-state action ownership.

**Owner/evidence:** [Cache section/action](../noobrocket/views/settings/page-sections/sucuri.php:32), [cache action](../noobrocket/views/settings/page-sections/sucuri.php:45), [current key registration](../noobrocket/inc/Engine/Admin/Settings/Page.php:1903), [fresh mobile output](../.artifacts/ux-improvement/perpage-reader-fr_FR-390-dark-sucuri.png).

**Acceptance:** first-time activation leads to saving a key; the cache action remains unavailable with explicit guidance until saved prerequisites exist. Labels identify configuration versus operation without redundant branding.

## 11. Heartbeat

**Task:** control Heartbeat frequency per WordPress context while understanding effects on editing and plugins.

- **HB01 · P2 · measured:** desktop label-text-to-select gaps are **454–501px**. A full-width row grid pushes selectors to the opposite edge. Pair each context label and select using a compact row or stacked label/control structure; keep its helper available at the intended content width.
- **HB02 · P2 · observed/source-confirmed:** Background activity explains its sole option before Control Heartbeat. Move its section description onto `control_heartbeat`, matching the other single-control corrections.
- **HB03 · P3 · judgment:** the rows warn about Disable even while Reduce activity is selected. Keep consequence warnings, but make the selected behavior/effect easier to identify so the warning is not mistaken for the current state. Dynamic copy is optional; clear conditional wording is sufficient.
- **Retain:** context-specific consequences for session notices, post locks and Heartbeat-dependent classic-editor autosave. Reduce means one request every two minutes; Disable stops Heartbeat in the selected context.

**Owner/evidence:** [Row grid](../noobrocket/assets/css/noobrocket-admin.css:233), [Background activity section](../noobrocket/inc/Engine/Admin/Settings/Page.php:1473), [owning checkbox](../noobrocket/inc/Engine/Admin/Settings/Page.php:1502), [desktop output](../.artifacts/ux-improvement/deep-four-route-captures/en_US-1440-light-heartbeat-neutral-clean.png).

**Acceptance:** every context label reads as belonging to its select at desktop and mobile widths. Main control precedes its help; Reduce/Disable effects remain accurate and accessible. Do not remove warnings to make the page shorter.

## 12. Database

**Task:** choose cleanup categories, review exact deletion/saving scope and optionally schedule repeat cleanup.

- **DB01 · P2 · source-confirmed:** the Post Cleanup introduction says revisions and drafts will be deleted and the count says draft, while the native cleanup selects **auto-draft**. Explicitly distinguish auto drafts from authored drafts in labels, counts and review text. This is wording correction, not permission to broaden deletion.
- **DB02 · P2 · observed/measured:** seven category labels repeat full count sentences and in your database. The first desktop card is **405.56px** high; Comments is **295.36px**. Single transient/table cards are about **204px**. Compact label/count rows and remove repeated location phrases while preserving clear category scope and comfortable checkbox targets.
- **DB03 · P2 · observed/source-confirmed:** scheduling is unchecked but Frequency remains visible/enabled with Daily selected. Make its conditional relationship clear and hide or disable the control appropriately when scheduling is off. Preserve the saved frequency and native submit semantics when changing presentation.
- **Retain:** cleanup review names selected categories/counts, schedule and unrelated changed settings. Cancel preserves the draft. Counts can change before execution; history cannot recover deleted data. Multi-option Post/Comments introductions remain useful.

**Owner/evidence:** [Deletion scope copy](../noobrocket/inc/Engine/Admin/Settings/Page.php:1254), [draft count](../noobrocket/inc/Engine/Admin/Settings/Page.php:1300), [native auto-draft selection](../noobrocket/inc/Engine/Admin/Database/OptimizationProcess.php:68), [category registration](../noobrocket/inc/Engine/Admin/Settings/Page.php:1286), [scheduling](../noobrocket/inc/Engine/Admin/Settings/Page.php:1356), [mobile unchecked schedule state](../.artifacts/ux-improvement/database-tools-audit-captures/fr_FR-390-dark-database-clean-full.png).

**Acceptance:** authored drafts are never described as targeted by auto-draft cleanup. Category/count rows remain scannable in French. Schedule dependency is obvious; turning it off/on preserves the intended frequency. Keep the consequential review boundary intact.

## 13. Settings history

**Task:** compare a retained snapshot with current saved state and deliberately restore its supported settings/rules.

- **SH01 · P2 · measured/observed:** collapsed entries consume **205.5px desktop / 223.1px French mobile** for a date, counters, names and disclosure. Date weight 400 is weaker than counters/actions. Combine date and change count in a compact desktop header, strengthen date hierarchy and reduce repeated vertical margins. Keep mobile stacking.
- **SH02 · P2 · observed:** the dirty global Save footer competes with Restore even though the actions own different state. Apply the shared Save correction below; restoration should continue to compare saved values rather than silently incorporate pending settings. Scope text lives inside expanded reviews and remains useful at that action boundary.
- **Retain:** empty-state explanation, current-versus-restored comparisons, required acknowledgement, credential/private-draft exclusions and saving current state before restore. Expanded comparison tables should retain their full useful width.

**Owner/evidence:** [Entry structure](../noobrocket/views/history.php:29), [restore review scope](../noobrocket/views/history.php:63), [history CSS](../noobrocket/assets/css/noobrocket-admin.css:391), [French mobile output](../.artifacts/ux-improvement/deep-four-route-captures/fr_FR-390-dark-history-list-with-draft.png).

**Acceptance:** collapsed entries make date and extent of change easy to compare without oversized empty space. Review still names before/after values and final restore scope. Global Save and Restore are visibly separate transactions.

## 14. Tools

**Task:** export settings or stage an import, compare saved/imported values and confirm the reviewed proposal.

- **TO01 · P1 · measured/automatically detected:** French mobile import differences scroll within a **384px** region containing **432px** of content. The container has no explicit focusability/name and no focusable descendants; `tabIndex` is -1. Axe reports `scrollable-region-focusable` in both themes. Reuse the existing named, keyboard-focusable inventory-region pattern. This is a confirmed focusable-scroll-container defect, not proof that every browser/user cannot reach a row.
- **TO02 · P2 · observed/measured:** three fixed comparison columns share a **314px** mobile area with **24px** cell padding. Words break into fragments. Reuse History's labeled stacked mobile comparison pattern while retaining table semantics/desktop columns.
- **TO03 · P2 · observed/source-confirmed:** review includes raw labels such as cloudflare api key, ambiguous Frequency, JSON `[]`/arrays and French saved Désactivé beside imported English On. Build human-readable labels with section context from existing registration metadata; use consistent localized display vocabulary and readable empty/list values. Keep all credential masking and native value semantics.
- **TO04 · P2 · measured/observed:** desktop file chooser ends at x728 while Upload starts at x1269: **541px separation**. Upload/Download labels wrap despite **1008px** content. Legacy expanding columns detach actions from their inputs; mobile buttons center while headings/fields align left. Pair each input and action in its own compact task group; use available button width and consistent left alignment.
- **TO05 · P3 · observed/source-confirmed:** Export uses a body-sized div and Import a label instead of clear task headings. Oversized outline icons and padding/margins create about **60px** between title and first task. Use semantic headings, restrained icon size and normal task spacing.
- **Retain:** independent, non-nested forms; selecting a file does not dirty global settings. Unrelated edits correctly reveal Save. Credentials stay hidden; reviewed import has expiration, saved-state checks and explicit confirmation. No real upload/import was performed.

**Owner/evidence:** [Scroll region](../noobrocket/views/settings/fields/import-form.php:50), [comparison rows](../noobrocket/views/settings/fields/import-form.php:54), [label metadata](../noobrocket/inc/Engine/Admin/Settings/Render.php:564), [import task](../noobrocket/views/settings/fields/import-form.php:80), [Tools tasks](../noobrocket/views/settings/page-sections/tools.php:36), [review CSS](../noobrocket/assets/css/noobrocket-admin.css:406), [mobile comparison](../.artifacts/ux-improvement/database-tools-audit-captures/fr_FR-390-dark-tools-import-review.png), [desktop task layout](../.artifacts/ux-improvement/database-tools-audit-captures/en_US-1440-light-tools-clean.png).

**Acceptance:** keyboard users can focus/scroll the named differences region. French mobile comparisons read as labeled saved/imported values without fragmented words. Display values are consistently translated, credential masking is unchanged and actions remain beside their inputs.

## Shared corrections and design rules

| ID / priority | Confirmed issue or governing rule | Responsible layer / correction |
| --- | --- | --- |
| GL01 · P2 | Dirty mobile Save occupies approximately **195–196px** at 390px. Scope repeats outside/inside review and competes with Add, Publish, Restore and Upload. | [Save footer](../noobrocket/views/settings/page.php:65), [footer CSS](../noobrocket/assets/css/noobrocket-admin.css:163). Keep concise status, **Save optimization settings** and review count; retain detailed scope inside review. Preserve ownership of separate forms and reviewed operations. |
| GL02 · P2 | Single-option sections place help inconsistently because section descriptions precede fields while checkbox descriptions follow the control. | [Section renderer](../noobrocket/views/settings/sections/fields-container.php:30), [checkbox renderer](../noobrocket/views/settings/fields/checkbox.php:55), field registration in `Page.php`. Correct only owning single-option registrations: Image Dimensions, Preload Links, Background activity, Transients Cleanup, Database Cleanup; apply equivalent main-toggle treatment to Preload Cache while keeping exclusions. Retain multi-option introductions. |
| GL03 · P2 | Wide container geometry and oversized noninteractive labels can detach controls from their labels. | Use adjacent or stacked label/control groups at the responsible component. Keep tables wide when comparison needs it; use a deliberately sized whole editor/task group for compact forms. Do not reintroduce arbitrary narrow helper columns inside wide cards. |
| GL04 · P2 | Titles, stage headings, fields, explanatory copy and operations do not always reflect task priority. | Use consistent heading weight/scale and meaningful workflow boundaries. Cards should group a task, not every sentence. Prefer concise primary help plus native disclosure for secondary evidence. |
| GL05 · P2 | Draft configuration visibility and saved operation readiness are different states. | Extend existing provider/action ownership rather than add another workflow. Gate operations on saved local prerequisites and show the next setup step. Do not claim remote connection success from populated fields. |
| GL06 · P2 | Failed validation must lead back to correctable input. | Use existing settings errors, linked summaries, inline field association and preserved rejected input. Routine completed successes can stay as theme-aware toasts; actionable errors/recovery remain persistent. |

The mobile footer initially covers Tools Upload, but native Tab traversal plus settled scrolling reveals it. This is a density/initial-visibility problem, **not** a confirmed permanently unreachable control or focus-obscured failure. Earlier font tests and four native import-review contexts confirm the system UI font; apparent text awkwardness there comes from fragmented column layout, not a remaining serif-font defect.

Retain the requested 14 direct navigation links/icons and hidden visible scrollbar. Do not introduce a top menu or new categories to solve content layout. Preserve current light/dark semantic colors, visible focus, form names/nonces, private/saved/published state boundaries and translation ownership in PO catalogs. New frontend strings should use WordPress translation functions with catalog updates, not language branches in code.

## Implementation order and completion evidence

1. Fix P1 action accuracy, CDN rejection/recovery, provider saved prerequisites and Tools keyboard scrolling.
2. Fix the shared single-control registration order and compact Save footer without changing transaction scope.
3. Recompose the four requested pages: Asset stage/editor hierarchy, CDN header/row labels, Heartbeat label pairing and History summary density.
4. Correct Database scope/count/schedule presentation and Tools stacked comparisons/task actions; then remove repeated setup/help on the remaining pages.
5. Verify changed output at real desktop/mobile sizes in English/French and both themes. For local handler changes, use focused isolated tests rather than real destructive/provider operations. Recheck keyboard focus, selected/saved/draft states, associated errors and intentionally empty asset publication. A new score requires a stated rubric and evidence after these corrections; passing a screenshot matrix alone is insufficient.

No product code changed during this audit. Documentation and QA artifacts/helpers record its findings and evidence. [Cleanup receipt](../.artifacts/ux-improvement/reaudit-preservation-proof.json) records `PASS_CURRENT_STATE_CLEANUP`: the fresh baseline and current protected rows/other sessions were preserved; only the owned session/request fixture were removed. Three pre-existing user-1 sessions remain. Unknown earlier baseline drift stays recorded; no old settings were restored. The preview server PID 9504 remains alive and its homepage returned HTTP 200. No runtime reader or command remains active from the audit.

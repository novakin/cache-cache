# Cache-Cache WordPress UI guidelines

The maintained templates and shared styles define the interface. Flight Deck mockups are historical references for hierarchy and restraint; they do not override real settings, validation, evidence or recovery. This document defines design expectations and acceptance checks. Current implementation and verification belong in [the full-system reaudit](FULL_SYSTEM_REAUDIT.md), [the batch record](batches/BATCH_FIXES.md) and [tests/README.md](../tests/README.md). Earlier audits and the [UI improvement record](UX_UI_IMPROVEMENTS.md) retain their recorded checkpoints.

Use the supplied Cache-Cache light/dark outlined horizontal SVGs with intrinsic dimensions and one accessible product name; decorative logo images have empty alternative text. Map brand colors through the existing semantic variables and retain distinct success, warning and danger colors. Keep backend identifiers and the `noobrocket` text domain stable. Branding is a display change, not a settings migration.

## Hierarchy and layout

Show the page’s main task first: its heading, essential controls and immediate prerequisites. Follow with optional configuration, then evidence and recovery. Keep important failures and destructive scope visible. Use short explanations that describe what an action changes.

Use one surface per related task, with divided rows for its settings. Avoid a card for each switch, repeated headings inside the same group and decorative status panels. Independent workflows can use separate surfaces: Asset Manager inspection, draft editing and publication have different ownership.

Paired dashboard cards share their row's height through native grid stretching. Their body content grows, and navigation uses a sibling footer with shared padding. Preload/CSS summary footers align even when one task exposes recovery controls. Expanded evidence grows the row; narrow stacked cards keep natural heights. Do not force all app cards to a fixed height or fabricate a footer when no related destination is needed. A recorded check groups its heading, one outcome badge and action together, with the visible check date beside its result. [Dashboard refinement proof](batches/DASHBOARD_CARD_REFINEMENT.md) records the earlier layout correction; the overview content review covers the current summaries.

All routes use the same shell and `.noobrocket-Content` width: full available space up to 1200px, with shared responsive padding. Keep source order equal to reading and keyboard order. Do not use CSS ordering to repair misplaced content. Keep the existing icon navigation, direct provider destinations and compact Settings control; hide the scrollbar without removing access to links. The compact menu starts at 1100px in both CSS and JavaScript; WordPress admin-bar offsets retain their platform breakpoints. Reuse WordPress’s main landmark; the plugin content is the named `#noobrocket-main` region, and its branding remains a div rather than another main or banner.

Owners: [page.php](../noobrocket/views/settings/page.php), [navigation.php](../noobrocket/views/settings/navigation.php), [noobrocket-admin.css](../noobrocket/assets/css/noobrocket-admin.css).

## Controls and disclosures

| Control | Required presentation |
| --- | --- |
| Ongoing feature | Label and concise purpose at the start of its row; switch at the end. Keep the native checkbox, name, value and label association. |
| Cleanup selection | Left checkbox selects data for deletion. Show its count separately at the end of the row. Do not make a deletion selection look like an immediate feature switch. |
| CSS delivery | Compact native radio choices for Original CSS, Critical CSS and Used CSS. Keep their short purposes and shared worker requirements. Avoid oversized choice cards. |
| Select | Label and control align across desktop rows. Stack label, control and help on narrow screens. Preserve native selection and keyboard behavior. |
| Secret field | Visible label, masked value and named Show/Hide control. Keep setup instructions and validation nearby. |
| Optional configuration | Native `details`/`summary`, concise title and Configured indication where supported. Closing a disclosure preserves values and submission. |

Keep exclusions beside the feature they qualify. Hide optional detail through disclosures; do not hide warnings that determine whether a main action is safe or available. Validation opens ancestor disclosures and reveals the owning route before native focus. Nonempty or rejected Asset Manager exceptions remain exposed. Avoid placing Help or other interactive controls inside a disclosure summary.

Operational descriptions span the full row beneath their labels. Reserve space for an end switch only beside the label. Informational helpers containing their own disclosure omit the legacy important icon; warning and failure indicators remain visible.

Owners: [Page.php](../noobrocket/inc/Engine/Admin/Settings/Page.php) registers groups and copy; [Render.php](../noobrocket/inc/Engine/Admin/Settings/Render.php) owns shared field rendering; [checkbox.php](../noobrocket/views/settings/fields/checkbox.php), [select.php](../noobrocket/views/settings/fields/select.php) and [fields-container.php](../noobrocket/views/settings/sections/fields-container.php) own markup. [noobrocket-settings.js](../noobrocket/assets/js/noobrocket-settings.js) owns native page navigation and invalid-field reveal; keep its maintained minified artifact current when changing it.

## Feedback and action ownership

Standalone outcomes use the existing lower-right toast tray, positioned above a visible Save footer. They remain readable until dismissed or the document is left. Preserve severity, instructions, links, lists, readonly recovery content and caller dismissal requirements. Do not flash a native light banner before converting it to a themed toast. No-JavaScript and failed-initialization paths must retain readable native feedback.

Independent action submissions use the same tray for contextual pending feedback; retain the busy button and duplicate-submit guard. Cancelled submissions and restored pages remove pending feedback. Settings Save keeps its own saving state. Persistent queues remain in their task surface: unfinished discovery, scheduled work and a recorded worker report must never imply a process is currently running. Show prerequisites and historical runtime evidence in labeled rows within the existing disclosure.

Field validation and publication conflicts remain with the relevant field or workflow. Errors use danger colors, warnings use warning colors, success uses success colors, and informational states use the accent. Text and icons identify severity; color alone is insufficient. Failure must never inherit an informational appearance.

Feature confirmation warnings stay inline with the affected control. Use the shared warning tokens, compact logical spacing and explicit `type="button"` confirmation controls. Check the opened state and all painted pseudo-elements in both themes; testing only the element's background misses inherited overlays. Preserve prerequisites, deliberate opt-in, native change events, returned focus and cancellation. [Opened warning proof](batches/INLINE_WARNINGS_FIX.md) records the corrected component and evidence limits.

Keep dashboard operation buttons separate from settings navigation. Place related destinations in a compact divided task footer, using chevrons and hover/focus underlines rather than permanent underlines beside buttons. Use the shared Help disclosure for optional task guidance. Required setup links stay with their prerequisite message. Preserve native anchor navigation and 44px targets; do not place a loose explanatory link beside an operation button.

Keep specialist help in its owning task. CSS worker setup belongs beside Local CSS generation when it is a prerequisite; otherwise keep it in the full-width CSS worker details disclosure below the queue summaries. Show one setup destination, not duplicate links. Disabled or paused generation keeps previous worker reports collapsed and does not present them as active warnings.

| Workflow | Ownership |
| --- | --- |
| Global Save / Cancel | Save submits pending native settings together. Show changed sections. Cancel restores the current document's loaded controls and dynamic CDN rows in place, without reloading; keep route, appearance, saved private asset draft and logical focus. It does not fetch a newer baseline from another tab. Keep the existing unfinished editor warning. |
| Asset Manager | Add/Remove saves a private draft. Inspection previews it. Publish replaces visitor rules only after explicit review and confirmation. Unchanged publication is disabled; clearing an existing published rule list keeps its deletion warning. |
| Saved-draft preview | Open the entered page in a new tab without leaving, discarding or locking the editor. Enhance the native POST/new-tab fallback to a real link for browser tab/window controls. Preview uses the saved draft; retain capability, nonce, reviewed revision and same-site frontend URL checks. All mutations remain POST-only. |
| Provider cache action | Uses saved credentials. Show readiness and disable unavailable actions with a visible explanation. Pending global settings have a separate save/discard decision. |
| Cleanup | Independent native action submits only its confirmed deletion categories and nonce. Show scope/counts and any unsaved edits that leaving the page would lose; never submit unrelated pending settings or change recurring cleanup. No-JavaScript cleanup explicitly uses saved categories. Settings history cannot recover deleted data. |
| Restore / Import | Review concrete saved-state differences before replacement. Active import review comes before transfer controls. Preserve confirmation, conflicts, credential handling and recovery. |

Owners: [noobrocket-admin.js](../noobrocket/assets/js/noobrocket-admin.js), [noobrocket-appearance.js](../noobrocket/assets/js/noobrocket-appearance.js), [notices.php](../noobrocket/inc/admin/ui/notices.php), [assets.php](../noobrocket/views/assets.php), [history.php](../noobrocket/views/history.php), [import-form.php](../noobrocket/views/settings/fields/import-form.php).

## Visual rules

Reuse existing semantic variables from `noobrocket-admin.css`: `--nbr-background`, `--nbr-surface`, `--nbr-text`, `--nbr-muted`, `--nbr-border`, `--nbr-accent`, `--nbr-action`, `--nbr-control`, `--nbr-focus` and the success/warning/danger pairs. Both themes share structure and semantics. Do not add a second palette or per-page font system.

Use the system UI family for interface text and the existing monospace family for code. Current desktop body text is 14px with approximately 1.55–1.6 line height; narrow-screen body text is 15px and input text is 16px. Reuse the 4/8/12/16/24px spacing scale. Bound explanations through placement and concise copy, without leaving arbitrary half-width text columns.

Provide 44px targets for action controls, labels and disclosure summaries, even when their visible checkbox or switch is smaller. Keep visible focus, logical labels, readable disabled states and text contrast of at least 4.5:1. Focus must clear the sticky header, Save footer and toast tray. Respect reduced motion. Prevent page-level horizontal overflow; long inventories and comparisons may use named, keyboard-accessible local scroll regions.

All source messages use English gettext with the `noobrocket` domain. French belongs in PO/MO catalogs, including labels, errors and dynamic statuses. Verify French wrapping instead of adding hardcoded line breaks or language-specific source strings. Maintain catalogs through [update-translations.py](update-translations.py).

Use native `_n` for resource, rule and settings counts, then compose localized fragments with positional placeholders. Do not build sentence grammar from an English plural suffix. Compile and verify the catalog's actual plural forms rather than assuming every locale has two forms.

Without JavaScript, retain static navigation and native forms/results. Hide controls that require JavaScript, such as compact-menu toggles, Appearance, dynamic hostname Add, secret Show and AJAX checks. State a material limitation beside its task. Do not leave an enabled-looking inert button or hide every destination behind an unavailable compact menu.

## Per-page expectations

| Route | Main task and supporting structure |
| --- | --- |
| Overview `dashboard` | Homepage cache result first, with check action, timestamp and visible homepage-only scope. Preloading and CSS show waiting/running counts before evidence; CSS counts and Retry share the saved mode. Images distinguish saved enablement, encoder availability, scheduled work and recorded scans. Object cache explains standard/persistent behavior before provider details. PageSpeed shows public-address/key prerequisites before configuration and Performance/FCP/LCP before secondary scores. Preserve failure evidence and useful recovery. |
| Preload `preload` | Cache warming first. Next-page prefetch and prerender are independent choices; keep their exclusions beside them. Disclose queue detail and optional configuration. |
| Cache rules `advanced_cache` | Show essential cache policy and lifespan together. Fold exclusions and specialist rules; keep configured state and cache prerequisites discoverable. |
| CSS & JavaScript `file_optimization` | CSS delivery choices, then JavaScript delivery. Operational switches share divided rows. Keep worker requirements and warnings adjacent; disclose exclusions and advanced delivery controls. |
| Media `media` | Group lazy loading, image delivery and dimension behavior by task. Keep dependent options and exclusions with their parent. Explain unavailable legacy WebP compatibility without hiding the reason. |
| Asset Manager `assets` | Inspect a page, edit a private draft, then preview and publish. Compact inventory summary; disclose metadata and source/dependency detail. Preserve editor reachability, recognizable scope choices, retained errors and publication boundaries. |
| CDN `page_cdn` | Enable delivery, edit hostnames and resource scope, then verify. Align rows and remove controls; expose hostname errors inline. Keep excluded files and verification evidence secondary. |
| Integrations `addons` | Two grouped surfaces with divided rows. Name/purpose and end switch stay together. Provider Configure links sit beside provider identity; compatibility detail is disclosed. |
| Cloudflare `cloudflare` | Credentials and saved readiness first. Specialist provider changes are disclosed with their concrete effects. Cache action sits beside its heading, with visible prerequisites. |
| Sucuri `sucuri` | Required firewall key and its format first. Compact cache action with saved readiness; retain disabled prerequisites and setup links. |
| Heartbeat `heartbeat` | Enable control, then aligned choices for each WordPress context. Keep their consequences nearby; stack controls on narrow screens. |
| Database `database` | Left deletion selections and separate counts in divided groups. Cleanup and its review stay with selection scope; recurring scheduling is separate. Confirmation exposes deletion scope and pending edits that leaving would lose. It never saves those edits. |
| Settings history `history` | One compact expandable list. Each summary identifies date, change counts and affected settings; full comparisons and confirmed native restore stay inside. Scope and limits remain discoverable. |
| Tools `tools` | Active import comparison and decision first, then one divided Export/Import surface. Keep concise export copy left and Download right. Place the native file picker and upload-and-review action together on desktop; stack them on narrow screens. State transfer scope once. Preserve credential privacy, review expiry, conflicts and recovery. Asset Manager and Settings history stay in the main menu; do not duplicate them as Tools footer shortcuts. |

## Acceptance checklist

Overview cards use one operational state, a short explanation, current count or recorded time where meaningful, then the useful action and secondary evidence. "Enabled" is a configuration fact, not proof that a feature completed work. Completed jobs do not prove current coverage of the whole site; a scheduled batch does not prove that it ran. Never show percentages, ETAs or whole-site health grades without the corresponding coverage data. Long translated headings and status badges wrap at narrow widths instead of competing for the same line. [Overview content review](batches/OVERVIEW_CONTENT_REVIEW.md) records the current card contracts and evidence limits.

For a single evidence fact, keep its label and value together at their natural width, wrapping when necessary. Show the result before secondary methodology or scope copy; reserve the full-width facts grid for multi-row evidence. Homepage cache details use this compact treatment without adding another card or repeating the outcome badge.

- Review all 14 routes at real output sizes in light and dark. Compare alignment, row spacing, surface hierarchy and warning placement across pages, not only against the mockup.
- Operate every changed switch, radio, select, disclosure and dismiss control with keyboard. Check focus after navigation, invalid submission, cancellation and dismissal; test Back and direct hash links. Cancel must make no document request and restore categorized selection badges/parent state as well as child controls.
- Check off/on/disabled, empty/configured, clean/dirty, pending/success/error and recovery states. Verify saved settings, private drafts and published rules remain distinct.
- Check delayed bootstrap, native-notice relocation and no-JavaScript feedback. Outcomes must remain readable without an expiry timer or a light-theme flash.
- Check long French labels, URLs, exclusions, inventory handles and restore values. No page overflow, clipped controls or hidden required validation.
- Run focused existing checks before broad UI verification. Record failures and actual evidence; do not assign a design score from test counts. Preserve the active preview's settings, drafts, history, locales and pre-existing sessions during native UI review; mutation fixtures belong in a marked disposable runtime.

| State matrix | Required coverage |
| --- | --- |
| Baseline | All 14 routes × English/French × light/dark × desktop 1440px and narrow 390px. |
| Tight layout | 320px for shared controls, menus, Save/Cancel, toasts, provider credentials, inventory and comparisons. |
| Settings | Clean, dirty, saved, canceled, parent disabled, configured disclosure and invalid hidden field. |
| Operational workflows | Empty/populated inspection; unchanged/changed/stale draft; restore/import comparison; cleanup selection/schedule; provider unavailable/ready. |
| Feedback | Success, information, warning, error, nondismissible and interactive recovery; focused control near footer/tray; delayed script and no-JavaScript. |

Existing focused checks: [appearance](../tests/settings-appearance-browser.mjs), [native notices](../tests/settings-notices-browser.mjs), [transactions](../tests/settings-transactions-browser.mjs), [CDN](../tests/cdn-settings-browser.mjs), [Asset Manager/history rendering](../tests/wordpress-assets-ux.php), [isolated asset/history regressions](../tests/wordpress-assets-second-pass.php), [import](../tests/wordpress-settings-import.php) and [Cloudflare validation](../tests/wordpress-cloudflare-settings-notices.php). Use the marked disposable WordPress site where a fixture requires runtime access; review local state preservation and actual screenshot evidence separately from isolated assertions.

# NoobRocket design re-audit

Date: 2026-10-09. Maintained app: `noobrocket/`. Status: audit complete; corrections outstanding. No packaging, production deployment or live settings changes.

The earlier **90/100 assessment is withdrawn**. This review gives no replacement score. The app has reproduced keyboard and unsaved-work defects, a severe confirmation-contrast failure, and inconsistent task/component ownership. Passing tests and a sound palette do not establish a coherent, usable app.

The main weakness is structural: shared defaults sit over legacy styles without fully owning states, pseudo-elements and variants. Equivalent tasks then use different surfaces, spacing, action placement, feedback and saving rules. Adding isolated page overrides would continue that pattern.

## Evidence and limits

Independent source reports:

- [Component system](../.artifacts/design-reaudit/system-findings.md): tokens, geometry, cascade, semantic states, buttons, help and controls.
- [Every page and control family](../.artifacts/design-reaudit/page-findings.md): all 14 destinations, conditional templates, populated/narrow output and preserved behavior.
- [Interaction behavior](../.artifacts/design-reaudit/interaction-findings.md): keyboard, form ownership, downloads, drafts, publication, cleanup, feedback and navigation.

Applied skills: UI/UX Pro Max, frontend-design and design-system. Their applicable guidance concerned readable hierarchy, consistent components, explicit consequences, keyboard/focus behavior, semantic feedback, contrast and theme/responsive review. Skill searches did not produce an objective beauty score.

Completed coverage comprises **212 baseline/disclosure/populated/catalog states, 56 dependency/provider/scope states and eight Tools error/selected-file states**. It includes English/French; light/dark; 1440px/980px desktop and 390px/320px narrow layouts; long imports; JavaScript confirmations; minify/defer; accepted Combine JS; catalog controls; all four asset scopes/expert input; schedule on/off; Heartbeat; and pending/ready provider states. The [control-by-control ledger](UX_UI_CONTROL_REAUDIT.md) inventories **306 distinct English control instances** and records their native type, labels, submission owner and observed rendering.

These two matrices recorded zero script errors, server operations, page overflow or scoped axe violations, with **24 + 12 contrast-incomplete result records**. Those incomplete records are not passes. The manually composed 1.27:1 JavaScript warning contrast remains a confirmed defect.

Evidence files: [runtime inventory](../.artifacts/design-reaudit/runtime-inventory.json), [dependency-state follow-up](../.artifacts/design-reaudit/state-followup.json), [interaction probes](../.artifacts/design-reaudit/interaction-probes.json), [shell probes](../.artifacts/design-reaudit/shell-interaction-probes.json), [button-state probe](../.artifacts/design-reaudit/system-state-probe.json).

The [Tools probe](../.artifacts/design-reaudit/tools-states.json) confirms upload-directory errors escape into global feedback and remain visible on Overview. Choosing a file correctly leaves optimization Save disabled. The [coverage summary](../.artifacts/design-reaudit/coverage-summary.json) records matrix counts and limits. [Owned QA cleanup](../.artifacts/design-reaudit/preservation-proof.json) confirms exact saved settings, rules, history, locale rows and private asset metadata were preserved. All three pre-existing QA sessions remain; only the new owned token and request fixture were removed. No baseline was restored over user work.

Audit boundaries: no real settings save, import, export, cleanup, publication, provider purge or worker operation. The safe discard attempt was blocked. Export behavior used a local static mock attachment; no real settings/credentials were downloaded. Saved private-draft deletion and partial-save server feedback remain source-proved paths. A screen reader was not run; semantic findings do not claim exact spoken output. Contrast-incomplete records remain inconclusive, even where automated tools report zero violations.

Coverage means source/control families and recorded instantiated states were inspected. It does **not** mean every dynamic catalog entry or server action was successfully operated. The control ledger distinguishes rendered/enabled/disabled states from unexecuted transactions and intentionally hidden credential proxies. Other browser engines, screen-reader speech, RTL, 200% browser zoom, all locale catalogs and user task-timing studies remain outside this audit.

## Findings by page

RA identifiers below point to the deduplicated priority register. Card heights can differ with content; the audit targets inconsistent ownership and geometry, not identical rectangles.

| Destination | Control families inspected | Principal findings | Correction direction |
| --- | --- | --- | --- |
| Overview `#dashboard` | Cache clear/check; preload/CSS pause/resume/retry; image queue; status badges; queue/failure/runtime disclosures; recovery/setup links | Status meanings differ by operation (RA08); setup/status/actions require route switching and repeated explanations (RA15, RA21). Heavy status text gives off/unverified states similar weight to next actions. | Keep a concise overview; make each status lead to one relevant task/action. Preserve separate diagnostic/media forms and the cache-check scope. |
| Preload `#preload` | Cache and link-preload checkboxes; exclusions/textarea; Help/docs; Overview links | Duplicate progress links and repeated copy (RA21); narrow title/Help competition (RA12); pending enablement versus operational controls remains indirect (RA15). | Two distinct tasks remain valid. Keep one progress entry with its cache-preload task; normalize header/help and section rhythm. |
| Cache rules `#advanced_cache` | Lifespan number/unit; guidance; background refresh; five rule disclosures/textareas; Help/docs | Enter can enable hidden Combine JS (RA01); disclosure text rails move on opening (RA09); Help relocates (RA12); render-time “Configured” becomes stale while editing (RA14); lifespan advice is vague (RA20). | Fix keyboard ownership first. Stable disclosure rails; useful saved/current rule state; specific lifespan reason and recovery. |
| CSS & JavaScript `#file_optimization` | Nine checkboxes; CSS radio modes; exclusions/safelist textareas; category toggles/Select all/entries; warning accept; setup/help/docs | Confirmations retain unreadable purple overlay (RA03); warning feedback not announced/associated (RA06); stale default submitters (RA01); repeated worker setup (RA21); wording/grouping inconsistencies (RA20); narrow nested catalog review (RA23). | Own the complete warning component and states. Keep CSS, JS and WordPress-asset tasks in deliberate order; one setup/status handoff. |
| Media `#media` | Eight checkboxes; lazyload exclusions; image-format status; dimensions/fonts; Help/docs | Image generation is operated elsewhere (RA15); generic important icon on ordinary help (RA08); repeated Help/More info and sparse single-control card rhythm (RA12, RA21); dimensions order lacks clear task hierarchy (RA20). | Group by layout stability, loading, formats and fonts where rendered review supports it; put saved-generation status/action beside image delivery. |
| Asset Manager `#assets` | URL/Inspect; inventory/row details/Prepare; rule type/handle/scope/targets; expert/exceptions; Add/Remove/Discard; preview/publish confirmation; History | Stage text and separately carded forms split task ownership (RA10); persisted draft discard unconfirmed (RA04); preview and publication disconnected (RA15); publication lacks rule delta (RA16); post/exception IDs remain manual (RA19); dark native controls differ (RA13). | One task surface per stage with unboxed native forms; protected discard; explicit edit → preview → publish checkpoint and readable delta/targets. |
| CDN `#page_cdn` | Rewriting; hostname/scope/Remove/Add; exclusions; Help; saved-delivery verification/sample | Naming drift (RA20); saved/current state and configuring while off need clear semantics (RA14); lengthy verification copy (RA21). Shared width/rail issues remain (RA09). | Preserve repaired row associations, validation, scope pairing, 44px Remove and saved-only verification. Clarify configure-while-off rather than automatically disabling useful draft entry. |
| Integrations `#addons` | User Cache/Varnish/WebP/Cloudflare/Sucuri toggles; Configure connection; compatibility/docs | Redundant groups; cache/legacy compatibility policies presented as external services; WebP pitch obscures legacy behavior (RA20); independent integration geometry (RA09); saved-state ownership needs clarity (RA14). | Group by real consequence; concise provider purpose and connection action; explicit legacy WebP scope. Keep destinations discoverable. |
| Cloudflare `#cloudflare` | Key/Show; email; Zone ID; development/preset/protocol switches; Help/docs; saved-connection purge/prerequisite | Pending-off prerequisite falsely claims saved integration off (RA14); loose purge section uses another surface (RA10); “Optimal settings” hides the consequence in its label and switches imply immediacy (RA20); French Zone ID link remains English (RA22). | Credentials, pending provider changes and saved-credential cache action each have clear ownership. Name the concrete preset and application-on-save timing. |
| Sucuri `#sucuri` | Firewall key/Show; Help/docs; saved-key purge/prerequisite | Same false saved-state prerequisite (RA14) and loose operation surface (RA10); width/rail split (RA09). | Shared provider operation surface; correct pending/saved language. Preserve exact key format, concealment and purge prerequisite. Short content needs no filler. |
| Heartbeat `#heartbeat` | Master checkbox; admin/editor/public selects; Help | Two introductory cards repeat one dependency (RA20); independent select/card rhythm (RA09). | One compact task with master and conditional contexts. **Off correctly hides contexts and preserves choices**; do not break this while fixing other conditional fields. |
| Database `#database` | Seven categories; counts; schedule/frequency; cleanup entry; deletion-review dialog/actions | Frequency stays visible/enabled with schedule off (RA05); cleanup saves unrelated pending settings despite explicit disclosure (RA17); recurring deletion scope is weak (RA20); count formatting varies (RA21). | Correct conditional visibility; independent cleanup ownership; concise recurring scope and parallel localized count rows. Preserve explicit irreversible-deletion review. |
| Settings history `#history` | Scope/limits; snapshot review; current/restore values; confirmation/Restore | Two Tab stops per setting even for short values (RA18); heavy dark stripes and nested long-value boxes (RA18); independently styled records/native confirmations (RA09, RA13). | Compact consistent record variant; concise scope; complete readable comparison with only necessary scroll focus. Keep review-first restore and retention safeguards. |
| Tools `#tools` | Download; JSON chooser/Upload review; pending comparison; Cancel/Replace; shortcuts; conditional legacy actions | Export drops unload protection (RA02); flat transfer blocks use another grammar (RA10); long import value hides other changes (RA18); pending review competes with transfer tasks (RA15); upload error escapes its page (RA26); rare one-time enablers remain source-reviewed (RA24). | Pending review first when present; coherent Export/Import task surfaces; chooser/action together; visible changed-setting summary; saved-only download semantics. |

## Priority register

P1: fix before design approval because behavior can change unintended settings, lose private work/protection, or hide essential text. P2: meaningful consistency, accessibility and task-flow gaps. P3: local wording/localization or an unresolved rare-state hypothesis. A source-proved condition is marked separately from an executed browser result.

| ID | Priority / evidence | Deduplicated finding | Owning correction |
| --- | --- | --- | --- |
| RA01 | P1 · browser reproduced | Hidden confirmation buttons are default settings submitters. Enter in Cache lifespan enables Combine JS when available, or is swallowed while unavailable. | [`checkbox.php:72`](../noobrocket/views/settings/fields/checkbox.php): use explicit button type; make intended Save the implicit settings action. Preserve acknowledgment handler in [`settings.js:1962`](../noobrocket/assets/js/noobrocket-settings.js). |
| RA02 | P1 · mock attachment reproduced | Download opens a discard-edits dialog, then leaves dirty edits intact while permanently disabling unload protection. | [`admin.js:433`](../noobrocket/assets/js/noobrocket-admin.js), [`admin.js:530`](../noobrocket/assets/js/noobrocket-admin.js): distinguish download from navigation. Export attachment is owned by [`admin.php:201`](../noobrocket/inc/admin/admin.php). |
| RA03 | P1 · rendered/composed contrast | Old purple confirmation pseudo-overlay survives new amber styling. Light body text against actual overlay measures **1.27:1**. | Own background, arrow/rail, copy, title and action together; remove obsolete app overlay from [`settings.css:2551`](../noobrocket/assets/css/noobrocket-settings.css); consolidate warning rule at [`admin.css:317`](../noobrocket/assets/css/noobrocket-admin.css). |
| RA04 | P1 · source + blocked route | Saved changed private drafts can discard without confirmation or undo because only global dirty/editor input triggers review. | [`assets.php:159`](../noobrocket/views/assets.php), [`admin.js:399`](../noobrocket/assets/js/noobrocket-admin.js), [`Assets/Admin.php:135`](../noobrocket/inc/Engine/Optimization/Assets/Admin.php): compare existing draft/published state and protect changed-draft discard. Actual deletion not executed. |
| RA05 | P2 · rendered dependency | Automatic-cleanup Frequency remains visible/enabled with Schedule unchecked. Generic select layout overrides conditional hiding. | [`admin.css:241`](../noobrocket/assets/css/noobrocket-admin.css) must respect the conditional layer registered at [`Page.php:1369`](../noobrocket/inc/Engine/Admin/Settings/Page.php). Preserve values. No activation/data loss observed; Heartbeat works correctly. |
| RA06 | P2 · browser DOM semantics | Warning opening reverts checkbox and leaves focus there, without announcing/associating new instructions. | [`checkbox.php:63`](../noobrocket/views/settings/fields/checkbox.php), [`settings.js:1944`](../noobrocket/assets/js/noobrocket-settings.js): one accessible acknowledgment pattern with relationship, announcement/focus and return path. Screen-reader speech not tested. |
| RA07 | P2 · source only | Rejected settings can coexist with an unqualified green “Settings saved” toast/saved state. | [`Settings.php:398`](../noobrocket/inc/Engine/Admin/Settings/Settings.php), [`Settings.php:439`](../noobrocket/inc/Engine/Admin/Settings/Settings.php): distinguish partial save; retain valid-setting persistence and error routing. No real save executed. |
| RA08 | P2 · source/rendered | Generic notices default to amber; failed/setup/pending states vary by operation; informational helpers inherit important icons. | Explicit neutral/info/success/warning/error semantics at [`admin.css:317`](../noobrocket/assets/css/noobrocket-admin.css); meaningful state text in [`local-css-diagnostics.php:59`](../noobrocket/views/settings/partials/local-css-diagnostics.php) and [`image-generation.php:7`](../noobrocket/views/settings/partials/image-generation.php). Partial failure may legitimately be warning. |
| RA09 | P2 · source/rendered | Equivalent surfaces drift in inset, radius, heading scale and page rail; disclosures move horizontally when opening; route width resizes ordinary tasks/footer. | Shared active-app surface/density/title contracts in [`admin.css:95`](../noobrocket/assets/css/noobrocket-admin.css), [`admin.css:176`](../noobrocket/assets/css/noobrocket-admin.css), [`admin.css:280`](../noobrocket/assets/css/noobrocket-admin.css). Preserve named compact records and wide data regions. |
| RA10 | P2 · rendered judgment | Tools and provider operations are flat task blocks; asset stage copy sits outside separately boxed forms; actions lose visual ownership. | Reuse shared task surfaces in [`tools.php:36`](../noobrocket/views/settings/page-sections/tools.php), [`cloudflare.php:37`](../noobrocket/views/settings/page-sections/cloudflare.php), [`sucuri.php:35`](../noobrocket/views/settings/page-sections/sucuri.php), [`assets.php:93`](../noobrocket/views/assets.php). Forms keep independent submissions without defining decoration. |
| RA11 | P2 · state reproduced | Generic buttons retain legacy −2px hover/focus movement despite new state styling and reduced motion. | Complete stable default/hover/active/focus/disabled/loading contract; reset legacy transform at [`settings.css:2181`](../noobrocket/assets/css/noobrocket-settings.css) through the owning [`admin.css:150`](../noobrocket/assets/css/noobrocket-admin.css) rules. Focus rings already exist. |
| RA12 | P2 · rendered geometry | Help moves from header into disclosure body; inline links ignore declared 44px height and measure 37px; narrow title/Help competition; repeated docs crowd labels. | Shared help location/target pattern in [`fields-container.php:25`](../noobrocket/views/settings/sections/fields-container.php), [`fields-container.php:29`](../noobrocket/views/settings/sections/fields-container.php), [`admin.css:210`](../noobrocket/assets/css/noobrocket-admin.css). Keep links for navigation and buttons for disclosure. |
| RA13 | P2 · source/rendered | Checkbox/switch disabled styling conflicts; native asset/history checkboxes remain bright white in dark beside dark custom checkboxes. | Intentional theme-aware native/custom family states, including disabled prerequisite text; consolidate [`admin.css:215`](../noobrocket/assets/css/noobrocket-admin.css), [`admin.css:228`](../noobrocket/assets/css/noobrocket-admin.css), [`admin.css:294`](../noobrocket/assets/css/noobrocket-admin.css). No specific disabled contrast failure established. |
| RA14 | P2 · dependency reproduced | Provider pending-off state falsely says saved integration off; render-time Configured badges remain stale after edits; switch/pending/saved meanings drift. | Current-form prerequisite language at [`provider-prerequisite.php:8`](../noobrocket/views/settings/partials/provider-prerequisite.php); meaningful saved or current indicators in [`Render.php:688`](../noobrocket/inc/Engine/Admin/Settings/Render.php). Prefer removing redundant badges over another tracker. |
| RA15 | P2 · workflow judgment | Setup/verification/recovery scattered across routes; final asset publication requires preview but offers it only above editing; pending import not the primary task. | Reuse existing operations beside owning settings/checkpoint: [`assets.php:161`](../noobrocket/views/assets.php), [`Page.php:918`](../noobrocket/inc/Engine/Admin/Settings/Page.php), [`import-form.php:54`](../noobrocket/views/settings/fields/import-form.php), [`local-css-diagnostics.php:113`](../noobrocket/views/settings/partials/local-css-diagnostics.php). Keep saved-state handoffs explicit. |
| RA16 | P2 · source review gap | Published/draft counts do not show what publication replaces; equal counts can hide changed scope/rules. | Compact readable added/removed delta using existing `$published`/draft data before [`assets.php:163`](../noobrocket/views/assets.php) publication. Keep confirmation and concurrency checks. |
| RA17 | P2 · source behavior | Cleanup still saves unrelated global edits. The dialog discloses this, so it is no longer hidden, but requested independent ownership is unresolved. | Separate native cleanup operation owning categories/saved schedule; current coupling is at [`database.php:29`](../noobrocket/views/settings/page-sections/database.php), [`page.php:77`](../noobrocket/views/settings/page.php), [`admin.js:461`](../noobrocket/assets/js/noobrocket-admin.js). Requires coherent behavior change, not CSS. |
| RA18 | P2 · keyboard/rendered | History adds two non-scrollable PRE Tab stops per setting; long import lists conceal other differences; dark table stripes look selected without selection. | Complete comparison summary first, bounded/disclosed long values and only necessary scroll focus; [`history.php:50`](../noobrocket/views/history.php), [`import-form.php:60`](../noobrocket/views/settings/fields/import-form.php), [`admin.css:354`](../noobrocket/assets/css/noobrocket-admin.css). Keep all data inspectable. |
| RA19 | P2 · source task gap | Ordinary posts/exceptions still require raw IDs; large inventories/drafts need deliberate find/edit/scroll paths. | Extend recognizable native choices at [`assets.php:132`](../noobrocket/views/assets.php), [`assets.php:155`](../noobrocket/views/assets.php); keep expert fallback and validation. Assess large-site choice volume; do not add an unlimited dropdown blindly. |
| RA20 | P2 · content/design judgment | Grouping/vocabulary differs by page; activation copy describes staged changes as immediate; alarming Combine recovery overpromises; Cloudflare preset/legacy WebP labels obscure consequences. | Consequence-based grouping, sentence case, precise Enable/Save/Check/Queue/Pause/Restore vocabulary in [`Page.php:558`](../noobrocket/inc/Engine/Admin/Settings/Page.php), [`Page.php:706`](../noobrocket/inc/Engine/Admin/Settings/Page.php), [`Page.php:1705`](../noobrocket/inc/Engine/Admin/Settings/Page.php), [`Page.php:1849`](../noobrocket/inc/Engine/Admin/Settings/Page.php). Preserve technical limitations. |
| RA21 | P2 · rendered hierarchy | Worker/setup/help explanations repeat; duplicate progress/shortcut links and verbose count sentences weaken hierarchy. | One prerequisite/status line, details for setup, concise primary copy and parallel count rows. Owners: [`select.php:41`](../noobrocket/views/settings/fields/select.php), [`preload.php:24`](../noobrocket/views/settings/page-sections/preload.php), [`Page.php:1311`](../noobrocket/inc/Engine/Admin/Settings/Page.php). No filler or forced height. |
| RA22 | P3 · rendered/catalog check | “Find your Zone ID” remains English in French. English dates may reflect missing WordPress locale data; cause unproven. | Check PO/MO coverage for [`Page.php:1829`](../noobrocket/inc/Engine/Admin/Settings/Page.php); update standard catalogs. Verify locale pack before changing date code. No language branches in templates. |
| RA23 | P2 · rendered usability review | Narrow exclusion catalogs create multiple capped inner scroll regions; exposed categories and Select all need deliberate Tab/Space/mixed-state review. | Preserve existing accessible labels/toggles; simplify collection presentation where scrolling obstructs task flow. Own [`categorized_multiselect.php:34`](../noobrocket/views/settings/fields/categorized_multiselect.php) and active catalog styles. Nested scrolling alone is not a proven accessibility failure. |
| RA24 | P3 · source-reviewed uncommon state | Rare Tools Mobile Cache/Google Fonts enablers use immediate legacy paths outside global Save. Upload-error routing is separately reproduced under RA26. | Render the relevant conditional state before changing uncommon behavior; label immediate consequences within the owning Tools task. |
| RA25 | P3 · DOM markup | Native `submit_button()` defaults repeat `id="submit"`: four inputs on Assets and five on populated History. No wrong submission or ARIA-reference collision observed. | Give repeated submit controls unique intentional IDs in [`assets.php:42`](../noobrocket/views/assets.php), [`assets.php:157`](../noobrocket/views/assets.php), [`history.php:71`](../noobrocket/views/history.php). Treat as markup correctness, not invented data-loss evidence. |
| RA26 | P2 · request-only browser reproduced | Tools upload-directory error lacks inline/page ownership. WordPress relocates it into global feedback; the Import-specific error is still visible on Overview in all four tested locale/theme/width combinations. | Keep the native error beside Import with explicit Tools ownership in [`import-form.php:84`](../noobrocket/views/settings/fields/import-form.php). Preserve the shell marker and other global/error routing. [Proof](../.artifacts/design-reaudit/tools-states.json). |

## Decisive defect evidence

### RA01 — hidden default submitter

With JavaScript minification pending on and delay pending off, Enter in Cache rules’ lifespan field changes Combine JavaScript from false to true while the administrator remains on Cache rules. No form request occurs. In the default unavailable-combine state, Enter does nothing. This is unrelated-setting activation through keyboard input, not a visual preference.

Proof: [interaction probe results](../.artifacts/design-reaudit/interaction-probes.json), [eligible Enter capture](../.artifacts/design-reaudit/interaction-probes-enter-eligible.png). Acceptance: Enter uses the intended settings submission and never clicks/acknowledges a hidden warning from another page.

### RA02 — attachment mistaken for page exit

With pending CSS edits, Download settings opens text claiming continuing loses those edits. A mocked attachment completes while the same Tools document and dirty state remain. The unload guard changes from active to inactive. Server export source confirms the real response is an attachment.

Proof: fifth [interaction probe](../.artifacts/design-reaudit/interaction-probes.json). Acceptance: download uses saved settings, preserves pending edits and their leave protection, and does not demand discard approval. Actual cache/import/restore operations retain their protection.

### RA03 — warning contrast/cascade

Expanded JavaScript confirmation retains the legacy `#19073B` full-size pseudo-background. New light-mode body text is `#182943`; computed contrast against the actual overlay is 1.27:1. The parent’s amber color is not the visible composed surface. Dark output remains off-palette purple. Zero automated violations cannot override this evidence when contrast checks are incomplete.

Proof: [light warning capture](../.artifacts/design-reaudit/captures/en_US-1440-light-file_optimization-combine-ready-detail.png), [dark warning capture](../.artifacts/design-reaudit/captures/en_US-1440-dark-file_optimization-combine-ready-detail.png), [system analysis](../.artifacts/design-reaudit/system-findings.md). Acceptance: composed body/title/action text meets applicable contrast in both themes, and warnings belong to the same semantic component system.

### RA04 — persisted private draft discard

Clean editor/global state bypasses operation review. The discard handler deletes private draft/entry metadata, and history excludes private drafts. The intercepted runtime click proves no dialog precedes the request; its fixture had no changed draft, so this audit did not execute deletion of changed work.

Proof: third [interaction probe](../.artifacts/design-reaudit/interaction-probes.json), [discard handler:135](../noobrocket/inc/Engine/Optimization/Assets/Admin.php). Acceptance: unchanged drafts do not present a meaningful discard action; changed saved drafts require scope-specific confirmation or recoverable undo.

## Correction order

1. **Protect behavior and legibility.** RA01–RA04 first; accessible warning feedback next. Keep native form/nonce/capability, draft/history, concurrency and import safeguards. Add focused regression proofs for actual reproduced failures.
2. **Make current state truthful.** Conditional schedule visibility, provider pending/saved claims, partial-success feedback and explicit severity. Fix the responsible visibility/feedback layer before visual polishing.
3. **Own shared components completely.** One active-app contract for task surfaces, heading roles, insets, buttons, disclosures, help, controls and notices. Cover default, hover, focus, checked, disabled, invalid, busy and confirmation states. Remove obsolete active-app rules as ownership moves; retain styles still needed by other admin surfaces.
4. **Repair task ownership.** Tools transfer/review, provider operations, asset stages/delta/preview and independent cleanup. Reuse the current forms/helpers; no new UI framework, parallel theme or generic operation abstraction.
5. **Reduce repetitive content and review friction.** Concise prerequisites, readable long comparisons, recognizable targets, sane catalog scrolling, localized copy and deliberate grouping.
6. **Re-review rendered output and actual interactions.** Same viewports across routes; light/dark; English/French; empty/populated/long/disabled/pending/error/confirmation states. Verify fixes, then stop expanding scope once acceptance conditions hold. Packaging/deployment remain stopped.

## Cohesive-app acceptance contract

### Surface and hierarchy

- Equivalent task sections share outer rails, horizontal inset, radius, heading level and action alignment at the same viewport.
- Collapsing a section changes vertical density, not its horizontal text/chevron/badge rail.
- Compact history records, grouped status rows and wider data tables are intentional named variants. They need not match a settings card’s height or prose measure.
- Ordinary prose remains readable inside wide data layouts. Widening a table does not stretch unrelated explanation or resize shared Save unpredictably.
- Task description, relevant inputs, prerequisite and primary action belong to one visible task unit. Independent form submission does not require unrelated visual boxes.
- Long cards remain long where the task needs complete content; remove repetition before removing useful constraints. Short provider pages remain short.

### Buttons, controls and state

- Same-purpose controls share target size, state colors, focus treatment, alignment and vocabulary. Relevant touch targets meet the 44px design requirement; measured inline exceptions are corrected.
- Explicit button types prevent accidental submission. Acknowledgment changes pending settings; Save persists them; Queue/Check/Purge/Restore describe their actual operation.
- Buttons keep stable bounds on hover/focus. Keyboard outlines remain visible; reduced motion applies to inherited states too.
- Native and custom checkboxes remain semantic and theme-aware. Radios may legitimately differ from switches; disabled controls retain their prerequisite text and cannot look available.
- Conditional groups obey their parent before applying grid/flex layout. Hidden saved values retain the intended submission behavior; activating a parent does not erase prior choices.
- Saved state, pending form state, private saved draft and published state are distinguishable in labels, badges and actions. No render-time badge claims current edits without updating.

### Feedback and color

- Neutral information, risky choice, degraded state, failure and completed work have explicit consistent meanings. Warning is not the fallback for every unknown notice.
- State meaning appears in text/icon as well as color. An Enabled badge does not conceal failed work; partial success does not announce complete success.
- Contrast is checked against the composed rendered background, including pseudo-elements, hover/focus/disabled variants and both themes. Incomplete tool results remain unresolved.
- Completed-action toasts stay brief, announced and dismissible, pause appropriately and do not cover focused controls. Actionable errors remain persistent beside their owning task/field.
- Newly opened warnings/errors explain cause and recovery, with appropriate association/announcement/focus. Confirmation acknowledgment is not an invisible side effect.

### Form and operation ownership

- Global Save applies only to optimization settings and makes cross-section scope reviewable. Asset editing/publication, import replacement, restore and operations retain their own paths.
- Cleanup selection and irreversible deletion scope are concrete before confirmation; unrelated optimization edits do not save as a hidden/accidental consequence. History does not imply recovery of deleted data.
- Download is a read-only attachment action. Pending edits and departure protection remain intact afterward.
- Saved private-draft discard is protected. Publication shows what changes, provides the relevant preview step and retains stale-base rejection.
- Import review exposes every changed setting before replacement, masks private values, preserves expiry/current-state checks and has independent Cancel/Replace ownership.
- Provider purge uses saved qualified credentials. Editing/toggling pending state does not falsely claim saved connection state or execute provider changes immediately.

### Review and navigation

- All 14 destinations remain discoverable and directly linkable. Native pointer and keyboard paths are reviewed separately.
- Keyboard order follows the visual task sequence; ordinary table text does not add needless Tab stops. Actual scrolling regions remain reachable/named, and focus is not obscured by sticky UI.
- Back restores the real departure state/reading position. Asset scope changes retain unfinished-input protections; deep links reach controls or their inactive prerequisites without silently enabling them.
- Menu open/close/Escape and control interactions work at 320×568 as well as desktop. French labels fit without excessive controls or unexplained wrapping.
- Counts/dates/copy use standard WordPress localization and PO/MO. Missing core locale data is diagnosed before introducing template-specific language code.

## Correction verification matrix

Use repository-native focused checks for behavior changes and real rendered inspection for presentation. This matrix specifies proof required after correction; it does not claim these server actions were executed during the audit.

| Checkpoint | Required evidence / acceptance | Boundary |
| --- | --- | --- |
| Enter from another settings page | Lifespan/provider/exclusion input Enter never accepts hidden Combine/Safe Mode or changes an unrelated checkbox; intended submitter recorded. | Intercept submission in a real DOM regression proof. |
| Download with pending edits | Attachment completes in current document; dirty state and unload protection remain; no discard modal; saved-only export scope clear. | Static attachment mock; no real credentials downloaded. |
| Changed private draft discard | Scope-specific confirm or meaningful undo; Cancel preserves draft; unchanged draft has no misleading action. | Isolated draft fixture; preserve user metadata. |
| JavaScript warnings | Light/dark default/hover/focus/accepted states have readable composed text/background; keyboard trigger announces instruction and acknowledgment returns predictably. | No global save required. |
| Schedule dependency | Off hides frequency; on restores saved frequency; hidden values retain intended submission; schedule wording names recurring deletion scope. | DOM/fixture only; never run cleanup. |
| Heartbeat dependency | Existing off-group hiding and preserved admin/editor/public choices remain; enabling returns prior values. | Preserve working behavior. |
| Provider pending toggle | Saved-on/current-off and saved-off/current-on copy never lies about saved connection; save/operation ownership clear. | Request-only saved states; no provider requests. |
| Provider ready operation | Complete/incomplete/off states show relevant prerequisite and enabled/disabled action; key reveal remains separate from Save. | Intercept purge; no credential/provider mutation. |
| Partial save | Rejected values retained; valid values may save; feedback explicitly distinguishes partial completion; error stays on owner and focuses correctly. | Repository-native isolated transaction fixture. |
| Surface consistency | Compare same-viewport edges/insets/title/action rails across all 14 routes; closed/open disclosure preserves horizontal rail. | Manual real-size light/dark screenshots. |
| Button/control states | Default/hover/active/focus-visible/checked/disabled/busy share intentional contract; no inherited motion or off-theme pseudos. | Pointer and keyboard separately; both themes. |
| Asset edit → preview → publish | Relevant next action reachable beside checkpoint; published/draft delta readable; unfinished-input guard and stale-base rejection remain. | Preview/publication intercepted or isolated fixture. |
| Asset scopes/targets | Page/post/type/template/everywhere and expert fallback work; labels/dependencies/exceptions remain readable at320px; long inventory does not bury editing. | All four scopes; no server draft change. |
| History short/long values | Tab follows actions, not every one-line value; long comparisons remain accessible; matching/nonmatching restore state clear. | No restore operation. |
| Tools pending review | All changed setting names visible before replacement; long values inspectable; Cancel/Replace belong to review; new transfer tasks subordinate. | Request-only long/matching/expired review. |
| Tools uncommon/error states | Upload-directory error stays beside Import; one-time actions explain immediate saved-setting consequences. | Fixture before claiming routing defect fixed. |
| Cleanup ownership | Category/count/schedule/deletion scope reviewable; no unrelated optimization edit submitted accidentally. | Isolated operation fixture and intercepted confirmation. |
| Exclusion catalogs | Category expand, Select all/mixed state, label click, Space and scroll work in long French narrow layout; selected values stay in settings ownership. | Every instantiated test entry; no save. |
| Navigation/Back/menu | Direct links, keyboard heading focus, menu Escape and actual departure position behave predictably; no focus hidden behind sticky UI. | Native pointer path separate from hash-driven captures. |
| Localization/markup | Updated POT/PO/MO show intended French labels; core dates diagnosed; repeated submit IDs unique; native labels/ARIA associations preserved. | Catalog/native rendered checks; no language branches. |

## Findings not justified by this evidence

- Latest Tools captures do not reproduce the earlier oversized icons/far-right actions. Its remaining flat structure and inconsistent task ownership are separate current findings.
- Card height differences alone are not defects. A collapsed disclosure, a one-key provider, a data table and a long review have different real content needs.
- White third-party logo plaques in dark mode are a branding/hierarchy judgment, not proof the palette is invalid; supplied logos should not be recolored blindly.
- Native pointer route changes focus headings without an outline; fresh programmatic hash navigation can show `:focus-visible`. Do not remove keyboard focus to fix an automation screenshot artifact.
- At 320×568, tested menu Tab/Escape and Back worked. Playwright scrolled 140px → 104px before navigation while opening the menu; Back restored the actual 104px departure. This is not a lost-scroll defect.
- Sticky headers visible mid-catalog capture do not prove duplicate DOM headers. Empty automated label extraction did not prove missing accessible names; source uses associated labels/headings.
- Audit metadata once treated a named `action` input as native `form.action`; that extractor defect is not an app submission bug. Corrected runtime metadata is required for final ownership claims.
- No real publication/import/restore/provider/cleanup outcome or whole-app accessibility conformance is claimed. Honest remaining limits belong with completion evidence.

## Evidence to retain when correcting

Representative rendered checkpoints: [Tools clean](../.artifacts/design-reaudit/captures/en_US-1440-dark-tools.png), [Tools long review](../.artifacts/design-reaudit/captures/en_US-1440-dark-tools-long-review.png), [Assets populated](../.artifacts/design-reaudit/captures/en_US-1440-dark-assets-populated.png), [History long](../.artifacts/design-reaudit/captures/en_US-1440-dark-history-long.png), [schedule off](../.artifacts/design-reaudit/captures/en_US-1440-dark-database.png), [Cloudflare](../.artifacts/design-reaudit/captures/en_US-1440-dark-cloudflare.png), [Sucuri](../.artifacts/design-reaudit/captures/en_US-1440-dark-sucuri.png), [French narrow catalog](../.artifacts/design-reaudit/captures/fr_FR-390-dark-file_optimization-catalog.png).

Keep this audit as pre-correction evidence. Record fixes and focused/rendered proof separately; do not rewrite unresolved findings into “finished” claims. The next approval gate is a coherent component/state/task system supported by evidence, not another numerical rating or a package build.

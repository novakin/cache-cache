# NoobRocket card-by-card UX/UI and frontend audit

Audit date: 9 October 2026. Current local WordPress output is the reference. This is an audit and improvement proposal; maintained plugin code, packages and live deployment are unchanged.

The interface has consistent page widths, useful task cards and sound light/dark palettes. Its remaining weaknesses are control-state rendering, review correctness, task ownership and uneven information density. Another font or palette change would not resolve these findings. No numerical design score is assigned.

## Coverage and evidence

All 14 routes and their top-level cards, nested operational surfaces and representative conditional workflows are inventoried. The detailed assessments record each surface's controls, retained strengths, issue, smallest proposed correction, priority, acceptance and source/screenshot evidence:

- [Overview, Integrations, Cloudflare and Sucuri](audits/CARD_AUDIT_OVERVIEW_PROVIDERS.md).
- [Preload, Cache rules, CSS/JavaScript, Media, Heartbeat and Database](audits/CARD_AUDIT_SETTINGS.md).
- [Asset Manager, CDN, Settings history and Tools](audits/CARD_AUDIT_WORKFLOWS.md).

UI/UX Pro Max, Frontend Design, Design System and the [existing design guidelines](DESIGN_GUIDELINES.md) informed the assessment. The mockup is a reference, not the acceptance target. Older fixes are retained where they still work; an earlier verification claim is not evidence that current output remains correct.

| Evidence | Current scope |
| --- | --- |
| [Card inventory](../.artifacts/card-audit-20261009/inventory.json) | 448 route/render records across 70 browser/scenario configurations; 1,526 card image tiles. Default and expanded disclosures are recorded separately. Nested cards and long-card tiles are not independent usability tests. |
| Baseline matrix | Every route in English and French, light and dark, at 1440px and 390px. Direct card review concentrated on English desktop and French narrow output; supplementary cross-locale geometry and selected screenshots check the other combinations. |
| Conditional matrix | Enabled dependent settings, populated/stale asset drafts, five snapshots, staged import, CDN scopes, provider disabled/configured states and dashboard unverified/problem/ready states. Selected French 320px cases supplement the baseline. |
| [Shared interaction evidence](../.artifacts/card-audit-20261009/shared-proof.json) | Ten shell states at 320/390/768/1024/1440px, both themes; 28 interaction records covering compact-menu access/Escape, cancellation, native checked markers, hover/focus and stale publication appearance. No action form was submitted. |
| Collection results | Zero recorded page script errors, page-level horizontal overflow or local operation requests in the final collectors. These results do not disprove the visual and interaction defects below. Local scroll regions still require direct review. |
| State preservation | Header-gated fixtures block database writes, cron and external PHP HTTP. An owned browser session isolates browser preferences and unsaved form edits. Final cleanup PASS confirms exact protected-state preservation and removal of only owned QA state; receipt is linked in the verification record. |

Synthetic credentials, grades, queue counts, asset identifiers and comparisons are representative display data, not real delivery or performance results. Card crops hide fixed/sticky chrome with visibility while preserving layout; full-page captures retain ordinary chrome. Supplemental import comparison-bottom images expose the end of its bounded review region. The minimal import fixture also causes an in-request Settings saved outcome; that toast is excluded from product findings and does not represent an import or persisted save.

**Observed** means directly inspected current output or browser behavior. **Source-confirmed** means the current owner establishes the behavior; destructive/provider transactions were not executed. **Proposal** means an optional improvement, not a demonstrated failure. P1 concerns reviewed replacement correctness; P2 concerns meaningful control, comprehension or recovery defects; P3 concerns presentation and polish.

## Fix first

| Priority | Finding | Proposed correction |
| --- | --- | --- |
| P1 | Asset publication rereads the current private draft without binding it to the draft reviewed in the submitting tab. History restoration similarly lacks a check against the saved state used for its displayed comparison. Source-confirmed W01/W02. | Validate the reviewed state in the existing publication/restore owner. On an intervening change, retain work and require a current comparison. Reuse the import review principle; no new transaction service. |
| P2 | Checked native boxes can look unchecked in light mode; stale disabled Publish remains blue; CSS mode radios show competing indicators. Observed and source-confirmed. | Correct state ownership in the shared controls and CSS-mode component. Preserve native semantics and clear focus. |
| P2 | Cancel still reloads the document, despite the in-place cancellation contract and historical fix record. Observed in all ten shared states. | Restore the saved form structure and values in the existing form owner; resynchronize dependent controls once, preserve route/theme/focus, and keep the unfinished-entry warning. Plain `form.reset()` alone does not restore removed dynamic rows. |
| P2 | Asset selection, retained errors, inspection evidence and publication are disconnected; unchanged publication and short rule lists consume unnecessary space. | Make selecting an inspected asset the next step; keep errors in their owning editor, mark outdated preview evidence, compact unchanged publication and show complete short saved-rule lists. |
| P2 | Queue/image/provider statuses can describe the wrong state or hide an unmet requirement. | Distinguish saved configuration, pending edits, availability, work state and recorded evidence in concise copy. Reuse existing data. |
| P2 | Database count prose dominates destructive categories, immediate cleanup is detached, and recurring scope is unclear. | Prioritize category/count scanning; attach immediate review to the selection surface; explain that the saved selections define scheduled cleanup. Preserve full confirmation and backup guidance. |
| P2 | Worker setup follows exception editors, cache policies lack subgroups, credential labels are distant from inputs, and CDN verification samples the wrong resource type. | Reorder existing content around the task and prerequisite. Keep supporting evidence/help secondary and appropriate to the saved scope. |
| P3 | Help, navigation footers, button movement/margins and repeated explanations differ across otherwise related cards. | Consolidate shared presentation at its responsible component; shorten copy without removing effects, requirements or recovery. |

## Shared frontend findings

### SH01 — Native checked state disappears in light mode · P2

Observed expert-mode and publication-acknowledgement boxes are checked while their visible square looks empty. The browser probe confirms a white SVG marker over `rgb(255, 255, 255)` at 320/390/1440px. Dark mode retains a visible white marker over its dark surface.

Current WordPress `wp-admin/css/forms.css:190–203` pairs its white checked SVG with a colored checked background. [The blanket plugin background override](../noobrocket/assets/css/noobrocket-admin.css#L271) removes that pairing. Settings switches and label-rendered cleanup boxes use separate indicators, so their screenshots do not prove native boxes work.

Proposal: give visible native checkbox states a matching checked background/indicator in the shared app component. Preserve custom switch/cleanup-label ownership. Acceptance: checked and unchecked expert, publication, history and import confirmations are unmistakable in both themes, with native keyboard behavior and visible focus. Fresh proof directly covers expert/publication; history/import share the source rule and require focused implementation checks.

### SH02 — Disabled primary actions look enabled · P2

The stale Publish button is actually disabled but remains white-on-blue: `#3157ce` in light and `#426ce4` in dark. The primary rule's `:is()` includes the Save button ID, giving every matching primary button that selector's higher specificity; the generic disabled rule loses. Save has a separate correction, which does not protect other primary actions. Owner: [shared button rules](../noobrocket/assets/css/noobrocket-admin.css#L207).

Proposal: separate the ID-specific Save rule from shared primary classes and make disabled state authoritative, including hover/focus. Acceptance: stale/unchanged Publish and other unavailable primary actions visibly differ from available actions; a nearby explanation names the blocking requirement. Color must support the native disabled state, not replace it.

### SH03 — CSS radio has two checked-indicator owners · P2

The 20px CSS-mode radio uses an inset dot while WordPress's checked `::before` remains active. French 320/390px captures show the resulting split/off-center mark; the probe records the inherited narrow pseudo-element margin and dimensions. Owner: [CSS mode component](../noobrocket/assets/css/noobrocket-admin.css#L327).

Proposal: retain one centered checked indicator, suppressing or coherently sizing the inherited pseudo-element. Acceptance: one centered dot in both themes at desktop and narrow widths; native grouping, labels and arrow-key selection remain intact. Full card assessment is in the settings report.

### SH04 — Cancel reloads · P2

[The current handler](../noobrocket/assets/js/noobrocket-admin.js#L426) calls `location.reload()`. The focused browser run awaited actual document responses, not just hash/history changes: all ten cases reload with HTTP 200, preserve the route/theme after initialization and clear the draft. Reload is therefore confirmed; a light-theme flash is not demonstrated by this audit.

Proposal: meet the existing in-place cancellation contract using the shared form's saved baseline, including dynamic CDN rows and dependent visibility. Acceptance: Cancel makes no document request, restores all pending native settings, retains the current route/theme, clears Save state, preserves private saved asset drafts and returns focus predictably. Keep the confirmation for unfinished entry loss. The older cancellation proof describes an earlier revision; it cannot close this current finding.

### SH05 — Buttons inherit movement and inconsistent margins · P3

Inspect page moves up exactly 2px on hover and focus in the fresh probe, including reduced motion. Legacy `noobrocket-settings.css` supplies `translateY(-2px)`; the admin component does not reset it. Its field-button `margin: 8px 0` also survives in PageSpeed key actions, where French wrapping increases the intended 10px action gap to 26px. The margin finding is source/visual evidence, not the Inspect button's margin, which is correctly zero.

Proposal: normalize transform/margins in the shared button component and let action rows own spacing. Acceptance: interactive bounds stay stationary, focus remains visible and wrapped action spacing is consistent. Preserve pressed/hover color feedback.

### SH06 — Help and navigation lack one placement rule · P3

Object cache, Preload's page footer, Media generation status, Asset publication and Tools still use older detached/permanently underlined navigation. Expanded settings groups put Help alone under the summary, while ordinary cards put it at the header. Some standalone help anchors are only 19–37px high; prose links are a separate case and should retain normal inline treatment. Target-size measurements alone are not a blanket WCAG failure claim.

Proposal: reuse the existing divided navigation footer for related destinations, one predictable internal help row for disclosed groups, and the current 44px target contract for standalone actions. Keep links outside interactive summaries. Acceptance: operations, navigation and help remain visually distinct without making every link a button. Remove duplicated destinations only where the task already exposes the same route.

### SH07 — Intermediate layout deserves a fit-based review · P3 proposal

At 1024px, WordPress chrome plus the 216px plugin navigation leaves 618px of card width; at 768px the compact menu leaves 734px. No clipping was found in the sampled shared layout, and menu keyboard access/Escape worked at 320/390/768px. This is a space-efficiency proposal, not a broken-menu claim.

Proposal: evaluate the existing compact-menu breakpoint against actual app space at small laptop widths. Keep current icon navigation and user-requested hidden scrollbar; do not add another navigation system. Acceptance: complex credential/CDN/editor rows have useful working width, and every destination remains keyboard-accessible with correct current-page and focus behavior.

### SH08 — Frontend cleanup needs profiling, not another override layer · P3 proposal

The English baseline response is about 277.5KB of uncompressed HTML and 2,701 DOM elements; French is about 289.3KB and 2,702 elements. All routes render into one document. The response loads three plugin stylesheets and five plugin scripts. Maintained admin CSS and generated settings CSS are 62,374 and 123,707 bytes; admin JS and generated settings JS are 36,428 and 140,484 bytes. These are source/response sizes, not transferred compressed sizes or measured speed regressions.

Proposal: profile admin startup/interaction cost before changing routing or bundling. First consolidate the confirmed legacy conflicts at shared button/input/help owners and remove proven unreachable styling through the existing build. Keep maintained/generated artifacts synchronized. Lazy rendering or a new state framework is not justified by these sizes alone.

Acceptance: focused interaction/layout checks bracket consolidation; no duplicate control owner, inaccessible hidden validation, initial-theme regression or generated/source mismatch. Report measured startup gains only if measured.

## Every page: card-level improvement map

Detailed reports above contain evidence and acceptance per row. “Retain” identifies a working surface; it is not a whole-page sign-off. Nested comparisons, operational disclosures and action regions are included even when they are not standalone cards.

| Page | Card or nested surface | Proposed treatment |
| --- | --- | --- |
| Overview | Heading / Clear cache | Retain concise site identity and separate native operation. |
| Overview | Site status / Homepage cache check | Retain cache-first hierarchy, scoped failure wording, action/timestamp and disclosed evidence. |
| Overview | Preloading / Queue counts | Say No queued pages without completion evidence; use truthful off/paused copy. Keep counters secondary. |
| Overview | Local CSS / Counts / Recent failures | Retain mode, fallback and failure evidence; opening deep evidence should not stretch the unrelated Preload summary to 750px. |
| Overview | Server worker | One readable setup destination; keep report age and scheduling caveat. |
| Overview | Recovery actions | Explain or omit unavailable CSS regeneration in Original mode; retain destructive scope. |
| Overview | Modern images / Encoder details | Name available formats, health and last-run evidence separately from Enabled. |
| Overview | Object cache | Reuse adjacent cards' navigation footer; keep provider scope honest. |
| Overview | PageSpeed / Key setup / Mobile and Desktop results | Retain separate key/check ownership and dated results; normalize wrapped actions and keep scores secondary to operational health. |
| Preload | Preload Cache | Put progress navigation in the warming card; shorten repeated title/label. |
| Preload | Next-page loading | Explain the two switches and fallback relationship once; preserve bandwidth/sensitive-route guidance. |
| Cache rules | Cache Lifespan | Show refresh eligibility beside the pending choice; clarify expiration exception and ordinary help typography. |
| Cache rules | Exclusions and purge rules | Group exclusion, related-purge and URL-parameter editors inside the existing surface. |
| CSS & JavaScript | CSS delivery | Correct radio indicator; keep essential worker requirement beside mode. |
| CSS & JavaScript | JavaScript delivery | Short concrete Defer/Delay descriptions and nearby test/recovery path. |
| CSS & JavaScript | CSS setup and exclusions | Setup/readiness first, then exception editors; one destination, normal helper weight. |
| CSS & JavaScript | JavaScript exclusions and compatibility | State Minify on / Delay off explicitly; remove duplicate preset heading and generic warning copy. |
| CSS & JavaScript | WordPress asset removal | Retain compact independent toggles; scope explanations remain useful. |
| Media | Images | Remove warning-like icon from ordinary generation navigation; own that destination in the card footer. |
| Media | Videos and iframes | Avoid a second disclosure/heading for one small YouTube child choice. |
| Media | Fonts | Retain clear grouped task and conditional behavior; reduce repeated help only where redundant. |
| Asset Manager | Inspect / Inventory / Source detail | Make selecting an inspected asset the next invitation; keep bounded inventory and dependency evidence. |
| Asset Manager | Draft editor / Saved rules / Retained error | Error beside editor, unmistakable expert checkbox, complete short rule list, secondary raw identifiers. |
| Asset Manager | Preview / Publication comparison / Confirmation | Compact unchanged state; reuse inspected URL, identify outdated preview evidence, enforce reviewed-draft check and clear disabled appearance. |
| CDN | Connection / Hostname rows / Exclusions | Retain grouped rows and adjacent remove controls; add concise rewrite-purpose copy. |
| CDN | Verify saved delivery | Match sample to saved resource scope; stack URL key/value pairs on narrow screens; compact instructions/recovery. |
| Integrations | Optional integrations | Use Cache compatibility heading; reduce repeated introductory space; retain explicit unavailable legacy WebP reason. |
| Integrations | Available integrations | Use Provider connections heading; align switch with provider identity when French wraps; distinguish draft from saved readiness. |
| Cloudflare | Credentials / Prerequisite | Group labels, fields and acquisition links in one form column; connect identifiable errors to fields. |
| Cloudflare | Specialist settings | Name concrete provider effects; consistent disclosure help placement. |
| Cloudflare | Cache action | Retain saved-credential ownership and visible unavailable/ready state. |
| Sucuri | Credentials / Prerequisite | Same compact form grouping and field-error association; keep key format visible. |
| Sucuri | Cache action | Retain saved readiness and disabled explanation; normalize help placement. |
| Heartbeat | Background activity | Retain aligned native context choices; shorten consequence prose without hiding editor/autosave risk. |
| Database | Cleanup selection | Compact counts, category-first mobile hierarchy and full deletion scope. |
| Database | Immediate cleanup / Confirmation | Attach review action to selections; preserve counts, schedule, pending-setting disclosure and Cancel. |
| Database | Automatic cleanup | Explain that saved selected categories run at the chosen frequency; avoid duplicate title/label. |
| Settings history | Scope and limits | Retain limits; clarify cleanup-related snapshots restore selections, not deleted records. |
| Settings history | Snapshot list / Comparisons / Restore | Short recognizable summaries, useful counts and compact closed rows; validate reviewed current state before replacement. |
| Tools | Export/Import transfer | Retain current compact divided structure and native upload ownership; put related navigation with transfer scope. |
| Tools | Staged review / Comparison / Confirm or Cancel | Put active review before routine transfer actions; neutral routine-review surface, readable labels/choices, short expiry/scope guidance and visible decision controls. Preserve review-conflict/credential protections. |

## Design direction and implementation boundaries

Keep the current system UI/code fonts and semantic palettes. Body text, divided rows, end switches, compact radios and native disclosures provide the shared vocabulary. Do not force identical card heights: related task structure matters more than symmetric empty space. Give label/control/help proximity priority over long desktop row alignment. Keep code identifiers available as a specialist path, not the initial task invitation.

Use four existing presentation roles consistently: task-card header, divided setting row, supporting evidence disclosure and related-navigation footer. Operations remain buttons in their owning forms. Routine explanations are muted; requirements and failures are visible where they affect the decision. Success/information/warning/error labels keep distinct semantics. Standalone outcomes stay in the existing persistent tray; local validation/review conflicts stay with their fields/workflows. Pending notices were protected rather than exhaustively re-triggered in this audit.

Implement in this order: reviewed-action protection; shared checked/disabled/cancel behavior; Asset Manager and Database task flow; operational state/readiness; setup/credential/CDN layout; navigation/help/copy polish. Extend the existing responsible owners. Avoid a new component library, router, job service, transaction service or duplicate CSS override layer.

For each implementation, run the relevant existing focused checks and inspect current WordPress output in both themes and supported sizes. Include pending/configured/error/unchanged states, keyboard/focus and no-JavaScript recovery where changed. Preserve saved settings, private drafts and published rules as different states. Keep translations in PO/MO. A screenshot count or passing automated assertion is not a beauty or usability score.

## Limits and delivery state

No publication, restoration, cleanup, provider purge, queue operation or Google check was executed. Concurrency findings are source-confirmed paths, not simulated destructive races. Warning activation dialogs, every native OS picker, manual screen-reader/touch use, RTL, complete no-JavaScript task testing and human task-success timing remain outside this pass. Capture fixtures and script mistakes were corrected before the final evidence; they are not product bugs.

Audit documents and temporary evidence are the changes in this pass. No maintained frontend/PHP product changes, package, commit, push or live deployment were made. This workspace has no Git repository. See [verification and cleanup](../tests/README.md#card-by-card-audit--2026-10-09-unpackaged).

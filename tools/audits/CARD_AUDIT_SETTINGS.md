# Settings card audit — 9 October 2026

Read-only audit of Preload, Cache rules, CSS & JavaScript, Media, Heartbeat and Database. Six routes, 15 registered settings cards and the Database action region. Findings describe the current application; previous audits and mockups are not used as proof of defects. No product change is made by this report.

## Evidence and limits

Directly inspected current native card crops in English at 1440px, light/dark, and French at 390px, light/dark. Inspected relevant French 320px light/dark controls: CSS modes, cache lifespan, Heartbeat, cleanup counts/scheduling and next-page loading. Baseline and expanded states are covered; request-only enabled settings expose parent-dependent controls. Complete capture ownership/geometry is recorded in [inventory.json](../../.artifacts/card-audit-20261009/inventory.json). Card crops temporarily hide sticky chrome for unobstructed inspection; full-page captures retain it.

Observed means visible in those captures. Source-confirmed means supported by current rendering/handlers. Proposal means a design change to consider; it is not a claimed functional failure. P2 denotes a material comprehension, control-rendering or workflow issue; P3 denotes refinement. No blocking runtime failure is demonstrated in these six routes. Capture runs do not prove production generation, cron, cleanup or every possible keyboard state.

Assessment uses [current design guidelines](../DESIGN_GUIDELINES.md), UI/UX Pro Max accessibility/forms/layout priorities, Frontend Design hierarchy/restraint, and Design System component/state ownership. The targeted progressive-disclosure search produced no verified relevant database match after its narrower retry; the disclosure assessment uses the skills' documented rules and the repository's existing contract.

Keep the existing semantic palettes, shared widths, divided task cards, end switches, native inputs and visible focus. Do not make cards equal height merely for symmetry. These are working foundations, not reasons to leave the issues below unresolved.

## Preload

### Preload Cache — `preload_section`

Controls covered: automatic warming, conditional URL exclusion disclosure/editor, pattern guidance and help.

- **Retain:** Warming and browser navigation are separate tasks. The switch, disclosure and exclusion editor remain within one card. English desktop and French narrow states wrap without observed clipping.
- **Observed, P3:** The route's progress link is detached below the *next-page* card, even though it belongs to server-side warming. Its long label also combines preload queue progress and homepage evidence. Title/label repeat the same concept as “Preload Cache” and “Activate Preloading.”
- **Impact:** Checking whether warming worked requires scanning past the unrelated browser task; the detached link feels like a leftover page footer.
- **Smallest proposal:** Put a compact “View preload progress” destination in this card's footer, using the established navigation treatment. Keep homepage evidence in Overview. Use sentence case and a short warming label.
- **Acceptance:** One preload-progress destination, owned by this card; native hash navigation preserved; no operation button disguised as a link; 44px navigation target at 320/390/1440px.
- **Evidence:** [French default page](../../.artifacts/card-audit-20261009/captures/fr_FR-390-light-design-preload-default.png), [desktop warming card](../../.artifacts/card-audit-20261009/captures/en_US-1440-dark-design-preload-expanded-card-00-1.png). Owner: `Page.php:990–1040`, `views/settings/page-sections/preload.php:24`.

### Next-page loading — `preload_links_section`

Controls covered: HTML preloading, public-page prerendering, browser compatibility and conditional prerender exclusions.

- **Retain:** The introduction distinguishes subsequent navigation from first-visit speed. The two switches are independent; bandwidth cost is visible. Exclusions sit in the owning card. The current frontend correctly falls back to HTML prefetch when prerendering is enabled, including when the independent prefetch switch is off.
- **Observed, P3:** Compatibility, fallback and precedence are explained in different places, so the relationship between two enabled switches requires reading several paragraphs. On French narrow screens the expanded card exceeds one viewport, though that is not clipping or a reason to compress touch targets.
- **Impact:** Users can reasonably wonder whether both switches are required or whether enabling both loads a page twice.
- **Smallest proposal:** Use one short shared relationship statement: prerender takes priority where supported; HTML prefetch is its fallback; the separate prefetch switch allows prefetch without prerender. Keep detailed browser and URI-pattern help folded.
- **Acceptance:** Prefetch-only, prerender-only and both-on choices can be understood without opening compatibility help; bandwidth and sensitive-route exclusions stay discoverable. Preserve the existing fallback behavior.
- **Evidence:** [French enabled card](../../.artifacts/card-audit-20261009/captures/fr_FR-390-dark-settings-enabled-preload-expanded-card-01-1.png), [320px card](../../.artifacts/card-audit-20261009/captures/fr_FR-320-light-settings-enabled-preload-expanded-card-01-1.png). Owner: `Page.php:1000–1077`, `assets/js/preload-links.js:23–29`.

## Cache rules

### Cache Lifespan — `cache_lifespan`

Controls covered: lifespan number/unit, unlimited lifespan, background refresh, guidance and prerequisites.

- **Retain:** Number and unit form one labeled control group. Zero's meaning is visible. The refresh description explains the extra-hour allowance and immediate content-change purge. The pair remains usable at 320px.
- **Observed + source-confirmed, P2:** Background refresh's prerequisites are folded. The adjacent inactive explanation is generated only when the *saved* switch is already on and ineligible. Newly enabling it while pending options violate requirements therefore has no equivalent in-form readiness explanation. This is a discoverability gap, not proof that the backend enables an unsafe configuration.
- **Observed, P3:** “Expired cache files are deleted” sounds unconditional beside an option designed to retain/serve expired pages temporarily. Expanded guidance is heavier than ordinary helper prose. The French “périodiquement.Pourquoi ?” loses the space before its link.
- **Impact:** A switch can look enabled while the user has not understood why background refresh will remain inactive. The typography and contradictory absolute wording increase reading effort.
- **Smallest proposal:** Keep guidance folded, but show concise current eligibility beside the switch when requested. Name the unmet requirement using existing settings. Clarify the expiration/refresh exception; make guidance ordinary helper text; correct French spacing in PO.
- **Acceptance:** Changing preloading/lifespan and enabling refresh immediately explains any unmet prerequisite. Saved ineligible states still explain the cause; Nginx/PHP requirements remain accurate. No new scheduling status is invented. Both themes and French 320px remain readable.
- **Evidence:** [French 320px card](../../.artifacts/card-audit-20261009/captures/fr_FR-320-dark-settings-enabled-advanced_cache-expanded-card-00-1.png), [desktop card](../../.artifacts/card-audit-20261009/captures/en_US-1440-light-design-advanced_cache-expanded-card-00-1.png). Owner: `Page.php:1087–1176`, `views/settings/fields/cache-lifespan.php`, shared helper typography.

### Exclusions and purge rules — `cache_exclusions`

Controls covered: URLs, cookies, user agents, additional purge URLs and cacheable URL parameters.

- **Retain:** The closed card reduces default-page complexity and marks configured content. Editors have visible labels and syntax explanations. Sensitive custom login/logout URLs are explicitly discussed inside the expanded surface.
- **Observed, P2:** Opening one disclosure exposes five large, equally weighted editors serving three different purposes: excluding visitors/pages, purging related content and enabling parameter variants. It is over 1100px on desktop and about 1600px on French mobile. The Help link occupies its own left-aligned row beneath the summary instead of the normal card-header placement.
- **Impact:** A user editing one rule must scan unrelated policies; the special Help placement makes the component feel different from ordinary cards.
- **Smallest proposal:** Within this existing surface, add clear lightweight subgroups for exclusions, additional purges and URL parameters; disclose specialist groups separately where needed. Keep configured summaries, examples and sensitive-page guidance available. Place Help in a predictable internal header row without nesting it in the interactive summary. Do not add five new cards.
- **Acceptance:** Users can identify each policy and reach its editor directly; existing configured values remain discoverable; validation opens the correct disclosure; each parameter/rule explanation remains associated with its field.
- **Evidence:** [desktop expanded card](../../.artifacts/card-audit-20261009/captures/en_US-1440-light-design-advanced_cache-expanded-card-01-1.png), [French first part](../../.artifacts/card-audit-20261009/captures/fr_FR-390-dark-settings-enabled-advanced_cache-expanded-card-01-1.png), [French final part](../../.artifacts/card-audit-20261009/captures/fr_FR-390-dark-settings-enabled-advanced_cache-expanded-card-01-2.png). Owner: `Page.php:1146–1228`, `views/settings/sections/fields-container.php`.

## CSS & JavaScript

### CSS delivery — `css`

Controls covered: Original/Critical/Used CSS radios, worker requirement, original-style fallback, CSS minification.

- **Retain:** Compact native choices precede the minification row; the three mode purposes and original-style fallback are visible. The prerequisite is not hidden inside advanced configuration.
- **Observed + source-confirmed, P2:** The selected CSS radio has an off-center/split inner mark on French 390/320px. The plugin's 20px radio/inset-dot styling leaves WordPress's checked `::before` active. Core narrow-screen rules give that pseudo-element dimensions/margins intended for a larger radio, producing competing indicators.
- **Impact:** The choice control appears broken despite correct native selection semantics; this is a frontend defect, not subjective card styling.
- **Smallest proposal:** Give the existing component one checked-indicator owner: suppress the core pseudo-element when using the inset-dot styling, or use one coherently sized/centered pseudo-element. Keep native radio semantics and label targets.
- **Acceptance:** One centered selected dot at 1440/390/320px, both themes; unselected and focus-visible states stay distinct; keyboard changes retain the same values.
- **Secondary proposal, P3:** Connect setup/readiness to the selected mode more directly. Use existing worker evidence without presenting a historic report as proof of current scheduling.
- **Evidence:** [390px selected radio](../../.artifacts/card-audit-20261009/captures/fr_FR-390-light-settings-enabled-file_optimization-expanded-card-00-1.png), [320px selected radio](../../.artifacts/card-audit-20261009/captures/fr_FR-320-dark-settings-enabled-file_optimization-expanded-card-00-1.png). Owner: `views/settings/fields/select.php`, `assets/css/noobrocket-admin.css:330–331`; platform evidence: local WordPress `wp-admin/css/forms.css:209–217,1643–1648`.

### JavaScript delivery — `js`

Controls covered: minification, defer and delay switches; per-feature explanations and help.

- **Retain:** Three divided rows have consistent end switches and full-width descriptions. Default and enabled states match the rest of the app. No confirmed control clipping or card-width defect in inspected states.
- **Observed, P3:** Header Help and repeated “More info” links compete with the three decisions. Defer and Delay are described with long performance wording; the concrete test/recovery guidance lives in the advanced exclusion card.
- **Impact:** Users must infer the practical difference between loading after parsing and waiting for interaction, then find a second card to recover an affected menu or form.
- **Smallest proposal:** Use concise concrete descriptions for defer/delay, retain necessary contextual links, and put one brief “test menus/forms after saving” instruction beside Delay with an anchored route to its existing exclusions. Avoid extra warning cards for normal states.
- **Acceptance:** Users distinguish defer from delay and find the related exclusion editor; labels/values, pending global Save ownership and existing dependency behavior stay unchanged.
- **Evidence:** [French enabled card](../../.artifacts/card-audit-20261009/captures/fr_FR-390-light-settings-enabled-file_optimization-expanded-card-01-1.png), [desktop baseline](../../.artifacts/card-audit-20261009/captures/en_US-1440-dark-design-file_optimization-expanded-card-01-1.png). Owner: `Page.php:487–541`.

### CSS setup and exclusions — `css_advanced`

Controls covered: minification exclusions, selector safelist, worker report and local setup instructions.

- **Retain:** Literal, case-sensitive safelist matching is explicit; wildcard semantics are separately described for minification. The report honestly says it does not prove current scheduling.
- **Observed, P2:** The surface is named “setup and exclusions” but places worker setup/report *after* both exception editors. In French this prerequisite/recovery block is nearly a screen below the opening summary. Setup/report paragraphs are visually heavier than the controls above; the setup link repeats the main-card destination.
- **Impact:** A user opening the card to set up generation must pass unrelated editors first. Secondary historical evidence visually outweighs the editing task.
- **Smallest proposal:** Move existing worker setup/report to the beginning of this opened surface; separate it from the two exception editors with a clear internal heading/divider. Use normal helper weight and one owned setup destination, while keeping essential requirements in the main CSS card.
- **Acceptance:** Setup is the first visible task after opening; historic-report caveat remains; minification and selector matching formats remain clear; no additional top-level card is introduced.
- **Evidence:** [French expanded card](../../.artifacts/card-audit-20261009/captures/fr_FR-390-dark-settings-enabled-file_optimization-expanded-card-02-1.png), [desktop expanded card](../../.artifacts/card-audit-20261009/captures/en_US-1440-light-design-file_optimization-expanded-card-02-1.png). Owner: `Page.php:410–416,441–446,543–568`, `views/settings/sections/fields-container.php`.

### JavaScript exclusions and compatibility — `js_advanced`

Controls covered: combine eligibility/activation, inline/minification/defer/delay exclusions, preset exclusion catalogue and Delay Safe Mode.

- **Retain:** Parent-dependent editors are exposed only for the relevant feature. Combine's availability follows current pending Minify/Delay settings, not just saved settings. Safe Mode is distinguished from a performance-neutral choice in its source warning.
- **Observed + source-confirmed, P2:** “Requires JavaScript minification and Delay JavaScript execution turned off” is ambiguous about whether minification should be on. French also uses “exécution différée,” which can be mistaken for the separate Defer switch. The actual handler requires Minify on and Delay off.
- **Observed, P3:** The preset disclosure immediately repeats “One-click exclusions” as a second field heading, then two explanatory paragraphs. Four large outlined category rows use a different visual density from the surrounding divided settings. The Safe Mode explanation contains “The Safe Mode mode.” Source warning copy uses generic “This could break things!” and an uppercase activation label; activation-warning layout itself was not captured.
- **Impact:** Prerequisite wording can send users to the wrong setting. Repeated headings and category chrome slow down the recovery task.
- **Smallest proposal:** Name exact prerequisite labels and state explicitly: enable Minify, turn off Delay. Remove the duplicated preset heading, shorten its explanation, and reuse compact divided category rows. Fix Safe Mode wording and use specific sentence-case risk/action copy; preserve its explicit acknowledgement.
- **Acceptance:** Minify-on/Delay-off eligibility is unambiguous in English/French; current-form dependency rules stay correct; preset selections remain accessible and discoverable; all named exclusion formats are retained. Verify activation warnings separately before implementation is signed off.
- **Evidence:** [desktop enabled first part](../../.artifacts/card-audit-20261009/captures/en_US-1440-light-settings-enabled-file_optimization-expanded-card-03-1.png), [desktop final part](../../.artifacts/card-audit-20261009/captures/en_US-1440-dark-settings-enabled-file_optimization-expanded-card-03-2.png), [French first part](../../.artifacts/card-audit-20261009/captures/fr_FR-390-light-settings-enabled-file_optimization-expanded-card-03-1.png), [French catalogue](../../.artifacts/card-audit-20261009/captures/fr_FR-390-dark-settings-enabled-file_optimization-expanded-card-03-2.png). Owner: `Page.php:571–705`, `assets/js/noobrocket-settings.js:1975–1988`, categorized multiselect template.

### WordPress asset removal — `wordpress_assets`

Controls covered: unused Dashicons, embed assets and block styles.

- **Retain:** One folded task surface, three matching rows and specific compatibility conditions. French long labels wrap while end switches remain separate. No confirmed defect in this card's inspected layout.
- **Optional refinement, P3:** Shorten internal vocabulary such as “discovery links and the host loader” into the effect users can recognize, retaining compatibility qualifications. Do not add detection metrics or another settings layer without an existing data source.
- **Acceptance:** Users still understand which pages qualify; anonymous-only and block-theme safeguards stay visible; configured state and native switches remain.
- **Evidence:** [desktop card](../../.artifacts/card-audit-20261009/captures/en_US-1440-dark-design-file_optimization-expanded-card-04-1.png), [French card](../../.artifacts/card-audit-20261009/captures/fr_FR-390-light-settings-enabled-file_optimization-expanded-card-04-1.png). Owner: `Page.php:707–725`.

## Media

### Images — `lazyload_section`

Controls covered: image/background lazy loading, local AVIF/WebP generation/delivery, dimensions, shared image/iframe exclusions and generation navigation.

- **Retain:** Image loading, delivery and reserved dimensions share one task surface with clear divided rows. Original-image fallback and the distinction between new uploads and existing-library generation are explicit. Dimension order matches source order.
- **Observed + source-confirmed, P2:** “Open media generation status” is ordinary navigation rendered with a circled exclamation helper glyph. It sits inline between settings as though it were a warning. The global checkbox helper template assigns the important icon to the plain anchor.
- **Impact:** Status navigation acquires false warning semantics and feels detached from a coherent settings/navigation pattern.
- **Smallest proposal:** Give generation navigation a compact owned destination row/footer, using the established navigation component and neutral semantics. Retain the local “save first, then queue” prerequisite. Do not turn the link into an operation button.
- **Acceptance:** Navigation carries no warning glyph; actual generation failures still use warning/danger semantics; the target remains keyboard/touch accessible; new uploads/existing-library scope remains clear.
- **Evidence:** [desktop card](../../.artifacts/card-audit-20261009/captures/en_US-1440-dark-design-media-expanded-card-00-1.png), [French card](../../.artifacts/card-audit-20261009/captures/fr_FR-390-light-settings-enabled-media-expanded-card-00-1.png). Owner: `Page.php:874–885`, `views/settings/fields/checkbox.php` helper markup, `assets/css/noobrocket-admin.css:267–268`.

### Videos and iframes — `video_delivery_section`

Controls covered: iframe/video lazy loading and parent-dependent YouTube previews.

- **Retain:** Parent and dependent choices remain together. The child is hidden when the parent is unavailable; configured state survives the closed disclosure. French labels do not overlap switches.
- **Observed, P3:** The only dependent switch is enclosed in a “YouTube previews” disclosure and then repeats essentially the same idea as “Replace YouTube iframe with preview image.” This adds an interaction and a second heading for one small choice; “Configured” communicates less directly than enabled/disabled for a sole boolean.
- **Smallest proposal:** Render the existing YouTube choice as a compact subordinate row while its parent is on; fold only extended help. Keep parent availability and any compatibility explanation.
- **Acceptance:** The child is reachable in one less interaction without appearing independent of its parent; saved value retention and disabled reasons remain intact; no switch is placed inside a clickable disclosure summary.
- **Evidence:** [French enabled card](../../.artifacts/card-audit-20261009/captures/fr_FR-390-dark-settings-enabled-media-expanded-card-01-1.png), [desktop card](../../.artifacts/card-audit-20261009/captures/en_US-1440-light-design-media-expanded-card-01-1.png). Owner: `Page.php:913–942`.

### Fonts — `font_optimization_section`

Controls covered: font preloading and self-hosting Google Fonts.

- **Retain:** Two independent decisions, consistent switches, short task grouping, visible consequences. No confirmed layout or control defect in inspected states.
- **Optional refinement, P3:** Explain “above-the-fold”/“text-based LCP” in the user's vocabulary and reduce duplicate header Help/inline help where destinations overlap. Preserve distinct documentation when it covers different behaviors.
- **Acceptance:** Font loading and hosting remain separate choices; short descriptions say when and where fonts load; English/French body copy wraps naturally in both themes.
- **Evidence:** [desktop card](../../.artifacts/card-audit-20261009/captures/en_US-1440-light-design-media-expanded-card-02-1.png), [French enabled card](../../.artifacts/card-audit-20261009/captures/fr_FR-390-dark-settings-enabled-media-expanded-card-02-1.png). Owner: `Page.php:945–967`.

## Heartbeat

### Background activity — `heartbeat_section`

Controls covered: master enable and WordPress admin/editor/public-site behavior selections.

- **Retain:** One surface, aligned selects on desktop, labeled stacked controls on mobile. Two-minute reduction and the consequences of disabling each context are explicit. The master promises retention of choices when off. No confirmed clipping, inconsistent width or unsafe missing-description defect in inspected states.
- **Observed, P3:** French 320px spends much of a viewport on several long descriptions of disabling while all selections currently read Reduce activity. This is density/readability polish, not evidence that a warning should be hidden.
- **Smallest proposal:** Shorten repeated session-notice prose; emphasize the editor's concrete autosave/post-lock consequence and shared two-minute meaning once. Keep each context's remaining consequences next to its control. Preserve this card's structure.
- **Acceptance:** All three contexts and consequences remain independently understandable; disabling the master retains values; native selects and their labels/focus remain readable at 320px. Do not replace this with three separate cards.
- **Evidence:** [desktop card](../../.artifacts/card-audit-20261009/captures/en_US-1440-dark-design-heartbeat-expanded-card-00-1.png), [French 320px card](../../.artifacts/card-audit-20261009/captures/fr_FR-320-light-settings-enabled-heartbeat-expanded-card-00-1.png). Owner: `Page.php:1458–1518`.

## Database

### Cleanup selection — `post_cleanup_section`

Controls covered: revisions, auto drafts, trashed posts, spam/trashed comments, all transients and table optimization, with seven available counts.

- **Retain:** Square selection checkboxes correctly differ from feature switches. Counts appear once per row; permanent deletion and backup scope are visible. Transients/table actions retain their additional explanations.
- **Observed, P2:** Each count repeats a full “in your database” sentence and competes with the category for a flexible row. French category labels wrap to two/three lines; counts reach four lines at 320px. The category name, which defines destructive scope, loses prominence to repeated count wording.
- **Impact:** Users scan deletion categories less reliably and spend unnecessary space on repeated context.
- **Smallest proposal:** Use a compact visible available-count value/label with one shared count heading/context. Keep complete category/count wording for accessible descriptions and cleanup review. At narrow widths, give category text priority rather than making the count prose the widest column.
- **Acceptance:** Each category and current count are unambiguous at 320/390/1440px; zero counts and pluralization remain correct; 44px checkbox/label targets remain; review still names full deletion scope/counts. Do not replace deletion selections with feature switches.
- **Evidence:** [390px selection](../../.artifacts/card-audit-20261009/captures/fr_FR-390-light-settings-enabled-database-expanded-card-00-1.png), [320px selection](../../.artifacts/card-audit-20261009/captures/fr_FR-320-dark-settings-enabled-database-expanded-card-00-1.png). Owner: `Page.php:1271–1340`, `views/settings/fields/checkbox.php`, `assets/css/noobrocket-admin.css:279`.

### Automatic Cleanup — `schedule_cleanup_section`

Controls covered: automatic schedule switch and conditional frequency.

- **Retain:** Scheduling is a distinct task with a feature switch rather than a deletion-selection checkbox. Frequency stacks correctly and remains readable at 320px.
- **Observed + source-confirmed, P2:** The card says “Automatic Cleanup” then “Schedule Automatic Cleanup” but provides no scope description. It does not explain here that future runs use the categories selected above, or that changing this form is pending until Save. The database subscriber actually reads those selections for cron cleanup.
- **Impact:** A destructive recurring task is under-explained at its point of configuration, despite clear scope later in the immediate-cleanup review.
- **Smallest proposal:** Add one short statement: after saving, the chosen categories run at this frequency. Keep the existing switch/select and remove redundant title wording where helpful. Show only scheduling facts the backend already knows.
- **Acceptance:** Users understand recurring scope and save ownership without invoking immediate cleanup; switching off retains the frequency; no next-run guarantee is fabricated.
- **Evidence:** [French enabled schedule](../../.artifacts/card-audit-20261009/captures/fr_FR-390-dark-settings-enabled-database-expanded-card-01-1.png), [320px schedule](../../.artifacts/card-audit-20261009/captures/fr_FR-320-light-settings-enabled-database-expanded-card-01-1.png). Owner: `Page.php:1343–1373`, `inc/Engine/Admin/Database/Subscriber.php:115–122`.

### Run database cleanup — action region

Controls covered: review-and-run submitter and its existing cleanup review/cancel/confirmation.

- **Retain:** The destructive button announces review; the prose names scope, schedule, other changed settings and permanent deletion. Source confirms the dialog lists selected counts, exposes changed settings, supports Cancel and restores focus. No claim that unrelated pending settings are silently submitted: this is already disclosed.
- **Observed, P2:** Immediate cleanup appears after the scheduling card, outside the selection surface, with a lighter standalone heading and another long backup/scope paragraph. It is visually owned by neither selection nor scheduling and repeats information a user has just read.
- **Impact:** The page does not clearly separate “run the selected cleanup now” from “schedule future runs.” Its most consequential action is detached from the categories it will delete.
- **Smallest proposal:** Make immediate review the footer action of the cleanup-selection surface, then present scheduling as its separate task. Keep a short visible destructive statement and the full existing confirmation including every pending setting. This is an ownership/layout change, not removal of safeguards.
- **Acceptance:** Selection and immediate review are adjacent; recurring scheduling remains separate; confirmation still names deletion scope/counts, schedule and all other pending changes; Cancel restores focus; submitting zero categories remains blocked.
- **Evidence:** [French full page](../../.artifacts/card-audit-20261009/captures/fr_FR-390-light-settings-enabled-database-default.png), [desktop full page](../../.artifacts/card-audit-20261009/captures/en_US-1440-dark-design-database-default.png). Owner: `views/settings/page-sections/database.php:24–31`, `views/settings/page.php` cleanup dialog, `assets/js/noobrocket-admin.js:509–563`.

## Recommended order

1. Correct the CSS radio indicator, Database count hierarchy and recurring-cleanup scope.
2. Restore ownership of image-generation/preload navigation and immediate Database cleanup; order CSS setup before exception editing.
3. Clarify background-refresh readiness and Combine prerequisites; improve the expanded cache-policy grouping.
4. Remove duplicated headings/help, reduce excess helper emphasis, simplify the single YouTube child disclosure and polish translated wording.

Review existing controls and surfaces after each focused change. An accessibility/test pass is required for implementation; this audit's screenshot coverage is not an implementation sign-off or a beauty score.

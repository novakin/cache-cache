# Card audit: Asset Manager, CDN, Settings history and Tools

Audit date: 2026-10-09. This is a review and improvement proposal; no maintained plugin code or saved site data was changed by this audit. Current native WordPress output and workflow ownership are the reference. The mockup is not an acceptance target. UI/UX Pro Max, Frontend Design, Design System and [the current design guidelines](../DESIGN_GUIDELINES.md) inform the assessment.

## Evidence and limits

Source-confirmed findings describe maintained rendering or action behavior. Observed findings require direct inspection of fresh screenshots. Inferred concerns are identified separately. Synthetic request-only workflow cases expose populated, stale and review states without running import, publication, restoration, export or inspection actions. A screenshot of a confirmation does not prove that a transaction succeeds. No aesthetic score is assigned.

Direct inspection covered the assigned cards in English at 1440px and French at 390px, both light and dark, with default and relevant expanded states. Synthetic populated/stale Asset Manager, CDN mixed/image-only, five-snapshot History and staged Import states were reviewed. Relevant French 320px states were inspected in both themes. Import comparison-bottom crops verify long values and private-value masking. Geometry comes from the [fresh runtime inventory](../../.artifacts/card-audit-20261009/inventory.json); referenced filenames below are relative to [its capture directory](../../.artifacts/card-audit-20261009/captures).

Limits: this branch did not execute transactions, inspect actual server upload errors, mutate published rules, simulate simultaneous writers, run a screen reader or validate a real CDN network response. Empty inspection/history and identical import behavior were read from source; empty draft, unchanged publication and an identical history snapshot were directly inspected. Forced French WordPress locale did not localize every core date; native file-picker language follows browser locale. These are fixture/runtime limits, not reported product translation defects. Minimal synthetic settings also triggered a normalization success message despite SQL writes being blocked; it is not evidence of an import or restore. That fixture toast is excluded from assessment, and fresh Import card crops hide it. The first workflow fixture had a metadata recursion and an incorrect synthetic key; both were corrected, and affected captures were rerun before review.

## Card and ownership map

| Route | Surface | Maintained owner | Important states |
| --- | --- | --- | --- |
| Asset Manager | Inspect a page | `noobrocket/views/assets.php:51` | Empty inventory, populated inventory, expanded source/dependency detail |
| Asset Manager | Draft rules | `noobrocket/views/assets.php:112` | Empty editor, prepared rule, configured exceptions, rejected entry, saved rules, discard |
| Asset Manager | Preview and publish | `noobrocket/views/assets.php:197` | Unchanged, added/removed rules, empty draft replacing published rules, stale base, confirmation |
| CDN | CDN connection | `noobrocket/inc/Engine/Admin/Settings/Page.php:1393`, `noobrocket/views/settings/fields/cnames.php` | Off/on, hostname rows, configured exclusions, inline invalid hostname |
| CDN | Verify saved delivery | `noobrocket/views/settings/page-sections/page-cdn.php:55` | CSS rewritten, image-only delivery with original CSS sample |
| Settings history | Restore scope and limits | `noobrocket/views/history.php:13` | Closed/open disclosure |
| Settings history | Snapshot list and opened comparison | `noobrocket/views/history.php:21` | Empty history, five snapshots, identical snapshot, setting/rule changes, long values |
| Tools | Export/Import settings | `noobrocket/views/settings/page-sections/tools.php:41`, `noobrocket/views/settings/fields/import-form.php:120` | Empty/selected native file input, transfer scope, related navigation |
| Tools | Review imported settings | `noobrocket/views/settings/fields/import-form.php:63` | Changed/identical import, hidden credentials, long values, expiry/conflict instructions |

## Source-confirmed interaction findings

### W01: Publication does not bind confirmation to the reviewed private draft

Priority: P1, interaction correctness. The publication form sends only the command and acknowledgement. `Assets\Admin::handle_request()` rereads the current user's draft on submission; `publish()` validates the published base, but does not compare the draft with the version displayed to the user. A second tab can change the same private draft after the first tab has reviewed it. The first tab's acknowledgement can therefore apply a different rule list. This is a source-derived concurrency path; no publication was executed during the audit.

Proposal: keep the existing action owner and published-base conflict check. Carry the reviewed draft state through the publication form and reject a changed draft with a local explanation and fresh review. Reuse the import flow's reviewed-current-state principle; do not introduce a new transaction service.

Acceptance: changing the private draft in another tab after review prevents publication; the draft remains available; the refreshed review names actual added/removed rules; unchanged publication remains unavailable. Source: `noobrocket/views/assets.php:233`, `noobrocket/inc/Engine/Optimization/Assets/Admin.php:108`, `:192`.

### W02: A history comparison can become stale before restoration

Priority: P1, interaction correctness. The scope copy accurately says comparison uses saved state when the page loaded. `Snapshots::restore()` rereads current settings and rules but does not receive or verify the state used to build that comparison. Another tab or administrator can change saved values between review and restore, making the displayed replacement scope incomplete. This is source-derived; no settings were changed to demonstrate it.

Proposal: use the existing snapshot restore owner to validate the reviewed saved state before replacement, then require a refreshed comparison on conflict. Keep current credentials, private drafts and the pre-restore recovery snapshot behavior.

Acceptance: an intervening settings or published-rule change refuses the old confirmation and shows a current comparison; an unchanged reviewed state restores normally; missing/evicted snapshots keep their existing recovery message. Source: `noobrocket/views/history.php:17`, `:86`, `noobrocket/inc/Engine/Optimization/Assets/Snapshots.php:144`.

### W03: Retained add-rule errors sit above the wrong card

Priority: P2, form recovery. An add-rule error is intentionally inline, but its markup is immediately below the page heading, before the Inspect card. The rejected values remain in the lower Draft rules card. A return-to-editor link compensates for this separation.

Proposal: render add-rule validation inside Draft rules immediately above the editor; place publication conflicts with Preview and publish. Retain the message, rejected values, open exception state and native fallback. Do not convert local validation into a toast.

Acceptance: the error and retained values share the owning card, keyboard focus can reach the first relevant field, and the rejected entry remains editable after reload. Source: `noobrocket/views/assets.php:46`, `:112`, `:154`.

### W04: Inspection and draft preview are the same operation under different labels

Priority: P2, workflow clarity. Inspect page and Preview saved draft both send `command=preview` and redirect through the same private frontend preview path. They each ask for a URL in separate cards. The inventory records URL/time/assets, but not which draft produced it. Earlier preview results can remain visible after subsequent draft edits.

Proposal: explain once that inspection opens a private preview and records its assets. Reuse that inspection URL/result in the publication surface, with a clear preview action when changes require another check. Associate existing inventory evidence with the draft it inspected so outdated results are visibly identified. Keep manual visual testing and the explicit publication acknowledgement; do not claim automated testing of site interactions.

Acceptance: users can distinguish opening the page, selecting an asset and publishing; URL reuse does not erase edits; old inventory is identified after a draft change; required dependency evidence remains available. Source: `noobrocket/views/assets.php:54`, `:203`, `noobrocket/inc/Engine/Optimization/Assets/Admin.php:154`, `noobrocket/inc/Engine/Optimization/Assets/Frontend.php:136`.

### W05: CDN verification only produces a CSS sample

Priority: P2, diagnostic fit. CDN resource scope supports Images, CSS, JavaScript and combinations, but Verify saved delivery always samples the plugin stylesheet. A valid image-only configuration therefore receives the original-host explanation and no example of its actual enabled scope. Copy avoids claiming a successful network check, which should be retained.

Proposal: identify the saved resource scope and present an appropriate saved sample for eligible types when one is available. If no representative asset exists, state that plainly and give a direct public-page verification step. Keep this a manual verification workflow; adding a network service is unnecessary for this proposal.

Acceptance: Images-only configuration is described as such rather than as a possibly disabled CDN; CSS-only and mixed configurations have relevant examples; samples clearly use saved settings; unavailable examples do not become failure claims. Source: `noobrocket/views/settings/fields/cnames.php:12`, `noobrocket/views/settings/page-sections/page-cdn.php:53`.

### W06: Checked native checkbox is invisible in light mode

Priority: P2, control-state accessibility. In the populated Asset editor, the expert option is checked and its Post ID field is visible. Light screenshots render an empty white square; dark screenshots render a visible white tick. Root's stylesheet inspection and browser probe confirm current WordPress uses a white check SVG on a blue checked background, while the plugin's blanket checkbox surface override removes that background. The same probe confirms invisible publication acknowledgements in light mode. This is a current rendered defect, not a fixture value or a preference about appearance.

Proposal: preserve native checkboxes and restore an authoritative checked background/marker with visible contrast in both themes. Scope operational switch styling separately; do not let a generic surface rule erase native checked-state presentation.

Acceptance: off/on/disabled states are distinguishable in light/dark for the expert checkbox and native publication/restore acknowledgements; keyboard focus remains visible; labels retain their 44px hit area. Evidence: `captures/en_US-1440-light-workflow-assets-populated-assets-default-card-01-1.png`, dark equivalent; source `noobrocket/assets/css/noobrocket-admin.css:271`.

### W07: Disabled primary Publish remains visually active

Priority: P2, interaction-state reliability. The stale publication form is disabled, but the captured Publish rules control retains the same blue background and white text as enabled publication. `inventory.json` confirms `disabled=true`, white text and `rgb(66, 108, 228)` background in the dark stale case; the shared browser probe confirms both themes. The shared primary selector includes the Save button ID inside `:is()`, giving all primary rules ID specificity; the generic disabled rule cannot override them. Save has a separate correction, while other primary buttons do not.

Proposal: separate the fixed-ID Save selector from shared primary classes, then make disabled presentation authoritative for background, text, border, cursor and hover. Reuse existing disabled semantic colors; do not add a one-off stale-publication patch.

Acceptance: stale publication looks and behaves unavailable; enabled publication remains the primary action; all primary controls use the same disabled state in both themes; disabled hover cannot reintroduce an active treatment. Evidence: `captures/en_US-1440-dark-workflow-assets-stale-assets-default-card-02-1.png`, FR320 light/dark equivalents; source `noobrocket/assets/css/noobrocket-admin.css:215`, `:219`.

## Card-by-card visual assessment

### Asset Manager: Inspect a page

Retain: the URL has a visible label; inspection is a native form; inventory is folded by default; the populated summary exposes asset totals; source and dependency details remain available; the inventory uses a named local scroll region rather than page-level horizontal scrolling. Light and dark use the same structure.

Observed improvement: the primary invitation after inspection is Go to draft editor, ahead of Browse inspected assets. The empty editor then asks for a registered handle. Asset selection is the prerequisite for most users, but its action is visually secondary. Expanded inventory also places four explanatory paragraphs before the first selectable asset.

Proposal: make Choose an inspected asset the first next step, using the existing disclosure and Prepare rule action. Keep manual handle entry available as a secondary path. Move detailed inventory limitations into one short disclosure while preserving a visible signed-in-preview qualification. Use a simple local handle/type filter only if the current 27-row scan remains cumbersome; no list framework is required.

Acceptance: a user can select an inspected asset without first identifying its raw handle; dependency warnings remain visible for the selected row; the draft editor stays reachable; inspection URL, time and draft freshness are identifiable. Workflow ownership and freshness also require W04. Evidence: `captures/en_US-1440-light-design-assets-expanded-card-00-1.png`, `captures/fr_FR-390-light-design-assets-expanded-card-00-1.png`.

### Asset Manager: Draft rules

Retain: recognizable scope choices, native labels, explicit private-draft ownership, hidden optional exceptions, retained invalid exceptions and separate saved-rule rows. The two-column desktop grid becomes one column on narrow screens without clipped fields in the baseline captures.

Observed improvement: the empty editor exposes the full technical form before an asset is selected. On French mobile, ownership copy is noticeably smaller than equivalent opening copy in Inspect and Preview. The baseline card is approximately 961px high, partly because the empty saved-draft subsection repeats instructions already given above. This is task hierarchy and density, not a palette defect.

Proposal: use one short essential ownership sentence at the normal body size, identify the selected asset/page as the editor context, and reduce the empty saved-draft subsection to one clear line. Keep manual controls and the current expert ID path; do not hide rejected input or configured exceptions. Move add errors into this card under W03 and correct native checked presentation under W06.

Observed populated-state finding: two saved rules already exceed the shared inventory's 28rem cap on French mobile, cutting the second row's exceptions/action above Discard draft. The mobile table also inserts an Action caption above the self-explanatory Remove button. Distinguish short saved-rule lists from large inspected inventories; keep short rows completely visible and remove redundant action captions while retaining accessible per-asset button names. Preserve bounded local scrolling for genuinely large lists.

Acceptance: selected asset context is recognizable; all existing scopes remain available; error values stay editable; empty and populated draft states do not repeat the same workflow explanation; a two-rule draft does not conceal its second action inside an unannounced nested scroll area. Evidence: `captures/fr_FR-390-light-design-assets-default-card-01-1.png`, `captures/en_US-1440-dark-design-assets-default-card-01-1.png`, `captures/fr_FR-320-light-workflow-assets-stale-assets-default-card-01-2.png`.

### Asset Manager: Preview and publish

Retain: explicit visitor impact, separate acknowledgement, disabled unchanged publication and concrete added/removed-rule review. The native forms preserve operation ownership.

Observed finding: an unchanged draft still renders a second URL field, preview action, disabled acknowledgement, disabled Publish button and history link. That empty workflow occupies approximately 400px on desktop and 496px in the French 390px capture. The history destination is also a permanent underlined link beneath the publication button, repeating the mixed action/navigation treatment already rejected elsewhere.

Proposal: show a compact No unpublished changes state when there is nothing to publish. Expand the existing review and confirmation surface only for a changed draft; show conflicts and removal scope explicitly. Reuse the inspection URL/evidence instead of duplicating its entry. Place history navigation in the same divided navigation-footer pattern as the dashboard. Correct the review boundary under W01 and disabled treatment under W07. A stale conflict should link directly to the existing discard/restart control rather than only instructing users to find it in the preceding card.

Acceptance: unchanged publication needs no disabled form; a changed or intentionally empty draft still displays concrete visitor impact, preview and confirmation; stale drafts expose recovery beside the blocked action; navigation and operations are separated. Evidence: `captures/en_US-1440-light-design-assets-default-card-02-1.png`, `captures/fr_FR-390-dark-design-assets-default-card-02-1.png`.

### CDN: CDN connection

Retain: one grouped surface, end-aligned operational switch, explicit hostname/resource-scope labels, a recognizable remove icon, 44px action targets, and folded exclusions. French controls stack coherently, and removal remains beside its hostname. No confirmed baseline card-width, clipping or palette defect was found in the directly inspected four contexts.

Proposal: preserve this structure. Add a short visible explanation of what rewriting changes and where the CDN provider setup occurs; the switch currently has no purpose copy. Keep actual hostname errors attached to their rows rather than introducing a general warning panel. This is a copy/discoverability refinement, not a reason to rebuild the card.

Acceptance: users can explain the effect before enabling rewriting; hostname scope and remove controls stay associated at 320px; invalid values have a nearby message. Evidence: `captures/en_US-1440-light-design-page_cdn-default-card-00-1.png`, `captures/fr_FR-390-dark-design-page_cdn-expanded-card-00-1.png`.

### CDN: Verify saved delivery

Retain: it is secondary to configuration; copy distinguishes saved settings from pending edits; manual public-page checks and recovery remain explicit. It does not falsely report a successful network measurement.

Observed improvement: the inactive/original-host state contains four paragraphs, reaching approximately 460px in French mobile, without a direct next-step link or relevant sample. The scope mismatch is described in W05. In enabled delivery, the key/value layout remains two columns at 390px and 320px, leaving long URLs in a narrow column and fragmenting their paths across many lines.

Proposal: begin with a compact state sentence for the saved scope, then an appropriate sample or its absence. Stack URL labels above full-width values at the shared narrow breakpoint. Keep public-page verification as one short sequence, with failure recovery disclosed below it. Preserve visible recovery when there is an actual failure; do not create an automatic verification service merely to make this surface look active.

Acceptance: enabled resource types determine the example; original-host CSS is not treated as suspicious under valid image-only delivery; recovery stays accessible; the initial state can be understood without reading four paragraphs; URLs have the full narrow card measure. Evidence: `captures/fr_FR-390-light-design-page_cdn-expanded-card-01-1.png`, `captures/en_US-1440-dark-design-page_cdn-expanded-card-01-1.png`, `captures/fr_FR-320-dark-workflow-cdn-enabled-page_cdn-expanded-card-01-1.png`.

### Settings history: Restore scope and limits

Retain: the disclosure names its job clearly, uses native semantics and stays outside the snapshot list. The route identifies that this is settings history, and scope explicitly excludes credentials, private drafts, deleted content and server changes.

Observed concern: real snapshot comparisons include cleanup selections labeled Revisions, Auto Drafts and Optimize Tables. The opened restore form states replacement of optimization values, but the explicit inability to recover deleted database data is inside the separate scope disclosure. The resulting misunderstanding is inferred; the placement and labels are observed.

Proposal: when a review contains cleanup selections, identify them as selection/schedule settings and retain one nearby sentence that restoration cannot recover deleted records. Keep the full limits disclosed rather than repeating all policy text for every entry.

Acceptance: reviewing a cleanup-related snapshot cannot reasonably be read as restoring deleted records; other snapshots remain compact; confirmation and recoverability statements remain accurate. Evidence: `captures/en_US-1440-dark-design-history-expanded-card-00-1.png`; owner `noobrocket/views/history.php:13`.

### Settings history: Snapshot list and opened comparisons

Retain: one grouped list, affected setting names, full before/after values, native expandable rows, separate acknowledgement and responsive value labels. These are useful strengths; converting each entry to a decorative card would weaken scanning.

Observed finding: each closed row repeats Review changes before restoring, both counters and affected setting names. Five rows occupy approximately 918px at French 390px. Timestamp is visually strongest while the information explaining the state is tertiary. Every zero asset-rule count remains in the row even when irrelevant. An identical synthetic snapshot has only its date and Review changes before restoring in the closed state; users must open it to learn that there are no changes or restore action. French confirmation calls the state an instantané, while its action says Restaurer la sauvegarde, which also weakens the partial-settings scope.

Proposal: use a concise affected-settings summary as the recognizable identifier, keep time secondary, omit irrelevant zero counts, and shorten the disclosure cue to Review changes or rely on the labeled summary plus chevron. Show Matches current saved state for identical snapshots. Keep full comparison and restore confirmation inside the disclosure. Use consistent French terminology such as état enregistré through PO/MO catalogs; avoid making a settings state sound like a whole-site backup. Correct W02 separately.

Acceptance: five closed snapshots scan without repeated long CTA text; identical snapshots are recognizable before opening; dates and counts remain accessible; opening reveals complete settings and rule differences; long values remain locally readable. Evidence: `captures/fr_FR-390-light-design-history-default-card-00-1.png`, `captures/en_US-1440-light-design-history-default-card-00-1.png`.

### Tools: Export/Import settings

Retain: the current single divided surface has coherent action ownership. Export and import have separate headings; privacy scope is explicit; the file input is labeled; upload and review is the primary action; controls stack in French mobile. No confirmed baseline card-layout, typography or palette defect was found in the four directly inspected contexts. This card does not need another redesign simply because other pages do.

Observed improvement outside the card: Settings history and Asset Manager are detached permanent underlined links below the surface, repeating destinations in the primary navigation and appearing in the opposite order to it. They relate directly to the stated transfer exclusions, but their visual relationship is weak.

Proposal: if these destinations are retained, connect them to the transfer-scope footer using the existing compact navigation pattern, consistent order and task-specific labels. Keep Download and Upload as operation controls. Do not turn navigation into extra action buttons or cards.

Acceptance: the transfer surface keeps its current compact structure; navigation clearly explains management of excluded asset rules/history; native labels, upload validation and privacy copy remain intact. Evidence: `captures/en_US-1440-light-design-tools-default.png`, `captures/fr_FR-390-dark-design-tools-default.png`.

### Tools: Review imported settings

Retain from source: actual saved/imported differences, masked private values, explicit replacement scope, ten-minute expiry, server checks against changed saved state, and a native Cancel import action. These are stronger transaction boundaries than publication/history currently have.

Observed hierarchy finding: staged review appears after Export settings but before the Import settings heading, inside the original transfer card. The active task is preceded by unrelated export controls, and the review heading is lighter than neighboring task headings. The review's full policy paragraph repeats transfer exclusions/privacy while adding expiry and concurrency requirements. On narrow screens, this is a long setup before a locally capped comparison and confirmation actions.

Proposal: make the active review the first task or place it immediately under the Import heading. Use the existing task heading style and concise scope text; display the actual expiry/readiness separately. Keep choosing another file a clear secondary path while a review exists. Preserve native forms and their separate action ownership. Keep the comparison bounded and keyboard reachable, but make the need to inspect remaining differences clear before Replace saved settings.

Observed metadata finding: the comparison displays internal `cdn zone`, `css_and_js`, and, with inactive provider fields, `cloudflare api key`. These are known option keys in a valid transfer path, not an arbitrary unknown file schema. The CDN scope is a nested control under CNAME configuration, and inactive provider fields are absent from the rendered field registry. Existing formatting then falls back to underscore replacement rather than localized labels and recognizable choices.

Proposal: complete review metadata for known nested controls and inactive integration fields at the current metadata owner. Reuse the actual CDN scope choices and provider labels, with English gettext source and PO/MO translations. Keep unknown extension keys identifiable rather than inventing a human label for them. Apply the same known-key formatting in history comparisons where applicable; no separate review UI or translation framework is needed.

Acceptance: the active import task precedes unrelated export; its heading matches other task headings; expiry and replacement scope remain visible; every supported known setting and enum has a readable localized name; private values stay masked; Cancel, Replace, stale-state and expired-review behavior remain intact. Evidence: `captures/en_US-1440-light-workflow-import-review-tools-default-card-00-1.png`, `captures/fr_FR-390-dark-workflow-import-review-tools-default-card-01-1.png`, `captures/fr_FR-320-light-workflow-import-review-tools-expanded-comparison-bottom.png`.

Source improvement: a renamed valid JSON export is rejected with Incorrect filename. Only JSON format is supported, which conflates filename policy with file format. The visible import instructions correctly request the original name, but the recovery message should explain how to recover it.

Proposal: preserve the existing original-filename policy and JSON/content validation. State that the file must keep the NoobRocket export filename, and direct users to select the original export or export again. Do not weaken validation as part of a wording fix. Source: `noobrocket/inc/admin/admin.php:403`.

## Implementation order proposed

| Priority | Work | Smallest responsible change | Proof before acceptance |
| --- | --- | --- | --- |
| P1 | Reviewed-state publication/restore, W01–W02 | Existing native form/action owners | Isolated stale-review transaction cases; no unreviewed replacement |
| P2 | Checked and disabled state presentation, W06–W07 | Shared native checkbox and primary-button styles | Checked/off/disabled/hover/focus in both themes at 320/390/1440 |
| P2 | Asset selection, retained errors and empty publication, W03–W04 | Existing three workflow surfaces, editor helpers and current preview metadata | Empty/prepared/rejected/changed/stale drafts; keyboard recovery |
| P2 | Short saved-rule visibility | Separate short draft list from large inventory layout | Two-rule FR320/390 list has complete actions and exceptions |
| P2 | CDN saved scope and narrow URL measure, W05 | Existing manual verification template and responsive key/value rule | Mixed/image-only examples, 320px long paths, saved/pending distinction |
| P2 | History scan and identical-state clarity | Existing disclosure summaries and comparison copy | Five rows, identical snapshot, cleanup selections, long comparison values |
| P2 | Import active-task hierarchy and known-field metadata | Existing import template and native settings metadata | Changed/identical/expired/conflicted review; inactive providers; translated scope enums |
| P2 | Publication plurals | `_n()` pairs and catalogs, as already used by History | 0/1/2 added and removed rules in English/French |
| P3 | Detached history/tool navigation | Existing divided navigation-footer pattern | Operations and destinations clearly separated, logical keyboard order |
| P3 | Original-filename recovery copy | Existing import error message | Renamed valid JSON receives actionable filename guidance |

Publication plurals are a current observed copy defect: one added/removed rule is rendered as 1 rules added; 1 rules removed in English and 1 règles ajoutées; 1 règles retirées in French. The template uses a fixed plural string. Reuse History's existing separate `_n()` count pattern and update catalogs, rather than hardcoding language-specific grammar in PHP.

The sound baseline Tools transfer card and CDN editable rows should be retained. These proposals do not require a new navigation model, component library, modal system, color palette or card-per-option layout. Shared control correctness precedes aesthetic refinements; operation ownership and review boundaries remain mandatory.

# UI and brand reaudit — 10 October 2026

Six confirmed gaps fixed in the existing shell, fields and views. The audit covered all 14 maintained settings routes, both supported themes, English/French, compact layouts, expanded disclosures, configured/provider prerequisites and the private asset/history/import workflows. Existing cards, typography, tokens, native form ownership and navigation remain the design system; no new component framework, runtime bundle or interaction layer.

## Confirmed findings and corrections

| ID | Reproduced gap | Correction and responsible files |
| --- | --- | --- |
| UI01 — Brand shell | The header retained the rocket mark, NoobRocket and Flight Deck identity, with the earlier palette. | [`settings/page.php`](../../noobrocket/views/settings/page.php) uses the supplied outlined Cache-Cache horizontal light/dark logos and one accessible brand name. [`noobrocket-admin.css`](../../noobrocket/assets/css/noobrocket-admin.css) switches the correct supplied asset with the persisted/system theme, including early HTML theme application. Existing semantic tokens now carry the supplied neutral/indigo palette. Desktop/mobile shell heights are 72/64px; the compact Settings action remains named at 320px. The two new SVGs are byte-identical copies of the supplied files. Source/runtime identifiers remain stable. |
| UI02 — CSS setup guidance | The CSS generation prerequisite repeated the advanced setup link and exposed its long destination in the guidance. | [`fields/select.php`](../../noobrocket/views/settings/fields/select.php) keeps one friendly setup link to `docs/index.html#css`. [`Settings/Page.php`](../../noobrocket/inc/Engine/Admin/Settings/Page.php) retains environment/status guidance without the duplicate link. |
| UI03 — JavaScript-free navigation | With JavaScript disabled, the compact navigation remained hidden while Settings/Appearance controls stayed visible and inert. The original compact shell also overflowed and exceeded its intended height. | The existing `.no-js` branch in [`noobrocket-admin.css`](../../noobrocket/assets/css/noobrocket-admin.css) hides JS-only shell actions and makes static compact navigation reachable. All 14 sections remain available; normal JS navigation still uses the existing controller. |
| UI04 — JavaScript-free field actions | Add hostname, credential Show and PageSpeed Check actions remained visible without working handlers. | [`fields/cnames.php`](../../noobrocket/views/settings/fields/cnames.php), [`fields/text.php`](../../noobrocket/views/settings/fields/text.php) and [`partials/pagespeed.php`](../../noobrocket/views/settings/partials/pagespeed.php) use the existing `hide-if-no-js` convention. PageSpeed explains the JavaScript requirement while preserving its native API-key form and saved results. |
| UI05 — Compact provider alignment | A later general provider rule overrode the narrow Cloudflare/Sucuri heading rule; the title/action arrangement became centered rather than matching the compact card layout. | The late mobile rule in [`noobrocket-admin.css`](../../noobrocket/assets/css/noobrocket-admin.css) matches the responsible specificity. Cache headings and actions stack with consistent left alignment. |
| UI06 — Count grammar | Views emitted “1 rules added,” “1 rules removed,” and “and 1 other settings.” CSS/JS and published/draft counts also lacked native singular/plural handling. | [`assets.php`](../../noobrocket/views/assets.php) and [`history.php`](../../noobrocket/views/history.php) use WordPress `_n` for each count, then compose localized phrases. [`wordpress-assets-ux.php`](../../tests/wordpress-assets-ux.php) updates the existing duplicate-occurrence assertions. Root updated/compiled the French PO/MO for all seven plural pairs and both composition keys. |

The existing appearance fixture in [`settings-appearance-browser.mjs`](../../tests/settings-appearance-browser.mjs) now expects the current light/dark brand backgrounds. Status semantic pairs were retained. The CSS count, provider and no-JS fixes reuse existing owners and utilities.

## Page-by-page visual review

All default routes were visually inspected at desktop English/light and mobile French/dark. Opened controls and workflow cases were reviewed at their actual crop widths; tight French 320px cases cover publication recovery, history, import comparison and configured/settings branches. Automated geometry complements this inspection.

| Route | Reviewed states and outcome |
| --- | --- |
| Overview — `dashboard` | Base, unverified, problem and ready summaries; page-cache/font/image/CSS status hierarchy and action placement remain clear. |
| CDN — `page_cdn` | Unconfigured, enabled and image-only scope; opened exclusions/verification; hostname columns, Add/Remove controls and long guidance wrap within mobile cards. UI04 fixes no-JS Add. |
| CSS & JavaScript — `file_optimization` | Base/enabled, opened exclusions and compatibility; setup prerequisite now has one friendly link. Dependent controls preserve their hierarchy. UI02 applied. |
| Media — `media` | Base/enabled and opened exclusions; images, dimensions and exclusions retain clear group boundaries and wrapping. |
| Preload — `preload` | Enabled controls and opened preload/prerender exclusions; disclosures, dependencies and long field guidance fit compact widths. |
| Cache exclusions — `advanced_cache` | Opened URI/cookie/user-agent/purge/query fields; spacing and monospaced multiline inputs remain legible. |
| Database — `database` | Cleanup categories, counts and opened schedule; native form scopes, scheduling dependencies and count alignment are intact. Native count text contrast checked separately after axe uncertainty. |
| Heartbeat — `heartbeat` | Base/enabled controls; independent areas and frequency choices remain coherent in both themes. |
| Integrations — `addons` | Disabled/configured integrations and opened compatibility guidance; badges and controls retain their existing hierarchy. |
| Cloudflare — `cloudflare` | Disabled, prerequisite and configured credentials/settings/cache; narrow action alignment corrected. Native masking and named Show/Hide remain correct with JS; UI04 hides inert no-JS Show. |
| Sucuri — `sucuri` | Disabled, prerequisite and configured credentials/cache; provider heading/action alignment corrected and long help text wraps. |
| Tools — `tools` | Import review, settings comparison and its scrolled bottom, including French 320px; native review/submission scopes and long values remain within the cards. PageSpeed saved/expanded output and key management reviewed. |
| Asset Manager — `assets` | Empty, populated and stale private drafts; opened inventory/source/dependency details, draft fields, publication comparison and stale recovery, including French 320px. Singular publication copy now reads correctly. |
| History — `history` | Populated snapshots and opened comparisons, including French 320px; remaining-setting singular copy corrected. |

The review found no reason to restyle established card spacing or add a replacement navigation system. The supplied wordmark, semantic palette and six corrections are the UI scope.

## Verification

| Evidence | Result |
| --- | --- |
| Native render inventory | The final refreshed matrix contains 448 default/open render records across all 14 routes and request-only variants: zero page overflow and zero browser errors. It uses the final branding/catalogs under `.artifacts/reaudit-final-20261010`; the initial 448-record evidence remains under `.artifacts/reaudit-20261010`. |
| Final geometry proof | Root verified 3,988 action instances at least 44px high. Baseline route widths are consistently 1,008px on desktop and 356px at a 390px viewport; no operation requests occurred. |
| Native shell proof | 495 assertions over 30 states: English/French, 1440/1100/783/600/390/320px, light/dark, and six actual JavaScript-disabled cases. Supplied assets load, one logo is visible, accessible brand name is present, header offsets/heights hold, menu/Escape works, early theme applies, no overflow occurs, provider alignment holds and all 14 no-JS sections/native key controls remain reachable. |
| Controls fixture | 248 assertions over 16 viewport/theme/motion cases. |
| Appearance fixture | 405 assertions and seven intercepted synthetic Save redirects. No WordPress writes. |
| Semantic color proof | 34 color pairs pass: text at least 4.5:1; controls/focus at least 3:1. |
| Native axe scope | 112 states: all 14 routes × default/open × English 1440px and French 390px × light/dark. WCAG 2.0/2.1/2.2 AA tags report zero violations and zero page errors within `.noobrocket-app`. |
| Axe incomplete resolution | The 28 incomplete rule records concern off-screen/partially obscured textareas and database counts whose labels contain checkbox pseudo-elements. All 58 distinct state/selector nodes were scrolled into view and checked using native computed foreground/background colors and ancestor opacity. Minimum text contrast is 7.578:1; no opacity effects. Existing actual-width crops confirm the layout. These are manual contrast resolutions, not a claim that axe returned no incomplete nodes. |
| Native count grammar | Six read-only English/French populated/stale/history states verify inventory and published/draft plurals, singular added/removed rules, localized composition and “1 other setting.” Zero page overflow/errors. |
| PHP syntax and isolated regression | Syntax passes for changed PHP views/settings owners and updated asset fixture. The existing isolated asset/history second-pass fixture passes 27 assertions without WordPress bootstrap, database or network. The WordPress-bootstrap asset UX fixture was not run against the protected shared runtime. |
| Final visual spot checks | The refreshed French 320px stale-publication card and French 390px dark configured Cloudflare page retain correct palette, count grammar, wrapping and provider action alignment. Root additionally reviewed final desktop overview and mobile CSS/JS captures. |

Native probes permit only the same-origin settings/static/read-only identity GETs, block non-GET/operation/external requests, and use request-only synthetic cases. Root owns the protected runtime, authentication fixture and final preservation checks. These UI checks never invoke cache purge, publication, inspection, PageSpeed network calls or credential writes.

Evidence artifacts: `brand/proof.json`, `brand/baseline.json`, `brand/token-proof.json`, `axe-native.json`, `grammar-contrast-proof.json`, `inventory.json` and actual-width captures under `.artifacts/reaudit-20261010`. The final refreshed captures are under `.artifacts/reaudit-final-20261010`. Source snapshots remain under `.artifacts/reaudit-20261010/before/`.

Quiet command receipts under `C:/Users/Guillaume/.codex/logs/commands`:

- Controls: `20261010-005234-771-f2a90868e72f4d10a49c2c1d20449d01.log`.
- Appearance: `20261010-005234-771-ebdc6c0d6c864fc2ba32f85d2844c935.log`.
- Native shell: `20261010-005234-781-ffabdcf236a74d3e8684d4f7397d5083.log`.
- Semantic colors: `20261010-004722-485-4f437fb5111c4fccbc61fd2c37edc634.log`.
- Isolated asset/history: `20261010-005933-961-9de1c9dbb67b4489b65a9f9e2a3ae46b.log`.
- Axe: `20261010-005933-961-8f199f74ed8a4bcbba6a259e10b30686.log`.
- Native grammar/contrast: `20261010-010156-694-78df67ece9e247268b87fd380badf5ef.log`.
- Final native capture: `20261010-005820-469-640c151ce93248059947fbc30608d15a.log`.
- Final geometry: `20261010-010300-967-d8b5fa6094d5476691bd7653b42985c3.log`.

## Limits and state

No new confirmed UI defect remains in the reviewed output. This is a scoped accessibility/visual proof, not a manual screen-reader certification or external PageSpeed/site-performance measurement. WordPress toolbar/avatar rendering and third-party provider branding outside the plugin-owned shell are outside the axe scope. Product-specific live operations require their own existing workflow evidence.

No packaging, commit, push or deployment. The workspace has no Git metadata; original source snapshots and command/evidence receipts provide review provenance. Root maintains the full audit, source/translation validation and runtime-preservation record.

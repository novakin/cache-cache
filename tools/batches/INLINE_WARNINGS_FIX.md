# Inline confirmation warnings — 2026-10-09

## Cause and correction

The shared theme override changed the warning element's background but missed its inherited pseudo-elements. The old `::before` painted a wider purple rectangle over the semantic background; `::after` added a purple pointer. Description text then used the wrong contrast in light mode. Baseline page captures missed this opened confirmation state.

The shared warning component in `noobrocket-admin.css` now removes those decorative layers, uses the existing warning/text tokens, aligns its icon through logical properties and normalizes panel/button spacing. Long labels wrap within the panel. JavaScript combining and Delay JavaScript Safe Mode receive the same correction. Radio warnings retain the same component with normalized narrow/RTL margins and button padding.

Confirmation buttons in the checkbox and radio templates now explicitly use `type="button"`. Their action changes the pending choice; it does not submit the settings form. Existing opt-in, prerequisites, native change events, focus return and Cancel behavior remain intact. Required confirmation stays beside its setting.

Only the shared, directly served admin stylesheet and two PHP templates changed. No JavaScript or generated legacy CSS changed; no rebuild or package was needed. Existing gettext messages and catalogs remain the copy owner.

## Verification

- [Native browser proof](../../.artifacts/settings-save-20261009/warning-review/warning-proof.json): 267 checks across 14 states. Both warnings were opened in English/French, light/dark and 320/390/1440px widths, with two additional mirrored-direction states using the actual legacy RTL stylesheet. Mirrored styling is not a full native RTL locale or screen-reader certification.
- Checked explicit confirmation, current combining prerequisites, cleared state when Delay becomes enabled, returned focus, pending/Cancel behavior, visible focused buttons, 44px targets, readable heading/body contrast and no page overflow.
- Reviewed native warning screenshots at desktop and narrow sizes in both themes. Title/body contrast exceeds 4.5:1; inherited purple layers are absent.
- Both edited PHP templates parse. Read-only browser requests are restricted to GET; settings/operation requests and SQL writes are blocked by the owned fixture. No settings were saved by this probe.
- Independent review found the inherited mobile radio-button padding; the shared component now clears it.

The exact cleanup comparison [correctly reported a concurrent-state difference](../../.artifacts/settings-save-20261009/warning-review/runtime-preservation.json). Current settings/history and preload/probe transients changed while the panel remained in use. [The sanitized comparison](../../.artifacts/settings-save-20261009/warning-review/preservation-diff.json) identifies the changed settings by name without stored values. Locales, private asset metadata and all four pre-existing sessions remain identical. Only the owned audit session and fixture were removed. No baseline was restored over current values. This is not an exact saved-settings-preservation PASS.

After the difference was diagnosed, owned private QA session/baseline/after files were removed. Sanitized evidence and the failed cleanup receipt remain. Current settings/history were retained.

Receipts under `C:/Users/Guillaume/.codex/logs/commands`: `20261009-234546-678-6934099477f64073931bd7bbec55ec63.log` (final browser checks), `20261009-234718-208-1c1121a1a0c2453bba0642c9968ef12a.log` and `20261009-234718-208-12b6ca2ee9654cbcb2b3d1c6244364b5.log` (PHP syntax), `20261009-234719-518-0846c7d12f704fd2899f5e8181b4dc1e.log` (cleanup difference). Earlier receipts remain available. Source is active through the local plugin junction; no package, push or live deployment occurred.

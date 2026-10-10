# Final integration review corrections

Date: 2026-10-09. Narrow corrections from the read-only integration review. No package, deployment, provider operation or shared WordPress state mutation.

## Behavior

| Owner | Correction |
| --- | --- |
| [Settings JavaScript](../../noobrocket/assets/js/noobrocket-settings.js) | Every categorized exclusion list recomputes its existing selection count, Select all checked state and native mixed state on the shared Cancel/reset event. Reset does not replay warning acceptance or save any setting. |
| [Asset Manager admin owner](../../noobrocket/inc/Engine/Optimization/Assets/Admin.php) | Private add/remove operations read back the exact persisted draft. A refused add retains its existing rejected entry. Rejected input persistence and private discard deletions are verified too. A native no-op is accepted when stored content already matches; return-value truthiness is not treated as proof of failure. |
| Asset Manager partial outcomes | A saved rule with refused entry cleanup, or a discarded draft with refused rejected-entry cleanup, reports the actual partial outcome through the existing private notice. It does not claim success or invent rollback of a committed write. |
| Asset Manager discard review | Discard has a specific revision covering both its saved draft and rejected entry. An older form cannot erase a newer rejected entry from another tab merely because draft rules stayed the same. Frontend inspection/publication revisions remain based on rule content. |
| Asset Manager field metadata | The registered `cnames` field also registers its positional `cdn_zone` companion with the existing “Files to serve” label and the shared translated CDN resource choices. History shows readable scope values rather than raw identifiers. |
| [Snapshots](../../noobrocket/inc/Engine/Optimization/Assets/Snapshots.php) and [history](../../noobrocket/views/history.php) | History differences match each rule occurrence once. Duplicate additions/removals are counted correctly. A separate `rules_changed` flag exposes order-only changes, which now retain an explicit reviewed restore action instead of claiming the snapshot matches. Identical state still has no changes. |

The existing Rules/WordPress lock, private metadata, notice transient, history owner and reset event remain responsible. No queue, transaction layer, new storage or generic framework was introduced. Seven new feedback messages use the plugin text domain; catalog generation remains the root integration step.

## Verification

| Gate | Evidence |
| --- | --- |
| [Private metadata/history fixture](../../tests/wordpress-private-draft-persistence.php) | **32 assertions** against actual Admin/Snapshots owners with intercepted platform callbacks. Covers refused add/remove/discard/rejected-input writes, partial cleanup, no-op content, entry-only stale discard, lock release, unchanged public state, duplicate/order history differences and readable CDN companion values. |
| [Categorized controls browser fixture](../../tests/second-audit-catalog-browser.mjs) | **110 native-browser assertions**, including actual confirmed Cancel at 1440 px light/dark, 390 px light and 320 px dark. Saved category values remain; count, visible badge, Select all checked state and mixed state match restored values. No operation is submitted. |
| [Reviewed state](../../tests/wordpress-reviewed-state.php) | **14 assertions** pass. |
| [Native lock/history mechanism](../../tests/wordpress-history-lock.php) | **30 assertions** pass. This fixture mocks platform callbacks; it is not concurrent MySQL proof. |
| Syntax | Admin, Snapshots and history pass PHP syntax checks; maintained settings JavaScript passes `node --check`. |
| [Repeated native Linux/MariaDB proof](NATIVE_WORDPRESS_PROOF.md) | **266 assertions + 10 preflight checks** pass with empty WordPress debug output and verified owned process cleanup. Final provider/settings/Snapshots/Rules/preload owners match the tested copy. Discard-specific metadata actions are proved separately by the intercepted owner fixture above. |

Quiet receipts under `C:/Users/Guillaume/.codex/logs/commands/`: `20261009-230746-432-6f9a1435eed3409d8efc3c47ea8ee8e0.log` (32 private/history assertions), `20261009-230524-826-1d8b6b88fb124b68b30990f3bf43e61c.log` (110 browser assertions), `20261009-230714-040-394fb872787049819e3f731297cd38e8.log` (reviewed-state, lock/history and syntax), `20261009-230611-759-263d9ce51a714794bd9bf3caca9c6686.log` (repeated native proof).

Tests intercept rejected storage outcomes deliberately; they do not require corrupting an installed WordPress database. Production object-cache behavior, real simultaneous private metadata requests and live provider APIs remain separate runtime boundaries. This review adds no claim of a 95/100 visual score or universal site speed improvement.

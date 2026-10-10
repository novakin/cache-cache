# History recovery and lock verification

Date: 2026-10-09. Extends the root [AR02/AR06](../audits/FULL_REVIEW_ARCHITECTURE.md) implementation with verified recovery persistence and native lock lifecycle proof. No package, deployment, shared WordPress bootstrap or external request.

| Owner | Final correction |
| --- | --- |
| [Snapshots.php](../../noobrocket/inc/Engine/Optimization/Assets/Snapshots.php) | `record()` returns ownership/persistence errors and reads back the exact bounded history write. Restore stops before changing settings/rules when its original-state recovery snapshot cannot persist. |
| Ordinary post-commit callbacks | A committed change stays committed if only history fails. Native feedback says the change was saved and recovery history could not be recorded. Rule publication uses the existing private asset-notice transient so this warning survives admin-post redirect. |
| [Rules.php](../../noobrocket/inc/Engine/Optimization/Assets/Rules.php) | Existing WordPress upgrader lock remains the owner. Already-loaded platform class is reused. No new lock storage, queue or concurrency framework. |
| WordPress hook registration | Removed nonexistent `pre_add_option_noobrocket_asset_rules` registration/method. A first rule write through the maintained `update_option()` path is guarded by the real pre-update hook and recorded by the native post-add hook. |

The root ordinary lock/depth logic correctly covers nested provider metadata writes: the outer settings write acquires ownership; a nested clear increments depth; its metadata-only post-commit callback consumes no history slot and releases only nested depth; the outer callback records its actual original state and releases ownership. A failed nested write has no post hook; the outer history still records once, and request shutdown releases remaining ownership. Provider credentials and backup metadata remain outside restorable history.

Recovery history and actual settings/rules are separate writes. A refused recovery-history write blocks restore entirely. A later failure in one actual restore write remains an incomplete restore, with the verified original state retained for recovery. This batch does not invent a database transaction or claim provider operations can be rolled back atomically.

Supported maintained settings/rule writes share the existing native lock. Direct SQL or arbitrary third-party `add_option()` operations are not serialized by the plugin's pre-update subscriber; the removed nonexistent hook offered no such protection. No new generalized option sanitizer/locking layer is introduced.

## Verification

| Gate | Result |
| --- | --- |
| [Native lock/history fixture](../../tests/wordpress-history-lock.php) | 30 assertions passed against real Rules/Snapshots and intercepted native platform callbacks/options. Covers separate lock owners, nonowner release rejection, fresh cache invalidation, no-op/refused writes, native first-write lifecycle, intervening saved state, successful/failed nested provider metadata writes, ordinary history refusal, persistent admin-post failure feedback, restore recovery refusal and partial/successful restoration. |
| [Reviewed-state fixture](../../tests/wordpress-reviewed-state.php) | 14 regressions passed. Root fixture buffering retained. |
| [Asset/history fixture](../../tests/wordpress-assets-second-pass.php) | 27 assertions passed. Fixture source unchanged by this batch. |
| PHP syntax | Two changed owners and the new fixture pass PHP 8.3 syntax checks. |

Receipts under `C:/Users/Guillaume/.codex/logs/commands/`: `20261009-224338-438-c47af24f046446d2aa77f5068e912ef8.log` (native ownership/recovery), `20261009-224338-418-09973275741c4fe982558b656c19abd2.log` (reviewed state), `20261009-224338-435-2a4c455a1fab48d389f5532d10d162b0.log` (asset/history), `20261009-224338-421-6888e51c14c54793940ef5fc142000cc.log` (syntax).

The lock fixture calls the actual plugin owner but mocks `WP_Upgrader`/options/hook delivery; it is deterministic mechanism proof, not real simultaneous MySQL requests. Current installed WordPress core was read to verify native hook names and upgrader ownership behavior. Real database contention, persistent object-cache coherence and older WordPress/PHP execution remain consolidated runtime/release validation boundaries.

# Betas, upstream intake and releases

Cache-Cache has separate product and engine versions. The downloadable local package is **0.1.0-beta.1**; process-related shipped metadata/documentation changes advance development source to **0.1.0-beta.2**. This does not publish a beta. The current source header/manifests own development metadata; stable `1.0.0` remains reserved for later acceptance. Earlier 1.x development identifiers are historical betas, not stable releases.

## Immutable packages

The existing `cache-cache-0.1.0-beta.1.zip` SHA-256 is `635b94d9d3deed6c6648fc9dc6d5534c975d65e0fada2748cffd9062b39a15da`. Preserve that file/checksum. Never rebuild changed bytes under a published version. ZIPs and private verification artifacts are excluded from Git; an authorized release may attach a ZIP and checksum. The installable root remains `noobrocket/noobrocket.php`, so updates replace the installed plugin and preserve technical identifiers.

Ordinary checks do not publish packages, screenshot Gists or sites. Packaging remains separate from design acceptance and deployment. `python tools/check.py package --zip /path/to/existing.zip` verifies an archive; `--compare-source` asserts exact current-source equality only when appropriate. The preserved beta.1 predates later source edits. [Beta release readiness](../.github/workflows/release.yml) is a manual verification workflow requiring the intended version and expected checksum; it cannot create a GitHub release, push or publish artifacts.

## Release checklist

1. Define acceptance and known limits; select a new `0.x.y-beta.N` product version. Update plugin header/constants, worker manifest/root lock metadata and gettext catalog headers through their actual owners. Do not change the engine marker for a product-only release.
2. Run applicable syntax, fast, generated, native WordPress, browser and server/worker checks from a clean checkout. Record executed PHP/WordPress/database/browser pairs. A declared PHP 7.4/WordPress 5.8 floor requires native proof before claiming that release matrix passes.
3. Review package inputs and exclusions. Keep shipped dependencies/licenses, generated settings assets, PO/MO and packaged help. Exclude `node_modules`, browser binaries, runtime/cache/config files, databases, credentials and developer tools.
4. Build intentionally with `python tools/build.py` only within packaging authorization; the builder must refuse replacing an existing versioned ZIP. Verify CRC, unique entries, metadata, one plugin root, manifests/catalogs and every archived byte against source. Write and verify SHA-256.
5. Extract to a separately marked disposable WordPress installation. Prove clean activation, update from prior development/beta settings, preserved optimization choices/site keys and independent engine migrations. Include uninstall/ownership/privacy cases when release risk warrants them.
6. Review the final release revision and integration candidate; write concise notes with actual behavior, compatibility, checks and unresolved defects. Run Beta release readiness against the version/checksum. Publish release assets only when authorized, through a separate explicit action; verify the published checksum and deploy sites separately.

The [acceptance record](workflow-acceptance.md) distinguishes process rehearsal from beta release proof. Prior browser screenshots or server logs do not prove a later package. Label unavailable checks; do not substitute syntax for runtime or lab scores for target-host behavior.

## Upstream ancestry and intake

The inherited engine baseline is WP Rocket `v3.23.5.1`, commit `8e89a5917009777d51669c9f3e76cf8f85401a6b`. A downloaded `develop` head is not that release. Preserve tagged ancestry during the `novakin/cache-cache` fork migration; maintain the curated distribution layout instead of overwriting history with an orphan commit.

For each later upstream release, compare from the last adopted engine baseline and classify relevant changes as **adopt**, **adapt** or **omit with reason**. Track issue/commit provenance, affected owner, local behavior and proof. Evaluate cache/privacy, invalidation, optimization and WordPress compatibility fixes first. Do not blindly merge hosted CSS, licensing, telemetry, account/UI or update-server paths disabled in Cache-Cache.

Change the engine marker only with the associated compatible migration logic and native upgrade proof. Product betas can update local behavior without pretending the whole upstream release was adopted. The [upstream release review](../tools/UPSTREAM_RELEASE_REVIEW.md) is dated evidence, not an automatically current status feed.

## GitHub gates

CI workflows and required-check enforcement are different controls. Read back effective branch rules after setup; record whether required statuses/human approvals can actually be enforced on the chosen hosting plan. If unavailable, document the manual current-candidate gate and solo-maintainer review honestly. Never claim protection because YAML exists, and never merge solely because a historical head was green.

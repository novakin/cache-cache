# Development-process acceptance

Date: 2026-10-10. Status: local implementation and curated-checkout rehearsal pass. The public `novakin/cache-cache` fork exists; the first import issue is [#1](https://github.com/novakin/cache-cache/issues/1). Hosted checks and effective merge gates remain pending.

## Scope

Implement the [approved development-process plan](../tools/DEVELOPMENT_WORKFLOW_PLAN.md): compact contributor instructions, current documentation ownership, explicit fixture catalog, portable checks, GitHub templates/workflows and a curated migration preserving upstream release ancestry. Product metadata advances to `0.1.0-beta.2` because packaged setup metadata changes. The local `0.1.0-beta.1` ZIP remains immutable.

No cache algorithm, optional optimizer behavior or site design is changed in this work. Open product findings remain in [the source inventory](source-inventory.md).

## Required proof

- Current documentation links/configuration and fixture catalog resolve.
- Relevant syntax, fast fixtures and generated-output checks pass without rewriting source.
- The frozen beta.1 ZIP remains unchanged; candidate packaging is verified only in a disposable destination.
- Native WordPress fixtures use a copied plugin and separate marked database/runtime.
- The curated checkout contains intended inputs, excludes private/runtime artifacts and retains release commit `8e89a5917009777d51669c9f3e76cf8f85401a6b` as its ancestor.
- Check a representative failure and unsupported/missing prerequisite; neither may return success.
- CI/template syntax and risk-selection controls pass. Hosted checks and branch rules are reported only after actual GitHub readback.
- Fresh review covers the final implementation and relevant proof. Agent review is identified as agent analysis, not independent human approval.

## Publication boundary

The user selected `novakin/cache-cache`, a fork of `wp-media/wp-rocket`, and requested commit/PR rehearsal followed by sequential publication. Finish correcting and verifying the process first. Record each published PR's head, base, tested integration candidate, checks and review scope. Merge, release and live deployment remain separate actions.

## Executed local proof

| Scope | Result and environment |
| --- | --- |
| Current docs/configuration | 11 guides and 89 catalog scenarios across 73 fixture files pass; local links also checked on case-sensitive Linux. |
| Syntax | 1,474 PHP, 61 JavaScript, 9 Python and 4 shell files parse, including CI helpers; PHP 8.3.35 on Windows. Parsing is not static analysis. |
| Fast checks | 32 isolated fixtures pass. Ten tooling negative controls prove missing prerequisites, invalid syntax, omitted fixtures, unsupported selections, unsafe ZIP/environment files and unowned runtime boundaries fail. |
| Generated output | 74 tracked settings/catalog outputs match disposable regeneration. Validation does not rewrite source. |
| Native WordPress | 30 default fixtures and all 17 acceptance phases pass against a marked copied-plugin WordPress 7.1.3/SQLite site with PHP 8.3.35. Destructive lifecycle phases restore activation without deleting an unowned drop-in. |
| Windows browser | 13 fixtures pass with Node 26.7.0 and bundled Chromium. These are synthetic controls/worker checks, not a fresh visual audit of every admin page. |
| Linux browser | 13 serial fixtures and 2,061 assertions pass with Python 3.13.5, PHP 8.4.26, Node 22.23.3, sandboxed Chromium 154.0.8037.92 and WordPress 7.1.3 core reads. No native site bootstrap. All 1,755 browser-relevant plugin/fixture/runner files match final source; concurrent differences were Composer labels and test documentation. |
| Linux server/worker | Full owned runner passes with PHP/FPM 8.4.26, WordPress 7.1.3, MariaDB, Node 22.23.3 and Chromium 154.0.8037.92. Apache/FPM and Nginx cache/privacy checks, real CSS worker generation and multisite queue/settings/lease isolation pass. Chromium runs unprivileged with sandbox enabled. WordPress debug log is empty; owned processes stop and port is freed. |
| CI controls | Pinned workflow actions and local actionlint/YAML parsing pass. Disposable Git controls validate head/base/candidate receipts, reject stale candidates and exercise aggregate failure behavior. Catalog/toolchain changes select all heavy groups; a deliberate selection defect fails the self-test. Current hosted CI does not run actionlint. |
| Immutable package | Existing beta.1 integrity passes: 1,681 entries, 36 compiled catalogs, SHA-256 `635b94d9d3deed6c6648fc9dc6d5534c975d65e0fada2748cffd9062b39a15da`. |
| Curated checkout/package | The initial rehearsal verified 1,840 intended tracked files against maintained source and Git index bytes, with the exact engine release parent preserved. Import review restored one omitted third-party MIT license, giving 1,841 files and 1,682 plugin archive entries. Nested/existing destinations fail before writes; runtime/private inputs are excluded. Docs, 32 fast fixtures and 74 generated outputs pass from the curated checkout. Disposable beta.2 builds and exact source equality verify; no beta.2 distribution is published. |

Primary commands are `python tools/check.py docs`, `syntax`, `fast`, `generated`, `wordpress`, `browser` and `package`; prerequisites and explicit executable/core arguments are in [the test recipe](../tests/README.md). Linux server proof executed `sh tools/linux-production-proof.sh WORKSPACE WORDPRESS_ZIP NODE CHROMIUM WORKER_MANIFEST` against new owned paths. The HTTP boundary blocks real requests in default native checks and verifies the exact disposable listener before explicit fixed-port HTTP proof.

Private command receipts remain outside the curated repository. Decisive receipt timestamps are `091636` (generated), `091805` (fast), `092417` (native), `092611` (lifecycle), `092018` (Windows browser), `092619` (syntax), `092645` (Linux server), `092725` (Linux browser), `093044` (curated docs/fast), and `093139` (curated generated/package). They are local evidence, not public setup prerequisites.

## Fresh review and corrections

A separate agent reviewed runner, packaging, migration preparation, CI selection/results, fixtures and current docs. This is agent analysis, not independent human approval. Review found and corrected lifecycle restore after uninstall, `.env.*` packaging admission, recursively nested migration destinations, catalog-only CI bypass, ignored fixture selectors, case-mismatched Linux links, invalid catalog descriptions and an unsupported YAML-gate claim. Focused regression controls pass; no remaining concrete blocker was found in the covered paths.

## Practical process review

A further review checked whether the process is usable before the first commit/PR, rather than pursuing a perfect score. The contributor recipe has clear ownership, focused checks, optional review roles and separate publication/merge/release boundaries. It needs no additional process framework.

Two executable gaps were corrected: the minimum-version job attempted full syntax parsing of bundled PHP 8 libraries under PHP 7.4, and settings PHP owners under `inc/Engine/Admin/Settings/` did not select browser checks. Full bundled-source syntax remains mandatory on PHP 8.3; the minimum pair retains actual native activation/settings/render/update/ownership checks and loaded dependencies. Scope self-tests now cover both `Page.php` and `Settings.php`. Local actionlint, docs, selection controls and the previous-selector negative control pass after these changes. This does not establish that the minimum pair has executed successfully.

The process is ready for the first coherent local batch and draft PR. Before merge, require successful applicable hosted checks on the current integration candidate and verify effective repository gates; use an explicit manual gate if enforcement is unavailable. Releases still need their separate version, package and compatibility acceptance.

This is pre-commit local evidence, not acceptance for a committed PR head. Each PR starts its own head/base/candidate/reviewed-revision record. Recurring actionlint, useful configured static analysis and dependency reconstruction can improve the base later; none requires another layer or blocks local contributor work now.

## GitHub import setup

The user authorized the fork and sequential issue/commit/PR publication. `novakin/cache-cache` is a public fork of `wp-media/wp-rocket`; its default `cache-cache` branch starts at the recorded engine release. Issues are enabled. Merge commits preserve individual commit ancestry; automatic merge, squash and rebase merge are disabled. Effective check enforcement is verified after the first hosted run, not inferred from these settings.

Migration-focused fresh agent review checked source/index byte preservation, private/runtime exclusions, generated assets, dependencies and license notices. It found an omitted `cron-expression` MIT license; the exact release-baseline notice is restored. Other shipped dependency notice lines remain preserved. This review does not substitute for a new full product audit or human approval. The import PR owns revision-bound acceptance; known product gaps remain open.

## Remaining proof and action boundaries

- PHP 7.4/WordPress 5.8 execution and the hosted PHP 8.3 matrix remain unverified. Newer local PHP passes do not prove either hosted pair.
- Hosted GitHub CI, final PR integration revisions and effective branch rules are recorded in the import PR after execution and readback. Workflow files alone do not establish enforcement.
- Bundled Composer dependencies are preserved; a complete reconstruction pipeline, PHPCS/PHPStan and new product/UI improvements remain outside this process checkpoint.
- The fork and import issue are created. No merge, release or live deployment occurred. The existing preview, reference distributions and beta.1 ZIP remain unchanged.

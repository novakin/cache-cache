# Development-process acceptance

Date: 2026-10-10. Status: local rehearsal, hosted acceptance and GitHub import complete. The standalone import is merged into the protected `cache-cache` branch of [novakin/cache-cache](https://github.com/novakin/cache-cache). Current integration candidates passed applicable hosted checks; effective merge gates were read back. Release and live deployment remain separate.

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

The user selected `novakin/cache-cache`, a fork of `wp-media/wp-rocket`, and authorized sequential issue/commit/PR publication and merge after current review and applicable checks. Apply that sequence to every later coherent batch, including documentation. Record each PR's head, base, tested integration candidate, checks and review scope. Release and live deployment remain separate actions.

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

Two executable gaps were corrected: the minimum-version job attempted full syntax parsing of bundled PHP 8 libraries under PHP 7.4, and settings PHP owners under `inc/Engine/Admin/Settings/` did not select browser checks. Full bundled-source syntax remains mandatory on PHP 8.3; the minimum pair retains actual native activation/settings/render/update/ownership checks and loaded dependencies. Scope self-tests now cover both `Page.php` and `Settings.php`. Local actionlint, docs, selection controls and the previous-selector negative control passed at this checkpoint; the hosted record below now establishes selected native execution on the minimum pair.

The local checkpoint made the process ready for its first coherent batch and draft PR. Before every merge, require successful applicable hosted checks on the current integration candidate and verified effective repository gates; use an explicit manual gate if enforcement is unavailable. Releases still need separate version, package and compatibility acceptance.

The local table is historical rehearsal evidence, not acceptance for a committed PR head. Each PR starts its own head/base/candidate/reviewed-revision record. Recurring actionlint, useful configured static analysis and dependency reconstruction can improve the base later; none requires another layer or blocks contributor work now.

## GitHub import setup

`novakin/cache-cache` is a public fork of `wp-media/wp-rocket`. Its default `cache-cache` branch was anchored to the recorded engine release before the import and retains that ancestry. Issues are enabled. Merge commits preserve individual commit ancestry; automatic merge, squash and rebase merge are disabled. Effective check enforcement is recorded below from actual readback.

Migration-focused fresh agent review checked source/index byte preservation, private/runtime exclusions, generated assets, dependencies and license notices. It found an omitted `cron-expression` MIT license; the exact release-baseline notice is restored. Other shipped dependency notice lines remain preserved. This review does not substitute for a new full product audit or human approval. The import PR owns revision-bound acceptance; known product gaps remain open.

## Hosted acceptance and publication

The first import run [38035586839](https://github.com/novakin/cache-cache/actions/runs/38035586839) failed correctly: Ubuntu 22.04 could not install `php8.3-fpm`, and Linux catalog regeneration changed CRLF text to LF. No failed head was merged. [Issue #3](https://github.com/novakin/cache-cache/issues/3) and [PR #4](https://github.com/novakin/cache-cache/pull/4) repaired the runner and canonical generated bytes without changing plugin behavior or weakening comparison.

| Batch | Head / base / tested candidate | Hosted result and publication |
| --- | --- | --- |
| CI portability repair, PR #4 | Head `2e50196013501c847caee3eb1decae42a5773b51`; base `5615fc3658edad1da9b4b9e4f9ea93df8599b170`; candidate `1ed740d6ad5e5190fcb511000255635155fed69e`. | [Run 38036372557](https://github.com/novakin/cache-cache/actions/runs/38036372557): all seven jobs pass. Merged into the import branch as `ade41965aa09b7a4418c48fbf9d3b33b0ffa665f`; the stacked branch used an explicit current-review/current-CI manual gate. |
| Standalone import, [PR #2](https://github.com/novakin/cache-cache/pull/2) | Head `ade41965aa09b7a4418c48fbf9d3b33b0ffa665f`; base `8e89a5917009777d51669c9f3e76cf8f85401a6b`; candidate `c47032e117beca71d72b12e4114635092782beb4`. | [Run 38036608304](https://github.com/novakin/cache-cache/actions/runs/38036608304): all seven jobs pass. Merged into protected `cache-cache` as `81385baa64caa99842cef5e569c4e77ec729169e`; import issue #1 closed. |

Both runs verified exact candidate parents and selected every heavy group. PHP 7.4.33/WordPress 5.8 passed 17 selected native acceptance phases; PHP 8.3.35/WordPress 7.1.3 passed 30 default fixtures plus five lifecycle phases. Fast checks passed syntax, 32 fixtures and all 74 generated outputs. Thirteen browser fixtures and the full Ubuntu 24.04 MariaDB/Apache/Nginx/PHP-FPM/local CSS worker/multisite proof passed, using Node 22.23.3 and Chrome 154. The minimum pair does not claim to parse every bundled PHP 8 library; browser fixtures do not establish every production layout.

Fresh agent reviews were posted for the [repair head](https://github.com/novakin/cache-cache/pull/4#pullrequestreview-5478252191) and [synchronized import head](https://github.com/novakin/cache-cache/pull/2#pullrequestreview-5478261468). They found no remaining blocker in import/portability scope, confirmed all 1,842 committed files against maintained inputs, preserved ancestry and exclusions, and checked all 37 catalog text changes are newline-only with 36 unchanged MOs. Windows and Linux regenerated outputs matched; a real `core.autocrlf=true` clone preserved every tracked byte. Reviews are agent analysis under solo-maintainer ownership, not independent human approval or a new full product audit.

Before the protected import merge, API readback verified required GitHub Actions `Required checks` (app ID 15368), strict current-base integration, enforcement for admins, resolved conversations and disabled force-push/deletion. Required human approvals are zero, matching the disclosed solo-maintainer process. Source workflows alone are not this evidence. The frozen beta.1 ZIP retains its recorded SHA-256; the preview and upstream checkout remain untouched.

## Remaining limits and action boundaries

- Hosted proof covers the selected compatibility pairs and fixtures above. New changes require their own applicable current-candidate checks; these results do not establish all environments or full product/UI acceptance.
- Bundled Composer dependencies are preserved; a complete reconstruction pipeline, PHPCS/PHPStan and new product/UI improvements remain outside this process checkpoint.
- The import and repair are merged. No release or live deployment occurred. The existing preview, reference distributions and beta.1 ZIP remain unchanged.

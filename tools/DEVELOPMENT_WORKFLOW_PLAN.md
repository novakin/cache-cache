# Cache-Cache agents, documentation and development process

Date: 2026-10-10 (Europe/Paris). Status: local implementation and process rehearsal pass; GitHub migration is in progress. [Workflow acceptance](../docs/workflow-acceptance.md) owns actual proof and remaining limits. Hosted proof and effective merge enforcement remain pending.

## Outcome

Prepare a repository that a senior WordPress developer or coding agent can understand, change and verify from a clean checkout. Keep guidance short, checks executable and reviews tied to the delivered revision. Use existing owners and native WordPress/platform APIs. No orchestration framework, mandatory dependency graph, code golf or layers added for process alone.

This plan combines [the agent-guidance audit](audits/UPSTREAM_AI_GUIDANCE_REVIEW_20261010.md), [the workflow comparison](audits/AI_WORKFLOW_COMPARISON_20261010.md) and [the six-PR delivery review](audits/RECENT_PR_PROCESS_AUDIT_20261010.md). Adopt WireGuard's state preservation and current acceptance record, plus WP Rocket's applicable PHP/WordPress checks. Successful upstream CI does not make its defective agent instructions suitable for copying.

The existing beta.1 package is preserved. Contributor guidance, current docs, portable checks, templates and workflows are implemented locally. Development source is beta.2; no new beta is published. GitHub publication is a later step.

## Preserve these invariants

- Product: **Cache-Cache**. Current beta: `0.1.0-beta.1`; engine: `3.23.5.1`. Engine migrations remain independent of product releases. Stable `1.0.0` is reserved for later acceptance.
- Compatibility identifiers stay: `noobrocket/`, `noobrocket.php`, `NoobRocket`, the `noobrocket` text domain, options, hooks and routes. GitHub preparation does not rename stored data or install a second plugin.
- Preserve the maintained source, original reference distribution, downloaded upstream checkout, existing user edits and preview data. Lifecycle tests use separate marked disposable installations; localhost alone does not make a site disposable.
- Preserve the beta ZIP and checksum. Its SHA-256 is `635b94d9d3deed6c6648fc9dc6d5534c975d65e0fada2748cffd9062b39a15da`. Do not replace its contents under the same version when later work changes shipped source.
- Keep current behavior and unresolved product findings distinct. Process work does not silently implement speculative optimizations or claim comparative speed.

## 1. Establish the baseline and ownership

**Deliverables:** a curated source inventory, current documentation index and verification catalog.

Record which files are maintained inputs, generated outputs, bundled dependencies, historical evidence and private runtime data. Identify the actual owners of cache eligibility/publication, invalidation, preload, expiry, settings transactions, local CSS, image generation and admin presentation. Describe the active hook/subscriber/container patterns; do not mandate a new provider, context or interface for every change.

Inventory every maintained fixture by prerequisites, mutation scope, supported environment and purpose. Treat fixture helpers as helpers, not automatically executable tests. Record tests that exercise native WordPress separately from modeled standalone proofs and browser checks. Map known audit findings to implemented, verified, open or deferred status using the latest source review.

**Acceptance:** another contributor can identify the responsible source and meaningful checks for a cache, settings or UI change without reading historical reports. Every referenced path exists. No private runtime is required merely to understand the repository.

## 2. Make setup and verification executable

**Deliverables:** repaired developer commands and one thin, portable check entry point over existing fixtures.

- Replace or remove inherited Composer aliases that reference missing PHPUnit suites/configuration, PHPCS/PHPStan configuration or removed Mozart tooling. Do not advertise upstream suites that this repository does not contain.
- Define how bundled `vendor/` and prefixed `inc/Dependencies/` are maintained before recommending Composer installation. Avoid an install/update hook that overwrites shipped runtime dependencies. Dependency reconstruction is a separate explicit task if the current inputs cannot reproduce it.
- Reuse the existing settings bundle generator, translation updater and ZIP builder. Check generated output in a disposable copy; never let a validation command silently rewrite a contributor's source.
- Add explicit fast, native WordPress, browser and server/worker check groups as needed. Keep commands transparent, fail on missing prerequisites and distinguish skipped checks from passes. A small runner coordinates existing fixtures; it does not become a test framework.
- Use supported PHP/WordPress combinations. Verify the declared PHP 7.4 / WordPress 5.8 floor and a recent compatible pair before claiming that matrix passes. Do not assume every cross-product of PHP and WordPress versions is supported.
- Keep mutating WordPress fixtures serial within their isolated site. Parallel jobs require separate databases, paths and ports. Block provider requests in fixtures except explicitly owned local endpoints.
- Adapt the existing Linux runner's PHP-FPM version and OS paths for reproducible CI setup. Retain unprivileged Chromium, isolated MariaDB and owned service cleanup.

Introduce PHPCS/PHPStan only with working local configuration and useful results on maintained code. Separate inherited findings from regressions; do not obtain a green check by hiding new findings or relabeling syntax checks as static analysis.

**Acceptance:** documented setup works in a fresh checkout without `.artifacts/` from this workstation. Relevant checks execute actual assertions, report their environment and fail when a deliberate representative regression is introduced. No missing command is counted as a pass.

## 3. Write compact agent instructions

**Deliverables:** root `AGENTS.md` and a short role section in the development guide.

Root guidance owns the shared operating contract. Aim for roughly 600–900 words; link details rather than duplicating them. Cover:

- Product identity, preserved compatibility identifiers and beta/engine versions.
- Existing source ownership, trace-before-edit and the smallest coherent solution.
- WordPress capability/nonce checks, input validation, output escaping, public/private cache boundaries, safe filesystem ownership and settings preservation where the task crosses those boundaries.
- Maintained versus generated assets; English gettext message keys and PO/MO translations.
- Focused checks, disposable runtime requirements, real UI output and performance evidence.
- Dirty worktree/index preservation and explicit commit, push, merge, release and deployment scope. No automatic amend, rebase, reset or force push.
- Short findings and honest completion reports; unresolved defects and unverified environments remain visible.

Use optional role briefs rather than a fixed agent pipeline:

| Role | Responsibility | Output |
| --- | --- | --- |
| Investigator | Trace current behavior, callers and invariants; diagnose the failing layer. | Relevant paths, reproduction, evidence and smallest responsible fix. |
| Implementation owner | Make the coherent change, maintain docs/artifacts and run relevant proof. | Intentional diff, actual checks and remaining limits. |
| Fresh reviewer / verifier | Review the final diff and affected behavior; use cache or UI expertise when needed. | Reviewed revision, concrete findings, proof scope and verdict. |

Separate agent review from human approval. A fresh agent context provides another analysis; an author-account comment does not prove distinct-person approval. Use real human review when available, and disclose the solo-maintainer case instead of fabricating independence.

Tool-specific wrappers are optional. If a contributor needs `CLAUDE.md` or `.claude/agents/`, keep them thin and point to the same contract. Do not copy all 44 upstream files or maintain competing policy in `.claude` and `AGENTS.md`.

**Acceptance:** apply the instructions to one existing bug and one UI change as a dry run. Both lead to the correct owner, meaningful proof and accurate action boundaries without invented layers, stale paths or missing tools.

## 4. Give documentation one owner per topic

**Deliverables:** current guides and an index separating them from historical evidence.

| File | Owns |
| --- | --- |
| `README.md` | Product, prerequisites, installation, supported features, beta status and guide links. |
| `CONTRIBUTING.md` | Short issue → branch → implementation → proof → PR → review recipe. |
| `docs/development.md` | Portable setup, command entry points, source/generated ownership and optional agent roles. |
| `docs/architecture.md` | Actual cache/request, settings, queue/worker and filesystem ownership; important contracts and extension points already in use. |
| `docs/operations.md` | Diagnosis, server-delivery configuration, safe rollout and rollback. Link worker instructions instead of duplicating them. |
| `docs/releases.md` | Beta versioning, release checks, package integrity and upstream change assessment. |
| `tests/README.md` | Current verification catalog, prerequisites, fixture safety and evidence limits. |
| `tools/DESIGN_GUIDELINES.md` | Sole UI/design contract, supported themes/layouts and interaction acceptance. |
| `noobrocket/local-css/README.md` | Same-server worker installation, scheduling, requirements and recovery. |
| `noobrocket/docs/index.html` | Packaged user help; developer/process guidance stays outside the plugin ZIP. |
| `tools/audits/` and `tools/batches/` | Dated findings and execution records, with supersession made explicit. |

Shorten current entry points. Remove claims that superseded 2026-10-09 findings are the current implementation. Separate the long historical verification narrative from the current test recipe, preserving receipts and updating links if anything moves. Correct display-name guidance still directing users to Settings → NoobRocket.

Describe recovery precisely: replacing plugin code, restoring settings and recovering a database are different operations. Settings history does not undo database cleanup or schema changes. Document which cache paths a rollback invalidates and how to return to original CSS/image delivery when an optional optimizer fails.

**Acceptance:** validate local links and commands. A new contributor can install, locate the right owner, run focused checks and understand rollback using current guides alone. No secret, private capture or workstation-only path is needed in public instructions.

## 5. Define the daily PR process

**Deliverables:** bounded bug/feature templates, a short PR template and one current acceptance record per PR.

1. State expected behavior, reproduction and acceptance. Record excluded scope where it prevents ambiguity.
2. Inspect the base and current worktree. Preserve unrelated changes. Trace the invariant to its owner before editing.
3. Implement and update owning documentation/generated outputs. Add meaningful regression proof for a real defect or risk; avoid tests that only repeat trivial implementation.
4. Run the relevant local check groups. Review the intentional diff for unrelated churn, compatibility regressions and performance work shifted into synchronous requests.
5. Obtain fresh review for materially changed cache, security/data, lifecycle, settings-concurrency or UI behavior. Close findings with evidence; do not automatically resolve every review thread.
6. Publish only within the user's authorized GitHub scope. Update one concise acceptance record rather than posting repeated agent narratives.
7. Verify the final head and its integration with the current base. Material code or base changes invalidate affected proof and review; explain narrowly what remains applicable.
8. Merge only within authorization and actual applicable repository gates. Keep partial issues open or reconcile their acceptance when scope changes.

The acceptance record identifies the PR head SHA, base SHA, tested merge candidate, reviewed revision, actual commands/results and relevant limits. Test the merge candidate to catch base interaction; do not rerun the complete matrix on both head and candidate without a reason. Historical review links remain historical. A skipped, assigned or blocked check is not a completed result.

Performance claims require a controlled before/after measurement for the changed path. Compare cache hits with hits and cold work with cold work; record settings/environment, sample count and variability. Lab scores, screenshots and syntax passes do not establish site-speed improvement or superiority over WP Rocket.

**Acceptance:** the final description explains the resulting behavior and contains one verifiable current record. Another reviewer can determine what passed, on which revision, and what remains unproved.

## 6. Stage GitHub CI around actual risk

**Deliverables:** small workflows whose required jobs are locally reproducible.

| When | Checks |
| --- | --- |
| Every PR | Resolvable docs/config, applicable syntax and fast isolated regressions. Generated bundle/catalog consistency when their inputs change. |
| Plugin behavior or lifecycle changes | Disposable native WordPress activation, product/engine update separation, saved settings, cache ownership and relevant privacy/invalidation/transaction fixtures. |
| UI or browser optimization changes | Relevant Chromium fixtures and real output checks: light/dark, English/French, desktop/narrow, keyboard order/focus, validation, save/cancel and recovery states affected by the change. |
| Cache/server/worker changes and beta releases | Relevant Linux/MariaDB, Apache/Nginx/PHP-FPM, worker and multisite cases. Add third-party scenarios for integrations actually changed. |
| Beta release | Extracted ZIP/source integrity, manifests/catalogs, runtime exclusions, clean install and update from previous development/beta settings, plus the declared release compatibility checks. |

Required jobs must always return an explicit applicable/not-applicable result; a path-filtered job must not leave the branch waiting forever or silently bypass relevant work. CI uses read-only tokens, no persisted checkout credentials and no production/provider secrets for pull-request code. Cancel obsolete PR runs; retain bounded failed evidence and publish only sanitized summaries. Use maintained native tooling and pinned setup dependencies where appropriate; avoid copying mutable external upstream workflow references wholesale.

Run package publication through an explicitly invoked release workflow. Ordinary checks do not upload distributable betas, deploy sites or publish screenshot Gists. Do not add numerous agents, reports or services to compensate for a missing executable check.

**Acceptance:** representative docs, PHP, UI and server/worker PR changes select the intended checks. Failures block the applicable aggregate result; irrelevant heavy checks are clearly skipped. A current integration candidate passes before merge. Required check enforcement is verified on GitHub after setup, not inferred from YAML.

## 7. Prepare GitHub without losing history or private data

**Deliverables:** curated migration branch/PR and verified repository settings, after publication is requested.

- Prepare a separate working checkout. Preserve upstream ancestry and anchor the engine comparison to `v3.23.5.1` at `8e89a5917009777d51669c9f3e76cf8f85401a6b`, rather than treating the downloaded `develop` head as our release baseline.
- Import the existing maintained layout coherently; preserve the `noobrocket/` distribution root and root tests/tools. Keep the original workspace and upstream checkout intact. No orphan-history replacement or automatic history rewrite.
- Commit intentional source, runtime dependencies needed by the current build, generated assets/catalogs, tests, tools and current documentation. Keep original dependency licenses.
- Exclude `.artifacts/`, private captures, databases, cache/config output, credentials, `node_modules`, command logs, duplicate reference distributions and historical ZIPs from the curated new commits. ZIP/checksum downloads belong in releases when publication is authorized. Preserve local copies.
- Review proposed Git contents and dependency/build inputs before committing. Do not run `git add .` across the current uncurated workspace.
- Add templates and verified CI in bounded migration work. Explain layout, naming, engine baseline, disabled cloud features and retained limitations in the migration PR.
- Confirm destination, visibility, primary branch and applicable maintainer/reviewer permissions when publishing. Verify required checks and branch rules with readback. If hosting/account limits prevent enforcement, state the actual manual gate; do not claim protection exists.
- Track future upstream releases by adopting, adapting or intentionally omitting relevant fixes. Preserve separate engine/product versions and test affected behavior; do not blindly merge all upstream cloud/UI changes.

**Acceptance:** a clean checkout builds and verifies the declared package inputs. Git contains the curated content and expected ancestry. CI and accessible merge rules match the stated process. Publishing, merging and deployment remain distinct decisions.

## Execution order and stop condition

| Batch | Work | Exit |
| --- | --- | --- |
| A | Inventory, current/historical documentation map and executable setup/check repair. | Portable commands verified; missing tooling is repaired or explicitly unsupported. |
| B | Root `AGENTS.md`, short role briefs, owning guides and local templates. | Dry-run tasks follow the correct owner, proof and action boundaries; links resolve. |
| C | Local CI rehearsal and clean-checkout/package rehearsal. | Applicable checks pass on identified source; failure controls and exclusions verified. |
| D | Authorized GitHub migration and repository configuration. | Curated PR, successful current-candidate CI and verified applicable merge rules. |
| E | Next authorized beta publication. | New version, verified ZIP/checksum and honest release notes; existing beta remains immutable. |

Stop at a usable, verified process. Do not assign a 100/100 quality score to unfinished guidance or add tooling to pursue a score. Quality is demonstrated by real contributor tasks, reproducible checks, review freshness and accurate limits.

## Implementation checkpoint

Batches A–C are implemented and rehearsed locally. Current docs, fixture selection, generated outputs, native lifecycle, sandboxed browser/server execution and curated package inputs pass. A fresh agent review found concrete process defects; fixes and limits are recorded in [workflow acceptance](../docs/workflow-acceptance.md).

Batch D is in progress: the selected public fork and [import issue #1](https://github.com/novakin/cache-cache/issues/1) exist. Publish each coherent commit/PR, execute hosted checks and read back applicable branch rules. The declared PHP 7.4/WordPress 5.8 pair still needs execution. Batch E remains separate; beta.1 is immutable and no beta.2 release is published. Local staging is not a completed GitHub migration.

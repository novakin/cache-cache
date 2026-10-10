# Contributing to Cache-Cache

Read [AGENTS.md](AGENTS.md), [development setup](docs/development.md) and the [architecture map](docs/architecture.md). The same contract applies to human and agent changes. Cache-Cache remains beta; technical `noobrocket` identifiers preserve installed data.

## Daily change process

Apply this sequence to every coherent batch, including documentation-only changes: issue, task branch, intentional commit, PR, fresh review of the final diff, applicable checks on the current integration candidate, then authorized merge. Start the next dependent batch from the updated development branch. Keep skipped heavy checks explicit and identify agent review separately from human approval.

1. Open a bounded issue: concrete trigger, current/expected behavior, reproduction and acceptance. Identify relevant data/cache/UI boundaries and excluded scope when useful.
2. Inspect the base, worktree and index. Preserve unrelated changes. Create a task branch from the repository's current development branch; do not assume upstream `develop` is our release baseline.
3. Trace the owner and invariant before editing. Implement the smallest coherent solution; update owning documentation and required generated output. Add meaningful regression proof where risk warrants it.
4. Run relevant [check profiles](tests/README.md). Inspect the intentional diff, failure handling, compatibility and synchronous work. Keep detailed logs private and publish bounded sanitized evidence.
5. Obtain fresh review for materially changed cache, security/data, lifecycle, settings concurrency or UI behavior. Optional agent review adds analysis; it does not establish distinct-person approval. Describe the solo-maintainer case accurately. Address concrete findings with evidence, without blanket thread resolution.
6. Open a PR within authorized publication scope. Lead its description with resulting behavior; maintain one concise acceptance record rather than repeated agent narratives.
7. Verify the final head integrated with the current base. Record the tested merge candidate and reviewed revision. Code/base updates invalidate affected proof; rerun only the affected checks unless broader risk requires the matrix.
8. Merge only within authorization and actual repository gates. Keep partially satisfied issues open, or revise their acceptance explicitly. Release and site deployment remain separate decisions.

## Current acceptance record

The [PR template](.github/pull_request_template.md) provides the shared record. Include head/base SHAs, tested integration candidate, reviewed revision, commands/results, environment and material limits. Do not represent skipped, unavailable or assigned QA as executed. One source revision can have several proof scopes; distinguish modeled PHP fixtures, native WordPress, browser checks and server execution.

For a local merge candidate, use a separate checkout/worktree and test that candidate without modifying the contributor's branch or dirty worktree. Do not repeat the full matrix on both head and candidate without a reason. Review freshness follows the affected behavior, not a historical green badge.

## Scope and evidence

Keep issues and PRs small enough to review coherently. Shared invariants may require a wider change; arbitrary file-count limits must not force duplication. Preserve credentials, reference copies and private receipts outside Git. Do not include runtime databases, cache/config output, `node_modules` or historical ZIPs in source commits.

Performance claims need controlled measurements of the changed path. Match settings, environment and cold/hit state; report samples and variability. See [benchmark protocol](tools/BENCHMARKS.md). Source review, syntax passes and screenshots do not prove site-speed gains.

Use the [release guide](docs/releases.md) for beta packaging/upstream intake and the [operations guide](docs/operations.md) for rollout/recovery. Available checks are executable evidence; branch protection is a separate GitHub setting whose effective rules must be read back after configuration.

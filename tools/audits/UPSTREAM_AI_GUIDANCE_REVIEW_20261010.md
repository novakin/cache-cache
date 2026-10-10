# Upstream AI guidance review

Last updated: 2026-10-10 (Europe/Paris).

## Decision

Reuse the engineering principles selectively. Do not adopt the downloaded automation unchanged. The bundle contains useful WordPress guidance, but broken references, conflicting gates, upstream-specific operations and publication steps need correction. Its size and number of agents do not establish workflow quality.

This is an assessment, not adoption or execution of the inspected instructions. No upstream file, product setting, GitHub resource or local WordPress state was changed. Packaging remains paused.

## Scope and evidence

Reviewed snapshot: `.artifacts/upstream/wp-rocket`, commit `3a96b66db41791fb1f4bb1e62dfe658ec4807454`. The clone was clean before review. It is the downloaded development snapshot, not Cache-Cache's release baseline, `v3.23.5.1` at `8e89a5917009777d51669c9f3e76cf8f85401a6b`. No fetch or claim about today's remote head was made.

The tracked guidance inventory contains 45 files: `AGENTS.md` and 44 files under `.claude/`.

| Material | Count | Review purpose |
| --- | ---: | --- |
| Root instructions | 1 | Scope, architecture, verification and publication boundaries. |
| Agent definitions | 9 | Implementation, review, QA and handoff responsibilities. |
| Skill definitions | 15 | Engineering rules and workflow gates. |
| Command aliases | 9 | Target resolution and platform assumptions. |
| Supporting scripts, template and log format | 6 | Actual operations and publication behavior. |
| PHPCS specifications | 3 | WordPress input, output and nonce guidance. |
| Tool settings | 1 | Enabled automation. |
| Dependency graph | 1 | Schema, freshness, producer and inference limits. |

Paths below are relative to that upstream snapshot unless explicitly identified as Cache-Cache paths. Referenced code, configuration and CLI help were inspected to check selected claims. Workflow scripts, runtime boot, tests and external publication were not executed. Findings describe source-confirmed inconsistencies and adoption risks; they do not claim a reproduced production failure.

## Useful principles to retain

- Trace existing ownership before editing. Keep hook registration, business logic and presentation responsibilities clear.
- Preserve compatibility, settings and unrelated changes. Prefer a small coherent change over incidental cleanup.
- Discover actual repository checks and use their configurations. Verify behavior rather than inferring success from source or absence of errors.
- Validate and sanitize input, escape output, and check capabilities and nonces where required by the operation. Keep these checks at the responsible WordPress boundary.
- Review acceptance criteria individually. Report failures, skipped checks and environment limits explicitly.
- Keep PR descriptions concrete: problem, resulting behavior, scope, verification and remaining limits.
- Maintain documentation with the implementation, and retain distinct responsibilities for implementation and independent review.

## Findings

### 1. Missing instruction paths

`AGENTS.md:358`, `:367`, `:378` and `:402` reference agents, skills, graph and specs under `.aiassistant/`. That directory is absent; the tracked material is under `.claude/`. These instructions cannot be followed literally in this checkout.

The nine `.claude/commands/*.md` entries are Git symlinks, confirmed by tracked mode `120000`. In this Windows checkout they are ordinary files containing a relative target path. Their targets exist, but the aliases are not usable as ordinary prompt content without resolving them. This is a checkout/tooling portability issue, not nine missing skills.

**Action:** choose canonical paths for our repository, verify every reference, and avoid duplicated rule copies.

### 2. Mandatory graph refresh is broken

The guidance reads the graph under `.claude/graph/`, but `bin/build-knowledge-graph.js:29` writes to `.aiassistant/graph/dependency-graph.json`. Its writer at `:217` does not create the missing directory. Refreshing as instructed would fail in this checkout and would not update the file the skills read.

The graph records base `d3f6e5454d9dcf73aaca904ac63ab4588d2fe48a` and generation time `2026-05-19T11:49:18.278Z`, rather than the reviewed head. It contains 880 nodes and 722 indexed symbols; the current `GetOptions` class is absent from the index.

The graph is an index of symbols/imports, not runtime proof of service registration or feature availability. The knowledge-graph skill at `:68` assumes provider imports locate registered classes, but `inc/Engine/Cache/ServiceProvider.php:77` registers same-namespace `Purge::class` without an import. Its dependency inference at `:87` also cannot distinguish imports from constructor dependencies. It must not override current source or become a compulsory first step for every search.

**Action:** keep ordinary source search as the default. Retain a graph only if a concrete task needs it, with one producer/consumer path and an explicit freshness check.

### 3. Upstream identity and runtime are hardcoded

`AGENTS.md:391` makes `wp-media/wp-rocket` the default repository. The E2E guidance targets port 8888, `page=wprocket`, and WP Rocket activation. Cache-Cache uses a different product and preview: `127.0.0.1:8765`, `page=noobrocket` and the maintained `noobrocket/` plugin.

`bin/dev-up.sh:11` starts the environment, `:14` activates WP Rocket, and `:16` seeds by default. `bin/dev-seed.sh:24` updates plugin settings and `:43` purges cache. These are state changes, despite the boot instructions presenting the command as a routine idempotent prerequisite. `.claude/agents/e2e-qa-tester.md:135` also requires a license-setting key; this does not fit the free standalone plugin.

**Action:** use an explicitly disposable QA site. Keep the user's preview separate. Replace repository, routes, plugin identity and setup commands; remove the license prerequisite.

### 4. QA includes public screenshot publication

`.claude/agents/e2e-qa-tester.md:21` requires public Gist uploads, with the actual `gh gist create --public` command at `:24`. The requirement is repeated at `:277`. The standalone QA skill suppresses PR commenting, but that does not suppress the delegated screenshot uploads.

This introduces publication into an apparent local validation task. Screenshots can also contain account, provider or site details. Standalone challenge has a similar order problem: `.claude/skills/challenge/SKILL.md:39` suppresses JSON output only; `.claude/agents/challenger.md:60` posts its report before the skill asks whether to publish at `.claude/skills/challenge/SKILL.md:45`.

The Slack notifier has a hardcoded channel and upstream URLs (`.claude/skills/issue-workflow/scripts/slack-notify.js:10`, `:65`, `:94`). Its match logic does not identify the repository at `:27`, and readiness notification can be inferred from the command number rather than verified success at `:89`. The current `.claude/settings.json:3` has empty hooks: the notifier is dormant, not demonstrated active.

**Action:** retain evidence locally or in the intended access-controlled CI artifact store. Make any public publication a distinct authorized action. Do not carry upstream notification destinations into our project.

### 5. PR test-report gate has an ordering conflict

`.claude/agents/release-agent.md:125` says to leave "What was tested" blank. `.claude/skills/dod/SKILL.md:82` requires concrete scenarios in that section and returns FAIL for an empty section at `:89`. The orchestrator fills it at `:721`, after the preceding gate at `:664` requires DOD to return PASS or WARN.

The earlier sequence also disagrees about the local draft: DOD Layer 1 reads it at `.claude/skills/dod/SKILL.md:79`, but draft initialization is assigned to release-agent at `:85`, after the implementation agents' Layer 1 checks. The backend agent separately treats a PR-description failure as not applicable at `.claude/agents/backend-agent.md:93`. These instructions need one consistent ordering and applicability rule.

**Action:** finish actual verification and the PR description before the gate that checks them. Updating the description later must preserve the tested revision and limits.

### 6. Review repair resolves unrelated threads

`.claude/skills/orchestrator/SKILL.md:594` selects every unresolved thread and resolves each at `:602`. The same block is duplicated at `:622` and `:630`. It does not match a thread to the specific finding, fix or reviewer decision.

Clearing a review thread does not prove that its concern was addressed.

**Action:** respond to specific findings with the changed behavior and evidence. Preserve unrelated review threads; require review of material edits at the new head.

### 7. File-scope gate contradicts itself

`.claude/skills/dod/SKILL.md:231` says scope checking is skipped in Layer 2, but `:254` says a Layer 2 scope failure blocks. Layer 1 downgrades a scope failure to a warning at `:253`. The exceptions also allow formatter changes outside scope at `:246`.

**Action:** use one scope rule for the final diff. Inspect generated changes and formatting changes as part of that diff. Do not exempt unrelated edits merely because a tool produced them.

### 8. Browser QA's issue-to-PR query is invalid

`.claude/agents/e2e-qa-tester.md:81` uses `gh issue view --json pullRequests`. Installed GitHub CLI 2.102.0 lists no such issue field in its help. It exposes `closedByPullRequestsReferences`, which is not a general replacement for locating an open PR.

**Action:** pass the known PR number into QA and verify its repository/head directly. Avoid another discovery mechanism when the caller already knows the PR.

### 9. Architecture guidance mandates unnecessary structure

`AGENTS.md:49` prefers adding new ServiceProviders. `:168` requires separate Context classes for feature eligibility; `:169` forbids manual construction categorically. These can be useful patterns, but they are too broad as rules for every change.

Actual upstream lifecycle code uses direct hook registration in `inc/Engine/Cache/WPCache.php:32`, despite the categorical Subscriber rule. Four custom PHPStan rules do exist, but their implementation scope differs from some prose: `tests/phpstan/Rules/DiscourageWPOptionUsage.php:24` checks option reads, updates and deletes; `tests/phpstan/Rules/NoHooksInORM.php:44` checks BerlinDB Query subclasses, not every class in every database directory.

**Action:** extend the current responsible owner first. Add a provider, context or injected collaborator only when the behavior or existing architecture needs that boundary. Avoid duplicating eligibility rules or creating layers for a fixed operation. Document what analysis actually enforces rather than a broader aspiration.

### 10. Documentation scope is too narrow

`.claude/skills/docs/SKILL.md:7` makes documentation a no-op without a public API change. `:34` explicitly skips test/build artifact changes. Setup, verification, workflow, performance behavior and UI changes can need documentation even when no public API changed.

**Action:** assess documentation impact for every meaningful change. Update the owning guide when behavior or operation changes; give a concrete no-impact reason for mechanical changes.

### 11. Temporary E2E evidence cannot replace our regression suite

`.claude/skills/e2e/SKILL.md:19` forbids committing its browser tests, and `.claude/agents/e2e-qa-tester.md:13` assumes a permanent suite in an external repository. That external suite was not obtained or executed during this review.

Cache-Cache already has meaningful maintained browser and WordPress fixtures in `tests/`. Discarding these in favor of temporary screenshots/specs would weaken repeatable verification.

**Action:** retain focused regression tests in our repository. Keep run artifacts ignored. Add manual browser evidence where it proves a condition the tests do not cover.

### 12. Git mutation policy is inconsistent

`AGENTS.md:289` permits exactly one push in its issue workflow, while orchestrator repair loops require further pushes at `:556`, `:558`, `:573` and `:652`. More consequentially, `.claude/agents/release-agent.md:49` and `:54` direct automatic amend/rebase to add model attribution trailers. This conflicts with our existing requirement not to amend or rebase without a request. A human-author exemption at `:67` also differs from the DOD every-commit trailer check at `.claude/skills/dod/SKILL.md:215`.

**Action:** write one explicit Git policy. Preserve existing history and index; never rewrite commits merely to satisfy an agent-specific trailer. Keep push, merge and deployment boundaries separate.

### 13. Independent review and verification freshness are overstated

`.claude/skills/dod/SKILL.md:23` promises a fresh independent context, but the orchestrator invokes it inline at `:530`. This is a coordinator check, not independent review.

The orchestrator carries prior QA forward for SECURITY, TESTS and CONVENTIONS fixes at `:573`, assuming these cannot alter behavior. Security guards can change observable behavior. Conversely, the QA-failure loop at `:652` requests another QA pass without explicitly rerunning affected earlier gates.

**Action:** associate proof with its tested revision and scope. Recheck affected behavior and gates after edits. Keep author checks distinct from independent review; use a separate reviewer for material risks.

### 14. Lint and comment-update helpers contain concrete errors

`bin/phpcs-changed.sh:3` gathers only modified/untracked working-tree paths. After commits it can silently check nothing, even when the branch contains changes. At `:5` it quotes all newline-separated paths as one argument, which is incorrect for multiple files.

`.claude/agents/lead-reviewer.md:22` searches issue comments but posts its summary to the PR at `:221`, so the intended update lookup is aimed at a different conversation. `.claude/agents/qa-engineer.md:242` extracts the trailing component of a comment URL as its REST comment ID; that component contains the page number and `#issuecomment-…`, rather than the numeric comment ID required by the endpoint at `:243`.

**Action:** lint the intended branch delta or explicitly staged paths, with correct argument handling. For publishing, read and retain the API's actual numeric ID and update the correct PR conversation. Add focused fixtures for helpers before making them delivery gates.

### 15. Frontend build and ownership guidance needs correction

`.claude/skills/wp-rocket-frontend-architecture/SKILL.md:20` directs work toward compiled `assets/`, while upstream Gulp builds JavaScript from `src/js/global/app.js` (`src/js/gulp/tasks/js.js:40`). `.claude/agents/frontend-agent.md:104` requests nonexistent `npm run build`; upstream `package.json:11` defines `build:css` and `build:js` instead. Cache-Cache's source/build arrangement is different again.

The frontend agent at `:43` allows an implemented API contract to override the agreed spec without resolving the mismatch. Its PHP prohibition at `:51`, combined with the backend agent's HTML exclusion at `.claude/agents/backend-agent.md:37`, also leaves WordPress PHP templates awkwardly owned. Both agents contain auto-commit directions and inconsistent handling of failed outcomes.

**Action:** define ownership by behavior, including PHP templates, rather than file extension alone. Discover real source/generated relationships and checks. Resolve material contract drift explicitly and permit truthful failed or blocked results.

### 16. WordPress specifications need contextual correction

`.claude/specs/phpcs/validated-sanitized-input.md:39` correctly offers `FILTER_VALIDATE_BOOLEAN`, but its alternative at `:40`, `(bool) filter_input(...)`, is unsafe if an unparsed string such as `"false"` is returned: PHP casts that string to true. Use the explicit boolean parser and handle its admitted values and defaults.

The nonce specification at `.claude/specs/phpcs/nonce-verification-recommended.md:37` applies nonce gating to read-only GET filtering too broadly; ordinary bookmarked filters should not require a state-changing request token solely because they use GET. Its alternative token suggestion at `:46` should not become a new authentication mechanism. The inline JavaScript advice at `.claude/specs/phpcs/escaped-output.md:22` also needs to distinguish script content from an HTML attribute containing JavaScript.

**Action:** keep validation, sanitization, authorization and contextual escaping at the actual boundary. Nonces do not replace capability checks. Reuse WordPress APIs and avoid inventing a second security protocol.

## Recommended adoption

Use a compact root `AGENTS.md` for shared project rules and an owning development guide for GitHub workflow. Link existing design, architecture, test and operational guidance rather than copying it into every agent.

The normal path should be: bounded issue acceptance, dedicated branch, implementation with focused proof and documentation impact, PR with complete verification, independent review of the final head, and authorized merge. Worktrees are useful for isolation; they need not become mandatory for every tiny change.

Retain optional specialist review for cross-cutting cache, optimization, migration and UI work. Do not import the entire orchestrator, repeated reporting layers, public screenshot publication or upstream release/notification automation.

Consolidate the overlapping compliance skills. Preserve English gettext keys, our `noobrocket` domain, `noobrocket_*` capabilities and existing PO/MO workflow. The WordPress Abilities guidance has useful registration, schema and permission checks, but its malformed capability example at `.claude/skills/wordpress-ability/SKILL.md:29` needs correction, and REST/MCP exposure at `:33` must be decided per ability rather than automatic.

Do not carry model-specific attribution enforcement or detailed reconstructed agent "reasoning" panels into operational logs. Retain decisions, changed scope, actual checks and results. One current PR acceptance record is sufficient; a second generated HTML narrative is not a correctness requirement.

Repair Cache-Cache's actual tooling before making it a mandatory gate. `noobrocket/composer.json` currently references missing PHPUnit configurations, PHPCS files/helper and PHPStan configuration, and its installation scripts still reference the removed Mozart tool. Copying upstream instructions would not repair those commands. Adapt checks to the source and fixtures we actually maintain.

## Verification and limits

This review used local tracked-file inventory, instruction and script reads, selected source/configuration checks, Git status and CLI help. It did not boot a site, run the imported automation, publish evidence, modify GitHub, package the plugin or deploy anything. Presence of a workflow definition does not prove it works or is enforced remotely.

The recommended workflow is proposed, not installed. A later adoption change must verify references, executable checks, disposable runtime ownership and the final GitHub targets before enabling automation.

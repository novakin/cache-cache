# WireGuard Android and WP Rocket workflow comparison

Last updated: 2026-10-10 (Europe/Paris).

## Assessment

Follow-up: the [latest-three-PR audit for each project](RECENT_PR_PROCESS_AUDIT_20261010.md) separates observed delivery from instruction quality. Current sample delivery estimates are **85/100 for WireGuard and 84/100 for WP Rocket**; the gap is within review uncertainty. The 89/68 process scores below preserve the initial mixed documented-process assessment and must not be read as current observed-delivery grades. Agent-guidance scores remain 90/52.

| Evaluated area | WireGuard Android | WP Rocket |
| --- | ---: | ---: |
| Development process, including AI delivery | **89/100** | **68/100** |
| Agent instructions and guidance | **90/100** | **52/100** |

These are senior-review estimates using the common rubrics below, not measured productivity or model-quality scores. Treat differences of about five points as judgment uncertainty. The assessment covers the inspected process and guidance, not organizational compliance, product correctness or production performance. More agent files do not earn more points.

WireGuard has stronger scope preservation, explicit authorization, ownership and revision-specific acceptance. WP Rocket has broader conventional PHP/WordPress CI, but its AI delivery instructions contain material contradictions and broken tooling. Licensed prerequisites and WP Rocket's own repository identity are appropriate inside upstream; they were not scored as defects merely because they would need changing for Cache-Cache.

## Scope and independent checks

- WireGuard snapshot: clean `2cba7f90ba077131823ed606d1656c08e9e128db`, under `C:/Users/Guillaume/Documents/Projets/wireguard-android`.
- WP Rocket snapshot: clean `3a96b66db41791fb1f4bb1e62dfe658ec4807454`, under `.artifacts/upstream/wp-rocket`.
- WireGuard material: root `AGENTS.md`, development and verification guidance, issue/PR templates, Android workflow, relevant build/configuration references and existing review records. Only `AGENTS.md` is tracked as agent configuration; the local `.claude` directory contains ignored user settings, which were not opened.
- WP Rocket material: the 45-file instruction/agent/skill bundle reviewed in [the upstream guidance audit](UPSTREAM_AI_GUIDANCE_REVIEW_20261010.md), plus contribution guidance and actual CI definitions.
- Two independent read-only assessments informed the final scores. Their WireGuard estimates ranged from 88–91 for process and 90–92 for guidance. Final values below use conservative deductions and the subsequent CI readback.

No source, workflow, branch, issue, PR or runtime configuration was changed. No build or test suite was rerun for this assessment. The only maintained change is this assessment document.

## Process rubric

Each dimension is scored from 0 to 100. Weighted total is `sum(score * weight / 100)`, rounded to the nearest integer. The rubric treats 50 as partial with material contradictions, 75 as useful with significant gaps, and 90 as coherent and well grounded. A score of 100 requires unusually complete proof and no material unresolved gap.

| Dimension | Weight | WireGuard | WP Rocket |
| --- | ---: | ---: | ---: |
| Scope and clarity | 20% | 90 | 75 |
| Authorization and state preservation | 20% | 95 | 55 |
| Verification and freshness | 25% | 88 | 68 |
| Operational tooling and CI | 15% | 85 | 88 |
| Review and handoff | 15% | 93 | 66 |
| Simplicity | 5% | 60 | 42 |
| **Weighted result** | **100%** | **88.70 → 89** | **68.20 → 68** |

## Agent-guidance rubric

This evaluates the instructions that guide implementation and review, including `AGENTS.md` and specialist definitions where present. It does not evaluate the intelligence of the model following them or require specialist files for their own sake.

| Dimension | Weight | WireGuard | WP Rocket |
| --- | ---: | ---: | ---: |
| Clarity | 20% | 88 | 54 |
| Architectural fit | 20% | 96 | 66 |
| Tooling accuracy | 20% | 87 | 40 |
| Failure and evidence honesty | 20% | 97 | 62 |
| Safe autonomy | 15% | 94 | 42 |
| Concision | 5% | 50 | 28 |
| **Weighted result** | **100%** | **90.20 → 90** | **52.10 → 52** |

## WireGuard evidence and deductions

Paths in this section are relative to the WireGuard repository.

**Strong ownership and scope.** `AGENTS.md:36` assigns presentation, tunnel state, transport, orchestration and persistence to existing owners. `:37` prefers existing helpers/platform APIs. `:40` and `:41` preserve failure, cancellation and uncertain-operation semantics. `docs/development.md:66` and `:74` require current base inspection, index preservation and intentional staging.

**Strong acceptance freshness.** `docs/development.md:100` requires one current acceptance record, identifies tested/reviewed revisions and separates self-review from independent analysis. `:102` explicitly states that same-account COMMENT reviews do not satisfy required approval from another account. `:125` requires final published head coverage; `:127` blocks merge for unavailable review, unresolved findings or failed required checks.

**Clear action boundaries.** `docs/development.md:104` separates PR publication from merge/deploy authorization. `:123` bounds autonomous goals. `.github/workflows/android.yml:61` makes APK assembly an explicit manual input; normal checks do not build or install it. Destructive VPS and device work retain separate approval and target requirements.

**Concrete CI.** `.github/workflows/android.yml:14` uses read-only token permissions, `:30` checks out the exact PR head, `:32` does not persist credentials, and `:34`, `:47`, `:49` run offline Python, JVM/static and host-native checks. Reports are retained at `:51`. These are actual commands with identifiable owners rather than a generic promise to run tests.

**Main deductions:**

- The PR run deliberately checks the head rather than the synthetic merge candidate. It verifies the accepted branch revision but does not establish base/head interaction before merge. A post-merge run is later evidence, not an equivalent preventive gate.
- The workflow does not invoke the real Bash provisioning safety suite documented at `docs/development.md:159`. Android ABI compilation, rendered UI, physical VPN behavior and real SSH/VPS operation are also outside ordinary checks. These limits are disclosed; missing execution is not treated as a concealed failure.
- Reading burden remains high: approximately 2,515 words in `AGENTS.md` and 15,064 in the development guide. The latter mixes current process with substantial historical execution records despite separate review-document ownership. Rules repeat across the root file, guide and PR template.
- The example build command at `AGENTS.md:112` includes a maintainer-specific Windows/WSL path. Ignored `.design` references also need local material or an approved replacement. Both have documented limitations, so these are portability costs rather than broken core ownership.
- Current required-check protection, independent-account approval enforcement and universal PR compliance were not audited.

The best next improvements are to separate historical evidence from the short current development recipe, reduce repeated policy, and add relevant automated coverage where it provides a useful preventive gate. Device/server tests remain opt-in when they mutate real state.

## WP Rocket evidence and deductions

Paths in this section are relative to the downloaded WP Rocket repository.

**Conventional contribution flow is useful.** `CONTRIBUTING.md:12` requests reproduction and relevant version information. `:20` describes fork/change/PR contribution and human review. Its `master` target at `:25` disagrees with the AI workflow's `origin/develop` default at `.claude/skills/issue-workflow/SKILL.md:42`.

**Broad CI deserves credit.** `.github/workflows/test_wprocket_php8.yml:24` declares PHP 8.0–8.5 with latest WordPress; `:78` through `:91` invoke unit, integration, admin, performance and third-party suites. Legacy PHP 7.4/WordPress 5.9 appears in `.github/workflows/test_wprocket.yml:24`. PHPCS and PHPStan workflows exist with actual configurations and Composer commands. Reusable workflow/action implementations are referenced through mutable `@main`, outside the inspected snapshot.

**The agent pipeline is the weak area.** The separate [16-finding audit](UPSTREAM_AI_GUIDANCE_REVIEW_20261010.md) records exact citations. Principal score deductions are:

- Missing `.aiassistant` paths, mismatched graph producer/consumer and a stale graph treated as mandatory authority.
- DOD scope rules that skip and require the same gate, and PR testing/draft gates that precede their required inputs.
- Automatic amendment/rebase for model trailers and automatic resolution of every unresolved review thread.
- Public screenshot uploads and standalone publication paths that precede the requested publication decision.
- Carrying QA forward after security changes and describing an inline coordinator check as independent review.
- Invalid QA issue-field queries, incorrect comment-ID extraction and changed-file lint that misses committed changes and mishandles multiple paths.
- Competing agent instructions, blanket architecture mandates, unclear PHP-template ownership and unnecessarily elaborate reporting.

These defects lower confidence in the prescribed AI-assisted process. They do not establish that WP Rocket's ordinary CI or human engineering organization is failing.

## Read-only GitHub evidence

Readback performed on 2026-10-10. Check conclusions are GitHub's reported results; job logs and individual test counts were not audited.

| Sample | Verified result | Limit |
| --- | --- | --- |
| [WireGuard PR #102](https://github.com/novakin/wireguard-android/pull/102), head `b7c649f436b31d53139c34eb0eba518571e91048` | Merged; Native checks SUCCESS. Reviews include COMMENTED records by `novakin` on the final head. | Metadata does not establish a second-account approval or prove independent reviewer context. |
| WireGuard merge snapshot `2cba7f90ba077131823ed606d1656c08e9e128db` | Native checks completed successfully. | One successful run does not prove every required check or all prior merges followed policy. |
| [WP Rocket PR #8957](https://github.com/wp-media/wp-rocket/pull/8957), head `9000da9c276c5efe49520a9fde3e10cfc48443f6` | Merged; 15 successful checks and one skipped build. Successes include PHPCS, PHPStan, PHP 7.4/WordPress 5.9 and PHP 8.0–8.5/latest-WordPress checks. | Coverage details, skipped individual tests and external review channels were not audited. The PR reviews response was empty; no organization-wide conclusion follows. |
| WP Rocket merge snapshot `3a96b66db41791fb1f4bb1e62dfe658ec4807454` | Copilot-review and licence check-runs completed successfully. | PR-triggered tests are visible on the PR head, so their absence on this merge SHA is not evidence of missing or failed CI. |

The comparison therefore credits actual sampled CI success for both projects while retaining the source-confirmed guidance deductions. No GitHub fields, reviews or repository settings were written.

## Adoption recommendation

Use WireGuard's authorization, preservation, final-head acceptance and documentation ownership as the foundation for Cache-Cache. Shorten that guidance rather than reproducing the full historical development guide. Add WP Rocket's applicable WordPress/PHP test and analysis patterns after repairing our tooling. Do not transplant the upstream AI orchestrator wholesale.

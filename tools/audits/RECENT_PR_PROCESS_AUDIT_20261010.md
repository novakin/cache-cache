# Recent PR process audit

Last updated: 2026-10-10 (Europe/Paris).

## Result and rating correction

All six sampled PRs had successful CI associated with their final merged branch head before merge. Review and acceptance evidence were less uniform.

| Area | WireGuard Android | WP Rocket |
| --- | ---: | ---: |
| Observed delivery in the three-PR sample | **85/100** | **84/100** |
| Previously inspected agent guidance | **90/100** | **52/100** |

The delivery estimates overlap within review uncertainty. Three PRs do not establish a meaningful one-point ranking. WireGuard shows clearer revision-specific acceptance and limitation handling; WP Rocket shows broader automated coverage and distinct-account approvals on two sampled PRs.

The earlier [comparison](AI_WORKFLOW_COMPARISON_20261010.md) rated a combined documented contribution/AI process at 89/68. That was too easy to interpret as actual delivery quality, and the WP Rocket number understated its demonstrated recent delivery. The current delivery assessment above is separate from instruction quality. The source-confirmed defects in the [upstream AI guidance](UPSTREAM_AI_GUIDANCE_REVIEW_20261010.md) remain valid; successful delivery does not establish that every prescribed automation step is sound.

Scores are weighted senior-review estimates, not measured productivity, organizational compliance or product correctness. Treat differences of roughly five points as judgment uncertainty. No model-intelligence score is assigned.

## Selection and verification

The sample uses the three most recently **merged** PRs in each repository as observed on 2026-10-10. It does not use the three highest PR numbers or most recently commented PRs.

Selection retrieved merged PRs ordered by update time, then sorted by merge time locally: all 55 available WireGuard PRs and 100 WP Rocket PRs. The oldest retrieved WP update precedes the third selected merge, so any remaining older-updated PR cannot have a newer merge time. This verifies the latest-three boundary at retrieval.

Read-only evidence includes PR descriptions, commits, files/diffs, comments, reviews, linked issues, check runs, selected Actions run/job metadata and available branch-rule endpoints. Three independent review assignments covered WireGuard delivery, WP Rocket delivery and chronology/source associations. Raw receipts are retained under ignored `.artifacts/pr-process-review-20261010/`.

No builds, tests, imported scripts, device operations, deployments, Git updates or GitHub writes occurred. CI success is GitHub's reported result; local/manual claims in descriptions remain attributed reports. Full runner logs, private captures, TestRail/PDF reports and individual skip counts were not independently audited. A check run's head association identifies its PR revision; it does not by itself disclose whether runner checkout also included the synthetic merge candidate.

All times below use Europe/Paris, CEST (UTC+02:00) for these October dates.

## WireGuard Android

| PR | Scope | Review and CI before merge | Finding |
| --- | --- | --- | --- |
| [#102 — app data transfer](https://github.com/novakin/wireguard-android/pull/102) | 31 files, four commits; transfer behavior, authentication repair, seven test files, docs and test-worker resources. | Final-head COMMENT review at Oct 10 08:21:21; Native checks success at 08:27:11; merged 08:29:44. | Detailed evidence and retained limits; all review records use the author account. |
| [#97 — quick-tile membership](https://github.com/novakin/wireguard-android/pull/97) | 13 files, seven commits; four production files, two tests and seven docs. | Final-head COMMENT review at Oct 9 19:00:20; Native checks success at 19:04:47; merged 19:08:30. | Regression proof improved after review; physical/accessibility limits remain separate. |
| [#99 — optional engine assessment](https://github.com/novakin/wireguard-android/pull/99) | Three documentation files, two commits. | Final-head COMMENT review at Oct 9 17:50:28; Native checks success at 17:52:10; merged 18:33:58. | Proper assessment-only scope; no implementation or runtime success claimed. |

### #102

Final branch head: `b7c649f436b31d53139c34eb0eba518571e91048`. [Review 5477936422](https://github.com/novakin/wireguard-android/pull/102#pullrequestreview-5477936422) and [run 38030610654](https://github.com/novakin/wireguard-android/actions/runs/38030610654) identify that head. Earlier commit-specific review checkpoints remain visible. [Review 5475716211](https://github.com/novakin/wireguard-android/pull/102#pullrequestreview-5475716211) retained a live Detail smoke as a blocker; later recorded evidence reports a real handshake, growing counters and 60/60 pings before merge.

The description discloses emulator-only acceptance, an accidental synthetic activation, untranslated copy and unproved physical/provider/migration boundaries. [#71](https://github.com/novakin/wireguard-android/issues/71) remains open and is referenced without a closing keyword, correctly preserving partial delivery.

A small presentation gap remains: an earlier review link is labelled "final-head review" beside the later actual final review. A concise current acceptance summary should identify one current head and retain earlier evidence as historical.

### #97

Final branch head: `97568b44d70353b4d3910ccb25c4dc3c77180118`. [Review 5473027621](https://github.com/novakin/wireguard-android/pull/97#pullrequestreview-5473027621) and [run 37963172019](https://github.com/novakin/wireguard-android/actions/runs/37963172019) identify that head after base integration/conflict handling.

Tests cover unavailable/loading state, reconstruction, failed persistence and queued removal during add. The transaction-state assertion prevents eventual removal from masking stale publication. The description reports 48 focused passing tests, zero Detekt findings, bounded API 36/native proof and retained restoration failures. These local/native details were not rerun during this audit.

[#85](https://github.com/novakin/wireguard-android/issues/85) closed as completed at Oct 9 19:08:32, two seconds after merge. Broader physical, TalkBack and VPN acceptance remains separate.

### #99

Final branch head: `231582ec3b4b8b163a07f176f64c71fea1eff5e3`. [Review 5472324784](https://github.com/novakin/wireguard-android/pull/99#pullrequestreview-5472324784) and [run 37954610829](https://github.com/novakin/wireguard-android/actions/runs/37954610829) identify that head.

The assessment pins external revisions and explains engine, parser, licensing and compatibility limits. [#76](https://github.com/novakin/wireguard-android/issues/76) and [#72](https://github.com/novakin/wireguard-android/issues/72) remain open. CI success here does not turn an assessment into delivered runtime functionality.

### Shared WireGuard limit

All seven submitted review records across these PRs are `novakin` / COMMENTED. PR author and merger are also `novakin`. These records prove published revision-specific analysis; they do not identify a distinct human reviewer or satisfy an independently accountable second-account approval. The descriptions disclose that distinction. Claims about independently performed agent analysis and private runtime captures cannot be fully verified from review metadata alone.

## WP Rocket

| PR | Scope | Review and CI before merge | Finding |
| --- | --- | --- | --- |
| [#8913 — rollback maintenance](https://github.com/wp-media/wp-rocket/pull/8913) | Six files; two production rollback paths and mirrored tests/fixtures. | Two distinct-account approvals; 14 successful checks. Latest CI completed Oct 9 15:46:57; merged 15:51:25. | Meaningful QA found corrections; approval chronology and concurrent-request exception need qualification. |
| [#8986 — PHPStan dead code](https://github.com/wp-media/wp-rocket/pull/8986) | 43 files, three commits; source cleanup, test repairs and three limit-enforcement fixes. | Distinct-account final-head approval; 14 successful checks and skipped Alpha Release. Latest CI completed Oct 8 11:55:27; merged Oct 9 15:37:11. | Strong CI, but issue's "no behavior change" acceptance disagrees with the documented behavior fixes. |
| [#8957 — HTTP-mocking migration](https://github.com/wp-media/wp-rocket/pull/8957) | Eight files, three commits; test infrastructure and AGENTS update. | No submitted PR reviews; 15 successful checks and skipped Alpha Release. Latest CI completed Oct 1 21:33:42; merged Oct 2 20:14:42. | Good verification description; missing public review evidence and temporary refusal controls. |

Each merged into `develop`; linked issues closed after merge. Representative PHP 8.0 and legacy PHP 7.4 jobs for all three report successful Unit, Integration, Admin integration, Performance Hints and Third Party integration steps. The skipped builds on #8986/#8957 belong to Alpha Release, not the test suites.

### #8913

Final branch head: `0ffd53f0d6f51c0fc678173137e2afd91efd284e`. Tests check ordered filesystem initialization, maintenance enable/disable, rollback and output cleanup on success, false/WP_Error returns and filesystem refusal. [PHP 8.0 job 113845553642](https://github.com/wp-media/wp-rocket/actions/runs/37938208710/job/113845553642) is one of the successful checks associated with this head.

Visible AI-pipeline comments caught filesystem initialization and output-footer defects, followed by correction commits. This is actual positive delivery evidence despite defects in the stored workflow instructions. The description also distinguishes a local concurrent-response check without calling the real upgrade from a complete end-to-end rollback, and discloses that the older WordPress failure was verified from source rather than reproduced.

Distinct-account approvals are [review 5309939276](https://github.com/wp-media/wp-rocket/pull/8913#pullrequestreview-5309939276), Sep 24 22:37:15, and [QA review 5393464809](https://github.com/wp-media/wp-rocket/pull/8913#pullrequestreview-5393464809), Oct 2 17:13:07. Both APIs currently associate these approvals with the final head, while the final merge-from-develop commit is dated Oct 9 15:37:43. Submission times therefore do not establish a newly performed post-update review. The association/chronology mismatch is an evidence limitation; it is not proof that no review covered the feature.

QA explicitly reports that requests already running when rollback starts can still fail, tracking [#8959](https://github.com/wp-media/wp-rocket/issues/8959). The PR's broader claim of preventing concurrent-request fatals and its all-criteria checklist should include that exception. Latest explicitly revisioned AI QA [comment 5818893279](https://github.com/wp-media/wp-rocket/pull/8913#issuecomment-5818893279) is scoped to an earlier head. External TestRail PDFs were not inspected.

### #8986

Final branch head: `3610b175bb2b84d38ccd90c809e3f3c0542ded35`. [Approval 5454764127](https://github.com/wp-media/wp-rocket/pull/8986#pullrequestreview-5454764127) is by another account at Oct 8 11:52:40, after the last commit at Oct 7 20:32:53. No later commit appears. [Legacy job 112951159918](https://github.com/wp-media/wp-rocket/actions/runs/37667726312/job/112951159918) is one successful final-head-associated check.

The PR explains why unlocked PHPStan resolution introduced new dead-code findings. It fixes source and test assertions without changing analysis configuration or adding baseline entries. New tests cover actual enforcement of preconnect/font/LRC item limits.

[#8985](https://github.com/wp-media/wp-rocket/issues/8985) requires "No behavior change", but the approved PR explicitly introduces those limit fixes. This is disclosed implementation with unreconciled issue acceptance, not hidden behavior. A template placeholder also remains.

[QA comment 6077205051](https://github.com/wp-media/wp-rocket/pull/8986#issuecomment-6077205051) reports external E2E with an expected failure tracked in [wp-rocket-e2e#436](https://github.com/wp-media/wp-rocket-e2e/issues/436). It assigns manual TestRail execution rather than reporting completed manual QA. That assignment is not acceptance proof. [Skipped run 37668375918](https://github.com/wp-media/wp-rocket/actions/runs/37668375918) is Alpha Release.

### #8957

Final branch head: `9000da9c276c5efe49520a9fde3e10cfc48443f6`. [PHP 8.0 job 110543845044](https://github.com/wp-media/wp-rocket/actions/runs/36914146776/job/110543845044) is one successful final-head-associated check; [skipped run 36914147013](https://github.com/wp-media/wp-rocket/actions/runs/36914147013) is Alpha Release.

The description names all 47 locally exercised integration invocations, unchanged credential-dependent failures, measured zero outbound requests and positive/negative controls. It also discloses approximately 37 tests using vendor bases outside the new local-base guard. This separates present measurements from future universal enforcement correctly.

There are no submitted PR reviews and only Codacy's comment. Author and merger are both `remyperona`. This establishes absence of visible PR-review evidence, not absence of every possible private review or violation of an unverified rule. The guard-refusal controls were temporary; retaining a focused permanent regression test would improve repeatability.

## Merge-gate readback

For WireGuard `master`, both protection and effective-rule endpoints return the plan restriction: "Upgrade to GitHub Pro or make this repository public to enable this feature." This audit cannot establish an enforced required-check/approval gate. Current published acceptance therefore remains stronger than verified platform enforcement.

For WP Rocket `develop`, the legacy protection endpoint returns 404 Not Found. The effective-rule endpoint succeeds and lists deletion protection, Copilot code review and non-fast-forward protection. It lists no required human approval or required status-check rule. Because legacy protection readback is unavailable, the result must not be expanded into a universal claim that no other control exists.

These endpoints were read only. No rules, account plan or repository visibility was changed.

## Observed-delivery rubric

This uses the earlier process dimensions, applied to observed PR delivery rather than defects in unused instruction paths. Authorization/state and review scores retain uncertainty because private authorization/review evidence and enforcement are incomplete.

| Dimension | Weight | WireGuard | WP Rocket |
| --- | ---: | ---: | ---: |
| Scope and clarity | 20% | 90 | 86 |
| Authorization and state preservation | 20% | 82 | 78 |
| Verification and freshness | 25% | 90 | 84 |
| Operational tooling and CI | 15% | 88 | 94 |
| Review and handoff | 15% | 78 | 76 |
| Simplicity | 5% | 65 | 85 |
| **Weighted observed result** | **100%** | **85.05 → 85** | **83.55 → 84** |

WireGuard's exact-head acceptance and limitations deserve credit, with deductions for same-account review, manual gate enforcement and large historical descriptions. WP Rocket deserves substantial credit for actual broad CI and real QA corrections, with deductions for uneven public review, acceptance drift and incomplete final-update/manual evidence.

One WireGuard PR is documentation-only; the WP sample contains functional and test-infrastructure work. The audit does not claim equal difficulty, statistically representative throughput or that a one-point difference proves superiority.

## Improvements supported by this sample

- Preserve one concise current acceptance record and distinguish historical review links from the current final-head verdict.
- Distinguish author-account agent analysis from an independently accountable reviewer approval. Do not represent an additional account identity as proof of actual independence.
- Verify applicable merge gates and disclose hosting/access limits; do not claim enforcement merely because CI passed.
- Reconcile issue acceptance when a reviewed PR intentionally changes behavior or carries a known exception. Keep follow-up work open where delivery is partial.
- Preserve meaningful regression controls for critical infrastructure behavior instead of relying solely on temporary demonstrations.
- Revalidate affected behavior after material code/base updates and disclose what was not rerun. Assignment of manual QA is not a completed result.

## Changed state

This audit and a cross-reference in the earlier comparison are the maintained documentation changes. Ignored evidence receipts were added. Both inspected source repositories remain untouched; no commit, push, merge, package or deployment was performed.

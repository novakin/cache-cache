# Cache-Cache contributor contract

## Identity and scope

Cache-Cache is a standalone, free WordPress caching plugin. The maintained distribution is `noobrocket/`; its entry point is `noobrocket/noobrocket.php`. Preserve the `NoobRocket` namespace, `noobrocket` text domain, hooks, routes, option names and directory layout. They identify installed data and integrations, not the displayed brand. Product and engine versions are independent. The plugin header owns the development version; the engine baseline is `3.23.5.1`. Releases remain beta until stable acceptance. See [release rules](docs/releases.md).

Work from the current source and runtime evidence. Reference distributions, upstream history and dated audits explain provenance; they do not override the implementation. Keep [current guides](README.md#contributor-guides) separate from [historical evidence](docs/history/README.md). Cloud services are disabled even where inherited classes remain. Trace registration and callers before removing apparently unused code.

## Inspect before changing

Read the relevant guide, inspect worktree/index state when Git exists, and reproduce the behavior at its responsible layer. Follow registration from `inc/Plugin.php`, service providers and event subscribers to the owner. [Architecture](docs/architecture.md) maps cache, settings, queues, workers and UI. Use `rg` for focused searches. Preserve unrelated edits and runtime data; do not restore an old baseline over user changes.

Prefer existing behavior, the responsible owner, then native WordPress/platform APIs. Build a small new implementation only when needed. Consolidate shared invariants where they belong. Clear names and ordinary control flow outrank short code. Do not add adapters, interfaces, configuration, logging or an agent framework without a concrete requirement. A coherent refactor is appropriate when current ownership obstructs the requested behavior; its proof must cover preserved behavior.

## Protect real boundaries

For admin mutations, preserve WordPress capability checks, nonces, validated input, contextual escaping and failure feedback. A nonce is not authorization. Use the existing settings-domain validation for form, import, CLI and Abilities callers. Preserve site keys, product/engine markers, concurrent-review checks, private draft ownership and history exclusions. Provider requests use saved configuration and the action's own authorization.

Shared page-cache admission must preserve anonymous/public eligibility, response privacy headers, cookie/query exclusions and device/scheme variants. Do not cache authenticated, private or action responses through a shortcut. Cache publication must remain complete; invalidation must reach the affected variant without deleting another site's or plugin's files. Verify resolved paths and explicit ownership before destructive filesystem operations. Network operations must restore blog context and preserve independently active sites.

Fixtures that bootstrap or mutate WordPress require a marked disposable installation with a copied plugin, separate database and owned paths. A localhost site or existing preview is not disposable merely because it is local. Run mutating fixtures serially per site. Block unowned external requests; do not exercise production providers for ordinary checks. Never publish credentials, sessions, databases, captures or private command logs.

## Source, generated output and UI

Edit maintained inputs and update required tracked outputs. [Development setup](docs/development.md) lists settings JavaScript, translation generators and bundled dependency ownership. Validation uses disposable copies and must not rewrite source. Do not run Composer install/update over shipped runtime dependencies; their reconstruction is a separate task. Keep dependency licenses.

English gettext message keys belong in PHP/JavaScript; translations belong in PO/MO catalogs. Use WordPress plural/context APIs and preserve placeholders. Keep technical identifiers stable when changing display text. [Design guidelines](tools/DESIGN_GUIDELINES.md) own layout, tokens, control sizes, action ownership, notifications and accessibility. Inspect affected production pages in light/dark, English/French, desktop/narrow sizes and keyboard states. Screenshots or a mockup alone do not establish functional acceptance.

## Verify and report

Run the smallest relevant [check profiles](tests/README.md), then broaden for cross-cutting, lifecycle or release changes. Add regression proof for meaningful defects and risks, not assertions that merely mirror trivial code. Missing prerequisites, skipped work and assigned QA are not passes. Keep actual commands, environment, revision and limitations in one acceptance record. Syntax is not static analysis; modeled fixtures are not native WordPress execution.

Measure performance before claiming improvement. Compare equivalent cold/hit states, settings and environments; report samples and variability. Do not shift expensive work into settings saves or visitor requests without measuring that path. A Lighthouse score does not establish superiority over another plugin.

Use optional investigator, implementation owner and fresh reviewer/verifier roles from the development guide. Another agent supplies analysis, not human approval. Review the delivered diff and affected behavior; material code/base changes invalidate affected proof. Retain historical review links as historical, and disclose solo-maintainer review.

Commit, push, merge, release and deployment are separate actions within the user's authorized scope. Never automatically amend, rebase, reset or force-push. Do not package or deploy merely to validate design. Keep commands/output bounded, give brief useful progress updates, and finish required commands/reviews before the final response. Report intentional changes, actual proof, publication state and remaining risks honestly. Stop when the requested outcome is satisfied.

## Mandatory GitHub publication templates

Before creating or editing an issue, read the matching [bug report](.github/ISSUE_TEMPLATE/bug.yml) or [feature proposal](.github/ISSUE_TEMPLATE/feature.yml) template. Before creating or editing a PR, read the [PR template](.github/pull_request_template.md). These are mandatory publication gates for browser, CLI and API actions. CLI/API commands do not apply templates automatically: construct the body from the selected template, preserve its field labels/headings and complete every required field. Use explicit pending or unavailable evidence where appropriate; never invent proof to fill a field.

Inspect the prepared body against the template before publication, then read back the published body and correct any discrepancy. Never publish an ad hoc body, skip required fields or claim the tool's default body satisfies this gate. If the template cannot be read or a required field cannot be truthfully completed, stop publication and report the missing input; continue independent local work. Existing issues or PRs being used for delivery must satisfy the same gate before the next publication or merge.

# Source inventory and current boundaries

Date: 2026-10-10. Public product: Cache-Cache. Technical identifiers: `NoobRocket` / `noobrocket`.

## Curated repository inputs

| Location | Ownership | Treatment |
| --- | --- | --- |
| `noobrocket/inc/`, `views/` | Maintained WordPress engine, feature owners and templates | Commit intentional source. Extend the responsible owner before adding structure. |
| `noobrocket/assets/` | Maintained scripts/styles and served generated assets | Commit inputs and required generated outputs together. |
| `noobrocket/languages/` | POT/PO message catalogs and compiled MO | English gettext keys in source; translations in catalogs. |
| `noobrocket/vendor/`, `inc/Dependencies/`, `inc/classes/dependencies/` | Bundled runtime dependencies and prefixed distributions | Commit current runtime inputs. Dependency reconstruction is not yet a verified release pipeline. |
| `noobrocket/local-css/` | Same-server Node/Chromium worker and pinned npm manifests | Commit source and lock file; install `node_modules` separately. |
| `noobrocket/docs/` | Packaged user help | Ship with plugin; keep developer process guidance outside this directory. |
| `tests/` | Maintained fixtures and explicit verification catalog | Commit tests/helpers; isolate mutation and generated evidence. |
| `tools/` | Build/check tools, maintained generators, dated reviews and proof summaries | Commit intentional scripts/docs; exclude installed dependencies and private artifacts. |
| Root guides, `docs/`, `.github/` | Current contributor contract, operations, architecture and GitHub process | Commit current guidance and small workflows/templates. |

The curated import excludes `.artifacts/`, `dist/`, installed `node_modules`, private captures, credentials, cache/config output and duplicate reference distributions. Local copies remain intact. The beta ZIP is a release artifact rather than a source-control input.

`tools/prepare-github.py` creates a new checkout from upstream release `v3.23.5.1` and copies these inputs. It stages a reviewable migration; it does not commit or publish. The downloaded shallow checkout retains the release commit identity; the GitHub fork provides the existing upstream ancestry. Local history depth is not a claim that every historical blob was downloaded.

## Product findings kept open

These source-confirmed findings remain separate from the developer-process implementation. They require focused reproduction before a product fix, rather than deletion of legacy code or a speculative cache redesign.

| Finding | Current status | Responsible path / next proof |
| --- | --- | --- |
| Expiry scanning walks the owned cache tree; a historical 50k-URL Windows run took about 11–12 seconds. | Open performance work | Measure representative host workload, then change the expiry owner only if the cost is material. |
| Cold local-font preparation uses a synchronous request budget. | Open performance work | Measure cold versus prepared requests; preserve font exclusions, failures and original delivery. |
| Config cleanup retrieves site IDs with WordPress's default site-query limit. | Open correctness review | Reproduce a network above 100 sites before changing configuration cleanup. |
| Combined JavaScript insertion matches lowercase `</body>` before removing originals. | Open correctness review | Reproduce uppercase/missing closing tags with actual optimizer output and adjacent CSP/module invariants. |
| Delay runtime insertion assumes a head element. | Open correctness review | Exercise headless markup and preserve original scripts when required runtime cannot be placed. |
| Hosted font replacement can omit original link attributes. | Open correctness review | Verify conditional/media/disabled/onload behavior before changing the font controller. |
| Dormant cloud/license/account/tracking classes remain in the distribution. | Retained pending caller assessment | Trace active callers and compatibility before removal; dormant classes are not proof that remote services run. |
| Document speculation rules and eagerness selection are not exposed. | Deferred product proposal | Current handler dynamically prepares one destination after intent. Native document rules need separate acceptance and exclusions. |

The [latest full-system review](../tools/FULL_SYSTEM_REAUDIT.md) owns the reviewed behavior and limits. Older audits retain their original findings and proofs. No process check claims these product gaps are fixed.

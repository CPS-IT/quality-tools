---
stepsCompleted: [1, 2, 3, 4, 5, 6]
inputDocuments: []
workflowType: 'research'
lastStep: 1
research_type: 'technical'
research_topic: 'TYPO3 core branching and versioning strategy adaptation for cpsit/quality-tools'
research_goals: 'Understand how TYPO3 core (https://github.com/TYPO3/typo3) structures its branches, releases, and LTS/ELTS support windows; evaluate 2-3 concrete options for adapting an analogous model to the cpsit/quality-tools composer package so that: (1) it is clear which TYPO3 version a given quality-tools version targets, (2) developers can select the matching quality-tools version via composer constraints, (3) older supported TYPO3 versions keep receiving bugfixes only (no new features). Findings feed Epic 1 stories 1-3 (publish TYPO3 version support matrix, GL#16) and 1-4 (establish LTS/ELTS bugfix branch strategy, GL#17).'
user_name: 'QT Team'
date: '2026-07-03'
web_research_enabled: true
source_verification: true
---

# Research Report: technical

**Date:** 2026-07-03
**Author:** QT Team
**Research Type:** technical

---

## Research Overview

This report investigates how TYPO3 core structures its release, branching, and long-term support model, how existing TYPO3-ecosystem composer packages (extensions and tooling) adapt that model for their own versioning, and evaluates concrete options for cpsit/quality-tools. Note on scope adaptation: the generic technical-research template (technology stack, integration protocols, microservices, cloud infrastructure) does not fit this narrow question about release/branching policy, so the sections below are scoped directly to the research goals rather than following that generic structure. All findings are drawn from current TYPO3.org/TYPO3.com documentation, endoflife.date, and the composer.json/documentation of relevant packages, with sources cited inline. See the Recommendation section for the proposed path for quality-tools.

---

<!-- Content will be appended sequentially through research workflow steps -->

## Technical Research Scope Confirmation

**Research Topic:** TYPO3 core branching and versioning strategy adaptation for cpsit/quality-tools
**Research Goals:** Understand how TYPO3 core (https://github.com/TYPO3/typo3) structures its branches, releases, and LTS/ELTS support windows; evaluate 2-3 concrete options for adapting an analogous model to the cpsit/quality-tools composer package so that: (1) it is clear which TYPO3 version a given quality-tools version targets, (2) developers can select the matching quality-tools version via composer constraints, (3) older supported TYPO3 versions keep receiving bugfixes only (no new features). Findings feed Epic 1 stories 1-3 (publish TYPO3 version support matrix, GL#16) and 1-4 (establish LTS/ELTS bugfix branch strategy, GL#17).

**Technical Research Scope:**

- TYPO3 core release model - LTS cadence, branch naming, ELTS program, support timelines and end-of-life dates
- Compatibility signaling - how TYPO3 core and extensions communicate supported core versions (composer.json constraints, ext_emconf.php, documentation conventions)
- Composer package precedent - how other TYPO3-ecosystem composer packages handle multi-version support with bugfix-only branches for older lines
- Adaptation options for quality-tools - 2-3 concrete branching/versioning models sized for a single composer package, weighing maintenance overhead against developer clarity
- Practical mechanics - composer version constraint patterns, branch protection/merge-forward implications, interaction with the versioned-config approach already underway in story 1-1

**Research Methodology:**

- Current web data with rigorous source verification
- Multi-source validation for critical technical claims
- Confidence level framework for uncertain information
- Comprehensive technical coverage with architecture-specific insights

**Scope Confirmed:** 2026-07-03

---

## 1. TYPO3 Core Release, Branch, and Support Model

### Release structure

TYPO3 core ships two kinds of releases from the same development line:

- **Sprint releases** — the first releases cut from a new major line (e.g. v14.0, v14.1, v14.2). Each sprint release is supported only until the *next* sprint release ships; features are still being added.
- **LTS release** — the final release of a major line (e.g. v14.3 is the "14 LTS", v13.4 is the "13 LTS", v12.4 is the "12 LTS"). From the LTS tag onward, no new features are added to that line — only bugfixes and security fixes. A new LTS is cut roughly every 18 months.
_Source: [TYPO3 version support and security updates](https://docs.typo3.org/m/typo3/reference-coreapi/main/en-us/Security/Versions/Index.html), [TYPO3 Explained 13.4](https://docs.typo3.org/m/typo3/reference-coreapi/13.4/en-us/Security/Versions/Index.html)_

### Branch naming

Development happens on `main` on [github.com/TYPO3/typo3](https://github.com/typo3/typo3) (renamed from `master` on 2021-11-29), which tracks the next unreleased major (v15 at the time of writing). **Correction from an earlier draft of this report:** TYPO3 core does maintain a persistent, real branch per release line, not merely tags on trunk. Verified directly via the GitHub API against `TYPO3/typo3`:

- Every sprint and LTS release has its own long-lived branch: `9.2`, `9.3`, `9.5`, `10.2`, `10.4`, `11.1`, `11.3`, `11.5`, `12.1`, `12.4`, `13.0`, `13.1`, `13.3`, `13.4`, `14.0`, `14.1`, `14.3`, plus historical `TYPO3_x-y` branches back to v3.6.
- A single logical fix is cherry-picked from Gerrit into `main` and every branch still under support at once. Example, a commit on the `13.4` branch dated 2026-06-29 carries the trailer `Releases: main, 14.3, 13.4` — the same change landed in three branches simultaneously.
- Sprint branches freeze the moment the next sprint ships (e.g. `13.0`'s last commit is 2024-02-17, matching the "sprint supported only until the next sprint" policy in Section 1), but the branch itself is never deleted — it just stops receiving commits.
- An LTS branch keeps receiving commits for as long as it is under regular or ELTS support; `12.4`'s last commit (2026-04-14) is a version-bump right at the edge of its documented security-support end date (2026-04-30, see table below).

_Source: `gh api repos/TYPO3/typo3/branches` and `gh api repos/TYPO3/typo3/branches/{name}` (primary source, verified 2026-07-03), [TYPO3 Core Development to Change Branch Name](https://typo3.org/article/typo3-core-development-to-change-branch-name)_

### Support concurrency

At any point, TYPO3 GmbH/Association actively supports **the current LTS and the immediately preceding LTS** with bugfixes and security fixes — i.e. two major lines in parallel, never more, via the regular (free) support channel.
_Source: [TYPO3 version support and security updates](https://docs.typo3.org/m/typo3/reference-coreapi/main/en-us/Security/Versions/Index.html)_

### Support timeline — confidence caveat, dates conflict between sources

| Version | Released | Active support ends | Security support ends | ELTS ends |
|---|---|---|---|---|
| 14 | 2025-11-25 (v14.0 sprint; 14.3 LTS shipped 2026-04-21) | 2027-12-31 | 2029-06-30 | 2032-06-30 |
| 13 | 2024-01-30 | 2026-06-30 | 2027-12-31 | 2030-12-31 |
| 12 | 2022-10-04 | 2024-10-31 | 2026-04-30 | 2029-04-30 |
| 11 | 2020-12-22 | 2023-03-31 | 2024-10-31 | 2027-10-31 |
| 10 | 2019-07-23 | 2021-10-31 | 2023-04-30 | 2026-04-30 |

_Source: [endoflife.date/typo3](https://endoflife.date/typo3)_

**Confidence caveat (medium, not high):** the exact calendar dates in this table do not fully agree across TYPO3's own sources, and this table should be re-verified against a live source before being copied into story 1-3's published matrix. Specifics found during verification:

- The "Released" date for v14 (2025-11-25) is the **v14.0 sprint release**, not the v14.3 LTS tag. TYPO3 news gives the actual v14 sprint/LTS schedule as: v14.0 = 2025-11-25, v14.1 = 2026-01-20, v14.2 = 2026-03-31, **v14.3 (LTS) = 2026-04-21**. _Source: [TYPO3 v14 Release Schedule: A Smarter Way Forward](https://news.typo3.com/archive/typo3-v14-release-schedule-a-smarter-way-forward)_
- For v13, endoflife.date states security support ends 2027-12-31, but TYPO3's own maintenance-releases page states "the v13 series (until October 31st, 2027)" for security releases — a ~2 month discrepancy between two TYPO3-adjacent sources, and the typo3.com page itself shows signs of being a stale/infrequently-updated announcement (it still describes v14.0.0 as not yet available, though it has since shipped). _Source: [TYPO3 Maintenance Release Schedule](https://typo3.com/typo3-cms/development-roadmap/maintenance-releases)_
- For v12, both sources agree: security support ends 2026-04-30 (also independently corroborated by the TYPO3 ELTS product page, which states "Free support is provided until 2026-04-30" for v12.4, and by the `12.4` branch's own last commit landing 2026-04-14 — see Section "Branch naming").
- The v9/v10/v11 rows were not independently re-verified beyond the endoflife.date aggregation; treat them as medium confidence.

Given this, the **policy mechanics** in this report (sprint-vs-LTS distinction, 18-month cadence, current+preceding-LTS concurrency, no-new-features-after-LTS) are high confidence — they are quoted directly from the official [TYPO3 version support and security updates](https://docs.typo3.org/m/typo3/reference-coreapi/main/en-us/Security/Versions/Index.html) page. The **specific calendar dates** are medium confidence and should be re-checked at the point story 1-3's matrix is actually written, not copied from this report as final.

### Extended Long Term Support (ELTS)

Once regular security support for an LTS line ends, TYPO3 GmbH offers a **paid ELTS subscription** providing security and compatibility fixes only (no features). Verified directly against the primary product page (fetched 2026-07-03): "An ELTS coverage period runs for a fixed, one-year time period, but you may book two periods (two years) or three periods (three years) at once. However, TYPO3 Partners have the exclusive option to extend coverage to a fourth year." The same page states a combined "planning horizon of up to seven years" from the LTS release date. Example given for v12.4: free support until 2026-04-30, ELTS extends it "up to four more years until 2030-04-30."

**Minor source conflict noted:** the official core-api reference docs (`docs.typo3.org`) describe ELTS more tersely as "up to three years after the regular support has ended," without mentioning the 4th Partner-only year that the commercial ELTS product page documents in detail. The product page is more specific and gives concrete per-version end dates, so this report treats it as the more current/authoritative figure for planning purposes, but the two official TYPO3 sources do not literally agree on the word "three" vs. "four."
_Source: [TYPO3 ELTS product page](https://typo3.com/products-services/extended-support-elts) (primary, fetched 2026-07-03), [TYPO3 version support and security updates](https://docs.typo3.org/m/typo3/reference-coreapi/main/en-us/Security/Versions/Index.html) (primary, fetched 2026-07-03)_

**Key mechanic to note:** the "no new features, bugfix only" guarantee in TYPO3 core is enforced by *permanent parallel git branches plus cherry-pick discipline* — every supported line (currently `main`, `14.3`, `13.4`, and `12.4` until its security support lapsed) is a real branch, and each bugfix/security fix is deliberately cherry-picked into every branch still under support, never just applied to trunk and forgotten.

---

## 2. Ecosystem Precedent: How Composer Packages Signal TYPO3 Version Support

### TYPO3 extension composer.json convention

TYPO3 extensions (which do depend on `typo3/cms-core`) signal supported core versions via composer version constraints combined with OR-ranges. Verified directly against a real, actively maintained extension's `composer.json` (`georgringer/news`, fetched 2026-07-03): `"typo3/cms-core": "^13.4.20 || ^14.0"`. The caret operator scopes each range to one major/minor line; the double-pipe lets one extension release support two TYPO3 majors at once without branching — the same "current + preceding LTS" concurrency TYPO3 core itself applies (Section 1) is mirrored one level up in the extension's own constraint.
_Source: `raw.githubusercontent.com/georgringer/news/main/composer.json` (primary source, verified 2026-07-03), [composer.json — TYPO3 Explained](https://docs.typo3.org/m/typo3/reference-coreapi/main/en-us/ExtensionArchitecture/FileStructure/ComposerJson.html)_

### TYPO3 EXT:tea — documented branching strategy for extensions

**Upgraded from medium to high confidence.** The rendered docs.typo3.org page 404'd, but its documentation source is public on GitHub; the `.rst` source file was fetched directly (`TYPO3BestPractices/tea`, `Documentation/TechnicalBackground/ReleaseBranchingStrategy.rst`, primary source, verified 2026-07-03) and confirms the two strategies verbatim:

1. **Approach 1 — one branch for multiple TYPO3 LTS versions:** "a single main branch that receives new features and updates, while supporting multiple TYPO3 LTS versions at the same time... may require some version-dependent code switches, which can increase complexity. However, the major advantage is that there is only one branch to maintain."
2. **Approach 2 — separate branch per TYPO3 LTS version:** "one main branch for each TYPO3 LTS version... version-specific code can be used without requiring version-dependent switches, reducing complexity in the codebase. However, this approach increases the maintenance burden, as any new features or updates must be applied to each branch individually."

The doc's own conclusion: "If reducing maintenance complexity is a priority, using a single branch for multiple versions is often the better choice. However, if you need to tailor your extension for each version, a separate branch for each version may be more suitable."
_Source: [TYPO3BestPractices/tea — ReleaseBranchingStrategy.rst](https://github.com/TYPO3BestPractices/tea/blob/main/Documentation/TechnicalBackground/ReleaseBranchingStrategy.rst) (primary source, verified 2026-07-03)_

### ssch/typo3-rector — directly relevant precedent (already a quality-tools dependency)

`ssch/typo3-rector` is itself a Rector-based tool package (not a TYPO3 extension loaded into a site) that ships upgrade rule sets for TYPO3 v10 through v14 **from a single `main` branch and a single package version line** — version support is expressed by which rule-set classes exist in the package, not by shipping separate git branches per TYPO3 version. Verified directly (primary source, 2026-07-03): the `main` branch's `config/` directory contains `typo3-10.php` through `typo3-14.php` and `config/level/up-to-typo3-{10..14}.php` side by side in one branch. The repository does have `1.x`/`2.x` branches, but those track the *package's own* major-version history (breaking changes to the tool itself), not per-TYPO3-version support. quality-tools already depends on this package (`ssch/typo3-rector: ^3.5`) and on `a9f/typo3-fractor` (`~0.5.1`), which follows the same model.
_Source: `gh api repos/sabbelasichon/typo3-rector/branches` and `gh api repos/sabbelasichon/typo3-rector/git/trees/main` (primary source, verified 2026-07-03), [sabbelasichon/typo3-rector](https://github.com/sabbelasichon/typo3-rector), [ssch/typo3-rector — Packagist](https://packagist.org/packages/ssch/typo3-rector)_

This is the closest structural analogy to quality-tools: a **tool package that targets multiple TYPO3 versions via internal, selectable configuration rather than via `typo3/cms-core` composer constraints or parallel branches.**

---

## 3. Current State of cpsit/quality-tools

A few facts about this package change how directly the core/extension model applies:

- **quality-tools is not a TYPO3 extension.** Its `composer.json` has no `typo3/cms-core` requirement and no `ext_emconf.php` — it is a standalone composer dev-tool that is installed alongside a TYPO3 project's `vendor/`, not loaded into the TYPO3 runtime. So the classic "extension composer.json constraint against core" mechanism (e.g. `^13.4.20 || ^14.0`, Section 2) has no direct equivalent here.
- **"TYPO3-version-awareness" already lives at the config level, not the dependency level.** The package ships `config/rector.php`, `config/fractor.php`, `config/phpstan.neon`, etc. Compatibility with a given TYPO3 version is a function of (a) which rule sets `ssch/typo3-rector`/`a9f/typo3-fractor` expose for that version, and (b) which config file quality-tools ships/selects.
- **Story 1-1 (`add-versioned-rector-configurations-for-typo3-v13-and-v14`, ready-for-dev)** already establishes the pattern of shipping version-specific config variants inside the same package/branch — i.e. the project has already implicitly chosen the "single branch, multiple config variants" direction for the Rector config specifically, consistent with the `ssch/typo3-rector` precedent above.
- **Story 1-3 (publish TYPO3 version support matrix, GL#16)** and **story 1-4 (establish LTS/ELTS bugfix branch strategy, GL#17)** are both still backlog and are where this research's conclusions apply directly.

---

## 4. Adaptation Options for quality-tools

### Option A — Trunk-only, versioned config files (extrapolates story 1-1)

One `develop`/`main` line. All currently-supported TYPO3 versions' configs (`rector-v13.php`, `rector-v14.php`, etc.) ship together in every quality-tools release. A documented support matrix (story 1-3) states which quality-tools version pairs with which TYPO3 versions. No new features means "no new tool integrations or breaking config changes" gated by normal semver, not by branch.

- **Pros:** Lowest maintenance overhead (no branch/cherry-pick bookkeeping); mirrors the `ssch/typo3-rector` precedent already in the dependency tree; fastest to ship.
- **Cons:** Cannot truly freeze one TYPO3 version's support at "bugfix only" while shipping new features for another — a single release line carries all versions forward together. Does not literally satisfy rationale (3) ("support older versions with bugfixes, no new features") once TYPO3 v13 supersedes v12's freshness, because there is nothing to "freeze."

### Option B — TYPO3-core-style parallel maintenance lines

Mirror TYPO3 core more literally: quality-tools majors are tied to TYPO3 majors. When TYPO3 v15 lands, quality-tools cuts a new major (e.g. `4.0`) targeting v14+v15, and the previous major (`3.x`, which targeted v13+v14) is frozen — no new features, only backported bugfixes/security fixes, tagged as `3.x.y` patch releases, for a defined support window. Developers pin via composer (`cpsit/quality-tools: ^3.0` vs `^4.0`).

- **Pros:** Directly satisfies rationale (1)-(3): explicit, composer-enforceable version pairing; genuine bugfix-only freeze for the old line, matching the LTS mental model TYPO3 users already know.
- **Cons:** Highest overhead — requires an actual maintenance branch, backport/cherry-pick discipline, and a defined support-window policy (how long is "3.x" maintained after "4.x" ships?) for a package this size. Requires resourcing decision.

### Option C — Hybrid: trunk-based development + support matrix + on-demand maintenance branch (recommended)

Keep single-branch, versioned-config development (as in Option A / story 1-1) as the default. Additionally:

1. Publish and maintain an explicit **support matrix** (story 1-3): a table of quality-tools version <-> supported TYPO3 version(s), following the "current + preceding LTS" concurrency pattern TYPO3 core itself uses (never chase more than 2 TYPO3 majors at once).
2. Express the pairing in `composer.json` via a documented convention even without a `typo3/cms-core` dependency — e.g. a `extra.typo3/cms-core-versions` metadata block or a clearly labeled composer branch-alias/tag scheme (`^2.x` = "targets TYPO3 v13/v14"), so developers can select the matching quality-tools version the same way they would read an extension's constraint.
3. **Only cut a maintenance branch when a TYPO3 version actually drops out of the matrix** (i.e. at the same cadence TYPO3 core retires an LTS): tag the last commit still supporting the outgoing TYPO3 version, branch it (e.g. `support/typo3-v12`), and backport bugfixes there for a bounded window (proposal: mirror TYPO3's own "current + preceding" free-support window, not the multi-year ELTS window, since quality-tools has no paid-support program). New features only ever land on the trunk/newest line.

- **Pros:** Matches rationale (1)-(3) as closely as Option B for the versions that matter most (recent, actively used ones), without carrying permanent branch-maintenance cost for the whole package lifetime. Note this is a deliberately **lighter-weight approximation** of what TYPO3 core actually does (Section 1: a real, permanently-branched line per supported version with continuous cherry-picking) — Option C only creates a branch reactively, at the point a version is deprecated, rather than maintaining one proactively for every supported version from day one. That trade-off is appropriate for a package this size, but it is a conscious downgrade from core's model, not an equivalent of it.
- **Cons:** Requires a documented, disciplined process for *when* to cut a maintenance branch and how long to keep backporting to it — this must be written down (story 1-4) so it doesn't rely on tribal knowledge. Unlike core's model, a fix landing after a version has already been "let go" cannot be trivially cherry-picked into a branch that was never created for it.

---

## 5. Recommendation

**Adopt Option C.** It extends the direction story 1-1 has already started (versioned configs on trunk), adds the explicit compatibility signaling the user asked for via story 1-3, and satisfies the "bugfix-only for older versions" requirement via story 1-4 without taking on TYPO3-core-scale branch-maintenance overhead that this package's size doesn't warrant. It also stays consistent with how the package's own closest dependencies (`ssch/typo3-rector`, `a9f/typo3-fractor`) already operate.

**Concrete next steps, mapped to existing backlog:**

- **Story 1-3 (support matrix):** Publish a table (README + `docs/`) of quality-tools version <-> supported TYPO3 version(s), scoped to "current + preceding TYPO3 LTS" at any time, refreshed whenever a new TYPO3 LTS ships.
- **Story 1-4 (branch strategy):** Write the policy for *when* a `support/typo3-vNN` maintenance branch is cut (recommend: at the point a TYPO3 version falls out of the 2-version window above), how long it receives backports (recommend: until that TYPO3 version's own regular security support ends, per the endoflife.date table in Section 1 — not the multi-year ELTS window), and how composer users pin to it.
- Per this project's CLAUDE.md convention, the chosen strategy should be captured as an **ADR** in `docs/architecture/` once agreed, since it is a system-wide, hard-to-reverse-later decision.

**Open questions for the team (not resolvable by research alone):**

- Does cpsit have the capacity to actually backport bugfixes to a frozen `support/typo3-vNN` branch when needed, or should Option A (no freeze guarantee) be accepted as a pragmatic simplification given team size?
- Should the quality-tools major version number track TYPO3 major versions 1:1, or stay independent (semver driven by quality-tools' own breaking changes) with the matrix as the only linkage? The research found no requirement either way — this is a project preference.

---

## Sources

**Primary sources fetched/queried directly (highest confidence):**

- `gh api repos/TYPO3/typo3/branches` and `repos/TYPO3/typo3/branches/{name}`, plus `Typo3Version.php` content on `main`/`14.3`/`13.4`/`12.4` — verified 2026-07-03 (branch list, last-commit dates, dev-version strings; Section 1)
- `docs.typo3.org/m/typo3/reference-coreapi/main/en-us/Security/Versions/Index.html` fetched and parsed directly (not just search snippet) — verified 2026-07-03 (sprint/LTS policy quotes, current+preceding concurrency; Section 1)
- `typo3.com/products-services/extended-support-elts` fetched and parsed directly — verified 2026-07-03 (ELTS 1-year-increment/4th-Partner-year/7-year-horizon quotes; Section 1)
- `typo3.org/article/typo3-core-development-to-change-branch-name` fetched and parsed directly — verified 2026-07-03 (master-to-main rename date, 2021-11-29)
- `raw.githubusercontent.com/georgringer/news/main/composer.json` — verified 2026-07-03 (real-world `^13.4.20 || ^14.0` constraint; Section 2)
- `github.com/TYPO3BestPractices/tea` — `.rst` doc source fetched directly via `gh api` — verified 2026-07-03 (two branching-strategy approaches, verbatim; Section 2)
- `gh api repos/sabbelasichon/typo3-rector/branches` and `.../git/trees/main` — verified 2026-07-03 (multi-version config layout; Section 2)
- Local `composer.json` and directory listing of this repository — verified 2026-07-03 (no `typo3/cms-core` dependency, no `ext_emconf.php`; Section 3)

**Secondary sources (used for cross-reference, cadence, or context; see confidence caveats inline where a conflict was found):**

- [endoflife.date/typo3](https://endoflife.date/typo3) — aggregator; support-timeline table in Section 1 has a noted date conflict for v13 vs. the next source
- [TYPO3 Maintenance Release Schedule](https://typo3.com/typo3-cms/development-roadmap/maintenance-releases) — appears stale (still describes v14.0.0 as unreleased); used only to flag the v13 date conflict, not as a standalone fact
- [TYPO3 v14 Release Schedule: A Smarter Way Forward](https://news.typo3.com/archive/typo3-v14-release-schedule-a-smarter-way-forward) — used to distinguish the v14.0 sprint date from the v14.3 LTS date (2026-04-21)
- [TYPO3 version support and security updates (13.4 doc variant)](https://docs.typo3.org/m/typo3/reference-coreapi/13.4/en-us/Security/Versions/Index.html)
- [composer.json — TYPO3 Explained](https://docs.typo3.org/m/typo3/reference-coreapi/main/en-us/ExtensionArchitecture/FileStructure/ComposerJson.html)
- [sabbelasichon/typo3-rector (GitHub)](https://github.com/sabbelasichon/typo3-rector), [ssch/typo3-rector (Packagist)](https://packagist.org/packages/ssch/typo3-rector)

# [ADR-0006] TYPO3-Aligned Trunk-Based Versioning and Branching Strategy

## Status

Accepted

## Context

quality-tools ships TYPO3-version-specific configuration (Rector, Fractor rule sets, etc.) and depends on tools (`ssch/typo3-rector`, `a9f/typo3-fractor`) that themselves evolve per TYPO3 major version. Story 1-1 already introduced versioned Rector configs for TYPO3 v13 and v14. Two backlog items depend on a clear policy:

- Story 1-3: publish a TYPO3 version support matrix (GL#16)
- Story 1-4: establish an LTS/ELTS-style bugfix branch strategy (GL#17 / issue 79373)

The underlying problem, as stated in issue 79373: it must be clear which TYPO3 version a given quality-tools release targets, developers must be able to select the matching quality-tools version through a normal Composer constraint, and older, still-supported TYPO3 versions must keep receiving bugfixes without picking up new features meant for newer TYPO3 versions.

A dedicated research pass (`docs/../_bmad-output/planning-artifacts/research/technical-typo3-core-branching-versioning-strategy-research-2026-07-03.md`) investigated how TYPO3 core itself solves this, and found:

- TYPO3 core maintains a real, persistent branch per release line (e.g. `12.4`, `13.4`, `14.3`), and cherry-picks every bugfix into every branch still under support at once. Only the current and the immediately preceding LTS line receive free support concurrently.
- TYPO3 extensions signal supported core versions through Composer OR-ranges, e.g. `"typo3/cms-core": "^13.4.20 || ^14.0"` (verified against `georgringer/news`).
- quality-tools is not a TYPO3 extension: it has no `typo3/cms-core` dependency and no `ext_emconf.php`, so that constraint mechanism has no direct equivalent. Its closest structural precedent is `ssch/typo3-rector`, which supports TYPO3 v10-v14 from a single `main` branch through internal, version-specific configuration rather than parallel git branches.

Maintaining a permanently branched line per TYPO3 major version, proactively, the way TYPO3 core does, was judged disproportionate to the size and maintenance capacity of this package. A lighter-weight model was needed that still gives a genuine bugfix-only guarantee for older versions.

## Decision

We will use trunk-based development with on-demand support branches, and align quality-tools' own major version number to the TYPO3 major version it targets.

Specifically:

1. **Trunk-based development.** Ongoing development, including the configuration for the currently-targeted TYPO3 major version, happens on `main`. New features are only ever added on `main`.
2. **Versioning parallel to TYPO3 major versions.** The quality-tools major version number tracks the TYPO3 major version it is built for (e.g. quality-tools `13.x` targets TYPO3 v13, `14.x` targets TYPO3 v14). The first version built under this scheme is **13.0**, replacing the pre-1.0 `0.x` line; it is a deliberate jump, not a continuation of ordinary SemVer progression, and must be called out explicitly in the changelog and README so consumers do not mistake it for 13 prior breaking releases.
3. **On-demand support branches for bugfixes.** When `main` moves on to target a new TYPO3 major version, a support branch for the outgoing major version is **cut only when a bugfix for it is actually needed** (reactive, not proactive). The support branch receives bugfix tags in its own line (e.g. `13.0.1`, `13.1.0`) and never receives new features.

## Consequences

### Positive Consequences
- Developers can select the quality-tools version matching their TYPO3 installation the same way they would read a Composer constraint on an extension, without quality-tools needing a `typo3/cms-core` dependency.
- Older TYPO3 versions genuinely keep receiving bugfix-only releases once a support branch exists for them, satisfying the rationale behind issue 79373.
- No branch-maintenance overhead is paid for TYPO3 versions that never need a backport after `main` moves on.
- The model mirrors a real-world precedent already in the dependency tree (`ssch/typo3-rector`'s trunk-based approach) for the common case, and TYPO3 core's own "freeze and backport" mechanic for the exceptional case.

### Negative Consequences
- The version jump to 13.0 breaks ordinary SemVer expectations and must be documented clearly to avoid confusing Composer consumers.
- Because support branches are cut reactively, there is no branch ready in advance; the first bugfix request for an outgoing version requires someone to cut the branch under time pressure. This must be a documented, repeatable process (story 1-4), not tribal knowledge.
- Unlike TYPO3 core's proactive model, a fix cannot be trivially cherry-picked into a branch that does not exist yet; cutting it correctly (from the right commit) requires care.

### Neutral Consequences
- quality-tools' major version number no longer reflects the number of breaking changes to quality-tools itself, only which TYPO3 major it targets. This is an intentional trade-off, not a defect, but should be stated plainly in developer-facing documentation.

## Alternatives Considered

### Alternative 1: Trunk-only, versioned config files, no branch freeze (Option A in the research)
All currently supported TYPO3 versions' configs ship together in every release, on `main` only. Lowest maintenance overhead, but there is no way to freeze one TYPO3 version's support at "bugfix only" while `main` keeps moving forward for another. Rejected because it does not satisfy the "bugfix-only for older versions" requirement in issue 79373.

### Alternative 2: TYPO3-core-style proactive parallel maintenance branches (Option B in the research)
Cut a permanent branch for every supported TYPO3 major as soon as it is supported, mirroring TYPO3 core exactly, with continuous cherry-picking into all of them. Gives the strongest guarantees but requires permanent branch/cherry-pick discipline for every supported version at all times. Rejected as disproportionate maintenance overhead for a package of this size; the on-demand variant (this decision) gives the same guarantee only where it is actually used.

## Implementation Notes

- **Pending infrastructure action:** at the time of this decision, the repository's trunk branch was `develop` (with `master` as a separate branch); no `main` branch existed. Adopting this decision requires renaming `develop` to `main` (mirroring TYPO3 core's own 2021 master-to-main rename), including the GitLab default branch, branch protection rules, and CI configuration. This is a deliberate repository-administration action for the team to carry out, not something assumed to already be in place.
- Story 1-3 publishes the actual, current TYPO3-version support matrix (which quality-tools version(s) currently target which TYPO3 version(s)).
- Story 1-4 formalizes the exact process and naming convention for cutting a support branch (proposed: branch named after the major version, e.g. `13.x`), and how long it continues to receive backports.
- `composer.json` and the README must clearly explain the 0.2.0 -> 13.0.0 version jump before the 13.0 release ships.

## References

- Research: `_bmad-output/planning-artifacts/research/technical-typo3-core-branching-versioning-strategy-research-2026-07-03.md`
- Redmine issue: https://frs.plan.io/issues/79373 (GL#17)
- Story 1-3: publish TYPO3 version support matrix (GL#16)
- Story 1-4: establish LTS/ELTS bugfix branch strategy (GL#17)

# Versioning and Branching Strategy

This describes how quality-tools is versioned and branched across TYPO3 major versions. See [ADR-0006](../architecture/0006-typo3-aligned-trunk-based-versioning.md) for the full context and rationale, and the [user-facing summary](../user-guide/versioning.md) for how consumers select a version.

## Trunk-Based Development

All ongoing development happens on `main`. New features and new TYPO3-version support are only ever added on `main`. There is no permanent, proactively-maintained branch per TYPO3 version.

> **Pending infrastructure action:** at the time this decision was made, this repository's trunk branch was `develop` (with `master` as a separate branch); no `main` branch existed yet. Adopting this strategy requires renaming `develop` to `main` (mirroring TYPO3 core's own master-to-main rename, see the linked research), including updating the GitLab default branch, branch protection rules, and CI configuration. That rename is a repository-administration action to be carried out deliberately by the team, not assumed to have already happened.

## Version Numbers Track TYPO3 Major Versions

The quality-tools major version equals the TYPO3 major version it targets:

- `13.x` targets TYPO3 v13
- `14.x` targets TYPO3 v14

The first release under this scheme is **13.0**, replacing the pre-1.0 `0.x` line. This is a one-time, deliberate jump - not 13 prior breaking releases - and must be called out in `CHANGELOG.md` and the README so Composer consumers are not misled by ordinary SemVer expectations.

When `main` starts targeting a new TYPO3 major version, that becomes the next quality-tools major release (e.g. `main` moving from targeting v13 to v14 ships as `14.0`).

## Support Branches Are Cut On Demand

Support branches are **reactive, not proactive**:

1. `main` moves on to target a new TYPO3 major version.
2. If and when a bugfix is needed for the TYPO3 version `main` no longer targets, a support branch is cut from the last commit on `main` that still supported it (proposed naming: `<major>.x`, e.g. `13.x`).
3. Bugfixes are backported/cherry-picked into that branch and released as tags within its own line (e.g. `13.0.1`, `13.1.0`).
4. New features are never added to a support branch. If a change doesn't fit "bugfix," it belongs on `main` for the next major instead.

No branch is created preemptively "just in case" - if a TYPO3 version's config never needs a fix after `main` moves on, no branch is ever cut for it. This trades TYPO3 core's stronger, always-ready guarantee for significantly lower standing maintenance cost, which was judged the right trade-off for this package's size (see ADR-0006, "Alternatives Considered").

## Why Not Mirror TYPO3 Core Exactly

TYPO3 core keeps a permanent branch per release line and cherry-picks every fix into all of them continuously. quality-tools' closest dependency, `ssch/typo3-rector`, instead supports multiple TYPO3 versions from a single `main` branch via internal configuration. quality-tools' strategy sits between the two: single-branch development like `ssch/typo3-rector` for the common case, with a TYPO3-core-style frozen branch cut only when actually needed for an older line.

## Related Work

- Story 1-3: publish the current TYPO3 version support matrix
- Story 1-4: formalize the exact support-branch cutting process and naming convention
- Full research behind this decision: `_bmad-output/planning-artifacts/research/technical-typo3-core-branching-versioning-strategy-research-2026-07-03.md`

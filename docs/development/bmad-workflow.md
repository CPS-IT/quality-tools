# BMAD Workflow Integration

This document describes how BMAD planning artifacts relate to the issue trackers and local planning docs, and what to do at each BMAD workflow step.

## Artifact Map

| BMAD Artifact | Location | Relation to Trackers |
|---------------|----------|---------------------|
| PRD | `_bmad-output/planning-artifacts/prd.md` | References no tracker numbers; describes requirements only |
| Architecture | `_bmad-output/planning-artifacts/architecture.md` | References ADRs in `docs/architecture/` |
| Epics | `_bmad-output/planning-artifacts/epics.md` | References local doc paths and open issue numbers where applicable |
| Stories (per sprint) | `_bmad-output/stories/` | One file per story; must reference GL#/GH# and local doc |
| Sprint plan | `_bmad-output/planning-artifacts/sprint-plan.md` | References epic numbers and story files |

## Before Running BMAD Sprint Planning

1. Run the sync health check from `docs/development/issue-tracker-sync.md`.
2. Ensure all open GitHub issues are triaged (local doc exists, or explicit deferral).
3. Verify that open local docs for the planned epics have GL issue numbers. Create missing ones if needed.
4. Read `_bmad-output/planning-artifacts/epics.md` to confirm epic prerequisites are still current.

## After Sprint Planning: Create GitLab Issues for Sprint Stories

BMAD story files are developer-internal implementation specs. They are too detailed and technical
to use as GitLab issue descriptions directly. However, non-developers need visibility into which
concrete tasks are in progress within a sprint. The solution is to create one slim GitLab issue
per story when the sprint scope is confirmed.

**When to do this:** Immediately after `bmad-sprint-planning` produces the sprint scope, before
`bmad-create-story` is run for those stories.

**For each story selected for the sprint:**

1. Create a GitLab issue with the story title as the issue title.
2. Set the milestone to the corresponding epic milestone (see milestone list below).
3. Add to the issue description: the story's acceptance criteria and the path to the story file
   (e.g. `_bmad-output/stories/STORY-ID.md`).
4. If the story is user-visible (new command, new config option, observable behaviour change),
   also create a GitHub issue and link both trackers.
5. Note the GL# (and GH# if applicable) as a comment on the story line in
   `_bmad-output/implementation-artifacts/sprint-status.yaml`.

**What not to do:** Do not create GitLab issues for all stories in all epics upfront. Create
issues only for stories that enter an active sprint. Unmapped stories in future epics stay as
entries in `epics.md` until they are planned.

**Note (2026-05-12):** All sprint stories across Epics 1-9 have been pre-created as GitLab issues
(GL#14-39). The sprint-status.yaml already contains GL# references for every story. When a future
epic is planned, create issues for those stories at that time following the steps above.

**GitLab Milestones (Epic mapping):**

| GL Milestone | Epic | Stories |
|-------------|------|---------|
| [Epic 1: TYPO3 v14 Compatibility](https://gitlab.321.works/DevOps/testing/quality-tools/-/milestones/1) | Epic 1 | GL#14-17 |
| [Epic 2: Platform Stability and Defect Resolution](https://gitlab.321.works/DevOps/testing/quality-tools/-/milestones/2) | Epic 2 | GL#9, 13, 18, 19 |
| [Epic 3: Machine-Readable Report Generation](https://gitlab.321.works/DevOps/testing/quality-tools/-/milestones/3) | Epic 3 | GL#8, 20-26 |
| [Epic 4: Human-Readable Quality Reports](https://gitlab.321.works/DevOps/testing/quality-tools/-/milestones/4) | Epic 4 | GL#27-28 |
| [Epic 5: Reliable CI/CD Quality Gates](https://gitlab.321.works/DevOps/testing/quality-tools/-/milestones/5) | Epic 5 | GL#10, 29 |
| [Epic 6: Enhanced Configuration and Schema Validation](https://gitlab.321.works/DevOps/testing/quality-tools/-/milestones/6) | Epic 6 | GL#3, 11, 30-31 |
| [Epic 7: EditorConfig Integration](https://gitlab.321.works/DevOps/testing/quality-tools/-/milestones/7) | Epic 7 | GL#4, 12, 32 |
| [Epic 8: Unified Quality Commands](https://gitlab.321.works/DevOps/testing/quality-tools/-/milestones/8) | Epic 8 | GL#33-34 |
| [Epic 9: Cross-Project Quality Dashboard](https://gitlab.321.works/DevOps/testing/quality-tools/-/milestones/9) | Epic 9 | GL#35-39 |

## During BMAD Story Creation

When the `bmad-create-story` skill creates a story file, the story must include:

```markdown
## Tracker References
- GitLab: [GL#N](url)
- GitHub: [GH#N](url)   <- include only if a public GH issue exists
- Planning doc: `docs/plan/issue/NNN-title.md` or `docs/plan/feature/NNN-title.md`
```

If the story addresses a new capability with no existing tracker issue, create the GitLab (and if
user-visible, GitHub) issue first following the steps above, then reference it in the story file.

## Epic Prerequisites (Current)

BMAD Epic 2 (Prerequisites) must be completed before Epics 3, 5, 6:

| Prerequisite | Local doc | GL# | GH# | Status |
|-------------|-----------|-----|-----|--------|
| Fix PathResolutionService TypeError | `docs/plan/issue/025-...md` | GL#9 | GH#7 | Open |
| Resolve DI configuration inconsistency | `docs/plan/issue/done/020-...md` | GL#19 | - | Open (story 2.3) |
| Triage all open GL/GH issues | `tmp/gitlab-sync-overview.md` | GL#18 | - | Open (story 2.1) |

## After BMAD Story Implementation

When a story is implemented and the MR merged:

1. Move the local planning doc to `done/` (if not already there)
2. Close the GitLab issue with reference to the MR
3. Close the GitHub issue (if one exists) with reference to the commit / release
4. Update the story file status to `completed`
5. Update `_bmad-output/planning-artifacts/epics.md` if the story completes an epic

## Retrospective Inputs

At the end of each sprint, the BMAD retrospective (`bmad-retrospective` skill) should include:

- Which GL/GH issues were closed
- Whether any issues were found that had no tracker entry (add them retroactively)
- Whether any tracker issues are stale (open >60 days with no activity -- add a comment or close as "won't fix")

## Issue Number Reference (as of 2026-05-12)

### Open GitLab Issues

| GL# | Title | Local doc / Story | GH# | Milestone |
|----:|-------|-------------------|----:|-----------|
| #3 | Lint Composer within the bundle itself | `docs/plan/issue/027-...md` | GH#3 | Epic 6 |
| #4 | qt should lint xlf-Files | `docs/plan/feature/034-...md` | GH#4 | Epic 7 |
| #7 | typoscript-lint.yaml extension not recognized | `docs/plan/issue/026-...md` | - | Epic 2 |
| #8 | Support more Parameter (pass-through) | `docs/plan/issue/028-...md` | - | Epic 3 |
| #9 | PathResolutionService TypeError | `docs/plan/issue/025-...md` | GH#7 | Epic 2 |
| #10 | Fail-on-warnings configuration | `docs/plan/feature/026-...md` | GH#8 | Epic 5 |
| #11 | Enhanced schema validation | `docs/plan/feature/031-...md` | - | Epic 6 |
| #12 | EditorConfig CLI integration | `docs/plan/feature/032-...md` | GH#9 | Epic 7 |
| #13 | Comprehensive security test suite | `docs/plan/feature/033-...md` | - | Epic 2 |
| #14 | Story 1.1: Add versioned Rector configs for TYPO3 v13 and v14 | Story 1.1 | - | Epic 1 |
| #15 | Story 1.2: Add versioned Fractor configs for TYPO3 v13 and v14 | Story 1.2 | - | Epic 1 |
| #16 | Story 1.3: Publish TYPO3 version support matrix | Story 1.3 | - | Epic 1 |
| #17 | Story 1.4: Establish LTS/ELTS bugfix branch strategy | Story 1.4 | - | Epic 1 |
| #18 | Story 2.1: Triage all open GitLab issues | Story 2.1 | - | Epic 2 |
| #19 | Story 2.3: Resolve DI configuration inconsistency (Issue 020) | Story 2.3 | - | Epic 2 |
| #20 | Story 3.1: Extend ToolRunResult with a typed findings collection | Story 3.1 | - | Epic 3 |
| #21 | Story 3.2: Research and align on shared contracts package | Story 3.2 | - | Epic 3 |
| #22 | Story 3.4: Implement dual-output mode and Finding DTOs for PHPStan | Story 3.4 | - | Epic 3 |
| #23 | Story 3.5: Implement dual-output mode and Finding DTOs for Rector | Story 3.5 | - | Epic 3 |
| #24 | Story 3.6: Dual-output mode for PHP CS Fixer, Fractor, TypoScript Lint | Story 3.6 | - | Epic 3 |
| #25 | Story 3.7: Implement ReportWriter and JSON report output | Story 3.7 | - | Epic 3 |
| #26 | Story 3.8: Publish JSON report schema documentation | Story 3.8 | - | Epic 3 |
| #27 | Story 4.1: Implement Markdown report output | Story 4.1 | - | Epic 4 |
| #28 | Story 4.2: Implement HTML report output | Story 4.2 | - | Epic 4 |
| #29 | Story 5.1: Implement warning detection in all existing runners | Story 5.1 | - | Epic 5 |
| #30 | Story 6.1: Enhanced config:validate error messages | Story 6.1 | - | Epic 6 |
| #31 | Story 6.2: Additional scan paths and tool-specific path overrides | Story 6.2 | - | Epic 6 |
| #32 | Story 7.2: Auto-provision .editorconfig template on qt config:init | Story 7.2 | - | Epic 7 |
| #33 | Story 8.1: Implement qt lint and qt fix unified commands | Story 8.1 | - | Epic 8 |
| #34 | Story 8.2: Runner enable/disable and execution order configuration | Story 8.2 | - | Epic 8 |
| #35 | Story 9.1: Aggregation service -- report ingestion endpoint | Story 9.1 | - | Epic 9 |
| #36 | Story 9.2: Per-project quality status storage and trend calculation | Story 9.2 | - | Epic 9 |
| #37 | Story 9.3: Cross-project dashboard -- tech lead view | Story 9.3 | - | Epic 9 |
| #38 | Story 9.4: Non-technical summary view for product owners | Story 9.4 | - | Epic 9 |
| #39 | Story 9.5: Automated issue creation from report thresholds | Story 9.5 | - | Epic 9 |

### Closed GitLab Issues

| GL# | Title | Resolved by |
|----:|-------|-------------|
| #1 | Configuration Overwrites (Feature 015) | GH PR#2 (merged 2026-03-12) |
| #5 | Configuration File Replacement Schema Validation | GH PR#6 (merged 2026-03-01) |

### Open GitHub Issues

| GH# | Title | GL# |
|----:|-------|----:|
| #3 | Lint Composer within the bundle itself | GL#3 |
| #4 | qt should lint xlf-Files | GL#4 |
| #7 | PathResolutionService TypeError | GL#9 |
| #8 | Fail-on-warnings configuration | GL#10 |
| #9 | EditorConfig CLI integration | GL#12 |

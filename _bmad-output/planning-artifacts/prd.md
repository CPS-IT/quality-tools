---
stepsCompleted:
  - step-01-init
  - step-02-discovery
  - step-02b-vision
  - step-02c-executive-summary
  - step-03-success
  - step-04-journeys
  - step-05-domain
  - step-06-innovation
  - step-07-project-type
  - step-08-scoping
  - step-09-functional
  - step-10-nonfunctional
  - step-11-polish
  - step-12-complete
workflowStatus: complete
completedAt: 2026-04-22
lastEdited: 2026-04-22
editHistory:
  - date: 2026-04-22
    changes: >
      Post-validation fixes: removed implementation leakage from NFR05-07,
      replaced subjective adjectives in FR07/FR09/FR17, added FR39/FR40/FR41,
      added measurement methods to NFR01-04, added out-of-scope section.
      Edit workflow: NFR09 size threshold, NFR16 versioning semantics,
      FR14 full hierarchy documented, FR38 journey reference, Project
      Classification folded into Executive Summary, section split.
  - date: 2026-04-22
    changes: >
      Leakage cleanup: FR30 GitLab replaced with generic issue tracker,
      NFR12 vfsStream replaced with virtual filesystem abstraction.
classification:
  projectType: developer_tool
  domain: general
  complexity: medium
  projectContext: brownfield
inputDocuments:
  - _bmad-output/project-context.md
  - CLAUDE.md
  - docs/plan/goal.md
  - docs/plan/index.md
  - docs/plan/001-mvp.md
  - docs/plan/002-configuration.md
  - docs/plan/003-reporting.md
  - docs/plan/feature/026-fail-on-warnings-configuration.md
  - docs/plan/feature/031-enhanced-schema-validation.md
  - docs/plan/feature/032-editorconfig-integration.md
  - docs/plan/feature/033-comprehensive-security-test-suite.md
  - docs/plan/feature/005-report-format-research-and-standards.md
  - docs/plan/feature/006-implement-basics-for-report-generation.md
  - docs/plan/feature/007-json-report-generation.md
  - docs/plan/feature/012-human-readable-reports.md
  - docs/plan/issue/025-path-resolution-service-tool-paths-structure.md
  - docs/architecture/README.md
  - docs/architecture/0001-context-aware-security-validation.md
  - docs/architecture/0002-security-at-entry-points.md
  - docs/architecture/0003-code-duplication-elimination-through-refactoring.md
  - docs/architecture/0004-inheritance-based-security-propagation.md
  - docs/architecture/0005-simplified-command-architecture.md
workflowType: 'prd'
documentCounts:
  briefs: 0
  research: 1
  brainstorming: 0
  projectDocs: 21
---

# Product Requirements Document - quality-tools

**Author:** QT Team
**Date:** 2026-04-22

## Executive Summary

`cpsit/quality-tools` is a Composer package serving as the company's unified quality assurance standard for PHP/TYPO3 projects. It solves two coupled problems: the absence of a shared quality baseline across projects, and the cognitive overhead that prevents individual developers from adopting powerful tools like Rector, PHPStan, or Fractor.

The package ships pre-configured, production-tested configurations for all major PHP/TYPO3 quality tools and exposes them through a single CLI binary (`qt`) with a consistent `lint:<tool>` / `fix:<tool>` interface. A developer can run `qt lint:rector` on their first day without knowing what Rector is, how it is configured, or where its config file lives. The same commands work in CI pipelines as quality gates.

The longer-term trajectory extends beyond local developer ergonomics: machine-readable reports enable cross-project quality dashboards, automated ticket and merge request creation, and AI-assisted remediation. The package is designed to grow into the company's quality intelligence layer — making overall project health visible and actionable at scale.

Target users are PHP/TYPO3 development teams within the company. External or community adoption is not a goal.

### What Makes This Special

Tool adoption fails at the configuration and interface layer, not at the capability layer. The tools exist; the friction is knowing which to run, how to configure them for TYPO3, and how to interpret their output. `cpsit/quality-tools` removes that friction at the company level: one package installs, one command runs, one configuration enforces the standard.

The `lint` / `fix` symmetry — every analysis command has a corresponding fix command — creates a low-friction feedback loop that makes fixing as easy as identifying. A future unified `qt lint` / `qt fix` collapses the entire quality workflow into a single invocation, positioning the tool as a coherent quality workflow rather than a collection of shortcuts.

**Project context:** Developer tool (Composer package with CLI binary) — internal PHP/TYPO3 ecosystem, medium complexity. Brownfield: MVP delivered at v0.2.0 (10 tool commands, 96.91% test coverage); active post-MVP iteration backlog.

## Success Criteria

### User Success

- A developer on a new TYPO3 v13 project can run their first quality check within 5 minutes of adding `cpsit/quality-tools` — no tool-specific knowledge required.
- Every linting tool has a corresponding fix command (`qt lint:<tool>` -> `qt fix:<tool>`), eliminating the need to look up individual tool invocations.
- A unified `qt lint` / `qt fix` command applies all configured linters and fixers in a single invocation.
- CI pipeline integration requires no additional configuration beyond what is used in local development.

### Business Success

- All new PHP/TYPO3 projects based on TYPO3 v13 adopt `qt` as the mandatory quality tool from project start, aligned with the pending company-wide quality standard.
- Existing projects can opt in without breaking changes or mandatory migration effort.
- A cross-project quality dashboard is available as soon as mandatory adoption begins, showing per-project status and quality trend (improving / degrading) over time.
- A frontend equivalent package (JS/TS/CSS) follows as the next tool in the company quality standard.
- Configurations and tool rules for each stable TYPO3 release are available promptly at or before release; new major versions are supported early to surface deprecations before teams upgrade production systems.
- Older TYPO3 versions (LTS, ELTS) receive bug fixes for the duration of their support lifetime without breaking changes.

### Technical Success

- All linting commands pass with zero errors and zero warnings before any commit (enforced locally and in CI).
- Test coverage at or above 95% line coverage; any reduction requires explicit justification.
- PHPStan level 6 passes with zero issues.
- The package passes all its own quality checks — it is an exemplar of the standards it enforces.
- Machine-readable report output is structured and stable for downstream consumers (dashboards, ticket creation, AI tooling) without post-processing.
- A published TYPO3 version support matrix documents supported versions, PHP version floor, and support type per release.
- Rector and Fractor rule sets are versioned per TYPO3 target (e.g. `typo3-13`, `typo3-14`), enabling migration analysis against a new version without affecting production configuration.

### Measurable Outcomes

- CI pipeline: all quality gates green = pass; any linter failure = pipeline fails (phased rollout: non-blocking during adoption, mandatory thereafter).
- Dashboard: per-project quality status visible within one sprint of mandatory adoption going live; trend data visible after two consecutive report cycles.
- Adoption: 100% of new TYPO3 v13 projects use `qt` within the first project cycle after the company quality standard is published.

## User Journeys

### Journey 1: Developer — Pre-Commit Quality Check (Happy Path)

Lars is a PHP developer three hours into a refactoring task on a TYPO3 v13 project. He has restructured several service classes and wants to make sure the changes are clean before pushing. He runs `qt lint` — a single command that runs all configured linters in sequence. Two Rector suggestions appear; he runs `qt fix:rector` and the changes are applied automatically. PHPStan reports one type error he missed; he fixes it manually and re-runs `qt lint:phpstan` to confirm. Green across the board. He commits and pushes, confident the CI pipeline will pass.

**What this journey requires:** unified `qt lint` command, per-tool `lint`/`fix` symmetry, clear pass/fail output, fast local feedback loop, predictable exit codes.

---

### Journey 2: Developer — Encountering a Linter She Doesn't Know (Edge Case)

Mira joins a project mid-sprint. She runs `qt lint` as part of her onboarding and sees a Fractor warning about a deprecated TypoScript syntax. She has never used Fractor before. She runs `qt fix:fractor` without looking up what Fractor is or how it works. The issue is resolved. She moves on. Later, when a colleague asks how she handled it, she realizes she never needed to learn the tool — only the workflow.

**What this journey requires:** zero-configuration adoption, `fix` command mirrors `lint` command exactly, no tool-specific knowledge required to operate, helpful but non-verbose output.

---

### Journey 3: DevOps Engineer — CI Pipeline Quality Gate

Tobias is setting up the GitLab CI pipeline for a new TYPO3 v13 project. He adds a `qt lint` step to the pipeline using the same command developers run locally. No extra configuration. The step fails the pipeline on any non-zero exit code. During the adoption phase, he configures it as a non-blocking warning stage so developers can see failures without being blocked. After the team has resolved the backlog of issues, he promotes it to a blocking stage. Six months later he has forgotten it exists — it just works.

**What this journey requires:** consistent exit codes (0 = pass, non-zero = fail), non-interactive mode, configurable warn-only vs. blocking mode (fail-on-warnings configuration), no difference between local and CI invocation.

---

### Journey 4: Tech Lead — Cross-Project Quality Visibility

Sandra leads a team responsible for five TYPO3 projects. She opens the quality dashboard on a Monday morning. Two projects are green, one is yellow (warnings trending upward over the past three sprints), and two are red (linting failures in CI). She shares the dashboard link in the team Slack channel and flags the yellow project for review in the next sprint planning. She does not need to open a single project repository to get this picture.

**What this journey requires:** cross-project dashboard fed by machine-readable reports, per-project pass/fail status, trend visualization (improving / degrading over time), accessible without repository access, shareable view.

---

### Journey 5: Product Owner / Customer — Non-Technical Quality Overview

Peter is the product owner of a TYPO3 project. He does not read code but cares about long-term maintainability costs. In a quarterly review, the tech lead shows him the quality dashboard. He sees a simple traffic-light status and a trend graph showing that code quality has improved since the team adopted `qt`. He understands that "green" means the code follows current standards and is being actively modernized. The dashboard gives him enough to ask informed questions and support the team's refactoring budget.

**What this journey requires:** non-technical summary view (traffic-light status, trend, plain-language summary), no requirement for code or tool knowledge, exportable or shareable report format.

---

### Journey 6: Package Maintainer — TYPO3 Version Adoption

TYPO3 v14 enters RC phase. Anna, the package maintainer, creates a new branch and updates the Rector and Fractor configurations to target TYPO3 v14 rules. She runs the full test suite, resolves any rule conflicts, and updates the version support matrix. She publishes a pre-release version of `qt` so teams can run `qt lint:rector` against their codebase with v14 rules enabled — surfacing deprecations before the official release. Existing v13 configurations remain unchanged on the stable branch. When v14 is released stable, the pre-release becomes the new minor version. The v13 branch continues to receive bug fixes.

**What this journey requires:** versioned Rector/Fractor rule sets per TYPO3 target, separate release branches per major TYPO3 version, published version support matrix, ability to run migration-mode analysis without modifying production config, backward-compatible upgrade path.

---

### Journey 7: Downstream Automation Consumer — Report Ingestion Service

After every CI pipeline run, a pipeline job collects the JSON report generated by `qt lint --report-format=json` and pushes it via HTTP POST to an internal quality aggregation service. The service stores the report, updates the project's quality status in the dashboard database, and — if critical issues exceed a configured threshold — opens a GitLab issue automatically. No human is involved. The pipeline job exits 0 regardless of report content (quality gate logic lives in the service, not in `qt`). The service can replay historical reports and recalculate trends as the schema evolves.

**What this journey requires:** stable, versioned JSON report schema, `--report-format=json` flag on all lint commands, report output to stdout or a specified file path, schema versioning for forward compatibility, documented report schema for service integration.

---

### Journey Requirements Summary

| Capability Area | Journeys | FRs |
|---|---|---|
| Unified `qt lint` / `qt fix` commands | 1, 2 | FR03, FR04 |
| Per-tool `lint`/`fix` symmetry | 1, 2 | FR01, FR02 |
| Zero-configuration adoption | 2, 3 | FR01, FR08 |
| Tool-agnostic output (no tool knowledge required) | 2 | FR39 |
| Reliable exit codes + non-interactive CI mode | 3 | FR16, FR17 |
| Fail-on-warnings / warn-only configuration | 3 | FR13, FR18, FR19 |
| Machine-readable JSON report output | 3, 7 | FR20, FR21, FR22 |
| Versioned, stable report schema | 7 | FR22, NFR14 |
| Published schema documentation | 7 | FR41 |
| Cross-project quality dashboard | 4, 5 | FR26, FR27, FR28 |
| Shareable dashboard view | 4, 5 | FR40 |
| Trend visualization (improving / degrading) | 4, 5 | FR28 |
| Non-technical summary view | 5 | FR29 |
| TYPO3-versioned rule sets + release branches | 6 | FR31, FR32, FR34, FR35 |
| Version support matrix documentation | 6 | FR33 |
| Automated issue creation from report thresholds | 7 | FR30 |

## Project Scoping & Phased Development

### Current State

`cpsit/quality-tools` v0.2.0 is the delivered MVP: 10 tool commands, a unified YAML configuration system, security-hardened path resolution, and 96.91% test coverage. The current development focus is consolidation and expansion — completing the configuration iteration, resolving known defects, and building the reporting pipeline that enables the dashboard and CI quality gateway.

**Maintainer:** 1 developer (package maintainer role); contributions from the team as needed.

### Phase 1 — Consolidation & Iteration 002 Completion (current)

**Priority 0 — Urgent (TYPO3 v14.3 released 2026-04-21):**
- Add Rector and Fractor configuration targeting TYPO3 v14
- Verify PHPStan and PHP CS Fixer compatibility with v14 project structures
- Publish updated package version with v14 support
- Update version support matrix documentation

**Priority 1 — Known defect fixes:**
- Issue 025: PathResolutionService nested structure bug (tool-specific path overrides cause TypeError)
- Issue 020: DI configuration inconsistency
- Evaluate and triage open issues from the internal GitLab board

**Priority 2 — Iteration 002 remaining features:**
- Feature 026: Fail-on-warnings configuration (CI/CD-reliable exit codes)
- Feature 031: Enhanced schema validation (security patterns, tool-specific file extension validation)
- Feature 032: EditorConfig CLI integration (`qt lint:editorconfig`, `qt fix:editorconfig`)
- Feature 033: Comprehensive security test suite

**Core user journeys supported:** Journey 1 (developer local), Journey 2 (unfamiliar linter), Journey 3 (CI pipeline — partially; fail-on-warnings required for full support).

### Phase 2 — Reporting Pipeline & Quality Dashboard (next)

**Iteration 003 — Report generation:**
- Feature 005: Report format research and schema definition
- Feature 006: Unified report generation foundation (schema, writer interface, template engine)
- Feature 007: JSON report generation (`--report-format=json`, `--report-file`)
- Feature 012: Human-readable reports (HTML, Markdown)

**Dashboard and CI integration:**
- Internal quality aggregation service: HTTP endpoint accepting JSON report pushes from CI pipelines
- Per-project quality status storage and trend calculation
- Cross-project dashboard: traffic-light status, trend visualization (improving / degrading)
- Non-technical summary view for product owners
- Automated issue creation when report thresholds are exceeded

**Unified commands:**
- `qt lint` — runs all configured linters in sequence
- `qt fix` — runs all configured fixers in sequence

**Core user journeys supported:** Journey 3 (full CI gate), Journey 4 (tech lead dashboard), Journey 5 (product owner view), Journey 7 (report ingestion service).

### Phase 3 — Expansion & Vision

- Additional linters: XML lint, YAML lint, and others as team needs evolve
- Downstream automation: AI-assisted remediation from report data; automatic MR/ticket creation
- PHAR distribution and global installation (Feature 018) once path resolution is resolved
- Frontend quality package (JS/TS/CSS) following the same architecture and conventions
- TYPO3 version lifecycle: v14 as active target, v13 LTS bug fixes, v12/v11/v10 ELTS bug fixes

### Risk Mitigation Strategy

**Technical risks:**
- TYPO3 v14 tool compatibility: Rector/Fractor rules for v14 may conflict with existing v13 configurations — mitigated by separate versioned rule sets and release branches.
- Global installation path resolution: known issue, deferred. Per-project installation remains the stable path.
- Report schema stability: schema must be versioned from first release to avoid breaking the ingestion service — `schemaVersion` field included from the start.

**Resource risks:**
- Package maintainer is a single person. Phase 2 (dashboard, aggregation service) may require additional capacity or a dedicated service team. Scope to be validated when Phase 2 begins.

**Dependency risks:**
- GitLab board issues are unreviewed — some may affect Phase 1 scope. Triage required before Phase 1 work begins.

### Out of Scope

The following are explicitly excluded from all phases unless a separate decision reverses this:

- External or community adoption — the package targets internal PHP/TYPO3 teams only
- IDE integration — no editor plugins, language server integration, or IDE-specific configuration
- Global Composer installation — deferred; known path resolution issues block production use (tracked separately)
- Public plugin or extension API — new tool integrations require modifying package internals; no third-party extension point
- Explicit example configuration files for non-standard project structures — the reference documentation and `config:init` template cover standard cases
- Frontend quality package (JS/TS/CSS) — Phase 3 vision item; separate package, separate decision

## Runtime and Installation Requirements

### Language and Runtime Matrix

| Requirement | Value |
|---|---|
| PHP | ^8.3 (current target); ^8.2 supported for TYPO3 v13 compatibility |
| Framework | Symfony Console / DI / Process / Filesystem ^6.0 or ^7.0 |
| Package manager | Composer (sole distribution channel) |
| Binary | `vendor/bin/qt` (per-project installation) |
| TYPO3 target (current) | v13.4 (LTS) — actively supported |
| TYPO3 target (new stable) | v14.3 — released 2026-04-21; adoption overdue, high priority |
| TYPO3 target (older) | v12 (old stable), v11/v10 (ELTS) — bug fixes only |

### Installation Methods

**Per-project (primary, supported):**
```bash
composer require --dev cpsit/quality-tools
vendor/bin/qt lint:rector
```

**Global Composer installation (not yet stable):** tested in CI environments; known path resolution issues prevent production use. Feature 018 (deferred) tracks this work.

**IDE integration:** out of scope.

## CLI Interface and Configuration

### API Surface and Extensibility

The `qt` binary exposes:
- `lint:<tool>` and `fix:<tool>` commands for each integrated quality tool
- `qt lint` / `qt fix` (planned: unified commands, Phase 2)
- `config:init` — generates a starter `.quality-tools.yaml`
- `config:validate` — validates `.quality-tools.yaml` against the JSON schema with clear error messages
- `config:show` — displays the fully resolved configuration

No public plugin or extension API. New tool integrations are added by the package maintainer only and require modifying package internals.

### Configuration and Documentation

- Configuration is YAML-based (`.quality-tools.yaml` in the project root); full reference at `docs/user-guide/configuration/reference.md`
- `config:init` provides a baseline configuration; `config:validate` enforces the schema
- Configuration schema evolution is documented in `docs/user-guide/configuration/migration.md`
- Explicit example configuration files for non-standard project structures: out of scope

## Functional Requirements

### Tool Execution

- FR01: A developer can run a lint analysis for any individual quality tool using a single `qt lint:<tool>` command without knowledge of the underlying tool's interface or configuration.
- FR02: A developer can apply automatic fixes from any individual quality tool using a `qt fix:<tool>` command that mirrors the corresponding lint command.
- FR03: A developer can run all configured linters in sequence using a single `qt lint` command.
- FR04: A developer can apply all configured fixers in sequence using a single `qt fix` command.
- FR05: A developer can restrict the scope of any tool execution to a specific path using a command-line option.
- FR06: A developer can override the tool configuration file for any command using a command-line option.
- FR07: The system validates the availability of required tool binaries before execution and reports an error identifying the missing binary by name and expected location when a binary is not found.
- FR39: All lint and fix commands produce output that communicates results without requiring knowledge of the underlying tool; output is structured around pass/fail status and actionable issue descriptions, not raw tool output. (Journey 2)

### Configuration Management

- FR08: A developer can generate a starter `.quality-tools.yaml` configuration file using `qt config:init`.
- FR09: A developer can validate their `.quality-tools.yaml` against the defined schema using `qt config:validate`, receiving error messages that identify the failing field, the invalid value, and the expected format for any violations.
- FR10: A developer can inspect the fully resolved configuration (including all defaults and overrides) using `qt config:show`.
- FR11: A developer can configure additional scan paths beyond the standard project structure in `.quality-tools.yaml`.
- FR12: A developer can define tool-specific configuration overrides (config file path, scan paths, enabled state) per tool in `.quality-tools.yaml`.
- FR13: A developer can configure whether warnings cause a non-zero exit code globally and per tool in `.quality-tools.yaml`.
- FR14: The configuration system resolves an 8-level precedence hierarchy — from highest to lowest: CLI arguments, project root config, config directory config, tool-specific project config, tool-specific config directory, package config, global user config (~/.quality-tools.yaml), package defaults — ensuring local and CI invocations produce identical results when given the same inputs. (Journey 3)
- FR15: The configuration schema prevents directory traversal and system path injection in all path-accepting fields.

### CI/CD Integration

- FR16: All lint and fix commands exit with code 0 on success and a non-zero code on any failure or warning (subject to `tolerateWarnings` configuration).
- FR17: All lint and fix commands operate without user interaction and write all output to stdout or stderr as plain text; ANSI color codes are omitted unless the `--ansi` flag is explicitly set.
- FR18: A CI engineer can configure a lint command to produce a non-blocking warning exit code during an adoption phase and a blocking exit code after adoption.
- FR19: The system detects tool-specific warning patterns in output and applies the configured exit code behavior accordingly.

### Report Generation

- FR20: A developer can generate a machine-readable JSON report from any lint command by specifying a report format option.
- FR21: A developer can write the generated report to a specified file path or to stdout.
- FR22: Generated JSON reports conform to a versioned schema; the schema version is included in every report.
- FR23: A developer can generate a human-readable report (HTML or Markdown) from any lint command by specifying a report format option.
- FR24: Reports include metadata (project name, tool name, tool version, timestamp, exit status) and a structured list of issues per tool.
- FR41: The JSON report schema is published as a versioned document accessible to downstream consumers without repository access; the document specifies all fields, their types, and valid values. (Journey 7)

### Quality Dashboard & Aggregation

- FR25: A CI pipeline job can push a JSON report to an internal quality aggregation service endpoint after every pipeline run.
- FR26: The aggregation service stores per-project quality status derived from received reports.
- FR27: A tech lead can view the current quality status of all projects on a cross-project dashboard without repository access.
- FR28: The dashboard displays quality trend data (improving / degrading) across consecutive report cycles per project.
- FR29: A product owner can view a non-technical quality summary (traffic-light status, plain-language trend) without understanding the underlying tools.
- FR30: The aggregation service can automatically create an issue in the configured issue tracker when a project's report exceeds a configured issue threshold.
- FR40: A tech lead or product owner can share a direct link to a project's current quality status on the dashboard without requiring the recipient to log into a code repository. (Journeys 4, 5)

### TYPO3 Version Lifecycle

- FR31: The package ships Rector and Fractor configurations targeting the current stable TYPO3 version (v14.3 as of 2026-04-21).
- FR32: A developer can run lint analysis using next-version TYPO3 rule sets to surface deprecations without modifying their production configuration.
- FR33: The package maintains a published TYPO3 version support matrix documenting supported versions, PHP version floor, and support type (active / bug-fix-only).
- FR34: Older supported TYPO3 versions (LTS, ELTS) receive bug fixes on dedicated branches without receiving breaking changes.
- FR35: A package maintainer can release a new TYPO3 version configuration without affecting the existing stable release branch.

### EditorConfig Integration

- FR36: A developer can validate file formatting consistency across all configured paths using `qt lint:editorconfig`.
- FR37: A developer can automatically fix EditorConfig violations using `qt fix:editorconfig`.
- FR38: The system automatically provisions a TYPO3-optimized `.editorconfig` template when none exists in the project root during `qt config:init`, requiring no manual setup from the developer. (Journey 2)

## Non-Functional Requirements

### Performance

- NFR01: Individual tool commands (`qt lint:<tool>`, `qt fix:<tool>`) add no more than 10% overhead compared to invoking the underlying tool binary directly, as measured by comparing median wall-clock time over 10 consecutive runs on identical input on a standard TYPO3 project with at least 50 PHP files.
- NFR02: Configuration loading and resolution completes in under 50ms for a standard TYPO3 project structure, as measured by timing configuration initialization in isolation from tool execution.
- NFR03: The unified `qt lint` command produces first output within 500ms of invocation and continues streaming without buffering all tool output in memory.
- NFR04: Report generation (JSON, HTML, Markdown) adds no more than 10% of the tool execution time to the total command runtime, as measured by comparing total runtime with and without the `--report-format` option on identical input.

### Security

- NFR05: All path inputs from user configuration and CLI arguments are validated through a dedicated security validation layer before use in filesystem operations or process execution; path traversal and injection attempts result in a typed security exception with a descriptive message.
- NFR06: Tool binaries are invoked without shell string concatenation of user-supplied input; user-controlled values are never interpolated into command strings.
- NFR07: Environment variable values in configuration are accessed exclusively through a dedicated abstraction layer; direct environment variable access in business logic is forbidden.
- NFR08: Temporary files created during tool execution are cleaned up after command completion regardless of exit code.
- NFR09: The aggregation service endpoint validates the report schema and rejects malformed payloads or payloads exceeding 10MB before processing.

### Code Quality (Package Standard)

- NFR10: The package passes all five quality gates (`composer lint:composer`, `composer lint:editorconfig`, `composer lint:php`, `composer lint:rector`, `composer sca:php`) with zero errors before any release.
- NFR11: Test coverage is maintained at or above 95% line coverage; any reduction requires explicit justification and a plan to restore it.
- NFR12: All new tool integrations include both unit tests (isolated, using a virtual filesystem abstraction where applicable) and integration tests (real filesystem or subprocess) before the feature is considered complete.
- NFR13: PHPStan level 6 passes with zero issues using a 1G memory limit.

### Integration

- NFR14: The JSON report schema is versioned from the first release; every report document includes a `schemaVersion` field. Schema changes that remove or rename fields require a new major schema version.
- NFR15: The `--report-format` and `--report-file` options are available on all lint commands with identical syntax; no per-tool variation in option naming.
- NFR16: The aggregation service API contract (endpoint URL structure, authentication method, accepted payload format) is documented; breaking changes to any of these three elements require a major version increment and are not introduced in minor or patch releases.

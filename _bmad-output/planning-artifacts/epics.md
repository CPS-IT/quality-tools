---
stepsCompleted:
  - step-01-validate-prerequisites
  - step-02-design-epics
  - step-03-create-stories
  - step-04-final-validation
workflowStatus: complete
completedAt: 2026-05-05
inputDocuments:
  - _bmad-output/planning-artifacts/prd.md
  - _bmad-output/planning-artifacts/architecture.md
---

# quality-tools - Epic Breakdown

## Overview

This document provides the complete epic and story breakdown for quality-tools, decomposing the requirements from the PRD and Architecture into implementable stories.

## Requirements Inventory

### Functional Requirements

FR01: A developer can run a lint analysis for any individual quality tool using a single `qt lint:<tool>` command without knowledge of the underlying tool's interface or configuration.
FR02: A developer can apply automatic fixes from any individual quality tool using a `qt fix:<tool>` command that mirrors the corresponding lint command.
FR03: A developer can run all configured linters in sequence using a single `qt lint` command.
FR04: A developer can apply all configured fixers in sequence using a single `qt fix` command.
FR05: A developer can restrict the scope of any tool execution to a specific path using a command-line option.
FR06: A developer can override the tool configuration file for any command using a command-line option.
FR07: The system validates the availability of required tool binaries before execution and reports an error identifying the missing binary by name and expected location when a binary is not found.
FR08: A developer can generate a starter `.quality-tools.yaml` configuration file using `qt config:init`.
FR09: A developer can validate their `.quality-tools.yaml` against the defined schema using `qt config:validate`, receiving error messages that identify the failing field, the invalid value, and the expected format for any violations.
FR10: A developer can inspect the fully resolved configuration (including all defaults and overrides) using `qt config:show`.
FR11: A developer can configure additional scan paths beyond the standard project structure in `.quality-tools.yaml`.
FR12: A developer can define tool-specific configuration overrides (config file path, scan paths, enabled state) per tool in `.quality-tools.yaml`.
FR13: A developer can configure whether warnings cause a non-zero exit code globally and per tool in `.quality-tools.yaml`.
FR14: The configuration system resolves an 8-level precedence hierarchy -- from highest to lowest: CLI arguments, project root config, config directory config, tool-specific project config, tool-specific config directory, package config, global user config (~/.quality-tools.yaml), package defaults -- ensuring local and CI invocations produce identical results when given the same inputs.
FR15: The configuration schema prevents directory traversal and system path injection in all path-accepting fields.
FR16: All lint and fix commands exit with code 0 on success and a non-zero code on any failure or warning (subject to `tolerateWarnings` configuration).
FR17: All lint and fix commands operate without user interaction and write all output to stdout or stderr as plain text; ANSI color codes are omitted unless the `--ansi` flag is explicitly set.
FR18: A CI engineer can configure a lint command to produce a non-blocking warning exit code during an adoption phase and a blocking exit code after adoption.
FR19: The system detects tool-specific warning patterns in output and applies the configured exit code behavior accordingly.
FR20: A developer can generate a machine-readable JSON report from any lint command by specifying a report format option.
FR21: A developer can write the generated report to a specified file path or to stdout.
FR22: Generated JSON reports conform to a versioned schema; the schema version is included in every report.
FR23: A developer can generate a human-readable report (HTML or Markdown) from any lint command by specifying a report format option.
FR24: Reports include metadata (project name, tool name, tool version, timestamp, exit status) and a structured list of issues per tool.
FR25: A CI pipeline job can push a JSON report to an internal quality aggregation service endpoint after every pipeline run.
FR26: The aggregation service stores per-project quality status derived from received reports.
FR27: A tech lead can view the current quality status of all projects on a cross-project dashboard without repository access.
FR28: The dashboard displays quality trend data (improving / degrading) across consecutive report cycles per project.
FR29: A product owner can view a non-technical quality summary (traffic-light status, plain-language trend) without understanding the underlying tools.
FR30: The aggregation service can automatically create an issue in the configured issue tracker when a project's report exceeds a configured issue threshold.
FR31: The package ships Rector and Fractor configurations targeting the current stable TYPO3 version (v14.3 as of 2026-04-21).
FR32: A developer can run lint analysis using next-version TYPO3 rule sets to surface deprecations without modifying their production configuration.
FR33: The package maintains a published TYPO3 version support matrix documenting supported versions, PHP version floor, and support type (active / bug-fix-only).
FR34: Older supported TYPO3 versions (LTS, ELTS) receive bug fixes on dedicated branches without receiving breaking changes.
FR35: A package maintainer can release a new TYPO3 version configuration without affecting the existing stable release branch.
FR36: A developer can validate file formatting consistency across all configured paths using `qt lint:editorconfig`.
FR37: A developer can automatically fix EditorConfig violations using `qt fix:editorconfig`.
FR38: The system automatically provisions a TYPO3-optimized `.editorconfig` template when none exists in the project root during `qt config:init`, requiring no manual setup from the developer.
FR39: All lint and fix commands produce output that communicates results without requiring knowledge of the underlying tool; output is structured around pass/fail status and actionable issue descriptions, not raw tool output.
FR40: A tech lead or product owner can share a direct link to a project's current quality status on the dashboard without requiring the recipient to log into a code repository.
FR41: The JSON report schema is published as a versioned document accessible to downstream consumers without repository access; the document specifies all fields, their types, and valid values.

### NonFunctional Requirements

NFR01: Individual tool commands (`qt lint:<tool>`, `qt fix:<tool>`) add no more than 10% overhead compared to invoking the underlying tool binary directly, as measured by comparing median wall-clock time over 10 consecutive runs on identical input on a standard TYPO3 project with at least 50 PHP files.
NFR02: Configuration loading and resolution completes in under 50ms for a standard TYPO3 project structure, as measured by timing configuration initialization in isolation from tool execution.
NFR03: The unified `qt lint` command produces first output within 500ms of invocation and continues streaming without buffering all tool output in memory.
NFR04: Report generation (JSON, HTML, Markdown) adds no more than 10% of the tool execution time to the total command runtime, as measured by comparing total runtime with and without the `--report-format` option on identical input.
NFR05: All path inputs from user configuration and CLI arguments are validated through a dedicated security validation layer before use in filesystem operations or process execution; path traversal and injection attempts result in a typed security exception with a descriptive message.
NFR06: Tool binaries are invoked without shell string concatenation of user-supplied input; user-controlled values are never interpolated into command strings.
NFR07: Environment variable values in configuration are accessed exclusively through a dedicated abstraction layer; direct environment variable access in business logic is forbidden.
NFR08: Temporary files created during tool execution are cleaned up after command completion regardless of exit code.
NFR09: The aggregation service endpoint validates the report schema and rejects malformed payloads or payloads exceeding 10MB before processing.
NFR10: The package passes all five quality gates (`composer lint:composer`, `composer lint:editorconfig`, `composer lint:php`, `composer lint:rector`, `composer sca:php`) with zero errors before any release.
NFR11: Test coverage is maintained at or above 95% line coverage; any reduction requires explicit justification and a plan to restore it.
NFR12: All new tool integrations include both unit tests (isolated, using a virtual filesystem abstraction where applicable) and integration tests (real filesystem or subprocess) before the feature is considered complete.
NFR13: PHPStan level 6 passes with zero issues using a 1G memory limit.
NFR14: The JSON report schema is versioned from the first release; every report document includes a `schemaVersion` field. Schema changes that remove or rename fields require a new major schema version.
NFR15: The `--report-format` and `--report-file` options are available on all lint commands with identical syntax; no per-tool variation in option naming.
NFR16: The aggregation service API contract (endpoint URL structure, authentication method, accepted payload format) is documented; breaking changes to any of these three elements require a major version increment and are not introduced in minor or patch releases.

### Additional Requirements

- Issue 025 (PathResolutionService TypeError on tool-specific path override nested structure) must be resolved before any feature building on PathResolutionService (report pipeline, unified commands). Fix is scoped to PathResolutionService and its tests; no architectural change required.
- Issue 020 (DI configuration inconsistency) must be evaluated and triaged as part of Phase 1 consolidation.
- All open GitLab issues must be triaged before Phase 1 implementation begins; any issue that contradicts or extends architecture decisions requires the affected section to be updated before the corresponding implementation story is created.
- ToolRunResult must be extended with a typed findings collection (array of FindingInterface) as the foundational change for the report pipeline; all existing runners return an empty collection by default with no behaviour change.
- Warning detection (hasWarnings flag on ToolRunResult) applies retroactively to all existing runners, not only new ones; a single story covers all existing runners.
- The --report-format and --report-file options must be added to all existing lint commands in a single migration story before per-tool report generation is implemented.
- ReportWriter service: src/Tool/Report/ReportWriter.php and src/Tool/Report/ReportWriterInterface.php -- reads ToolRunResult.findings, serializes to JSON/HTML/Markdown, writes to stdout or specified file path.
- Contracts package (cpsit/quality-tools-contracts): extract ResultInterface, FindingInterface, AnalysisSummaryInterface, ReportDataProviderInterface, AnalyzerInterface after upgrade-analyser Epic 3 completion; quality-tools defines equivalent internal interfaces in src/Tool/Runner/DTO/ until then.
- TYPO3 v14.3 released 2026-04-21; config targeting v14 is overdue and is Priority 0.
- Named config files per TYPO3 version: config/rector-typo3-13.php, config/rector-typo3-14.php, config/fractor-typo3-13.php, config/fractor-typo3-14.php; config/rector.php and config/fractor.php remain aliases to the current stable target.
- Dedicated bugfix branches (release/typo3-13, release/typo3-14) for LTS/ELTS maintenance only; new TYPO3 version support always lands on main branch first.
- Per-tool Finding and Summary DTOs placed in src/Tool/Runner/{ToolName}/ subdirectory alongside the runner; runners are migrated from flat to subdirectory as the report pipeline is implemented tool by tool.
- LintAllCommand and FixAllCommand resolve runners via ToolRunnerRegistry; runner list and execution order come from configuration, never hardcoded; runners invoked sequentially.
- Dual-output mode is mandatory for all runners with JSON output mode: single tool invocation, JSON captured via OutputCollectorInterface, parsed to Finding DTOs, terminal output rendered from DTOs.
- Report JSON schema field naming: snake_case; schemaVersion is a semantic version string ("1.0.0"); schema alignment with upgrade-analyser 5-3-stable-json-output-schema required before field names are finalized.
- Feature 026 (fail-on-warnings configuration) and Feature 031 (enhanced schema validation) and Feature 032 (EditorConfig integration) and Feature 033 (comprehensive security test suite) are remaining Iteration 002 items.

### UX Design Requirements

No UX design document found. This is a CLI developer tool with no frontend.

### FR Coverage Map

FR01: Existing (v0.2.0 MVP) -- per-tool qt lint:<tool>
FR02: Existing (v0.2.0 MVP) -- per-tool qt fix:<tool>
FR03: Epic 8 -- LintAllCommand (qt lint)
FR04: Epic 8 -- FixAllCommand (qt fix)
FR05: Existing (v0.2.0 MVP) -- --path option
FR06: Existing (v0.2.0 MVP) -- --config option
FR07: Existing (v0.2.0 MVP) -- binary validation
FR08: Existing (v0.2.0 MVP) -- qt config:init
FR09: Epic 6 -- enhanced validation error messages (Feature 031)
FR10: Existing (v0.2.0 MVP) -- qt config:show
FR11: Epic 6 -- additional scan paths (requires Issue 025 fix)
FR12: Epic 6 -- tool-specific overrides (requires Issue 025 fix)
FR13: Epic 5 -- tolerateWarnings configuration (Feature 026)
FR14: Existing (v0.2.0 MVP) -- 8-level config hierarchy
FR15: Existing (v0.2.0 MVP) -- path injection prevention
FR16: Existing (v0.2.0 MVP) -- exit codes (extended by Epic 5)
FR17: Existing (v0.2.0 MVP) -- non-interactive mode
FR18: Epic 5 -- warn-only adoption mode (Feature 026)
FR19: Epic 5 -- tool-specific warning pattern detection
FR20: Epic 3 -- JSON report generation (--report-format=json)
FR21: Epic 3 -- report output to file or stdout (--report-file)
FR22: Epic 3 -- versioned report schema (schemaVersion field)
FR23: Epic 4 -- human-readable reports (HTML, Markdown)
FR24: Epic 3 -- report metadata + structured issues
FR25: Epic 9 -- CI pipeline report push to aggregation endpoint
FR26: Epic 9 -- per-project quality status storage
FR27: Epic 9 -- cross-project dashboard
FR28: Epic 9 -- trend visualization
FR29: Epic 9 -- non-technical summary view
FR30: Epic 9 -- automated issue creation from thresholds
FR31: Epic 1 -- TYPO3 v14 Rector/Fractor configs
FR32: Epic 1 -- next-version analysis mode
FR33: Epic 1 -- TYPO3 version support matrix
FR34: Epic 1 -- dedicated bugfix branches for LTS/ELTS
FR35: Epic 1 -- independent version config releases
FR36: Epic 7 -- qt lint:editorconfig
FR37: Epic 7 -- qt fix:editorconfig
FR38: Epic 7 -- .editorconfig provisioning in config:init
FR39: Epic 3 -- tool-agnostic output (terminal rendering from Finding DTOs)
FR40: Epic 9 -- shareable dashboard links
FR41: Epic 3 -- published JSON report schema document

## Epic List

### Epic 1: TYPO3 v14 Compatibility

Teams can analyze and modernize PHP/TypoScript code for TYPO3 v14 using versioned Rector and Fractor rule sets, surfacing deprecations before upgrading production systems. No src/ code changes required -- config files and documentation only.
**FRs covered:** FR31, FR32, FR33, FR34, FR35
**Priority:** 0 -- urgent (v14.3 released 2026-04-21, overdue)
**Dependencies:** none

---

### Epic 2: Platform Stability & Defect Resolution

Developers get reliable, well-tested tool behavior -- critical defects fixed, DI wired correctly, open issues triaged, and security behavior comprehensively verified. This epic is a prerequisite for all path-dependent features.
**Additional requirements:** Issue 025 (PathResolutionService TypeError), Issue 020 (DI inconsistency), GitLab issue triage, Feature 033 (comprehensive security test suite)
**Dependencies:** none; prerequisite for Epics 3, 5, 6, 7, 8

---

## Epic 2: Platform Stability & Defect Resolution

Developers get reliable, well-tested tool behavior -- critical defects fixed, DI wired correctly, open issues triaged, and security behavior comprehensively verified. This epic is a prerequisite for all path-dependent features.
**Additional requirements:** Issue 025 (PathResolutionService TypeError), Issue 020 (DI inconsistency), GitLab issue triage, Feature 033 (comprehensive security test suite)
**Dependencies:** none; prerequisite for Epics 3, 5, 6, 7, 8

### Story 2.1: Triage all open GitLab issues

As a package maintainer,
I want all open GitLab issues reviewed and categorized,
So that no unknown defect or scope item blocks or surprises Phase 1 implementation work.

**Acceptance Criteria:**

**Given** an unreviewed list of open issues on the GitLab board
**When** the triage is complete
**Then** every open issue has been reviewed and assigned one of: confirmed-bug, enhancement, duplicate, wont-fix, or already-fixed
**And** any issue that contradicts or extends an architecture decision in `_bmad-output/planning-artifacts/architecture.md` has triggered an update to the affected architecture section before the corresponding story is created
**And** any issue that adds scope to Phase 1 has been recorded as a story in the backlog
**And** issues confirmed as already-fixed in v0.2.0 are closed with a comment referencing the commit or release

### Story 2.2: Fix PathResolutionService nested structure bug (Issue 025)

As a developer,
I want tool-specific path overrides in `.quality-tools.yaml` to be resolved without error,
So that I can configure per-tool scan paths reliably without encountering a TypeError at runtime.

**Acceptance Criteria:**

**Given** a `.quality-tools.yaml` with a tool-specific path override in the nested `tools.<tool>.paths` structure
**When** any `qt lint:<tool>` or `qt fix:<tool>` command is executed
**Then** the command resolves the path override without throwing a TypeError or any uncaught exception
**And** the resolved path is used for the tool invocation as configured
**And** a unit test covers the nested structure path with at least: a valid override, an empty override, and a missing key
**And** an integration test verifies the full command executes without error when a tool-specific path override is present
**And** all existing tests continue to pass
**And** all five quality gates pass with zero errors

### Story 2.3: Resolve DI configuration inconsistency (Issue 020)

As a developer,
I want all `qt` commands to resolve their dependencies correctly via the DI container,
So that no command fails at startup due to a wiring error or missing service definition.

**Acceptance Criteria:**

**Given** the current `config/services.yaml` contains an inconsistency identified in Issue 020
**When** the fix is applied
**Then** every registered command resolves without a `ServiceNotFoundException` or container compilation error
**And** the specific wiring error from Issue 020 is corrected and covered by a unit or integration test that would have caught the original inconsistency
**And** `composer dump-autoload` and container compilation complete without warnings related to service definitions
**And** all existing tests continue to pass
**And** all five quality gates pass with zero errors

### Story 2.4: Comprehensive security test suite (Feature 033)

As a package maintainer,
I want a dedicated suite of security-focused tests covering all entry points,
So that path traversal, injection attempts, and environment variable misuse are demonstrably rejected and future regressions are caught automatically.

**Acceptance Criteria:**

**Given** SecurityService is the sole path validation entry point per ADRs 0001-0004
**When** the test suite is complete
**Then** unit tests exist for SecurityService covering: directory traversal sequences (`../`, `..\\`), null bytes, absolute paths where relative is expected, and shell metacharacters in path inputs -- each asserting a typed `SecurityException` is thrown
**And** unit tests verify that ProcessExecutor never constructs commands via string interpolation of user-supplied values (array-form arguments only)
**And** unit tests verify that `EnvironmentVariableInterpolationTrait` is the sole access path for environment variables in all classes that use them
**And** integration tests verify that a command invoked with a malicious `--path` argument exits with a non-zero code and prints a descriptive error without executing the underlying tool
**And** test coverage remains at or above 95% line coverage after all new tests are added
**And** all five quality gates pass with zero errors

---

### Epic 3: Machine-Readable Report Generation

CI pipelines and automation consumers receive structured, versioned JSON reports from any lint command -- enabling cross-project quality aggregation without raw tool output parsing. Establishes the dual-output mode pattern and Finding/Summary DTO infrastructure used by all subsequent tool reporting.
**FRs covered:** FR20, FR21, FR22, FR24, FR39, FR41
**NFRs covered:** NFR14, NFR15
**Architecture work:** ToolRunResult extension with findings collection, per-tool Finding/Summary DTOs (starting with PHPStan and Rector), dual-output mode pattern, ReportWriter service, --report-format/--report-file migration for all existing lint commands
**Dependencies:** Epic 2 (Issue 025 fix); schema alignment with upgrade-analyser 5-3 before finalizing field names

---

## Epic 3: Machine-Readable Report Generation

CI pipelines and automation consumers receive structured, versioned JSON reports from any lint command -- enabling cross-project quality aggregation without raw tool output parsing. Establishes the dual-output mode pattern and Finding/Summary DTO infrastructure used by all subsequent tool reporting.
**FRs covered:** FR20, FR21, FR22, FR24, FR39, FR41
**NFRs covered:** NFR14, NFR15
**Architecture work:** ToolRunResult extension with findings collection, per-tool Finding/Summary DTOs (starting with PHPStan and Rector), dual-output mode pattern, ReportWriter service, --report-format/--report-file migration for all existing lint commands
**Dependencies:** Epic 2 (Issue 025 fix); schema alignment with upgrade-analyser 5-3 before finalizing field names

### Story 3.1: Extend ToolRunResult with a typed findings collection

As a package maintainer,
I want ToolRunResult to carry a typed findings collection,
So that all subsequent report pipeline work has a stable contract to build on without changing existing runner behaviour.

**Acceptance Criteria:**

**Given** the existing `ToolRunResult` DTO in `src/Tool/Runner/DTO/`
**When** the story is complete
**Then** a `FindingInterface` exists in `src/Tool/Runner/DTO/FindingInterface.php` with at minimum: `getFile(): string`, `getLine(): int`, `getMessage(): string`, `getSeverity(): string`
**And** `ToolRunResult` declares a `findings: array<FindingInterface>` property, defaulting to an empty array
**And** all existing runners pass an empty array for `findings` -- no runner behaviour changes
**And** all existing unit and integration tests pass without modification
**And** all five quality gates pass with zero errors

### Story 3.2: Research and align on shared contracts package with upgrade-analyser

As a package maintainer,
I want the internal interfaces and JSON schema field names agreed upon with the upgrade-analyser team before implementation begins,
So that the quality-tools report output is compatible with upgrade-analyser consumers and a shared contracts package can be extracted without breaking changes.

**Acceptance Criteria:**

**Given** the architecture requires coordination with upgrade-analyser story 5-3-stable-json-output-schema
**When** the story is complete
**Then** the interfaces `ResultInterface`, `FindingInterface`, `AnalysisSummaryInterface`, `ReportDataProviderInterface`, and `AnalyzerInterface` are defined in `src/Tool/Runner/DTO/` with field signatures agreed upon with the upgrade-analyser team
**And** the JSON report schema field naming convention (snake_case, mandatory top-level fields, per-tool result object fields) is documented in a draft `config/schema/report-schema.json` aligned with upgrade-analyser's schema for the overlapping tools (Rector, Fractor, PHPStan)
**And** a decision is recorded -- either in an ADR or as a note in `architecture.md` -- on whether to create `cpsit/quality-tools-contracts` now or defer until after upgrade-analyser Epic 3 completion, with the rationale
**And** `FindingInterface` from Story 3.1 is updated if the agreed signature differs from the initial definition
**And** all five quality gates pass with zero errors

### Story 3.3: Add `--report-format` and `--report-file` options to all lint commands

As a developer,
I want `--report-format` and `--report-file` options available on every lint command with identical syntax,
So that report generation can be wired in per tool without requiring a second migration pass later.

**Acceptance Criteria:**

**Given** the existing lint commands for rector, phpstan, php-cs-fixer, fractor, typoscript-lint, and composer-normalize
**When** the story is complete
**Then** every lint command accepts `--report-format=<format>` (supported values: `json`; invalid values produce a clear error) and `--report-file=<path>` options
**And** when `--report-format` is omitted, command behaviour is identical to before this story
**And** when `--report-format=json` is passed but the runner returns an empty findings collection, the command exits normally with no report output and no error
**And** option names and syntax are identical across all commands (no per-tool variation)
**And** unit tests cover: valid format, invalid format value, file path provided, no options provided
**And** all five quality gates pass with zero errors

### Story 3.4: Implement dual-output mode and Finding DTOs for PHPStan

As a developer,
I want PHPStan invoked in JSON output mode with terminal output rendered from parsed Finding DTOs,
So that PHPStan results are available as structured data for report generation without running the tool twice.

**Acceptance Criteria:**

**Given** PHPStan supports `--error-format=json`
**When** `qt lint:phpstan` is executed
**Then** PHPStan is invoked once with `--error-format=json`; raw JSON is captured via `OutputCollectorInterface`
**And** the JSON output is parsed into `PhpstanFinding` and `PhpstanAnalysisSummary` DTOs located in `src/Tool/Runner/Phpstan/`
**And** terminal output is rendered from the parsed DTOs -- not from raw JSON
**And** `PhpStanRunner` is moved to `src/Tool/Runner/Phpstan/PhpStanRunner.php`
**And** `ToolRunResult.findings` contains the parsed `PhpstanFinding` instances
**And** `PhpstanFinding` implements `FindingInterface`; all fields are readonly and set in the constructor
**And** unit tests use a virtual filesystem abstraction for isolation; integration tests invoke the real binary
**And** all five quality gates pass with zero errors

### Story 3.5: Implement dual-output mode and Finding DTOs for Rector

As a developer,
I want Rector invoked in JSON output mode with terminal output rendered from parsed Finding DTOs,
So that Rector results are available as structured data for report generation without running the tool twice.

**Acceptance Criteria:**

**Given** Rector supports `--json-output`
**When** `qt lint:rector` is executed
**Then** Rector is invoked once with `--json-output`; raw JSON is captured via `OutputCollectorInterface`
**And** the JSON output is parsed into `RectorFinding`, `RectorAnalysisSummary`, and `RectorChangeType` DTOs located in `src/Tool/Runner/Rector/`
**And** terminal output is rendered from the parsed DTOs -- not from raw JSON
**And** `RectorRunner` is moved to `src/Tool/Runner/Rector/RectorRunner.php`
**And** `ToolRunResult.findings` contains the parsed `RectorFinding` instances
**And** `RectorFinding` implements `FindingInterface`; all fields are readonly and set in the constructor
**And** unit tests use a virtual filesystem abstraction; integration tests invoke the real binary
**And** all five quality gates pass with zero errors

### Story 3.6: Implement dual-output mode and Finding DTOs for PHP CS Fixer, Fractor, and TypoScript Lint

As a developer,
I want the remaining tool runners migrated to dual-output mode with Finding DTOs,
So that all major tools produce structured findings and the report pipeline covers the full tool set.

**Acceptance Criteria:**

**Given** PHP CS Fixer supports `--format=json`, TypoScript Lint supports `--format=json`, and Fractor JSON output availability is verified before implementation
**When** the story is complete
**Then** `PhpCsFixerRunner`, `FractorRunner`, and `TypoScriptLintRunner` are each migrated to per-tool subdirectories under `src/Tool/Runner/`
**And** each runner invokes its tool in JSON output mode (or uses the synthetic Finding fallback pattern documented in `architecture.md` if JSON mode is unavailable)
**And** each tool has its own `{Tool}Finding`, `{Tool}AnalysisSummary` DTOs implementing `FindingInterface`
**And** terminal output for each tool is rendered from parsed DTOs
**And** runners using the synthetic fallback document this explicitly in their class docblock
**And** unit and integration tests exist for each migrated runner
**And** all five quality gates pass with zero errors

### Story 3.7: Implement ReportWriter and JSON report output

As a CI engineer,
I want `qt lint:<tool> --report-format=json` to produce a versioned JSON report written to stdout or a file,
So that pipeline jobs can collect structured quality data without parsing raw tool output.

**Acceptance Criteria:**

**Given** a lint command is run with `--report-format=json`
**When** the command completes
**Then** a JSON document is written to stdout (default) or to the path specified by `--report-file`
**And** the document conforms to `config/schema/report-schema.json` and includes all mandatory top-level fields: `schema_version`, `generated_at`, `project_name`, `exit_code`, `tools`
**And** each tool result object includes: `tool_name`, `tool_version`, `exit_code`, `has_warnings`, `summary`, `findings`
**And** `schema_version` matches the version declared in `config/schema/report-schema.json`
**And** report generation adds no more than 10% to the tool execution time (NFR04)
**And** `ReportWriter` and `ReportWriterInterface` are located in `src/Tool/Report/`
**And** unit tests cover: output to stdout, output to file, empty findings, non-zero exit code in report
**And** all five quality gates pass with zero errors

### Story 3.8: Publish JSON report schema documentation

As a downstream automation consumer,
I want the JSON report schema documented in a stable, versioned reference accessible without repository access,
So that I can integrate against the report format without inspecting the source code.

**Acceptance Criteria:**

**Given** the report schema is defined in `config/schema/report-schema.json`
**When** the story is complete
**Then** `docs/developer-guide/report-schema.md` exists and documents every field in the schema: name, type, description, and valid values
**And** the document states the current schema version and defines the breaking-change policy (field removal or rename requires a major schema version increment)
**And** the document includes at minimum one complete example JSON report
**And** the document is linked from the main README or developer guide index
**And** all five quality gates pass with zero errors

---

### Epic 4: Human-Readable Quality Reports

Developers and tech leads can view quality analysis results as formatted HTML or Markdown reports for sprint reviews, documentation, and sharing with non-technical stakeholders.
**FRs covered:** FR23
**Dependencies:** Epic 3 (report pipeline foundation, Finding DTOs, ReportWriter)

---

## Epic 4: Human-Readable Quality Reports

Developers and tech leads can view quality analysis results as formatted HTML or Markdown reports for sprint reviews, documentation, and sharing with non-technical stakeholders.
**FRs covered:** FR23
**Dependencies:** Epic 3 (report pipeline foundation, Finding DTOs, ReportWriter)

### Story 4.1: Implement Markdown report output

As a developer,
I want `qt lint:<tool> --report-format=markdown` to produce a readable Markdown quality report,
So that I can include quality results in sprint reviews, pull request descriptions, or project documentation without manual formatting.

**Acceptance Criteria:**

**Given** a lint command is run with `--report-format=markdown`
**When** the command completes
**Then** a Markdown document is written to stdout (default) or to the path specified by `--report-file`
**And** the document includes a summary section with: project name, timestamp, overall exit status, and per-tool pass/fail status
**And** findings are grouped by tool, then by file, with line number, severity, and message for each finding
**And** a tool with no findings renders a brief pass confirmation rather than an empty findings section
**And** the document is valid CommonMark and renders correctly in standard Markdown viewers
**And** `ReportWriter` handles `markdown` as a valid `--report-format` value alongside `json`
**And** unit tests cover: output to stdout, output to file, report with findings, report with no findings, multiple tools
**And** all five quality gates pass with zero errors

### Story 4.2: Implement HTML report output

As a tech lead,
I want `qt lint:<tool> --report-format=html` to produce a self-contained HTML quality report,
So that I can share or archive quality results as a CI artifact without requiring the recipient to have any tooling installed.

**Acceptance Criteria:**

**Given** a lint command is run with `--report-format=html`
**When** the command completes
**Then** a self-contained HTML document is written to stdout (default) or to the path specified by `--report-file`
**And** the document requires no external stylesheets, scripts, or assets -- all styling is inline or embedded
**And** the document includes: project name, timestamp, overall exit status, per-tool pass/fail summary, and findings grouped by tool and file
**And** a tool with no findings renders a brief pass confirmation
**And** the HTML is valid and renders correctly in current versions of Firefox, Chrome, and Safari
**And** `ReportWriter` handles `html` as a valid `--report-format` value alongside `json` and `markdown`
**And** unit tests cover: output to stdout, output to file, report with findings, report with no findings
**And** all five quality gates pass with zero errors

---

### Epic 5: Reliable CI/CD Quality Gates

CI engineers can configure lint commands to produce non-blocking warnings during adoption and blocking failures after -- with predictable, configurable exit codes per tool and globally. Warning detection is applied retroactively to all existing runners.
**FRs covered:** FR13, FR18, FR19
**NFRs extended:** NFR01 (exit code behavior), FR16 (extended tolerateWarnings)
**Dependencies:** Epic 2 (stable path resolution); Epic 3 (hasWarnings flag pattern on ToolRunResult established)

---

## Epic 5: Reliable CI/CD Quality Gates

CI engineers can configure lint commands to produce non-blocking warnings during adoption and blocking failures after -- with predictable, configurable exit codes per tool and globally. Warning detection is applied retroactively to all existing runners.
**FRs covered:** FR13, FR18, FR19
**NFRs extended:** FR16 (extended by tolerateWarnings), NFR01
**Dependencies:** Epic 2 (stable path resolution); Epic 3 (hasWarnings flag pattern on ToolRunResult established)

### Story 5.1: Implement warning detection in all existing runners

As a CI engineer,
I want each tool runner to detect its own warning patterns and flag them on the result,
So that the command layer can apply configured exit code behaviour without parsing raw tool output.

**Acceptance Criteria:**

**Given** all existing runners (PhpStanRunner, RectorRunner, PhpCsFixerRunner, FractorRunner, TypoScriptLintRunner, ComposerNormalizeRunner)
**When** a tool produces output that matches its known warning pattern
**Then** `ToolRunResult.hasWarnings` is `true` for that invocation
**And** when a tool produces no warnings, `ToolRunResult.hasWarnings` is `false`
**And** warning pattern detection logic lives exclusively in the runner that owns the tool -- no shared WarningDetector service
**And** each runner has unit tests covering: output with warnings sets flag true, output without warnings sets flag false, empty output sets flag false
**And** the `hasWarnings` field is added to `ToolRunResult` as a readonly boolean defaulting to `false`
**And** all existing tests continue to pass without modification
**And** all five quality gates pass with zero errors

### Story 5.2: Implement `tolerateWarnings` configuration and exit code behaviour

As a CI engineer,
I want to configure whether warnings produce a non-zero exit code globally and per tool,
So that I can run lint commands as non-blocking during adoption and promote them to blocking after the team has resolved the backlog.

**Acceptance Criteria:**

**Given** `.quality-tools.yaml` supports a `tolerateWarnings` key at global level and under each `tools.<tool>` section
**When** a lint command completes with `ToolRunResult.hasWarnings = true`
**Then** if `tolerateWarnings: true` is configured (globally or for the specific tool), the command exits with code 0
**And** if `tolerateWarnings: false` (the default), the command exits with a non-zero code when warnings are present
**And** per-tool `tolerateWarnings` takes precedence over the global value
**And** the JSON schema in `config/schema/quality-tools.json` is updated to include `tolerateWarnings` with valid values and descriptions
**And** `qt config:validate` reports a clear error if `tolerateWarnings` is set to an invalid value
**And** unit tests cover: global true, global false, per-tool override true with global false, per-tool override false with global true, default behaviour
**And** an integration test verifies that a command with warnings exits 0 when `tolerateWarnings: true` and non-zero when `tolerateWarnings: false`
**And** all five quality gates pass with zero errors

---

### Epic 6: Enhanced Configuration & Schema Validation

Developers get precise, actionable validation feedback when `.quality-tools.yaml` is invalid, and tool-specific path overrides and additional scan paths work reliably after the Issue 025 fix.
**FRs covered:** FR09 (enhanced error messages), FR11, FR12, FR15
**Feature:** Feature 031 (enhanced schema validation, security patterns, tool-specific file extension validation)
**Dependencies:** Epic 2 (Issue 025 fix)

---

## Epic 6: Enhanced Configuration & Schema Validation

Developers get precise, actionable validation feedback when `.quality-tools.yaml` is invalid, and tool-specific path overrides and additional scan paths work reliably after the Issue 025 fix.
**FRs covered:** FR09, FR11, FR12, FR15
**Feature:** Feature 031 (enhanced schema validation, security patterns, tool-specific file extension validation)
**Dependencies:** Epic 2 (Issue 025 fix)

### Story 6.1: Enhanced `config:validate` error messages

As a developer,
I want `qt config:validate` to report exactly which field is invalid, what value was provided, and what the expected format is,
So that I can fix configuration errors without guessing or reading the schema manually.

**Acceptance Criteria:**

**Given** a `.quality-tools.yaml` with one or more invalid fields
**When** `qt config:validate` is run
**Then** each validation error message identifies: the field path (e.g. `tools.rector.level`), the invalid value, and the expected type or allowed values
**And** multiple errors are all reported in a single run rather than stopping at the first error
**And** a valid `.quality-tools.yaml` produces a clear pass confirmation with exit code 0
**And** an invalid file exits with a non-zero code
**And** unit tests cover: single field error, multiple field errors, unknown extra field, correct file passes
**And** all five quality gates pass with zero errors

### Story 6.2: Additional scan paths and tool-specific path overrides

As a developer,
I want `paths.additional` and `tools.<tool>.paths` configuration to be resolved and applied correctly end-to-end,
So that I can direct tools to scan non-standard directories without encountering runtime errors.

**Acceptance Criteria:**

**Given** a `.quality-tools.yaml` with `paths.additional` entries and at least one `tools.<tool>.paths` override
**When** any `qt lint:<tool>` command is executed
**Then** the tool is invoked with the resolved additional paths included in its scan scope
**And** a tool-specific path override replaces (not appends to) the default resolved paths for that tool
**And** paths are resolved relative to the project root; a path that does not exist produces a clear error identifying the missing path before tool invocation
**And** unit tests cover: additional paths only, tool-specific override only, both combined, non-existent path
**And** an integration test verifies a real tool invocation uses the configured paths
**And** all five quality gates pass with zero errors

### Story 6.3: Security pattern validation and tool-specific file extension validation

As a package maintainer,
I want the schema validator to reject path values containing security-violating patterns and tool config file paths with incorrect extensions,
So that malformed or malicious configuration is caught at validation time rather than at tool execution time.

**Acceptance Criteria:**

**Given** a `.quality-tools.yaml` with path fields or tool config file path fields
**When** `qt config:validate` is run
**Then** any path value containing directory traversal sequences (`../`, `..\\`), null bytes, or shell metacharacters is rejected with a descriptive error naming the field and the pattern detected
**And** a tool-specific config file path (e.g. `tools.rector.config`) is validated to have the expected file extension for that tool (e.g. `.php` for rector, `.neon` for phpstan, `.yml` or `.yaml` for typoscript-lint)
**And** an invalid extension produces an error identifying the field, the provided extension, and the expected extension
**And** security validation in the schema validator delegates to `SecurityService` -- no duplicate validation logic
**And** unit tests cover: traversal sequence, null byte, metacharacter, wrong extension per tool, valid values pass
**And** all five quality gates pass with zero errors

---

### Epic 7: EditorConfig Integration

Developers can enforce and auto-fix file formatting consistency via `qt lint:editorconfig` and `qt fix:editorconfig`, with automatic TYPO3-optimized `.editorconfig` provisioning on `qt config:init`. Follows the established runner pattern.
**FRs covered:** FR36, FR37, FR38
**Feature:** Feature 032
**Dependencies:** Epic 2 (stable foundation); NFR12 (unit + integration tests required)

---

## Epic 7: EditorConfig Integration

Developers can enforce and auto-fix file formatting consistency via `qt lint:editorconfig` and `qt fix:editorconfig`, with automatic TYPO3-optimized `.editorconfig` provisioning on `qt config:init`. Follows the established runner pattern.
**FRs covered:** FR36, FR37, FR38
**Feature:** Feature 032
**Dependencies:** Epic 2 (stable foundation); NFR12 (unit + integration tests required)

### Story 7.1: Implement `qt lint:editorconfig` and `qt fix:editorconfig` commands

As a developer,
I want `qt lint:editorconfig` to validate file formatting consistency and `qt fix:editorconfig` to apply corrections automatically,
So that EditorConfig compliance is part of the standard quality workflow without requiring knowledge of the underlying tool.

**Acceptance Criteria:**

**Given** `armin/editorconfig-cli` is available as a project dependency
**When** `qt lint:editorconfig` is executed
**Then** the EditorConfig CLI is invoked across all configured paths and reports any formatting violations
**And** violations are rendered as pass/fail terminal output structured around file and rule -- not raw tool output
**And** the command exits with code 0 when no violations are found and non-zero when violations exist
**And** when `qt fix:editorconfig` is executed, the EditorConfig CLI applies corrections and the command exits with code 0 on success
**And** `EditorConfigCommand` is located in `src/Console/Command/` and registered in `services.yaml` for both `lint:editorconfig` and `fix:editorconfig`
**And** `EditorConfigRunner` is located in `src/Tool/Runner/EditorConfig/` and implements `ToolRunnerInterface`
**And** if EditorConfig CLI has no JSON output mode, the runner uses the synthetic Finding fallback pattern and documents this in its class docblock
**And** `EditorConfigFinding` and `EditorConfigAnalysisSummary` DTOs are present in `src/Tool/Runner/EditorConfig/`
**And** unit tests use a virtual filesystem abstraction; integration tests invoke the real binary
**And** all five quality gates pass with zero errors

### Story 7.2: Auto-provision `.editorconfig` template on `qt config:init`

As a developer,
I want `qt config:init` to automatically create a TYPO3-optimized `.editorconfig` file when none exists,
So that file formatting standards are in place from project start without any manual setup.

**Acceptance Criteria:**

**Given** a project root with no `.editorconfig` file present
**When** `qt config:init` is run
**Then** a TYPO3-optimized `.editorconfig` file is created in the project root with rules appropriate for TYPO3 projects (4-space indent for PHP, 2-space for TypoScript/YAML/JS, tab indent for JSON/XLF/SQL, UTF-8 charset, LF line endings)
**And** the provisioned file is identical to the template stored in the package
**And** a confirmation message is printed identifying the file created
**Given** a project root where a `.editorconfig` file already exists
**When** `qt config:init` is run
**Then** the existing file is not modified
**And** a message is printed indicating the existing file was left unchanged
**And** `ConfigInitRunner` is extended to handle `.editorconfig` provisioning alongside the existing `.quality-tools.yaml` generation
**And** unit tests cover: no existing file creates template, existing file is preserved, template content matches package template
**And** all five quality gates pass with zero errors

---

### Epic 8: Unified Quality Commands

Developers run a single `qt lint` to check all configured tools in sequence and `qt fix` to apply all fixes -- the complete quality workflow in one invocation, with streaming output and aggregated exit codes.
**FRs covered:** FR03, FR04
**NFRs covered:** NFR03 (first output within 500ms, streaming)
**Architecture work:** LintAllCommand, FixAllCommand resolving runners via ToolRunnerRegistry; runner order and enabled state from configuration; never hardcoded
**Dependencies:** Epic 2; can proceed in parallel with Epics 3-7

---

## Epic 1: TYPO3 v14 Compatibility

Teams can analyze and modernize PHP/TypoScript code for TYPO3 v14 using versioned Rector and Fractor rule sets, surfacing deprecations before upgrading production systems. No src/ code changes required -- config files and documentation only.
**FRs covered:** FR31, FR32, FR33, FR34, FR35
**Priority:** 0 -- urgent (v14.3 released 2026-04-21, overdue)
**Dependencies:** none

### Story 1.1: Add versioned Rector configurations for TYPO3 v13 and v14

As a package maintainer,
I want versioned Rector configuration files for TYPO3 v13 and v14 to coexist on the main branch,
So that developers can analyze code against v14 rules immediately while v13 configs remain available for projects not yet upgrading.

**Acceptance Criteria:**

**Given** the repository contains `config/rector.php` targeting TYPO3 v13
**When** the story is complete
**Then** `config/rector-typo3-13.php` exists and contains the v13 Rector rules (equivalent to the previous `config/rector.php`)
**And** `config/rector-typo3-14.php` exists and contains Rector rules targeting TYPO3 v14.3 and PHP 8.3+
**And** `config/rector.php` requires/includes `config/rector-typo3-14.php` so it targets v14 as the new stable default
**And** running `vendor/bin/qt lint:rector` without `--config` applies v14 rules
**And** running `vendor/bin/qt lint:rector --config config/rector-typo3-13.php` applies v13 rules without error
**And** all existing tests pass with zero linting errors

### Story 1.2: Add versioned Fractor configurations for TYPO3 v13 and v14

As a package maintainer,
I want versioned Fractor configuration files for TYPO3 v13 and v14 to coexist on the main branch,
So that developers can surface TypoScript deprecations for v14 without modifying their production configuration.

**Acceptance Criteria:**

**Given** the repository contains `config/fractor.php` targeting TYPO3 v13
**When** the story is complete
**Then** `config/fractor-typo3-13.php` exists and contains the v13 Fractor rules (equivalent to the previous `config/fractor.php`)
**And** `config/fractor-typo3-14.php` exists and contains Fractor rules targeting TYPO3 v14.3
**And** `config/fractor.php` requires/includes `config/fractor-typo3-14.php` so it targets v14 as the new stable default
**And** running `vendor/bin/qt lint:fractor` without `--config` applies v14 rules
**And** running `vendor/bin/qt lint:fractor --config config/fractor-typo3-13.php` applies v13 rules without error
**And** all existing tests pass with zero linting errors

### Story 1.3: Publish TYPO3 version support matrix

As a developer evaluating the package,
I want a published document showing which TYPO3 versions are supported, with what PHP requirements and support type,
So that I can determine whether the package covers my project's TYPO3 version before adopting it.

**Acceptance Criteria:**

**Given** no version support matrix exists yet
**When** the story is complete
**Then** `docs/user-guide/configuration/typo3-version-support.md` exists and is accessible in the repository
**And** the document lists every supported TYPO3 version (v10, v11, v12, v13, v14) with: minimum PHP version, support type (active / bug-fix-only / ELTS bug-fix-only), and the corresponding config file name
**And** the document identifies v14.3 as the current active target and v13 as LTS (bug-fix-only)
**And** the document references the versioned config file names (`rector-typo3-13.php`, `rector-typo3-14.php`, etc.) for each version
**And** the document is linked from the main README or user guide index

### Story 1.4: Establish LTS/ELTS bugfix branch strategy

As a package maintainer,
I want a dedicated `release/typo3-13` branch and a documented release process,
So that v13 LTS projects receive bug fixes without being affected by v14 development on the main branch.

**Acceptance Criteria:**

**Given** the main branch has been updated to target TYPO3 v14 (Stories 1.1 and 1.2 complete)
**When** the story is complete
**Then** a `release/typo3-13` branch exists, branched from the last commit that targeted TYPO3 v13 as the stable default
**And** the branch contains `config/rector.php` and `config/fractor.php` still targeting v13 rules
**And** a `docs/contributing/release-branches.md` document (or equivalent section in CONTRIBUTING.md) describes: how bugfix branches are named, what types of changes are accepted on them, and how releases are tagged per branch
**And** no v14-specific config files are present on the `release/typo3-13` branch

---

## Epic 8: Unified Quality Commands

Developers run a single `qt lint` to check all configured tools in sequence and `qt fix` to apply all fixes -- the complete quality workflow in one invocation, with streaming output and aggregated exit codes.
**FRs covered:** FR03, FR04
**NFRs covered:** NFR03 (first output within 500ms, streaming)
**Architecture work:** LintAllCommand, FixAllCommand resolving runners via ToolRunnerRegistry; runner order and enabled state from configuration; never hardcoded
**Dependencies:** Epic 2; can proceed in parallel with Epics 3-7

### Story 8.1: Implement `qt lint` and `qt fix` unified commands

As a developer,
I want a single `qt lint` command to run all configured linters in sequence and `qt fix` to run all fixers,
So that I can check or fix my entire codebase with one command without knowing which tools are configured.

**Acceptance Criteria:**

**Given** all runners are registered in `ToolRunnerRegistry`
**When** `qt lint` is executed
**Then** all enabled runners are invoked sequentially in their configured order
**And** each runner's output is streamed to the terminal as it becomes available -- not buffered until all runners finish
**And** the first output appears within 500ms of invocation (NFR03)
**And** the overall exit code is 0 only if all runners exited with 0; any non-zero runner exit produces a non-zero overall exit
**And** when `qt fix` is executed, the same pattern applies for all fix runners
**And** `LintAllCommand` and `FixAllCommand` are located in `src/Console/Command/` and registered in `services.yaml` as `qt lint` and `qt fix` respectively
**And** runner names are never hardcoded in either command -- the list is resolved exclusively from `ToolRunnerRegistry`
**And** runners are never invoked in parallel
**And** unit tests cover: all runners pass, one runner fails, no runners registered, exit code aggregation
**And** an integration test verifies runner ordering and streamed output for at least two runners
**And** all five quality gates pass with zero errors

### Story 8.2: Runner enable/disable and execution order configuration

As a developer,
I want to disable individual tools and control their execution order in `.quality-tools.yaml`,
So that `qt lint` and `qt fix` adapt to my project's toolset without requiring code changes.

**Acceptance Criteria:**

**Given** `.quality-tools.yaml` supports `tools.<tool>.enabled: false` and a global `tools.execution_order` list
**When** `qt lint` or `qt fix` is executed
**Then** any runner with `enabled: false` is skipped without error or output
**And** runners are invoked in the order specified by `tools.execution_order`; runners not listed in the order key follow after listed runners in their default registration order
**And** if `tools.execution_order` is omitted, runners execute in their default registration order
**And** the JSON schema in `config/schema/quality-tools.json` is updated for `enabled` and `execution_order`
**And** `qt config:validate` reports a clear error if `execution_order` references an unknown tool name
**And** unit tests cover: one tool disabled, all tools disabled, custom order, partial order list, default order
**And** all five quality gates pass with zero errors

---

## Epic 9: Cross-Project Quality Dashboard

Tech leads and product owners get cross-project quality visibility -- traffic-light status, trend data, shareable links, and automated issue creation -- without repository access. This is a separate service outside the quality-tools package.
**FRs covered:** FR25, FR26, FR27, FR28, FR29, FR30, FR40
**NFRs covered:** NFR09, NFR16
**Note:** Separate aggregation service and dashboard application. Requires stable JSON report schema (Epic 3, FR41) and published schema documentation before service implementation begins.
**Dependencies:** Epic 3 (stable JSON schema, FR41 schema docs)

### Story 9.1: Aggregation service -- report ingestion endpoint

As a CI engineer,
I want a pipeline job to push a JSON quality report to an internal endpoint after every run,
So that per-project quality data is collected automatically without manual intervention.

**Acceptance Criteria:**

**Given** a CI pipeline job with a valid JSON report produced by `qt lint --report-format=json`
**When** the report is submitted via HTTP POST to the aggregation service endpoint
**Then** the service validates the payload against the published report schema (FR41)
**And** payloads exceeding 10MB are rejected with a 413 response and no data is stored (NFR09)
**And** payloads that fail schema validation are rejected with a 422 response and a descriptive error body identifying the failing fields
**And** a valid payload is accepted with a 201 response and stored associated with the project identified in the report
**And** the endpoint URL structure, authentication method, and accepted payload format are documented; any breaking change to these three elements requires a major API version increment (NFR16)
**And** the pipeline job exits 0 regardless of report content -- quality gate logic lives in the service, not in `qt`

### Story 9.2: Per-project quality status storage and trend calculation

As a tech lead,
I want the aggregation service to store quality reports per project and calculate whether quality is improving or degrading,
So that trend data is available for the dashboard without manual analysis.

**Acceptance Criteria:**

**Given** at least two consecutive reports stored for a project
**When** trend calculation runs
**Then** the project's trend is classified as: improving (fewer total findings than previous cycle), degrading (more findings), or stable (same count)
**And** trend data is recalculated on each new report ingestion
**And** a project with only one stored report has trend status: insufficient data
**And** historical reports are retained and can be replayed to recalculate trends as the schema evolves
**And** per-project quality status (latest exit code, finding count, trend) is queryable via an internal API

### Story 9.3: Cross-project dashboard -- tech lead view

As a tech lead,
I want a dashboard showing the current quality status and trend for all projects in one view,
So that I can identify at-risk projects without opening any repository.

**Acceptance Criteria:**

**Given** the aggregation service has stored at least one report per project
**When** the dashboard is opened
**Then** every project is listed with: project name, traffic-light status (green / yellow / red based on exit code and finding count), trend indicator (improving / degrading / stable / insufficient data), and timestamp of the last report
**And** a direct link to each project's status page is available and shareable without requiring the recipient to log into a code repository (FR40)
**And** the dashboard updates automatically when new reports are ingested without requiring a manual refresh
**And** projects with no reports in the last configured reporting cycle are visually distinguished

### Story 9.4: Non-technical summary view for product owners

As a product owner,
I want a plain-language quality summary for each project showing traffic-light status and trend without any tool-specific terminology,
So that I can understand project health and ask informed questions without reading code or tool output.

**Acceptance Criteria:**

**Given** a project with stored quality reports
**When** the product owner summary view is opened for that project
**Then** the view displays: a traffic-light status indicator, a plain-language trend statement (e.g. "Quality has improved over the last 3 reviews" or "New issues detected since the last review"), and the date of the last quality check
**And** no tool names, rule identifiers, file paths, or line numbers are visible in this view
**And** the view is accessible via the same shareable direct link as the tech lead view (FR40), differentiated by a query parameter or role setting
**And** the view renders correctly on mobile screen sizes

### Story 9.5: Automated issue creation from report thresholds

As a tech lead,
I want the aggregation service to automatically create an issue in the configured issue tracker when a project's quality report exceeds a threshold,
So that critical quality regressions are surfaced in the team's normal workflow without manual monitoring.

**Acceptance Criteria:**

**Given** a project configured with an issue threshold (e.g. total findings above N, or exit code non-zero for M consecutive cycles)
**When** an ingested report exceeds the configured threshold
**Then** the aggregation service creates an issue in the configured issue tracker with: project name, threshold that was exceeded, current finding count, link to the project dashboard, and timestamp
**And** a duplicate issue is not created if an open issue for the same project and threshold already exists
**And** the issue tracker integration (endpoint, authentication, issue template) is configurable per project
**And** threshold configuration and issue tracker connection are validated on service startup with a clear error if misconfigured
**And** the issue creation attempt is logged; a failure to create an issue does not prevent the report from being stored
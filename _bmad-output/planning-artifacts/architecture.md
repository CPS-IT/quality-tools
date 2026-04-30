---
stepsCompleted:
  - step-01-init
  - step-02-context
  - step-03-starter
  - step-04-decisions
  - step-05-patterns
  - step-06-structure
  - step-07-validation
  - step-08-complete
workflowStatus: complete
completedAt: '2026-04-23'
inputDocuments:
  - _bmad-output/planning-artifacts/prd.md
  - _bmad-output/project-context.md
  - _bmad-output/planning-artifacts/implementation-readiness-report-2026-04-22.md
  - docs/architecture/0001-context-aware-security-validation.md
  - docs/architecture/0002-security-at-entry-points.md
  - docs/architecture/0003-code-duplication-elimination-through-refactoring.md
  - docs/architecture/0004-inheritance-based-security-propagation.md
  - docs/architecture/0005-simplified-command-architecture.md
workflowType: 'architecture'
project_name: 'quality-tools'
user_name: 'QT Team'
date: '2026-04-22'
---

# Architecture Decision Document

_This document builds collaboratively through step-by-step discovery. Sections are appended as we work through each architectural decision together._

## Project Context Analysis

### Requirements Overview

**Functional Requirements (41 total):**

The 41 FRs fall into seven clusters with distinct architectural implications:

- Tool Execution (FR01-07, FR39): Per-tool lint/fix commands via parameterized command instances,
  unified qt lint/qt fix orchestration (FR03/04 - not yet built), path scoping, config override,
  binary availability validation, tool-agnostic output formatting.
- Configuration Management (FR08-15): config:init, config:validate, config:show commands;
  additional scan paths; tool-specific overrides; fail-on-warnings toggle; 8-level precedence
  hierarchy (highest: CLI args, lowest: package defaults); path injection prevention via schema.
- CI/CD Integration (FR16-19): Deterministic exit codes, non-interactive non-ANSI mode,
  configurable warn-only adoption mode, tool-specific warning pattern detection.
- Report Generation (FR20-24, FR41): JSON report with versioned schema (schemaVersion field
  mandatory from first release), HTML/Markdown human-readable reports, report to stdout or file,
  metadata + structured issues, published schema document for downstream consumers.
- Quality Dashboard & Aggregation (FR25-30, FR40): Separate service outside this package.
  This package's responsibility ends at producing a stable JSON report. The aggregation endpoint,
  storage, dashboard, trend calculation, and automated issue creation are a distinct system.
- TYPO3 Version Lifecycle (FR31-35): Versioned Rector/Fractor rule sets per TYPO3 target
  (currently v13; v14 overdue); next-version analysis mode without modifying production config;
  dedicated release branches per major TYPO3 version; support matrix documentation.
- EditorConfig Integration (FR36-38): New tool runner for lint:editorconfig / fix:editorconfig;
  auto-provision of TYPO3-optimized .editorconfig template on config:init.

**Non-Functional Requirements:**

- Performance: <10% overhead per tool command (NFR01); <50ms config loading (NFR02);
  <500ms first output for unified qt lint with streaming, no buffering (NFR03);
  <10% overhead for report generation (NFR04).
- Security: Path validation through SecurityService at entry points only (NFR05);
  no shell string concatenation of user input (NFR06); env var abstraction via trait (NFR07);
  temp file cleanup on any exit (NFR08); 10MB payload limit on aggregation endpoint (NFR09).
- Code Quality: All 5 quality gates pass before release (NFR10); 95% line coverage (NFR11);
  unit + integration tests per new tool (NFR12); PHPStan level 6 at 1G (NFR13).
- Integration: Versioned report schema from first release (NFR14); uniform --report-format /
  --report-file options across all lint commands (NFR15); documented aggregation API contract
  with breaking-change versioning (NFR16).

**Scale & Complexity:**

- Primary domain: CLI developer tool (Composer package, per-project installation)
- Complexity level: medium (brownfield - MVP delivered, post-MVP extension)
- Three new subsystems to design: multi-runner orchestration (unified commands), report generation
  pipeline, EditorConfig runner
- One scope boundary to enforce: aggregation service / dashboard is out-of-package

### Technical Constraints & Dependencies

- PHP ^8.3; Symfony Console/DI/Process/Filesystem ^6.0 || ^7.0
- Composer-only distribution; binary at vendor/bin/qt; no global install yet
- Existing architecture: thin commands -> ToolRunRequest DTO -> ToolRunnerInterface runners
  -> OutputCollectorInterface for live output
- Security model fixed by ADRs 0001-0004: entry-point validation, no inheritance between runners,
  SecurityService at command/loader boundary only
- TYPO3 v14.3 released 2026-04-21; FR31 is already overdue
- Issue 025 (PathResolutionService nested structure / TypeError on tool-specific path overrides)
  must be resolved before report generation or unified commands build on path resolution

### Cross-Cutting Concerns Identified

- Security validation: all user-supplied paths and config values validated at entry points
  via SecurityService before any runner or filesystem operation
- Configuration precedence: 8-level hierarchy resolved exclusively through ConfigurationLoader;
  runners must not read config files or resolve paths independently
- Output collection and streaming: live tool output via OutputCollectorInterface (never
  OutputInterface directly in runners); report output intercepted at runner result level
- Exception hierarchy: all exceptions extend QualityToolsException; ErrorFactory for
  standardized construction; SecurityException for path/injection violations
- Process execution safety: ProcessExecutor with array-form arguments only; no user input
  interpolated into command strings
- Report schema versioning: schemaVersion field in every report document from first release;
  schema changes to existing fields require a new major schema version
- TYPO3 version lifecycle: Rector/Fractor rule sets versioned per TYPO3 target;
  release branches per major version; v13 stable must not be affected by v14 work
- Warning detection: per-tool warning pattern matching (tool output is raw text);
  detection logic belongs in runners, not commands; result carries warning flag for exit code
  decision in command layer

## Technical Foundation

### Primary Technology Domain

CLI developer tool (PHP Composer package). No frontend, no database, no web framework.
This is a brownfield project; the technical foundation is established at v0.2.0.

### Established Technical Stack

**Language & Runtime:**
- PHP ^8.3 (strict types throughout, final classes, full type declarations)
- Composer as sole distribution channel; binary at vendor/bin/qt

**Framework:**
- Symfony Console ^6.0 || ^7.0 -- command registration, input/output, help text
- Symfony DI ^6.0 || ^7.0 -- pure constructor injection, service tagging, parameterized services
- Symfony Process ^6.0 || ^7.0 -- subprocess execution via array-form arguments
- Symfony Filesystem ^6.0 || ^7.0 -- filesystem abstraction

**Bundled Quality Tools (managed, not extended):**
- PHPStan ^2.1 (level 6, 1G memory limit)
- PHP CS Fixer ^3.45 (TYPO3 preset)
- Rector / ssch/typo3-rector ^3.5 (PHP 8.3 + TYPO3 13 target)
- Fractor / a9f/typo3-fractor ~0.5.1 (TypoScript modernization)
- EditorConfig CLI / armin/editorconfig-cli ^2.1
- Composer Normalize / ergebnis/composer-normalize ^2.47
- TypoScript Lint / helmich/typo3-typoscript-lint ^3.3

**Testing:**
- PHPUnit ^11.0; vfsStream ^1.6 for filesystem mocking in unit tests
- Two suites: Unit (isolated, vfsStream) and Integration (real filesystem/process)

### Architectural Decisions Already Made

The following decisions are fixed and must not be revisited without an ADR:

**Command layer (ADR 0005):**
- Commands are thin input/output adapters (~30 lines). All business logic lives in runners.
- Lint and fix variants share one parameterized command class, registered twice in services.yaml.
- Commands depend only on ToolRunnerRegistry and ToolRunInfoDisplay.

**Security model (ADRs 0001-0004):**
- Path validation through SecurityService at entry points (commands, config loaders) only.
- No path validation in runners or utilities.
- ProcessExecutor with array-form arguments; no user input interpolated into command strings.
- Environment variable access via EnvironmentVariableInterpolationTrait or ProjectEnvironment only.

**Runner layer:**
- One runner per tool, implementing ToolRunnerInterface.
- No inheritance between runners; shared behavior via injected services.
- OutputCollectorInterface for live tool output; never OutputInterface directly in runners.
- ToolRunRequest is the sole input to runners; immutable.

**Dependency injection:**
- Pure constructor injection throughout; no service locator, no ContainerAwareTrait.
- Tool runners registered via DI tagging; registry resolves by tool name.

**Configuration:**
- YAML-based (.quality-tools.yaml); schema in config/schema/ validated by ConfigurationValidator.
- ConfigurationLoader is the sole resolver; runners do not read config directly.
- Adding new config keys requires updating the schema and validator.

**Exception hierarchy:**
- All exceptions extend QualityToolsException.
- ErrorFactory for standardized construction.
- SecurityException for path/injection violations.

**Note:** No initialization command is needed. Project structure and tooling are in place.

## Core Architectural Decisions

### Decision Priority Analysis

**Critical (block implementation):**
- Report generation pipeline design (Group 1) -- shapes FR20-24, NFR14-15
- Multi-runner orchestration design (Group 2) -- required for FR03/04
- Issue 025 resolution (Group 5) -- prerequisite for Groups 1 and 2

**Important (shape architecture):**
- Warning detection mechanism (Group 3) -- required for FR13/FR16/FR18/FR19
- TYPO3 version config strategy (Group 4) -- required for FR31-35

**Deferred:**
- Shared reporting library implementation extraction -- depends on upgrade-analyser Epic 3 completion
- Report schema formal publication (FR41) -- depends on schema alignment with upgrade-analyser 5-3

---

### Open GitLab Issues — Prerequisite Triage

The PRD (Phase 1, Risk Mitigation) identifies unreviewed GitLab board issues as a scope risk.
Some open issues may add to Phase 1 scope, reprioritize existing work, or reveal defects that
affect the decisions documented here.

**Status:** GitLab MCP server configured but not yet available. Triage is pending.

**Constraint:** Before Phase 1 implementation begins, all open GitLab issues must be triaged.
Any issue that contradicts or extends a decision in this document requires the affected section
to be updated before the corresponding implementation story is created.

The architectural decisions below are structurally sound independently of issue triage.
Individual issues may add scope or reprioritize, but are not expected to invalidate the
design patterns.

---

### Report Generation Pipeline (FR20-24, NFR14-15)

**Decision:** Structured output interception via native tool JSON modes.

Runners invoke their underlying tool in its native machine-readable output mode where
available. Parsed output is mapped to typed Finding and Summary DTOs. Human-readable
terminal output and machine-readable report output are produced from the same runner
invocation without running the tool twice.

**Tool JSON output availability:**
- PHPStan: --error-format=json (confirmed)
- PHP CS Fixer: --format=json (confirmed)
- Rector: --json-output (confirmed, also used in typo3-upgrade-analyser)
- Fractor: --json-output (to be verified)
- TypoScript Lint: --format=json (confirmed)
- EditorConfig CLI: to be verified
- Composer Normalize: likely text-only; pass/fail only

**Internal data model:** Per-tool Finding DTOs (file, line, ruleClass, message, severity,
changeType, diff) and AnalysisSummary aggregates (totals by severity, by file, by rule).
These mirror the model in typo3-upgrade-analyser, intentionally.

**Library strategy:**
- Phase 1 (near-term): Extract contracts only -- ResultInterface, AnalyzerInterface,
  AnalysisReportDataProviderInterface, Finding/Summary interfaces, report JSON schema --
  into a dedicated contracts package (name TBD). Both quality-tools and upgrade-analyser
  align to these contracts.
- Phase 2 (deferred): Extract implementations into a second package after upgrade-analyser
  completes its streaming refactor (Epic 3) and JSON schema work (Epic 5-3). Both projects
  remain free to use the shared implementation or their own.
- Both projects stay independently deployable throughout.

**Schema prerequisite:** The quality-tools report JSON schema (NFR14) must be designed in
coordination with upgrade-analyser story 5-3-stable-json-output-schema to avoid producing
incompatible schemas for overlapping tools (Rector, Fractor, PHPStan).

**Detail levels supported by the schema:**
- Level 1 (summary): pass/fail per tool, issue counts
- Level 2 (file): which files have violations
- Level 3 (issue): file + line + rule + message + severity per violation

---

### Multi-Runner Orchestration (FR03, FR04)

**Decision:** Dedicated orchestrator commands; no meta-runner.

A LintAllCommand and FixAllCommand resolve the list of enabled runners from configuration
via ToolRunnerRegistry, invoke each in declared order, stream output per runner, and
aggregate exit codes (any non-zero exit = overall non-zero). These commands live in the
command layer. Runners remain isolated and unaware of orchestration.

Configuration controls runner order and enabled/disabled state per tool. A runner marked
disabled in configuration is skipped without error.

---

### Warning Detection (FR13, FR16, FR18, FR19)

**Decision:** Runner-level detection; flag carried in ToolRunResult.

Each runner inspects its own tool output for warning patterns and sets a hasWarnings flag
on ToolRunResult. The command layer reads the flag and applies the configured exit code
behavior (tolerateWarnings global and per-tool). Warning pattern knowledge stays with the
runner that owns the tool. No shared WarningDetector service.

---

### TYPO3 Version Config Strategy (FR31-35)

**Decision:** Named config files per TYPO3 version on master; dedicated bugfix branches for LTS.

Versioned Rector and Fractor config files coexist on the master/develop branch:
- config/rector-typo3-13.php, config/rector-typo3-14.php (and so on per version)
- config/rector.php remains the alias for the current stable target

Users targeting a non-default TYPO3 version pass --config to select the versioned file.
The next-version analysis mode (FR32) is implemented by passing the next-version config
file without modifying the production config.

Dedicated bugfix branches (release/typo3-13, release/typo3-14) are created only for
LTS/ELTS maintenance (FR34), not for new-version development. New TYPO3 version support
always lands on the main branch first.

---

### Issue 025 Resolution (PathResolutionService)

**Decision:** Fix as a prerequisite before report pipeline or unified commands work begins.

Issue 025 (TypeError on tool-specific path override nested structure) must be resolved
in isolation before any feature that builds on PathResolutionService. The fix is scoped
to PathResolutionService and its tests; no architectural change required.

---

### Decision Impact Analysis

**Implementation sequence constraints:**
1. GitLab issue triage -- prerequisite for finalizing Phase 1 scope
2. Issue 025 fix -- prerequisite for all path-dependent features
3. Warning detection (runner-level) -- prerequisite for fail-on-warnings feature
4. Contracts package extraction -- prerequisite for report pipeline implementation
5. Report schema alignment with upgrade-analyser 5-3 -- prerequisite for schema design
6. Per-tool report pipeline (Finding/Summary DTOs, JSON output mode) -- then per tool
7. Unified qt lint / qt fix orchestration commands -- can proceed in parallel with report pipeline
8. EditorConfig runner -- follows existing runner pattern; low dependency
9. TYPO3 v14 config files -- independent of all other items; can start immediately

**Cross-component dependencies:**
- Report pipeline depends on: Issue 025 fix, contracts package, schema alignment
- Unified commands depend on: ToolRunnerRegistry (already exists), configuration (enabled/order)
- Warning detection integrates into: existing ToolRunResult, existing command exit code logic
- TYPO3 v14 configs are self-contained; no code changes required, only config files

## Implementation Patterns & Consistency Rules

### New Subsystem Naming Conventions

**Finding and Summary DTOs (per tool):**
- Naming: {ToolName}Finding, {ToolName}AnalysisSummary
  - Examples: PhpstanFinding, PhpCsFixerFinding, RectorAnalysisSummary
- Location: src/Tool/Runner/{ToolName}/ subdirectory alongside the runner
  - Example: src/Tool/Runner/Phpstan/PhpstanFinding.php
- These are readonly DTOs. All fields declared in the constructor. No setters.

**Severity and ChangeType enums (per tool):**
- Naming: {ToolName}IssueSeverity, {ToolName}ChangeType
  - Example: PhpstanIssueSeverity
- Location: same subdirectory as the Finding DTO

**Contracts package namespace:** Cpsit\QualityToolsContracts\ (package name TBD)
- ResultInterface, FindingInterface, AnalysisSummaryInterface, ReportDataProviderInterface
- No implementations in the contracts package -- interfaces and value object base types only

**Orchestrator commands:**
- Naming: LintAllCommand, FixAllCommand (not UnifiedLintCommand, not AllToolsCommand)
- Location: src/Console/Command/ alongside existing commands
- Registered in services.yaml as qt lint and qt fix

---

### Dual-Output Mode Pattern (critical)

Every runner that supports report generation must produce both terminal output
and structured Finding data from a SINGLE tool invocation.

**Mandatory pattern:**
1. Invoke the tool in JSON output mode (--error-format=json, --format=json, --json-output)
2. Capture the raw JSON output via OutputCollectorInterface
3. Parse the JSON into Finding DTOs immediately after process completion
4. Render human-readable terminal output from the Finding DTOs (not from raw tool output)
5. Return ToolRunResult containing both the Finding collection and the terminal-formatted messages

**Forbidden:**
- Running the tool twice (once for human output, once for JSON)
- Buffering all tool output before streaming terminal output (violates NFR03)
- Passing raw JSON output directly to the terminal

**For unified qt lint (NFR03 compliance):**
- The orchestrator command starts each runner and streams its terminal-formatted output
  as it becomes available -- it does not wait for all runners to finish before rendering

---

### JSON Fallback Pattern

For tools with no machine-readable output mode (currently: Composer Normalize,
EditorConfig CLI -- verify before implementing):

- The runner produces a synthetic Finding with severity INFO and changeType NONE
  representing the overall pass/fail result
- File, line, and ruleClass fields are empty strings for pass/fail-only findings
- The Summary carries totalFindings=0 on pass; totalFindings=1 with the tool error
  message on fail
- Document the fallback explicitly in the runner class docblock

---

### Report JSON Schema Conventions

**Field naming:** snake_case throughout (aligns with upgrade-analyser convention)

**Schema version format:** semantic version string, e.g. "1.0.0"
- The schemaVersion field is a string, not an integer
- Breaking changes (field removal, rename) require a major version bump
- New optional fields are non-breaking (minor version bump)

**Mandatory top-level fields in every report:**
- schemaVersion (string)
- generatedAt (ISO 8601 datetime string)
- projectName (string)
- exitCode (integer, 0 or non-zero)
- tools (array of tool result objects)

**Per-tool result object mandatory fields:**
- toolName (string, matches qt command suffix, e.g. "rector", "phpstan")
- toolVersion (string)
- exitCode (integer)
- hasWarnings (boolean)
- summary (object: totalFindings, bySeverity, byFile)
- findings (array of Finding objects, may be empty)

**Schema alignment:** Do not finalize field names until the upgrade-analyser
5-3-stable-json-output-schema story is in progress. Coordinate to share the
schema for overlapping tools (rector, fractor, phpstan).

---

### Orchestrator Command Pattern

LintAllCommand and FixAllCommand resolve runners from ToolRunnerRegistry.
The list of tools to run and their order comes from configuration -- never hardcoded.

**Mandatory pattern:**
1. Resolve all registered runners from ToolRunnerRegistry
2. Filter to those enabled in configuration (default: all enabled)
3. Apply configured execution order (default: declared registration order)
4. Invoke each runner sequentially via the existing runner invocation path
5. Accumulate exit codes: track whether any runner returned non-zero
6. Return 0 only if all runners returned 0; otherwise return 1

**Forbidden:**
- Hardcoding a list of tool names in the orchestrator command
- Invoking runners in parallel (sequential execution, ordered output)
- Suppressing individual runner output -- each runner's output streams to terminal

---

### Enforcement Guidelines

**All agents implementing new runners, DTOs, or commands MUST:**

- Place Finding and Summary DTOs in src/Tool/Runner/{ToolName}/ not in src/Reporting/
- Implement ResultInterface from the contracts package once it exists
- Invoke tools in JSON output mode and render terminal output from parsed DTOs
- Never run the same tool twice in one command invocation
- Declare all DTO fields as readonly in the constructor (no setters)
- Add both Unit and Integration tests before marking any runner feature complete (NFR12)
- Verify EditorConfig, Fractor JSON output availability before assuming text fallback

**Anti-patterns to reject in code review:**
- Separate LintFooCommand / FixFooCommand classes for the same tool
- Finding DTOs outside the tool-specific Runner subdirectory
- OutputInterface injected into runners (use OutputCollectorInterface)
- Hardcoded tool name lists in the orchestrator command
- Report schema with camelCase field names
- Tool invocation without JSON mode even when JSON mode is available

## Project Structure & Boundaries

### Existing Structure (reference)

The current src/ layout follows these layers:
- src/Configuration/        -- ConfigurationLoader, ConfigurationValidator, per-tool validators
- src/Console/Command/      -- thin command classes (one per tool, parameterized for lint/fix)
- src/Console/Runner/       -- config command runners (ConfigInit, ConfigShow, ConfigValidate)
- src/Console/Output/       -- ToolRunInfoDisplay
- src/DependencyInjection/  -- ServiceContainer
- src/Exception/            -- QualityToolsException hierarchy
- src/Messaging/            -- OutputCollectorInterface, Message, StreamingOutputCollector
- src/Service/              -- SecurityService, ProcessExecutor, PathResolutionService, etc.
- src/Tool/Runner/          -- one runner per tool (flat), ToolRunnerInterface, ToolRunnerRegistry
- src/Tool/Runner/DTO/      -- ToolRunRequest, ToolRunResult, ToolRunDescription
- src/Traits/               -- EnvironmentVariableInterpolationTrait, etc.
- src/Utility/              -- PathScanner, PathExclusionFilter, ProjectAnalyzer, etc.

config/ contains: rector.php, fractor.php, php-cs-fixer.php, phpstan.neon,
typoscript-lint.yml, services.yaml, schema/quality-tools.json

---

### New Files & Directories by Feature Area

#### TYPO3 v14 Config Support (FR31-35) -- no src/ changes

```
config/
  rector-typo3-13.php          (rename or copy of current rector.php)
  rector-typo3-14.php          (new -- TYPO3 v14 Rector rules)
  fractor-typo3-13.php         (rename or copy of current fractor.php)
  fractor-typo3-14.php         (new -- TYPO3 v14 Fractor rules)
  rector.php                   (alias to rector-typo3-14.php once v14 is stable target)
  fractor.php                  (alias to fractor-typo3-14.php once v14 is stable target)

docs/user-guide/configuration/
  typo3-version-support.md     (support matrix -- FR33)
```

---

#### EditorConfig Runner (FR36-38) -- follows existing runner pattern

```
src/Console/Command/
  EditorConfigCommand.php      (new -- parameterized for lint:editorconfig / fix:editorconfig)

src/Tool/Runner/EditorConfig/
  EditorConfigRunner.php       (new -- implements ToolRunnerInterface)
  EditorConfigFinding.php      (new -- synthetic Finding if no JSON output mode)
  EditorConfigAnalysisSummary.php  (new)

tests/Unit/Tool/Runner/EditorConfig/
  EditorConfigRunnerTest.php

tests/Integration/Tool/Runner/EditorConfig/
  EditorConfigRunnerTest.php

config/
  services.yaml                (register EditorConfigCommand x2, EditorConfigRunner with tag)
```

---

#### Report Generation Pipeline (FR20-24) -- per-tool Finding/Summary DTOs + schema

When the report pipeline is implemented for a tool, the runner migrates from flat to
a per-tool subdirectory. New files alongside each migrated runner:

```
src/Tool/Runner/
  Phpstan/
    PhpStanRunner.php          (moved from flat; runner logic unchanged)
    PhpstanFinding.php         (new readonly DTO)
    PhpstanAnalysisSummary.php (new readonly DTO)
    PhpstanIssueSeverity.php   (new enum)
  Rector/
    RectorRunner.php           (moved from flat)
    RectorFinding.php
    RectorAnalysisSummary.php
    RectorIssueSeverity.php
    RectorChangeType.php
  Fractor/
    FractorRunner.php          (moved from flat)
    FractorFinding.php
    FractorAnalysisSummary.php
    FractorIssueSeverity.php
    FractorChangeType.php
  PhpCsFixer/
    PhpCsFixerRunner.php       (moved from flat)
    PhpCsFixerFinding.php
    PhpCsFixerAnalysisSummary.php
    PhpCsFixerIssueSeverity.php
  TypoScriptLint/
    TypoScriptLintRunner.php   (moved from flat)
    TypoScriptLintFinding.php
    TypoScriptLintAnalysisSummary.php
    TypoScriptLintIssueSeverity.php
  ComposerNormalize/
    ComposerNormalizeRunner.php    (moved from flat; synthetic Finding -- no JSON mode)
    ComposerNormalizeFinding.php
    ComposerNormalizeAnalysisSummary.php

config/schema/
  report-schema.json           (new -- versioned JSON report schema, NFR14)

docs/developer-guide/
  report-schema.md             (published schema reference -- FR41)
```

Migration note: Runners are migrated to subdirectories tool by tool as the report
pipeline is implemented. Both the runner file and its Finding/Summary DTOs move
together in the same story. No partial migrations.

---

#### Unified Orchestrator Commands (FR03, FR04)

```
src/Console/Command/
  LintAllCommand.php           (new -- qt lint, resolves all enabled runners via registry)
  FixAllCommand.php            (new -- qt fix)

tests/Unit/Console/Command/
  LintAllCommandTest.php
  FixAllCommandTest.php

tests/Integration/Console/Command/
  LintAllCommandTest.php       (verifies runner ordering and exit code aggregation)

config/
  services.yaml                (register LintAllCommand as qt lint, FixAllCommand as qt fix)
```

---

#### Contracts Package (separate Composer package -- name TBD)

This is a new repository, not a directory in quality-tools. Boundary definition:

```
cpsit/quality-tools-contracts (proposed name)
  src/
    ResultInterface.php
    FindingInterface.php
    AnalysisSummaryInterface.php
    ReportDataProviderInterface.php
    AnalyzerInterface.php
```

Both quality-tools and typo3-upgrade-analyser declare this as a dependency once
extracted. Until extracted, quality-tools defines equivalent internal interfaces in
src/Tool/Runner/DTO/ following the same contracts.

---

### Architectural Boundaries

**Command / Runner boundary:**
- Commands: input validation, security validation, ToolRunRequest construction, result rendering
- Runners: tool invocation, output parsing, Finding/Summary DTO construction
- Nothing crosses: runners never receive OutputInterface; commands never parse tool output

**Report pipeline boundary:**
- A ReportWriter service reads ToolRunResult (which carries the Finding collection)
  and serializes to JSON/HTML/Markdown
- Runners are unaware of report format; they produce Findings unconditionally when
  invoked in JSON output mode

**Configuration boundary:**
- ConfigurationLoader is the sole entry point for reading configuration
- Runners receive resolved values via injected services; they never call ConfigurationLoader
- New config keys require: schema update in config/schema/quality-tools.json + validator update

**Contracts package boundary:**
- Contracts package: interfaces and readonly base types only; no implementations, no Symfony deps
- quality-tools implements the contracts; upgrade-analyser may implement or adapt them
- Both projects remain independently deployable

---

### Requirements to Structure Mapping

| FR Area | Location |
|---|---|
| FR01/02 per-tool lint/fix | src/Console/Command/*Command.php (existing) |
| FR03/04 unified qt lint/fix | src/Console/Command/LintAllCommand.php, FixAllCommand.php |
| FR07 binary validation | src/Tool/Runner/*Runner.php (existing, extend as needed) |
| FR13/16/18/19 warning/exit codes | src/Tool/Runner/{Tool}/ + ToolRunResult.hasWarnings |
| FR20-24 report generation | src/Tool/Runner/{Tool}/Finding+Summary DTOs + ReportWriter |
| FR31-35 TYPO3 versioning | config/rector-typo3-*.php, config/fractor-typo3-*.php |
| FR36-38 EditorConfig | src/Console/Command/EditorConfigCommand.php + EditorConfig/ runner |
| NFR14 report schema | config/schema/report-schema.json |
| FR41 schema docs | docs/developer-guide/report-schema.md |
| Contracts extraction | cpsit/quality-tools-contracts (separate repo, future) |

## Architecture Validation Results

### Coherence Validation

**Decision Compatibility:** All decisions are compatible.
- PHP ^8.3 + Symfony ^6||^7 + PHPUnit ^11: no version conflicts
- Dual-output mode (single tool invocation, JSON parsing, terminal rendering from DTOs)
  is compatible with OutputCollectorInterface; no new abstractions required
- LintAllCommand/FixAllCommand reuse ToolRunnerRegistry without modification
- hasWarnings flag is an additive change to ToolRunResult; no breaking change to existing runners
- TYPO3 versioned config files are purely additive; no code changes required

**Pattern Consistency:** Consistent throughout.
- {ToolName}Finding, {ToolName}AnalysisSummary, {ToolName}IssueSeverity follow existing
  DTO and enum naming conventions
- EditorConfig runner follows the parameterized command + ToolRunnerInterface pattern exactly
- LintAllCommand/FixAllCommand follow the thin command adapter pattern
- Runner migration to subdirectories is a mechanical move with no logic changes

**Structure Alignment:** Structure supports all decisions.
- Per-tool subdirectory pattern for Finding/Summary DTOs is clearly defined with migration rules
- New config schema file and documentation files fit cleanly in existing layout
- Contracts package boundary is defined even though extraction is deferred

---

### Requirements Coverage Validation

**Functional Requirements (41/41 covered):**

- FR01/02: existing per-tool commands (no change)
- FR03/04: LintAllCommand, FixAllCommand
- FR05/06: existing --path, --config options (no change)
- FR07: existing binary validation in runners
- FR08-10: existing config commands
- FR11/12: existing configuration system (Issue 025 fix pending)
- FR13/18/19: warning detection (hasWarnings on ToolRunResult) + tolerateWarnings config
- FR14/15: existing ConfigurationLoader + SecurityService
- FR16/17: exit code logic in command layer + existing non-interactive mode
- FR20-24: dual-output mode + ReportWriter + report-schema.json
- FR25-30/40: explicitly out-of-package (separate aggregation service)
- FR31-35: versioned config files + release branch strategy
- FR36-37: EditorConfigCommand + EditorConfigRunner
- FR38: ConfigInitRunner extension for .editorconfig provisioning
- FR39: terminal rendering from Finding DTOs, not raw tool output
- FR41: docs/developer-guide/report-schema.md

**Non-Functional Requirements (16/16 covered):**

- NFR01/04: single tool invocation for dual output eliminates overhead
- NFR02: ConfigurationLoader caching already in place
- NFR03: LintAllCommand streams per-runner output; no full buffering
- NFR05-08: SecurityService at entry points; ProcessExecutor array args; env var trait;
  DisposableTemporaryFile -- all existing
- NFR09: aggregation service concern (out-of-package)
- NFR10-13: existing quality gates; 95% coverage target; PHPStan level 6
- NFR14: report-schema.json with schemaVersion field
- NFR15: --report-format/--report-file added to all existing lint commands (migration story)
- NFR16: aggregation service API contract (out-of-package documentation)

---

### Gap Analysis Results

**Gap 1 -- ReportWriter location (important):**
The ReportWriter service is referenced in boundaries but not placed in the structure.
Resolution: src/Tool/Report/ReportWriter.php and src/Tool/Report/ReportWriterInterface.php.
Reads ToolRunResult.findings, serializes to the requested format (JSON, HTML, Markdown),
writes to stdout or the specified file path.

**Gap 2 -- ToolRunResult modification as named prerequisite (important):**
Adding a findings collection (array of FindingInterface) to ToolRunResult is the
foundational change for the report pipeline. It must be the first story in the report
pipeline epic: extend ToolRunResult with a typed findings collection and update all
existing runners to return an empty collection by default. No behaviour change; sets
the contract for subsequent per-tool Finding implementation.

**Gap 3 -- --report-format/--report-file migration story (important):**
NFR15 requires these options on all lint commands. All existing commands
(RectorCommand, PhpStanCommand, PhpCsFixerCommand, FractorCommand,
TypoScriptLintCommand, ComposerNormalizeCommand) need the options added in a single
migration story before per-tool report generation is implemented. Add to the
implementation sequence between steps 1 and 6 (after ToolRunResult extension).

**Gap 4 -- Warning detection is retroactive (important):**
The hasWarnings flag pattern applies to all existing runners, not only new ones.
The fail-on-warnings feature (FR13/FR18/FR19) requires updating all runners to
detect their tool-specific warning patterns. This is a single story covering all
existing runners, not a per-runner incremental task.

---

### Architecture Completeness Checklist

**Requirements Analysis**
- [x] Project context thoroughly analyzed
- [x] Scale and complexity assessed (medium, brownfield)
- [x] Technical constraints identified
- [x] Cross-cutting concerns mapped

**Architectural Decisions**
- [x] Critical decisions documented (report pipeline, orchestration, warning detection)
- [x] Technology stack fully specified (existing + confirmed)
- [x] Integration patterns defined (dual-output, orchestrator, warning flag)
- [x] Performance considerations addressed (single invocation, streaming)
- [x] Security model confirmed (no new entry points; existing SecurityService applies)

**Implementation Patterns**
- [x] Naming conventions established (Finding, AnalysisSummary, IssueSeverity)
- [x] Structure patterns defined (per-tool subdirectory, dual-output mandatory)
- [x] Communication patterns specified (ToolRunResult.findings, hasWarnings)
- [x] Anti-patterns documented (separate lint/fix classes, OutputInterface in runners, etc.)

**Project Structure**
- [x] New directories and files defined per feature area
- [x] Component boundaries established (command/runner, report pipeline, config, contracts)
- [x] Requirements to structure mapping complete
- [x] Migration path for existing runners defined

---

### Architecture Readiness Assessment

**Overall Status:** READY FOR IMPLEMENTATION (with four gap stories to add to backlog)

**Confidence level:** High. The existing architecture is proven; new decisions extend it
without contradicting it. All gaps are specification clarifications, not design unknowns.

**Key strengths:**
- Brownfield foundation is solid; new decisions are additive, not disruptive
- Dual-output mode eliminates tool-double-invocation risk without new abstractions
- Contracts extraction strategy is pragmatic and decoupled from upgrade-analyser timeline
- Implementation sequence is clearly ordered with explicit prerequisites
- Warning detection is cleanly contained in the runner layer

**Areas for future enhancement:**
- HTML/Markdown report rendering (deferred to Phase 2; JSON schema is Phase 1)
- Contracts package extraction (deferred pending upgrade-analyser Epic 3/5-3)
- Global installation path resolution (deferred, Feature 018)
- GitLab issue triage may add stories to Phase 1 scope

### Implementation Handoff

**AI Agent Guidelines:**
- Read project-context.md and this document before implementing any story
- Follow all architectural decisions exactly as documented; open an ADR before deviating
- Respect the dual-output mode pattern -- never invoke a tool twice in one command
- Check the implementation sequence constraints before starting any story
- Place Finding/Summary DTOs in the tool-specific Runner subdirectory, not in src/Reporting/

**First implementation priorities (in sequence):**
1. TYPO3 v14 config files (no code changes; can start immediately)
2. Issue 025 PathResolutionService fix (unblocks path-dependent features)
3. ToolRunResult extension with findings collection (foundation for report pipeline)
4. Warning detection in all existing runners (unblocks fail-on-warnings)
5. GitLab issue triage (scope validation before further Phase 1 stories)
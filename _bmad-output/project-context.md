---
project: quality-tools
generated: 2026-04-22
status: complete
optimized_for_llm: true
---

# Project Context: quality-tools

Critical rules and patterns AI agents must follow when implementing code in this
project. Focus is on unobvious details — do not repeat general PHP or Symfony
best practices that agents already know.

## Technology Stack & Versions

### Runtime
- PHP ^8.3 (current platform lock — note: supporting TYPO3 v12/v11/v10 requires
  lowering the PHP floor, as v12 supports PHP 8.1+)
- Composer-based package: `cpsit/quality-tools`

### Bundled Quality Tools
- PHPStan ^2.1 — level 6, 1G memory limit
- PHP CS Fixer ^3.45 — TYPO3 coding standards preset
- Rector (ssch/typo3-rector ^3.5) — targeting PHP 8.3 + TYPO3 13
- Fractor (a9f/typo3-fractor ~0.5.1) — TypoScript modernization
- EditorConfig CLI (armin/editorconfig-cli ^2.1)
- Composer Normalize (ergebnis/composer-normalize ^2.47)
- TypoScript Lint (helmich/typo3-typoscript-lint ^3.3)

### Test Dependencies
- PHPUnit ^11.0
- vfsStream (mikey179/vfsstream ^1.6) — filesystem mocking only

### Framework
- Symfony Console/DI/YAML/Process/Filesystem ^6.0 || ^7.0

### TYPO3 Version Support Matrix
| TYPO3 | Status | PHP | Priority |
|---|---|---|---|
| v14 (stable) | planned — required soon | ^8.3 | must-have |
| v13 (LTS) | active, current target | ^8.2 | supported |
| v12 (old stable) | nice to have | ^8.1 | optional |
| v11 (ELTS) | nice to have | ^7.4 / ^8.0 | optional |
| v10 (ELTS) | nice to have | ^7.2 | optional |

When adding new tool configurations or Rector/Fractor rules, verify
compatibility with v13 first, then v14. Do not break v13 support for v14
readiness. Lower-version support must not raise the PHP floor above 8.1
for v12, or break the Symfony ^6.0 || ^7.0 constraint.

## Language-Specific Rules

### PHP

- Every file must start with `declare(strict_types=1);`
- All concrete classes must be `final` unless designed for extension
- All properties, parameters, and return types must be fully declared —
  no untyped or mixed unless unavoidable
- Typed class constants are required for repeated string/int/array literals:
  - `private const string KEY = 'value'`
  - `private const int LIMIT = 100`
  - `private const array DEFAULTS = [...]`
  - Names in SCREAMING_SNAKE_CASE
- No Unicode characters anywhere — not in code, comments, strings, or docs.
  Use ASCII text equivalents (e.g. "->" not "->", "Done" not "checkmark")
- Exception hierarchy: all exceptions extend `QualityToolsException`.
  Use the most specific subtype available. Use `ErrorFactory` for
  standardized exception construction where it applies.
- Environment variable interpolation for config values goes through
  `EnvironmentVariableInterpolationTrait` — do not read `$_ENV` directly
  in business logic
- Namespace: `Cpsit\QualityTools\` -> `src/`, `Cpsit\QualityTools\Tests\` -> `tests/`

## Framework-Specific Rules

### Symfony Console — Command Layer

- Commands are thin input/output adapters (~30 lines). No business logic in commands.
- Command flow: unwrap options -> build `ToolRunRequest` DTO -> call runner -> render result.
- Commands depend only on `ToolRunnerRegistry` and `ToolRunInfoDisplay`. No service
  locator, no direct service injection beyond these two.
- Lint and fix variants of the same tool use one parameterized command class
  registered twice in `services.yaml` with different `$dryRun`, `$name`,
  `$description`, `$help` values. Do not create separate LintCommand/FixCommand
  classes for tools that only differ by dry-run mode.
- Config commands (`ConfigInit`, `ConfigShow`, `ConfigValidate`) are fully separate
  from tool commands and follow their own runner pattern with dedicated DTOs
  in `src/Console/Runner/DTO/`.

### Symfony DI

- Pure constructor injection throughout — no service locator, no `ContainerAwareTrait`.
- Tool runners are registered via DI tagging; the registry resolves them by tool name.
- Parameterized command instances are resolved by suffixed service IDs.
- New tool runners must implement `ToolRunnerInterface` and be tagged accordingly
  in `services.yaml`.

### Runner Layer

- Runners live in `src/Tool/Runner/` and implement `ToolRunnerInterface`.
- A runner must expose both `run(ToolRunRequest, OutputCollectorInterface): ToolRunResult`
  and `describe(ToolRunRequest): ToolRunDescription`.
- `ToolRunRequest` carries user intent only (tool name, dry-run flag, optional
  config override, optional path override). Runners resolve project root, vendor
  paths, config paths, and target paths internally via injected services.
- Live process output flows through `OutputCollectorInterface` (never `OutputInterface`
  directly). Runner-level diagnostics go into `ToolRunResult` as `Message` objects.
- One runner per tool, no inheritance between runners. Shared behavior is composed
  via injected services (`ProcessExecutor`, `ProjectEnvironment`, `MemoryOptimizer`).

## Testing Rules

### Test Organization

- Two suites: `Unit` (`tests/Unit/`) and `Integration` (`tests/Integration/`).
- Unit tests: isolated, no real filesystem or processes. Use vfsStream for
  filesystem operations and `BufferingOutputCollector` in place of `OutputInterface`.
- Integration tests: may touch the real filesystem and run real subprocesses.
  Fixtures live in `tests/Fixtures/` keyed by issue number (e.g. `022-testing-plan/`).
- Test class base types: extend `BaseTestCase` for standard units, `FilesystemTestCase`
  for tests needing vfsStream setup. Do not extend PHPUnit's `TestCase` directly.
- Shared mock factories in `tests/Unit/MockFactory.php` — use these before
  creating inline mocks for commonly mocked types.
- Support utilities (`ConfigurationAssertions`, `ConfigurationBuilder`) live in
  `tests/Support/` — use them for configuration-related assertions.

### Test Naming and Structure

- Test files mirror `src/` structure: `src/Foo/Bar.php` -> `tests/Unit/Foo/BarTest.php`.
- Test method names describe behavior: `testReturnsEmptyArrayWhenNoPathsFound()`,
  not `testGetPaths()`.

### PHPUnit Configuration

- `failOnWarning=true` and `failOnRisky=true` are set — deprecation notices and
  risky tests fail the suite.
- `beStrictAboutOutputDuringTests=true` — tests must not produce output directly.
- The `QT_TEST_MODE=true` env variable is set for all test runs; use it to
  distinguish test context in production code only if unavoidable.

### Coverage

- Run with `XDEBUG_MODE=coverage vendor/bin/phpunit --coverage-html .build/coverage/html`.
- `requireCoverageMetadata=false` — coverage attributes are not required on every method,
  but new code should include `#[CoversClass]` on test classes.

## Code Quality & Style Rules

### Quality Gate — mandatory before every commit

Run in order:
1. `composer lint:composer` — composer.json format
2. `composer lint:editorconfig` — file formatting (fix with `composer fix:editorconfig`)
3. `composer lint:php` — PHP CS Fixer dry-run
4. `composer lint:rector` — Rector dry-run
5. `composer sca:php` — PHPStan level 6, 1G memory limit

All five must pass with zero errors before committing. Fix with
`composer fix` (covers composer, PHP CS Fixer, Rector) then
`composer fix:editorconfig` separately if needed.

### Naming Conventions

- Classes: PascalCase. Interfaces: PascalCase + `Interface` suffix.
  Traits: PascalCase + `Trait` suffix. Enums: PascalCase.
- Files mirror class names exactly (PSR-4).
- Constants: SCREAMING_SNAKE_CASE with explicit type (`const string`, `const int`, `const array`).
- Test classes: `{ClassName}Test` in mirrored namespace under `tests/`.

### File & Folder Conventions

- Source: `src/` — grouped by responsibility layer, not by tool name.
  Layers: `Configuration/`, `Console/`, `DependencyInjection/`, `Exception/`,
  `Messaging/`, `Service/`, `Tool/`, `Traits/`, `Utility/`.
- DTOs are immutable value objects; place them in a `DTO/` subfolder of their layer.
- No utility functions — only classes with explicit responsibility.

### ADR Process

- Significant architectural decisions require an ADR in `docs/architecture/`
  using `NNNN-descriptive-title.md` with sequential numbering.
- Template: `docs/.templates/adr.md`. Never delete ADRs; mark as Deprecated or Superseded.
- Add entry to `docs/architecture/README.md` index table on creation.

## Development Workflow Rules

### Commit Messages

- Format: `[TAG] short description` — keep the description humble and factual.
- Tags: `[FEATURE]`, `[TASK]`, `[BUGFIX]`, `[CI]`, `[DOC]`, `[SECURITY]`,
  `[WIP]`, `[DRAFT]`, `[DDEV]`, `[RELEASE]`.
- Never commit without explicit user request. Never use `--no-verify`.
- No emoji or Unicode in commit messages.

### Branch Strategy

- Main branch: `master`. Development branch: `develop`.
- Feature branches: `feature/NNN-short-description`.
- Issue/bugfix branches: `issue/NNN-short-description`.
- Release branches: `release/X.Y.Z`.

### Definition of Done

A feature or bugfix is not done until:
- All unit and integration tests pass (`composer test`)
- All five lint/sca checks pass with zero errors (`composer lint && composer sca:php`)
- Documentation updated if behavior changed (user-guide or developer-guide)
- ADR created if an architectural decision was made

### Planning Artifacts

- Active features tracked in BMad stories in `_bmad-output/`.
- Historical planning docs in `docs/plan/` — do not delete, treat as read-only history.
- Completed items move to `done/` subfolder — never delete historical planning docs.
- Deferred items move to `deferred/` subfolder.
- Architecture decisions recorded as ADRs in `docs/architecture/`.

## Critical Don't-Miss Rules

### Architecture Anti-Patterns

- Do NOT add logic to commands beyond input unwrapping and output rendering.
  If you find yourself resolving paths, reading config, or building command strings
  inside a command class, that logic belongs in a runner or service.
- Do NOT add a new base class or trait to share behavior between runners.
  Compose shared behavior via injected services.
- Do NOT pass `OutputInterface` into runners. Use `OutputCollectorInterface` and
  let the command provide a `StreamingOutputCollector`.
- Do NOT use `$_ENV`, `getenv()`, or `putenv()` directly in business logic.
  Environment variable handling goes through `EnvironmentVariableInterpolationTrait`
  or `ProjectEnvironment`.

### Security Rules (see ADRs 0001-0004)

- All path inputs from users or config files must be validated through
  `SecurityService` before use in filesystem operations or process execution.
- Validation happens at entry points (commands, config loaders) — not scattered
  through runners or utilities.
- `SecurityException` must be thrown for any path traversal or injection attempt.
- Never concatenate user-supplied strings directly into shell commands. Use
  `ProcessExecutor` with array-form arguments only.

### Configuration Rules

- The project config file is `.quality-tools.yaml` in the TYPO3 project root.
- Config loading uses a hierarchy: package defaults -> project config -> CLI overrides.
  Do not bypass `ConfigurationLoader` to read config files directly.
- Tool-specific config file overrides are detected via `ConfigurationLoader::resolveToolConfigPath()`.
  Do not hardcode config file paths in runners.
- `ConfigurationValidator` enforces the JSON schema in `config/schema/`. When adding
  new config keys, update the schema and the validator.

### Common Mistakes

- vfsStream does not support all filesystem operations — test real-filesystem edge
  cases in `tests/Integration/`, not `tests/Unit/`.
- `ToolRunRequest` is immutable. Do not attempt to mutate it inside runners; create
  a new instance if variant behavior is needed.
- PHPStan runs with `--memory-limit=1G`. Do not lower this; large TYPO3 projects
  exhaust memory at the default limit (the original reason for this package).
- `composer fix` does NOT fix editorconfig violations. Run `composer fix:editorconfig`
  separately or CI will fail on whitespace/line-ending issues.

---

## Usage Guidelines

For AI agents:
- Read this file before implementing any code in this project.
- Follow all rules exactly as documented.
- When in doubt, prefer the more restrictive option.
- Propose updates to this file when new patterns emerge.

For humans:
- Keep this file lean and focused on what agents actually need reminding of.
- Update when the technology stack or architectural patterns change.
- Remove rules that have become obvious or are now enforced by tooling.

Last updated: 2026-04-22

# Story 1.1: Add versioned Rector configurations for TYPO3 v13 and v14

Status: done

## Story

As a package maintainer,
I want versioned Rector configuration files for TYPO3 v13 and v14 to coexist on the main branch,
so that developers can analyze code against v14 rules immediately while v13 configs remain
available for projects not yet upgrading.

## Acceptance Criteria

1. `config/rector-typo3-13.php` exists and contains the v13 Rector rules equivalent to the
   current `config/rector.php` (no functional change for v13 users).
2. `config/rector-typo3-14.php` exists and contains Rector rules targeting TYPO3 v14.3 and
   PHP 8.3+.
3. `config/rector.php` is updated to require/include `config/rector-typo3-14.php` so that
   running `vendor/bin/qt lint:rector` without `--config` applies v14 rules.
4. Running `vendor/bin/qt lint:rector --config config/rector-typo3-13.php` applies v13 rules
   without error.
5. All existing tests pass with zero linting errors after the change.

## Tasks / Subtasks

- [x] Create `config/rector-typo3-13.php` (AC: 1)
  - [x] Copy the full content of `config/rector.php` verbatim into `config/rector-typo3-13.php`
  - [x] Update the leading comment to identify this as the v13-targeted configuration
- [x] Create `config/rector-typo3-14.php` (AC: 2)
  - [x] Use `Typo3LevelSetList::UP_TO_TYPO3_14` instead of `UP_TO_TYPO3_13`
  - [x] Update `ExtEmConfRector` constraints to target TYPO3 14.x
  - [x] Keep `PhpVersion::PHP_83` (package targets PHP ^8.3, not ^8.4)
  - [x] Keep all skip rules and paths identical to the v13 config
- [x] Update `config/rector.php` to delegate to `config/rector-typo3-14.php` (AC: 3)
  - [x] Replace inline configuration with a `require` of `config/rector-typo3-14.php`
  - [x] Add a comment explaining that this is the stable-default alias for the current version
- [x] Wire `rector.level` config to versioned config file selection in `RectorRunner` (AC: 4, backward compat)
  - [x] In `RectorRunner::resolveConfigPath()`, load the rector config and map `level` to `rector-{level}.php`
  - [x] Fall back to `rector.php` alias if the versioned file does not exist
  - [x] Add unit test covering level-based config selection
- [x] Verify all quality gates pass (AC: 5)
  - [x] Run `composer lint:composer`
  - [x] Run `composer lint:editorconfig`
  - [x] Run `composer lint:php`
  - [x] Run `composer lint:rector` (dry-run, must show zero errors against this package itself)
  - [x] Run `composer sca:php`
  - [x] Run `composer test` (all unit tests must pass)

## Dev Notes

### Scope: config files only -- no src/ changes

This story touches only the `config/` directory. No PHP source files under `src/` are modified.
No `composer.json` changes are needed: `ssch/typo3-rector ^3.5` already resolves to v3.14.1,
which ships full TYPO3 v14 support.

### Available constants in installed ssch/typo3-rector v3.14.1

The following constants exist and are ready to use:

```php
// For v14 config:
use Ssch\TYPO3Rector\Set\Typo3LevelSetList;
Typo3LevelSetList::UP_TO_TYPO3_14
// This constant expands to: sets([UP_TO_TYPO3_13, TYPO3_14])
// so no need to list both manually.

use Ssch\TYPO3Rector\Set\Typo3SetList;
Typo3SetList::TYPO3_14   // the v14-only set (without cumulative history)
```

Do NOT use `Typo3SetList::TYPO3_14` alone -- it is the delta only. Always use
`Typo3LevelSetList::UP_TO_TYPO3_14` to include all prior rules cumulatively.

### ExtEmConfRector version constraint for v14

The current `config/rector.php` has:

```php
->withConfiguredRule(ExtEmConfRector::class, [
    ExtEmConfRector::PHP_VERSION_CONSTRAINT => '8.2.0-8.3.99',
    ExtEmConfRector::TYPO3_VERSION_CONSTRAINT => '13.4.0-13.4.99',
    ExtEmConfRector::ADDITIONAL_VALUES_TO_BE_REMOVED => [],
])
```

For `config/rector-typo3-14.php`, update to:

```php
->withConfiguredRule(ExtEmConfRector::class, [
    ExtEmConfRector::PHP_VERSION_CONSTRAINT => '8.2.0-8.3.99',
    ExtEmConfRector::TYPO3_VERSION_CONSTRAINT => '14.0.0-14.99.99',
    ExtEmConfRector::ADDITIONAL_VALUES_TO_BE_REMOVED => [],
])
```

Keep the PHP range as `8.2.0-8.3.99`. TYPO3 v14 minimum is PHP 8.2; the quality-tools
package targets PHP ^8.3 but the ExtEmConf value reflects the project's PHP floor.

### PHP version constant

Use `PhpVersion::PHP_83` in both versioned configs. `PHP_84` exists in Rector but is not
appropriate here: the package declares `php: ^8.3` in composer.json.

### rector.php alias pattern

The simplest correct pattern for the alias file is a direct `return require`:

```php
<?php

declare(strict_types=1);

// This file is the stable-default alias for the current active TYPO3 target.
// To target a specific TYPO3 version, pass --config to qt lint:rector.
return require __DIR__ . '/rector-typo3-14.php';
```

This works because `rector-typo3-14.php` already returns a `RectorConfig` instance via
`RectorConfig::configure()->...`. The `return require` forwards the return value.

### Path resolution: $installPath

The current `config/rector.php` resolves the TYPO3 project root via
`\Composer\InstalledVersions::getInstalledPackagesByType('project')`. This logic is in
`config/rector-typo3-13.php` and `config/rector-typo3-14.php`. The alias `config/rector.php`
does not need to repeat it -- it simply delegates.

### Skip rules to keep unchanged

Both versioned files must keep the existing skip rules verbatim:

```php
->withSkip([
    $installPath . '/**/Configuration/ExtensionBuilder/*',
    NameImportingPostRector::class => [
        'ext_localconf.php',
        'ext_tables.php',
    ],
])
```

### Scan paths

Both versioned configs use the same paths:

```php
$scanPaths = [
    $installPath . '/config/system',
    $installPath . '/packages',
];
```

Do not add or remove paths. Path extension is covered by Epic 6 (Story 6.2), not this story.

### Project Structure Notes

Files to create/modify:

| Action | File |
|--------|------|
| Create | `config/rector-typo3-13.php` |
| Create | `config/rector-typo3-14.php` |
| Modify | `config/rector.php` (replace body with alias redirect) |

No other files change. Do not modify `src/`, `tests/`, `composer.json`, or any YAML config.

### References

- Current rector config: `config/rector.php`
- Architecture TYPO3 version config strategy: `_bmad-output/planning-artifacts/architecture.md`
  section "TYPO3 Version Config Strategy (FR31-35)"
- Installed set source: `vendor/ssch/typo3-rector/src/Set/Typo3LevelSetList.php`
- Level set content: `vendor/ssch/typo3-rector/config/level/up-to-typo3-14.php`
- GitLab issue: GL#14 (https://gitlab.321.works/DevOps/testing/quality-tools/-/work_items/14)
- Sprint milestone: Epic 1 - TYPO3 v14 Compatibility

## Tracker References

- GitLab: [GL#14](https://gitlab.321.works/DevOps/testing/quality-tools/-/work_items/14)

## Dev Agent Record

### Agent Model Used

claude-sonnet-4-6

### Debug Log References

### Completion Notes List

- Created `config/rector-typo3-13.php` with v13 rules (Typo3LevelSetList::UP_TO_TYPO3_13, TYPO3 version constraint 13.4.0-13.4.99).
- Created `config/rector-typo3-14.php` with v14 rules (Typo3LevelSetList::UP_TO_TYPO3_14, TYPO3 version constraint 14.0.0-14.99.99). Both use PhpVersion::PHP_83.
- Replaced `config/rector.php` body with a `return require` alias delegating to `rector-typo3-14.php`.
- Fixed missing backward-compat requirement: `RectorRunner::resolveConfigPath()` now reads `rector.level` from the loaded configuration and maps it to the corresponding versioned config file (e.g. `rector-typo3-13.php`). Falls back to `rector.php` for unknown levels.
- Updated `RectorRunnerTest` and the integration test in `ToolCommandPathConfigurationTest` to reflect the new config resolution behavior.
- All quality gates passed: composer lint:composer, lint:editorconfig, lint:php, lint:rector, sca:php, and all 1155 unit+integration tests pass.

### File List

- config/rector-typo3-13.php (created)
- config/rector-typo3-14.php (created)
- config/rector.php (modified)
- src/Tool/Runner/RectorRunner.php (modified)
- tests/Unit/Tool/Runner/RectorRunnerTest.php (modified)
- tests/Integration/Console/Command/ToolCommandPathConfigurationTest.php (modified)

### Review Findings

- [x] [Review][Decision] RESOLVED (option 1): DEFAULT_RECTOR_LEVEL changed to 'typo3-14'. Updated ConfigurationInterface, schema enum/default, ToolConfigService, ConfigurationTemplateGenerator (4 templates), and 3 test assertions. [src/Configuration/ConfigurationInterface.php:32]
- [x] [Review][Patch] FIXED: `level` value validated against ALLOWED_RECTOR_LEVELS before path construction; invalid values fall back to DEFAULT_RECTOR_LEVEL. [src/Tool/Runner/RectorRunner.php:126, src/Configuration/ConfigurationInterface.php:33]
- [x] [Review][Defer] $installPath undefined when count($installedProjects) > 1: error_log silently continues into undefined variable [config/rector-typo3-13.php:23, config/rector-typo3-14.php:23] — deferred, pre-existing pattern from original rector.php
- [x] [Review][Defer] getInstallPath() can return null, string concatenation produces broken paths silently [config/rector-typo3-13.php:28, config/rector-typo3-14.php:29] — deferred, pre-existing pattern from original rector.php
- [x] [Review][Defer] configLoader->load() called twice per resolveConfigPath invocation if loader is not cached [src/Tool/Runner/RectorRunner.php:124] — deferred, needs loader caching investigation
- [x] [Review][Defer] Fallback rector.php path returned without existence check [src/Tool/Runner/RectorRunner.php:131] — deferred, pre-existing pattern
- [x] [Review][Defer] No test covers the branch where resolveToolConfigPath returns a non-null discovered path, bypassing level logic [tests/Unit/Tool/Runner/RectorRunnerTest.php] — deferred, pre-existing test gap
- [x] [Review][Defer] setUp mock in RectorRunnerTest does not stub getResolvedPathsForTool, relying on PHPUnit null return [tests/Unit/Tool/Runner/RectorRunnerTest.php:44] — deferred, pre-existing test setup pattern

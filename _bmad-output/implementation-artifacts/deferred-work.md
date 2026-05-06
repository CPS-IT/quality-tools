# Deferred Work

## Deferred from: code review of 1-1-add-versioned-rector-configurations-for-typo3-v13-and-v14 (2026-05-06)

- `$installPath` undefined when count($installedProjects) > 1: error_log continues silently into undefined variable use. Pre-existing pattern copied from original rector.php. [config/rector-typo3-13.php:23, config/rector-typo3-14.php:23]
- `getInstallPath()` can return null, string concatenation produces broken paths silently. Pre-existing pattern. [config/rector-typo3-13.php:28, config/rector-typo3-14.php:29]
- `configLoader->load()` called twice per `resolveConfigPath` invocation if loader is not cached. Needs loader caching investigation. [src/Tool/Runner/RectorRunner.php:124]
- Fallback `rector.php` path returned without existence check. Pre-existing pattern. [src/Tool/Runner/RectorRunner.php:131]
- No test covers the branch where `resolveToolConfigPath` returns a non-null discovered path, bypassing level logic. Pre-existing test gap. [tests/Unit/Tool/Runner/RectorRunnerTest.php]
- `setUp` mock in `RectorRunnerTest` does not stub `getResolvedPathsForTool`, relying on PHPUnit null return for arrays. Pre-existing test setup pattern. [tests/Unit/Tool/Runner/RectorRunnerTest.php:44]

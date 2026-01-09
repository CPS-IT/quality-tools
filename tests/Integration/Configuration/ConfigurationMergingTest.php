<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Tests\Integration\Configuration;

use Cpsit\QualityTools\Configuration\Configuration;
use Cpsit\QualityTools\Configuration\YamlConfigurationLoader;
use Cpsit\QualityTools\Tests\Unit\TestHelper;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Cpsit\QualityTools\Configuration\YamlConfigurationLoader
 * @covers \Cpsit\QualityTools\Configuration\Configuration
 * @covers \Cpsit\QualityTools\Configuration\ConfigurationValidator
 */
final class ConfigurationMergingTest extends TestCase
{
    private YamlConfigurationLoader $loader;
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = TestHelper::createTempDirectory('config_merging_test_');
        $this->loader = new YamlConfigurationLoader();
    }

    protected function tearDown(): void
    {
        TestHelper::removeDirectory($this->tempDir);
    }

    public function testThreeTierConfigurationMerging(): void
    {
        $homeDir = $this->tempDir . '/home';
        mkdir($homeDir, 0o777, true);

        // Global configuration (user home)
        $globalConfig = <<<YAML
            quality-tools:
              project:
                php_version: "8.4"
                typo3_version: "12.4"
              tools:
                rector:
                  enabled: true
                  level: "typo3-12"
                  dry_run: false
                phpstan:
                  enabled: true
                  level: 5
                  memory_limit: "512M"
                fractor:
                  enabled: true
                  indentation: 2
              output:
                verbosity: "verbose"
                colors: false
                progress: true
              performance:
                parallel: false
                max_processes: 2
                cache_enabled: true
              paths:
                exclude:
                  - "var/"
                  - "vendor/"
                  - "build/"
            YAML;
        file_put_contents($homeDir . '/.quality-tools.yaml', $globalConfig);

        // Project configuration (overrides some global settings)
        $projectConfig = <<<YAML
            quality-tools:
              project:
                name: "merge-test-project"
                typo3_version: "13.4"
              tools:
                rector:
                  level: "typo3-13"
                  dry_run: true
                phpstan:
                  level: 8
                  paths:
                    scan:
                      - "src/"
                      - "packages/"
                php-cs-fixer:
                  enabled: true
                  preset: "typo3"
                  cache: false
              output:
                colors: true
                verbosity: "normal"
              performance:
                max_processes: 6
              paths:
                scan:
                  - "packages/"
                  - "src/"
                  - "tests/"
                exclude:
                  - "var/"
                  - "vendor/"
                  - "node_modules/"
            YAML;
        file_put_contents($this->tempDir . '/.quality-tools.yaml', $projectConfig);

        $config = TestHelper::withEnvironment(
            ['HOME' => $homeDir],
            fn (): \Cpsit\QualityTools\Configuration\Configuration => $this->loader->load($this->tempDir),
        );

        // Test project-level overrides
        self::assertSame('merge-test-project', $config->getProjectName());
        self::assertSame('8.4', $config->getProjectPhpVersion()); // from global
        self::assertSame('13.4', $config->getProjectTypo3Version()); // project override

        // Test tool configuration merging
        $rectorConfig = $config->getRectorConfig();
        self::assertTrue($rectorConfig['enabled']); // from global
        self::assertSame('typo3-13', $rectorConfig['level']); // project override
        self::assertTrue($rectorConfig['dry_run']); // project override
        self::assertSame('8.4', $rectorConfig['php_version']); // inherited from project php_version

        $phpStanConfig = $config->getPhpStanConfig();
        self::assertTrue($phpStanConfig['enabled']); // from global
        self::assertSame(8, $phpStanConfig['level']); // project override
        self::assertSame('512M', $phpStanConfig['memory_limit']); // from global
        // Test PHPStan path overrides
        $phpStanPaths = $config->getToolPaths('phpstan');
        self::assertSame(['src/', 'packages/'], $phpStanPaths['scan'] ?? []); // from project

        $phpCsFixerConfig = $config->getPhpCsFixerConfig();
        self::assertTrue($phpCsFixerConfig['enabled']); // from project
        self::assertSame('typo3', $phpCsFixerConfig['preset']); // from project
        self::assertFalse($phpCsFixerConfig['cache']); // from project

        // Test output configuration merging
        self::assertSame('normal', $config->getVerbosity()); // project override
        self::assertTrue($config->isColorsEnabled()); // project override
        self::assertTrue($config->isProgressEnabled()); // from global

        // Test performance configuration merging
        self::assertFalse($config->isParallelEnabled()); // from global
        self::assertSame(6, $config->getMaxProcesses()); // project override
        self::assertTrue($config->isCacheEnabled()); // from global

        // Test paths configuration merging
        self::assertSame(['packages/', 'src/', 'tests/'], $config->getScanPaths()); // from project
        self::assertSame(['var/', 'vendor/', 'node_modules/'], $config->getExcludePaths()); // project override
    }

    public function testComplexEnvironmentVariableInterpolation(): void
    {
        $configWithEnvVars = <<<YAML
            quality-tools:
              project:
                name: "\${PROJECT_NAME:-default-project}"
                php_version: "\${PHP_VERSION:-8.3}"
                typo3_version: "\${TYPO3_VERSION:-13.4}"
              tools:
                rector:
                  enabled: \${RECTOR_ENABLED:-true}
                  level: "\${RECTOR_LEVEL:-typo3-13}"
                  dry_run: \${RECTOR_DRY_RUN:-false}
                phpstan:
                  enabled: \${PHPSTAN_ENABLED:-true}
                  level: \${PHPSTAN_LEVEL:-6}
                  memory_limit: "\${PHPSTAN_MEMORY:-1G}"
                php-cs-fixer:
                  enabled: \${PHP_CS_FIXER_ENABLED:-true}
                  preset: "\${PHP_CS_FIXER_PRESET:-typo3}"
                  cache: \${PHP_CS_FIXER_CACHE:-true}
              paths:
                scan:
                  - "\${PRIMARY_SCAN_PATH:-packages/}"
                  - "\${SECONDARY_SCAN_PATH:-src/}"
                  - "\${TERTIARY_SCAN_PATH:-tests/}"
                exclude:
                  - "\${VAR_PATH:-var/}"
                  - "\${VENDOR_PATH:-vendor/}"
                  - "\${NODE_MODULES_PATH:-node_modules/}"
              output:
                verbosity: "\${OUTPUT_VERBOSITY:-normal}"
                colors: \${OUTPUT_COLORS:-true}
                progress: \${OUTPUT_PROGRESS:-true}
              performance:
                parallel: \${PARALLEL_ENABLED:-true}
                max_processes: \${MAX_PROCESSES:-4}
                cache_enabled: \${CACHE_ENABLED:-true}
            YAML;

        file_put_contents($this->tempDir . '/.quality-tools.yaml', $configWithEnvVars);

        // Test with some environment variables set, some using defaults
        $config = TestHelper::withEnvironment([
            'PROJECT_NAME' => 'env-integration-test',
            'PHP_VERSION' => '8.4',
            'RECTOR_LEVEL' => 'typo3-12',
            'PHPSTAN_LEVEL' => '8',
            'PHPSTAN_MEMORY' => '2G',
            'PHP_CS_FIXER_CACHE' => 'false',
            'PRIMARY_SCAN_PATH' => 'custom-packages/',
            'TERTIARY_SCAN_PATH' => 'integration-tests/',
            'OUTPUT_VERBOSITY' => 'verbose',
            'OUTPUT_COLORS' => 'false',
            'MAX_PROCESSES' => '8',
            // Other variables not set - should use defaults
        ], fn (): \Cpsit\QualityTools\Configuration\Configuration => $this->loader->load($this->tempDir));

        // Test interpolated project settings
        self::assertSame('env-integration-test', $config->getProjectName());
        self::assertSame('8.4', $config->getProjectPhpVersion());
        self::assertSame('13.4', $config->getProjectTypo3Version()); // default

        // Test interpolated tool settings
        $rectorConfig = $config->getRectorConfig();
        self::assertTrue($rectorConfig['enabled']); // default
        self::assertSame('typo3-12', $rectorConfig['level']); // from env
        self::assertFalse($rectorConfig['dry_run']); // default

        $phpStanConfig = $config->getPhpStanConfig();
        self::assertSame(8, $phpStanConfig['level']); // from env
        self::assertSame('2G', $phpStanConfig['memory_limit']); // from env

        $phpCsFixerConfig = $config->getPhpCsFixerConfig();
        self::assertSame('typo3', $phpCsFixerConfig['preset']); // default
        self::assertFalse($phpCsFixerConfig['cache']); // from env

        // Test interpolated paths
        self::assertSame(
            ['custom-packages/', 'src/', 'integration-tests/'],
            $config->getScanPaths(),
        );
        self::assertSame(
            ['var/', 'vendor/', 'node_modules/'],
            $config->getExcludePaths(),
        ); // all defaults

        // Test interpolated output settings
        self::assertSame('verbose', $config->getVerbosity()); // from env
        self::assertFalse($config->isColorsEnabled()); // from env
        self::assertTrue($config->isProgressEnabled()); // default

        // Test interpolated performance settings
        self::assertTrue($config->isParallelEnabled()); // default
        self::assertSame(8, $config->getMaxProcesses()); // from env
        self::assertTrue($config->isCacheEnabled()); // default
    }

    public function testEnvironmentVariableTypeCasting(): void
    {
        $configWithTypeVariations = <<<YAML
            quality-tools:
              tools:
                rector:
                  enabled: \${RECTOR_ENABLED_STRING}
                  dry_run: \${RECTOR_DRY_RUN_STRING}
                phpstan:
                  level: \${PHPSTAN_LEVEL_STRING}
                fractor:
                  indentation: \${FRACTOR_INDENTATION_STRING}
              output:
                colors: \${OUTPUT_COLORS_STRING}
                progress: \${OUTPUT_PROGRESS_STRING}
              performance:
                parallel: \${PARALLEL_STRING}
                max_processes: \${MAX_PROCESSES_STRING}
                cache_enabled: \${CACHE_ENABLED_STRING}
            YAML;

        file_put_contents($this->tempDir . '/.quality-tools.yaml', $configWithTypeVariations);

        $config = TestHelper::withEnvironment([
            'RECTOR_ENABLED_STRING' => 'false',
            'RECTOR_DRY_RUN_STRING' => 'true',
            'PHPSTAN_LEVEL_STRING' => '8',
            'FRACTOR_INDENTATION_STRING' => '4',
            'OUTPUT_COLORS_STRING' => 'false',
            'OUTPUT_PROGRESS_STRING' => 'true',
            'PARALLEL_STRING' => 'false',
            'MAX_PROCESSES_STRING' => '12',
            'CACHE_ENABLED_STRING' => 'false',
        ], fn (): \Cpsit\QualityTools\Configuration\Configuration => $this->loader->load($this->tempDir));

        // Verify that string values are properly interpreted as appropriate types
        $rectorConfig = $config->getRectorConfig();
        self::assertFalse($rectorConfig['enabled']); // string "false" -> boolean false
        self::assertTrue($rectorConfig['dry_run']); // string "true" -> boolean true

        $phpStanConfig = $config->getPhpStanConfig();
        self::assertSame(8, $phpStanConfig['level']); // string "8" -> integer 8

        $fractorConfig = $config->getFractorConfig();
        self::assertSame(4, $fractorConfig['indentation']); // string "4" -> integer 4

        self::assertFalse($config->isColorsEnabled()); // string "false" -> boolean false
        self::assertTrue($config->isProgressEnabled()); // string "true" -> boolean true
        self::assertFalse($config->isParallelEnabled()); // string "false" -> boolean false
        self::assertSame(12, $config->getMaxProcesses()); // string "12" -> integer 12
        self::assertFalse($config->isCacheEnabled()); // string "false" -> boolean false
    }

    public function testNestedConfigurationOverrides(): void
    {
        $homeDir = $this->tempDir . '/home';
        mkdir($homeDir, 0o777, true);

        // Global config with complex nested structure
        $globalConfig = <<<YAML
            quality-tools:
              tools:
                rector:
                  enabled: true
                  level: "typo3-12"
                  dry_run: false
                  php_version: "8.3"
                phpstan:
                  enabled: true
                  level: 5
                  memory_limit: "1G"
                  paths:
                    scan:
                      - "default-path/"
                fractor:
                  enabled: true
                  indentation: 2
                  paths:
                    exclude:
                      - "global-skip.ts"
                php-cs-fixer:
                  enabled: true
                  preset: "psr12"
                  cache: true
                typoscript-lint:
                  enabled: true
                  indentation: 2
                  paths:
                    exclude:
                      - "global-ignore.ts"
            YAML;
        file_put_contents($homeDir . '/.quality-tools.yaml', $globalConfig);

        // Project config with selective overrides
        $projectConfig = <<<YAML
            quality-tools:
              tools:
                rector:
                  level: "typo3-13"
                  dry_run: true
                  # enabled and php_version should remain from global
                phpstan:
                  level: 8
                  paths:
                    scan:
                      - "src/"
                      - "packages/"
                  # enabled and memory_limit should remain from global
                fractor:
                  indentation: 4
                  paths:
                    exclude:
                      - "project-skip.ts"
                      - "another-skip.ts"
                  # enabled should remain from global
                php-cs-fixer:
                  preset: "typo3"
                  # enabled and cache should remain from global
                typoscript-lint:
                  paths:
                    exclude:
                      - "project-ignore.ts"
                  # enabled and indentation should remain from global
            YAML;
        file_put_contents($this->tempDir . '/.quality-tools.yaml', $projectConfig);

        $config = TestHelper::withEnvironment(
            ['HOME' => $homeDir],
            fn (): \Cpsit\QualityTools\Configuration\Configuration => $this->loader->load($this->tempDir),
        );

        // Test rector merging
        $rectorConfig = $config->getRectorConfig();
        self::assertTrue($rectorConfig['enabled']); // from global
        self::assertSame('typo3-13', $rectorConfig['level']); // project override
        self::assertTrue($rectorConfig['dry_run']); // project override
        self::assertSame('8.3', $rectorConfig['php_version']); // from global

        // Test PHPStan merging
        $phpStanConfig = $config->getPhpStanConfig();
        self::assertTrue($phpStanConfig['enabled']); // from global
        self::assertSame(8, $phpStanConfig['level']); // project override
        self::assertSame('1G', $phpStanConfig['memory_limit']); // from global
        // Test PHPStan path overrides
        $phpStanPaths = $config->getToolPaths('phpstan');
        self::assertSame(['src/', 'packages/'], $phpStanPaths['scan'] ?? []); // project override

        // Test Fractor merging
        $fractorConfig = $config->getFractorConfig();
        self::assertTrue($fractorConfig['enabled']); // from global
        self::assertSame(4, $fractorConfig['indentation']); // project override

        // Test Fractor path overrides
        $fractorPaths = $config->getToolPaths('fractor');
        self::assertSame(['project-skip.ts', 'another-skip.ts'], $fractorPaths['exclude'] ?? []); // project override

        // Test PHP CS Fixer merging
        $phpCsFixerConfig = $config->getPhpCsFixerConfig();
        self::assertTrue($phpCsFixerConfig['enabled']); // from global
        self::assertSame('typo3', $phpCsFixerConfig['preset']); // project override
        self::assertTrue($phpCsFixerConfig['cache']); // from global

        // Test TypoScript Lint merging
        $typoscriptLintConfig = $config->getTypoScriptLintConfig();
        self::assertTrue($typoscriptLintConfig['enabled']); // from global
        self::assertSame(2, $typoscriptLintConfig['indentation']); // from global

        // Test TypoScript Lint path overrides
        $typoscriptLintPaths = $config->getToolPaths('typoscript-lint');
        self::assertSame(['project-ignore.ts'], $typoscriptLintPaths['exclude'] ?? []); // project override
    }

    public function testArrayMergingBehavior(): void
    {
        $homeDir = $this->tempDir . '/home';
        mkdir($homeDir, 0o777, true);

        // Global config with arrays
        $globalConfig = <<<YAML
            quality-tools:
              paths:
                scan:
                  - "global-scan1/"
                  - "global-scan2/"
                exclude:
                  - "global-exclude1/"
                  - "global-exclude2/"
              tools:
                phpstan:
                  paths:
                    scan:
                      - "global-phpstan1/"
                      - "global-phpstan2/"
            YAML;
        file_put_contents($homeDir . '/.quality-tools.yaml', $globalConfig);

        // Project config with arrays (should replace, not merge)
        $projectConfig = <<<YAML
            quality-tools:
              paths:
                scan:
                  - "project-scan/"
                exclude:
                  - "project-exclude1/"
                  - "project-exclude2/"
                  - "project-exclude3/"
              tools:
                phpstan:
                  paths:
                    scan:
                      - "project-phpstan/"
            YAML;
        file_put_contents($this->tempDir . '/.quality-tools.yaml', $projectConfig);

        $config = TestHelper::withEnvironment(
            ['HOME' => $homeDir],
            fn (): \Cpsit\QualityTools\Configuration\Configuration => $this->loader->load($this->tempDir),
        );

        // Arrays should be replaced by project config, not merged
        self::assertSame(['project-scan/'], $config->getScanPaths());
        self::assertSame(
            ['project-exclude1/', 'project-exclude2/', 'project-exclude3/'],
            $config->getExcludePaths(),
        );

        $phpStanPaths = $config->getToolPaths('phpstan');
        self::assertSame(['project-phpstan/'], $phpStanPaths['scan'] ?? []);
    }

    public function testConfigurationWithoutGlobalFile(): void
    {
        // Only project configuration, no global
        $projectConfig = <<<YAML
            quality-tools:
              project:
                name: "project-only"
                php_version: "8.4"
              tools:
                rector:
                  level: "typo3-13"
            YAML;
        file_put_contents($this->tempDir . '/.quality-tools.yaml', $projectConfig);

        // Set HOME to non-existent directory
        $config = TestHelper::withEnvironment(
            ['HOME' => '/nonexistent'],
            fn (): \Cpsit\QualityTools\Configuration\Configuration => $this->loader->load($this->tempDir),
        );

        // Should merge with package defaults only
        self::assertSame('project-only', $config->getProjectName());
        self::assertSame('8.4', $config->getProjectPhpVersion());
        self::assertSame('13.4', $config->getProjectTypo3Version()); // default

        $rectorConfig = $config->getRectorConfig();
        self::assertSame('typo3-13', $rectorConfig['level']);
        self::assertTrue($rectorConfig['enabled']); // default
    }

    public function testConfigurationWithoutProjectFile(): void
    {
        $homeDir = $this->tempDir . '/home';
        mkdir($homeDir, 0o777, true);

        // Only global configuration, no project
        $globalConfig = <<<YAML
            quality-tools:
              project:
                php_version: "8.4"
              tools:
                rector:
                  level: "typo3-12"
            YAML;
        file_put_contents($homeDir . '/.quality-tools.yaml', $globalConfig);

        $config = TestHelper::withEnvironment(
            ['HOME' => $homeDir],
            fn (): \Cpsit\QualityTools\Configuration\Configuration => $this->loader->load($this->tempDir),
        );

        // Should merge global with package defaults
        self::assertNull($config->getProjectName()); // not set in global or defaults
        self::assertSame('8.4', $config->getProjectPhpVersion()); // from global
        self::assertSame('13.4', $config->getProjectTypo3Version()); // default

        $rectorConfig = $config->getRectorConfig();
        self::assertSame('typo3-12', $rectorConfig['level']); // from global
        self::assertTrue($rectorConfig['enabled']); // default
    }
}

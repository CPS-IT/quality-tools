<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Tests\Integration\Configuration;

use Cpsit\QualityTools\Configuration\ConfigurationValidator;
use Cpsit\QualityTools\Configuration\HierarchicalConfigurationLoader;
use Cpsit\QualityTools\Service\FilesystemService;
use Cpsit\QualityTools\Service\SecurityService;
use Cpsit\QualityTools\Tests\Unit\TestHelper;
use PHPUnit\Framework\TestCase;

final class HierarchicalConfigurationTest extends TestCase
{
    private HierarchicalConfigurationLoader $loader;
    private string $projectRoot;
    private string $globalConfigPath;
    private string $originalHome;

    protected function setUp(): void
    {
        $this->projectRoot = TestHelper::createTempDirectory('hierarchical_config_test_');
        $this->loader = new HierarchicalConfigurationLoader(
            new ConfigurationValidator(),
            new SecurityService(),
            new FilesystemService(),
        );

        // Create a temporary home directory for global config tests
        $this->originalHome = $_SERVER['HOME'] ?? '';
        $tempHome = TestHelper::createTempDirectory('home_config_test_');
        $_SERVER['HOME'] = $tempHome;
        putenv('HOME=' . $tempHome);  // Set the environment variable so getenv() works
        $this->globalConfigPath = $tempHome . '/.quality-tools.yaml';
    }

    protected function tearDown(): void
    {
        TestHelper::removeDirectory($this->projectRoot);
        if (file_exists($this->globalConfigPath)) {
            TestHelper::removeDirectory(\dirname($this->globalConfigPath));
        }
        $_SERVER['HOME'] = $this->originalHome;
        if ($this->originalHome !== '' && $this->originalHome !== '0') {
            putenv('HOME=' . $this->originalHome);
        } else {
            putenv('HOME');  // Unset environment variable
        }
    }

    /**
     * Test Scenario 1: Basic project-level configuration loading.
     *
     * This test verifies that a simple project configuration file
     * is loaded correctly and parsed into the configuration object.
     */
    public function testBasicConfigurationLoading(): void
    {
        $projectConfig = <<<YAML
            quality-tools:
              project:
                name: "test-project"
                php_version: "8.4"

              tools:
                phpstan:
                  enabled: true
                  level: 7
            YAML;

        file_put_contents($this->projectRoot . '/.quality-tools.yaml', $projectConfig);

        $config = $this->loader->load($this->projectRoot);

        self::assertSame('test-project', $config->getProjectName());
        self::assertSame('8.4', $config->getProjectPhpVersion());

        $phpStanConfig = $config->getToolConfig('phpstan');
        self::assertSame(7, $phpStanConfig['level']);
        self::assertTrue($phpStanConfig['enabled']);
    }

    /**
     * Test Scenario 2: Global configuration in home directory.
     *
     * This test verifies that the global configuration in ~/.quality-tools.yaml
     * is loaded and provides default values for projects.
     */
    public function testGlobalConfigurationLoading(): void
    {
        $globalConfig = <<<YAML
            quality-tools:
              project:
                php_version: "8.3"
                typo3_version: "13.4"

              tools:
                phpstan:
                  enabled: true
                  level: 6
                  memory_limit: "2G"
            YAML;

        file_put_contents($this->globalConfigPath, $globalConfig);

        $config = $this->loader->load($this->projectRoot);

        self::assertSame('8.3', $config->getProjectPhpVersion());
        self::assertSame('13.4', $config->getProjectTypo3Version());

        $phpStanConfig = $config->getToolConfig('phpstan');
        self::assertSame(6, $phpStanConfig['level']);
        self::assertSame('2G', $phpStanConfig['memory_limit']);
        self::assertTrue($phpStanConfig['enabled']);
    }

    /**
     * Test Scenario 3: Project configuration overrides global configuration.
     *
     * This test verifies the precedence hierarchy where project-level
     * configuration overrides global defaults.
     */
    public function testProjectOverridesGlobal(): void
    {
        $globalConfig = <<<YAML
            quality-tools:
              project:
                php_version: "8.3"

              tools:
                phpstan:
                  level: 6
                  memory_limit: "2G"
            YAML;

        $projectConfig = <<<YAML
            quality-tools:
              project:
                name: "override-project"
                php_version: "8.4"

              tools:
                phpstan:
                  level: 8
            YAML;

        file_put_contents($this->globalConfigPath, $globalConfig);
        file_put_contents($this->projectRoot . '/.quality-tools.yaml', $projectConfig);

        $config = $this->loader->load($this->projectRoot);

        // Project values should override global
        self::assertSame('override-project', $config->getProjectName());
        self::assertSame('8.4', $config->getProjectPhpVersion());

        $phpStanConfig = $config->getToolConfig('phpstan');
        self::assertSame(8, $phpStanConfig['level']); // Overridden by a project
        self::assertSame('2G', $phpStanConfig['memory_limit']); // From global
    }

    /**
     * Test Scenario 4: Project configuration overrides the config directory.
     *
     * This test verifies that the project root configuration has higher precedence
     * than config/ directory configuration (per Feature 015 spec).
     */
    public function testProjectOverridesConfigDirectory(): void
    {
        $projectConfig = <<<YAML
            quality-tools:
              tools:
                phpstan:
                  level: 6
                  enabled: true

                rector:
                  enabled: false
            YAML;

        $configDirConfig = <<<YAML
            quality-tools:
              tools:
                phpstan:
                  level: 5

                rector:
                  enabled: true
                  level: "typo3-13"
            YAML;

        file_put_contents($this->projectRoot . '/.quality-tools.yaml', $projectConfig);

        // Create config directory and configuration
        $configDir = $this->projectRoot . '/config';
        mkdir($configDir, 0o777, true);
        file_put_contents($configDir . '/quality-tools.yaml', $configDirConfig);

        $config = $this->loader->load($this->projectRoot);

        $phpStanConfig = $config->getToolConfig('phpstan');
        self::assertSame(6, $phpStanConfig['level']); // Project root overrides config dir
        self::assertTrue($phpStanConfig['enabled']); // From project

        $rectorConfig = $config->getToolConfig('rector');
        self::assertFalse($rectorConfig['enabled']); // Project root overrides config dir
        self::assertSame('typo3-13', $rectorConfig['level']); // From config dir (not overridden by project)
    }

    /**
     * Test Scenario 5: Multiple tool configuration merging.
     *
     * This test verifies that different tools can be configured independently
     * and that the configuration merging works correctly for multiple tools.
     */
    public function testMultipleToolConfiguration(): void
    {
        $projectConfig = <<<YAML
            quality-tools:
              tools:
                rector:
                  enabled: true
                  level: "typo3-12"

                phpstan:
                  level: 6
                  enabled: true

                php-cs-fixer:
                  enabled: false
                  preset: "psr12"
            YAML;

        file_put_contents($this->projectRoot . '/.quality-tools.yaml', $projectConfig);

        $config = $this->loader->load($this->projectRoot);

        // Verify each tool gets its own configuration
        $phpStanConfig = $config->getToolConfig('phpstan');
        self::assertSame(6, $phpStanConfig['level']);
        self::assertTrue($phpStanConfig['enabled']);

        $rectorConfig = $config->getToolConfig('rector');
        self::assertTrue($rectorConfig['enabled']);
        self::assertSame('typo3-12', $rectorConfig['level']);

        $phpCsFixerConfig = $config->getToolConfig('php-cs-fixer');
        self::assertFalse($phpCsFixerConfig['enabled']);
        self::assertSame('psr12', $phpCsFixerConfig['preset']);
    }

    /**
     * Test Scenario 6: Environment variable interpolation in configuration.
     *
     * This test verifies that environment variables are properly interpolated
     * in configuration files with default values.
     */
    public function testEnvironmentVariableInterpolation(): void
    {
        // Set some environment variables
        TestHelper::withEnvironment([
            'PROJECT_NAME' => 'env-test-project',
            'PHPSTAN_MEMORY' => '4G',
            // Leave PHP_VERSION and PHPSTAN_LEVEL unset to test defaults
        ], function (): void {
            $configWithEnvVars = <<<YAML
                quality-tools:
                  project:
                    name: "\${PROJECT_NAME:-default-project}"
                    php_version: "\${PHP_VERSION:-8.4}"

                  tools:
                    phpstan:
                      memory_limit: "\${PHPSTAN_MEMORY:-2G}"
                      level: \${PHPSTAN_LEVEL:-6}

                  paths:
                    scan:
                      - "\${PROJECT_SRC_DIR:-src/}"
                      - "packages/"
                YAML;
            file_put_contents($this->projectRoot . '/.quality-tools.yaml', $configWithEnvVars);

            $config = $this->loader->load($this->projectRoot);

            // Values from environment
            self::assertSame('env-test-project', $config->getProjectName());

            $phpStanConfig = $config->getToolConfig('phpstan');
            self::assertSame('4G', $phpStanConfig['memory_limit']);

            // Default values (env vars not set)
            self::assertSame('8.4', $config->getProjectPhpVersion());
            self::assertSame(6, $phpStanConfig['level']);

            // Path interpolation
            $scanPaths = $config->getScanPaths();
            self::assertContains('src/', $scanPaths); // Default value used
            self::assertContains('packages/', $scanPaths);
        });
    }

    /**
     * Test Scenario 7: Array merging and deduplication.
     *
     * This test verifies that arrays from multiple configuration sources
     * are properly merged and deduplicated, particularly for paths.
     */
    public function testArrayMergingAndDeduplication(): void
    {
        $globalConfig = <<<YAML
            quality-tools:
              paths:
                scan:
                  - "packages/"
                  - "src/"
                exclude:
                  - "var/"
                  - ".git/"
            YAML;

        $projectConfig = <<<YAML
            quality-tools:
              paths:
                scan:
                  - "app/packages/"
                  - "config/"
                  - "packages/"
                exclude:
                  - "vendor/"
                  - "var/"
            YAML;

        file_put_contents($this->globalConfigPath, $globalConfig);
        file_put_contents($this->projectRoot . '/.quality-tools.yaml', $projectConfig);

        $config = $this->loader->load($this->projectRoot);

        $scanPaths = $config->getScanPaths();
        $excludePaths = $config->getExcludePaths();

        // Scan paths should be merged and deduplicated
        self::assertContains('packages/', $scanPaths);
        self::assertContains('src/', $scanPaths);
        self::assertContains('app/packages/', $scanPaths);
        self::assertContains('config/', $scanPaths);

        // Should not contain duplicates
        self::assertSame(1, array_count_values($scanPaths)['packages/']);

        // Exclude paths should be merged and deduplicated
        self::assertContains('var/', $excludePaths);
        self::assertContains('.git/', $excludePaths);
        self::assertContains('vendor/', $excludePaths);

        // Should not contain duplicates
        self::assertSame(1, array_count_values($excludePaths)['var/']);
    }

    /**
     * Test Scenario 8: Package configuration discovery and merging.
     *
     * This test verifies that configuration files in package directories
     * are discovered and merged with lower precedence than project config.
     */
    public function testPackageConfigurationMerging(): void
    {
        $projectConfig = <<<YAML
            quality-tools:
              project:
                name: "main-project"

              tools:
                phpstan:
                  level: 7
            YAML;

        $packageConfig = <<<YAML
            quality-tools:
              project:
                php_version: "8.3"

              tools:
                phpstan:
                  level: 5
                  memory_limit: "1G"

                rector:
                  enabled: true
            YAML;

        file_put_contents($this->projectRoot . '/.quality-tools.yaml', $projectConfig);

        // Create package directory structure
        $packageDir = $this->projectRoot . '/packages/test-package';
        mkdir($packageDir, 0o777, true);
        file_put_contents($packageDir . '/quality-tools.yaml', $packageConfig);

        $config = $this->loader->load($this->projectRoot);

        // Project config should override package config
        self::assertSame('main-project', $config->getProjectName()); // From project

        $phpStanConfig = $config->getToolConfig('phpstan');
        self::assertSame(7, $phpStanConfig['level']); // Overridden by a project
        self::assertSame('1G', $phpStanConfig['memory_limit']); // From package

        // Package-only config should be preserved
        $rectorConfig = $config->getToolConfig('rector');
        self::assertTrue($rectorConfig['enabled']); // From package

        self::assertSame('8.3', $config->getProjectPhpVersion()); // From package
    }

    /**
     * Test Scenario 9: Complete hierarchy testing with all configuration sources.
     *
     * This test verifies the complete precedence hierarchy with all possible
     * configuration sources present.
     */
    public function testCompleteConfigurationHierarchy(): void
    {
        // Global config (the lowest precedence for conflicts)
        $globalConfig = <<<YAML
            quality-tools:
              project:
                php_version: "8.2"
                typo3_version: "12.4"

              tools:
                phpstan:
                  level: 4
                  memory_limit: "1G"
                  enabled: true
            YAML;

        // Package config
        $packageConfig = <<<YAML
            quality-tools:
              project:
                php_version: "8.3"

              tools:
                phpstan:
                  level: 5
                  memory_limit: "2G"
            YAML;

        // Project config
        $projectConfig = <<<YAML
            quality-tools:
              project:
                name: "hierarchy-test"
                php_version: "8.4"

              tools:
                phpstan:
                  level: 6
            YAML;

        // Config directory (the highest precedence)
        $configDirConfig = <<<YAML
            quality-tools:
              tools:
                phpstan:
                  level: 7
                  memory_limit: "4G"
            YAML;

        file_put_contents($this->globalConfigPath, $globalConfig);

        $packageDir = $this->projectRoot . '/packages/hierarchy-package';
        mkdir($packageDir, 0o777, true);
        file_put_contents($packageDir . '/quality-tools.yaml', $packageConfig);

        file_put_contents($this->projectRoot . '/.quality-tools.yaml', $projectConfig);

        $configDir = $this->projectRoot . '/config';
        mkdir($configDir, 0o777, true);
        file_put_contents($configDir . '/quality-tools.yaml', $configDirConfig);

        $config = $this->loader->load($this->projectRoot);

        // Verify precedence hierarchy
        self::assertSame('hierarchy-test', $config->getProjectName()); // From project
        self::assertSame('8.4', $config->getProjectPhpVersion()); // From project (overrides package and global)
        self::assertSame('12.4', $config->getProjectTypo3Version()); // From global (only source)

        $phpStanConfig = $config->getToolConfig('phpstan');
        self::assertSame(6, $phpStanConfig['level']); // From project root (higher precedence than config dir)
        self::assertSame('4G', $phpStanConfig['memory_limit']); // From config dir (not overridden by project)
        self::assertTrue($phpStanConfig['enabled']); // From global (no override)
    }
}

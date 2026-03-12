<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Tests\Integration\Configuration;

use Cpsit\QualityTools\Configuration\ConfigurationLoader;
use Cpsit\QualityTools\Configuration\ConfigurationValidator;
use Cpsit\QualityTools\Service\FilesystemService;
use Cpsit\QualityTools\Service\SecurityService;
use Cpsit\QualityTools\Service\ToolConfigurationValidationService;
use Cpsit\QualityTools\Tests\Unit\TestHelper;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

final class HierarchicalConfigurationTest extends TestCase
{
    private ConfigurationLoader $loader;
    private string $projectRoot;
    private string $globalConfigPath;
    private string $originalHome;
    private SecurityService $securityService;
    private FilesystemService $filesystemService;

    protected function setUp(): void
    {
        $this->projectRoot = TestHelper::createTempDirectory('hierarchical_config_test_');
        $this->securityService = new SecurityService();
        $this->filesystemService = new FilesystemService(
            new Filesystem(),
            $this->securityService,
        );
        $this->loader = new ConfigurationLoader(
            new ConfigurationValidator(),
            $this->securityService,
            $this->filesystemService,
            new ToolConfigurationValidationService(),
        );

        // Create a temporary home directory for global config tests
        $this->originalHome = $_SERVER['HOME'] ?? '';
        $tempHome = TestHelper::createTempDirectory('home_config_test_');
        $_SERVER['HOME'] = $tempHome;
        putenv('HOME=' . $tempHome);
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
            putenv('HOME');
        }
    }

    /**
     * Test Scenario 1: Basic project-level configuration loading.
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

        self::assertSame('override-project', $config->getProjectName());
        self::assertSame('8.4', $config->getProjectPhpVersion());

        $phpStanConfig = $config->getToolConfig('phpstan');
        self::assertSame(8, $phpStanConfig['level']);
        self::assertSame('2G', $phpStanConfig['memory_limit']);
    }

    /**
     * Test Scenario 4: Project configuration overrides the config directory.
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

        $configDir = $this->projectRoot . '/config';
        mkdir($configDir, 0o777, true);
        file_put_contents($configDir . '/quality-tools.yaml', $configDirConfig);

        $config = $this->loader->load($this->projectRoot);

        $phpStanConfig = $config->getToolConfig('phpstan');
        self::assertSame(6, $phpStanConfig['level']);
        self::assertTrue($phpStanConfig['enabled']);

        $rectorConfig = $config->getToolConfig('rector');
        self::assertFalse($rectorConfig['enabled']);
        self::assertSame('typo3-13', $rectorConfig['level']);
    }

    /**
     * Test Scenario 5: Multiple tool configuration merging.
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
     */
    public function testEnvironmentVariableInterpolation(): void
    {
        TestHelper::withEnvironment([
            'PROJECT_NAME' => 'env-test-project',
            'PHPSTAN_MEMORY' => '4G',
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

            self::assertSame('env-test-project', $config->getProjectName());

            $phpStanConfig = $config->getToolConfig('phpstan');
            self::assertSame('4G', $phpStanConfig['memory_limit']);

            // PHP_VERSION may be set in CI (e.g. 8.3.30), so assert it starts with 8.
            self::assertStringStartsWith('8.', $config->getProjectPhpVersion());
            self::assertSame(6, $phpStanConfig['level']);

            $scanPaths = $config->getScanPaths();
            self::assertContains('src/', $scanPaths);
            self::assertContains('packages/', $scanPaths);
        });
    }

    /**
     * Test Scenario 7: Array merging and deduplication.
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

        self::assertContains('packages/', $scanPaths);
        self::assertContains('src/', $scanPaths);
        self::assertContains('app/packages/', $scanPaths);
        self::assertContains('config/', $scanPaths);

        self::assertSame(1, array_count_values($scanPaths)['packages/']);

        self::assertContains('var/', $excludePaths);
        self::assertContains('.git/', $excludePaths);
        self::assertContains('vendor/', $excludePaths);

        self::assertSame(1, array_count_values($excludePaths)['var/']);
    }

    /**
     * Test Scenario 8: Package configuration discovery and merging.
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

        $packageDir = $this->projectRoot . '/packages/test-package';
        mkdir($packageDir, 0o777, true);
        file_put_contents($packageDir . '/quality-tools.yaml', $packageConfig);

        $config = $this->loader->load($this->projectRoot);

        self::assertSame('main-project', $config->getProjectName());

        $phpStanConfig = $config->getToolConfig('phpstan');
        self::assertSame(7, $phpStanConfig['level']);
        self::assertSame('1G', $phpStanConfig['memory_limit']);

        $rectorConfig = $config->getToolConfig('rector');
        self::assertTrue($rectorConfig['enabled']);

        self::assertSame('8.3', $config->getProjectPhpVersion());
    }

    /**
     * Test Scenario 9: Complete hierarchy testing with all configuration sources.
     */
    public function testCompleteConfigurationHierarchy(): void
    {
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

        $packageConfig = <<<YAML
            quality-tools:
              project:
                php_version: "8.3"

              tools:
                phpstan:
                  level: 5
                  memory_limit: "2G"
            YAML;

        $projectConfig = <<<YAML
            quality-tools:
              project:
                name: "hierarchy-test"
                php_version: "8.4"

              tools:
                phpstan:
                  level: 6
            YAML;

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

        self::assertSame('hierarchy-test', $config->getProjectName());
        self::assertSame('8.4', $config->getProjectPhpVersion());
        self::assertSame('12.4', $config->getProjectTypo3Version());

        $phpStanConfig = $config->getToolConfig('phpstan');
        self::assertSame(6, $phpStanConfig['level']);
        self::assertSame('4G', $phpStanConfig['memory_limit']);
        self::assertTrue($phpStanConfig['enabled']);
    }
}

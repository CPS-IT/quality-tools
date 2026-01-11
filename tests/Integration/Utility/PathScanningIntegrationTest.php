<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Tests\Integration\Utility;

use Cpsit\QualityTools\Configuration\ConfigurationBuilder;
use Cpsit\QualityTools\Configuration\SimpleConfigurationLoader;
use Cpsit\QualityTools\Service\FilesystemService;
use Cpsit\QualityTools\Tests\Unit\TestHelper;
use PHPUnit\Framework\TestCase;

/**
 * Integration tests for path scanning with configuration system.
 */
final class PathScanningIntegrationTest extends TestCase
{
    private string $tempProjectRoot;
    private SimpleConfigurationLoader $loader;

    protected function setUp(): void
    {
        $this->tempProjectRoot = TestHelper::createTempDirectory('path_scanning_integration_test_');
        $this->loader = new SimpleConfigurationLoader(new \Cpsit\QualityTools\Configuration\ConfigurationValidator(), new \Cpsit\QualityTools\Service\SecurityService(), new FilesystemService());
    }

    protected function tearDown(): void
    {
        TestHelper::removeDirectory($this->tempProjectRoot);
    }

    public function testConfigurationWithAdditionalPaths(): void
    {
        // Create project structure
        mkdir($this->tempProjectRoot . '/packages', 0o777, true);
        mkdir($this->tempProjectRoot . '/config/system', 0o777, true);
        mkdir($this->tempProjectRoot . '/src/Custom', 0o777, true);
        mkdir($this->tempProjectRoot . '/app/Classes', 0o777, true);

        // Create YAML configuration with additional scan paths
        $configContent = <<<YAML
            quality-tools:
              project:
                name: "path-scanning-test"
              paths:
                scan:
                  - "packages/"
                  - "config/system/"
                  - "src/**"
                  - "app/Classes"
            YAML;
        file_put_contents($this->tempProjectRoot . '/.quality-tools.yaml', $configContent);

        // Load configuration
        $config = $this->loader->load($this->tempProjectRoot);

        // Test additional paths are configured
        // Check that custom scan paths are configured
        $scanPaths = $config->getScanPaths();
        self::assertContains('src/**', $scanPaths);
        self::assertContains('app/Classes', $scanPaths);

        // Test resolved paths include additional paths
        $rectorPaths = $config->getResolvedPathsForTool('rector');

        // Should include standard paths
        $expectedPackagesPath = realpath($this->tempProjectRoot . '/packages');
        $expectedConfigPath = realpath($this->tempProjectRoot . '/config/system');
        $expectedSrcPath = realpath($this->tempProjectRoot . '/src/Custom');
        $expectedAppPath = realpath($this->tempProjectRoot . '/app/Classes');

        self::assertContains($expectedPackagesPath, $rectorPaths);
        self::assertContains($expectedConfigPath, $rectorPaths);

        // Should include additional paths
        self::assertContains($expectedSrcPath, $rectorPaths);
        self::assertContains($expectedAppPath, $rectorPaths);
    }

    public function testConfigurationWithVendorNamespacePatterns(): void
    {
        // Create vendor structure
        $vendorDir = $this->tempProjectRoot . '/vendor';
        mkdir($vendorDir . '/cpsit/package1/Classes', 0o777, true);
        mkdir($vendorDir . '/cpsit/package2/Classes', 0o777, true);
        mkdir($vendorDir . '/fr/package3/Classes', 0o777, true);
        mkdir($vendorDir . '/composer', 0o777, true);
        file_put_contents($vendorDir . '/autoload.php', '<?php // Composer autoload');

        // Create standard structure
        mkdir($this->tempProjectRoot . '/packages', 0o777, true);

        // Create configuration with vendor namespace patterns
        $configContent = <<<YAML
            quality-tools:
              project:
                name: "vendor-namespace-test"
              paths:
                scan:
                  - "packages/"
                  - "cpsit/*/Classes"
                  - "fr/*/Classes"
            YAML;
        file_put_contents($this->tempProjectRoot . '/.quality-tools.yaml', $configContent);

        // Load configuration
        $config = $this->loader->load($this->tempProjectRoot);

        // Get resolved paths
        $paths = $config->getResolvedPathsForTool('rector');

        // Should include vendor namespace paths
        self::assertContains(realpath($vendorDir . '/cpsit/package1/Classes'), $paths);
        self::assertContains(realpath($vendorDir . '/cpsit/package2/Classes'), $paths);
        self::assertContains(realpath($vendorDir . '/fr/package3/Classes'), $paths);
    }

    public function testConfigurationWithExclusionPatterns(): void
    {
        // Create project structure
        mkdir($this->tempProjectRoot . '/packages/good', 0o777, true);
        mkdir($this->tempProjectRoot . '/packages/legacy', 0o777, true);
        mkdir($this->tempProjectRoot . '/packages/experimental', 0o777, true);

        // Create configuration with exclusions
        $configContent = <<<YAML
            quality-tools:
              project:
                name: "exclusion-test"
              paths:
                scan:
                  - "packages/*"
                exclude:
                  - "var/"
                  - "vendor/"
                  - "public/"
                  - "packages/legacy"
                  - "packages/experimental"
            YAML;
        file_put_contents($this->tempProjectRoot . '/.quality-tools.yaml', $configContent);

        // Load configuration
        $config = $this->loader->load($this->tempProjectRoot);

        // Get resolved paths
        $paths = $config->getResolvedPathsForTool('rector');

        // Should include good package
        self::assertContains(realpath($this->tempProjectRoot . '/packages/good'), $paths);

        // Should exclude legacy and experimental
        self::assertNotContains(realpath($this->tempProjectRoot . '/packages/legacy'), $paths);
        self::assertNotContains(realpath($this->tempProjectRoot . '/packages/experimental'), $paths);
    }

    public function testConfigurationWithToolSpecificOverrides(): void
    {
        // Create project structure
        mkdir($this->tempProjectRoot . '/packages', 0o777, true);
        mkdir($this->tempProjectRoot . '/config/system', 0o777, true);
        mkdir($this->tempProjectRoot . '/config/custom', 0o777, true);
        mkdir($this->tempProjectRoot . '/config/sites', 0o777, true);

        // Create configuration with tool-specific paths
        $configContent = <<<YAML
            quality-tools:
              project:
                name: "tool-override-test"
              tools:
                rector:
                  enabled: true
                  paths:
                    scan:
                      - "config/custom"
                fractor:
                  enabled: true
                  paths:
                    scan:
                      - "config/sites"
            YAML;
        file_put_contents($this->tempProjectRoot . '/.quality-tools.yaml', $configContent);

        // Load configuration
        $config = $this->loader->load($this->tempProjectRoot);

        // Test rector gets its additional path
        $rectorPaths = $config->getResolvedPathsForTool('rector');
        self::assertContains(realpath($this->tempProjectRoot . '/config/custom'), $rectorPaths);
        self::assertNotContains(realpath($this->tempProjectRoot . '/config/sites'), $rectorPaths);

        // Test fractor gets its additional path
        $fractorPaths = $config->getResolvedPathsForTool('fractor');
        self::assertContains(realpath($this->tempProjectRoot . '/config/sites'), $fractorPaths);
        self::assertNotContains(realpath($this->tempProjectRoot . '/config/custom'), $fractorPaths);
    }

    public function testConfigurationBuilderWithResolvedPaths(): void
    {
        // Create project structure
        mkdir($this->tempProjectRoot . '/packages', 0o777, true);
        mkdir($this->tempProjectRoot . '/src', 0o777, true);

        // Create configuration
        $configContent = <<<YAML
            quality-tools:
              project:
                name: "builder-test"
              paths:
                scan:
                  - "packages/"
                  - "config/system/"
                  - "src"
            YAML;
        file_put_contents($this->tempProjectRoot . '/.quality-tools.yaml', $configContent);

        // Load configuration
        $config = $this->loader->load($this->tempProjectRoot);
        $builder = new ConfigurationBuilder($config);

        // Test builder includes resolved paths
        $rectorConfig = $builder->buildRectorConfiguration();

        self::assertArrayHasKey('paths', $rectorConfig);
        self::assertContains(realpath($this->tempProjectRoot . '/packages'), $rectorConfig['paths']);
        self::assertContains(realpath($this->tempProjectRoot . '/src'), $rectorConfig['paths']);

        self::assertEquals($this->tempProjectRoot, $rectorConfig['project_root']);
    }

    public function testPathScanningDebugInfo(): void
    {
        // Create project structure
        mkdir($this->tempProjectRoot . '/packages', 0o777, true);

        // Create configuration
        $configContent = <<<YAML
            quality-tools:
              project:
                name: "debug-test"
              paths:
                scan:
                  - "packages/"
                  - "config/system/"
                  - "src/**"
                  - "cpsit/*"
            YAML;
        file_put_contents($this->tempProjectRoot . '/.quality-tools.yaml', $configContent);

        // Load configuration
        $config = $this->loader->load($this->tempProjectRoot);

        // Get debug info
        $debugInfo = $config->getPathScanningDebugInfo('rector');

        self::assertIsArray($debugInfo);
        self::assertArrayHasKey('tool', $debugInfo);
        self::assertArrayHasKey('project_root', $debugInfo);
        self::assertArrayHasKey('resolved_paths', $debugInfo);
        self::assertArrayHasKey('path_scanner_debug', $debugInfo);

        self::assertEquals('rector', $debugInfo['tool']);
        self::assertEquals($this->tempProjectRoot, $debugInfo['project_root']);
    }

    public function testConfigurationWithoutAdditionalPaths(): void
    {
        // Create basic project structure
        mkdir($this->tempProjectRoot . '/packages', 0o777, true);
        mkdir($this->tempProjectRoot . '/config/system', 0o777, true);

        // Create minimal configuration
        $configContent = <<<YAML
            quality-tools:
              project:
                name: "minimal-test"
            YAML;
        file_put_contents($this->tempProjectRoot . '/.quality-tools.yaml', $configContent);

        // Load configuration
        $config = $this->loader->load($this->tempProjectRoot);

        // Should use default paths configuration
        self::assertEquals(['packages/', 'config/system/'], $config->getScanPaths());
        self::assertNotEmpty($config->getExcludePaths()); // Has default excludes
        self::assertEmpty($config->getToolPaths('rector')); // No tool-specific paths

        // Should still resolve standard paths
        $paths = $config->getResolvedPathsForTool('rector');
        $expectedPackagesPath = realpath($this->tempProjectRoot . '/packages');
        $expectedConfigPath = realpath($this->tempProjectRoot . '/config/system');

        self::assertContains($expectedPackagesPath, $paths);
        self::assertContains($expectedConfigPath, $paths);
    }

    public function testConfigurationGeneratesValidFiles(): void
    {
        // Create project structure
        mkdir($this->tempProjectRoot . '/packages', 0o777, true);

        // Create configuration
        $configContent = <<<YAML
            quality-tools:
              project:
                name: "file-generation-test"
                php_version: "8.3"
            YAML;
        file_put_contents($this->tempProjectRoot . '/.quality-tools.yaml', $configContent);

        // Load configuration
        $config = $this->loader->load($this->tempProjectRoot);
        $builder = new ConfigurationBuilder($config);

        // Test file generation doesn't throw errors
        $rectorContent = $builder->generateConfigurationFileContent('rector');
        $fractorContent = $builder->generateConfigurationFileContent('fractor');

        self::assertIsString($rectorContent);
        self::assertIsString($fractorContent);
        self::assertStringContainsString('RectorConfig::configure()', $rectorContent);
        self::assertStringContainsString('FractorConfiguration::configure()', $fractorContent);
    }
}

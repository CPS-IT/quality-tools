<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Tests\Integration\Utility;

use Cpsit\QualityTools\Configuration\YamlConfigurationLoader;
use Cpsit\QualityTools\Tests\Unit\TestHelper;
use PHPUnit\Framework\TestCase;

/**
 * Integration tests for vendor directory detection with configuration system
 */
final class VendorDirectoryIntegrationTest extends TestCase
{
    private string $tempProjectRoot;
    private YamlConfigurationLoader $loader;

    protected function setUp(): void
    {
        $this->tempProjectRoot = TestHelper::createTempDirectory('vendor_integration_test_');
        $this->loader = new YamlConfigurationLoader();
    }

    protected function tearDown(): void
    {
        TestHelper::removeDirectory($this->tempProjectRoot);
    }

    public function testConfigurationIntegratesVendorDetection(): void
    {
        // Create vendor directory
        $vendorDir = $this->tempProjectRoot . '/vendor';
        mkdir($vendorDir, 0777, true);
        mkdir($vendorDir . '/composer', 0777, true);
        file_put_contents($vendorDir . '/autoload.php', '<?php // Composer autoload');

        // Create basic YAML configuration
        $configContent = <<<YAML
quality-tools:
  project:
    name: "vendor-integration-test"
YAML;
        file_put_contents($this->tempProjectRoot . '/.quality-tools.yaml', $configContent);

        // Load configuration
        $config = $this->loader->load($this->tempProjectRoot);

        // Test vendor directory integration
        self::assertTrue($config->hasVendorDirectory());
        self::assertEquals(realpath($vendorDir), $config->getVendorPath());
        self::assertEquals(realpath($vendorDir) . '/bin', $config->getVendorBinPath());
        self::assertEquals($this->tempProjectRoot, $config->getProjectRoot());
    }

    public function testConfigurationWithCustomVendorDir(): void
    {
        // Create custom vendor directory
        $customVendorDir = $this->tempProjectRoot . '/deps';
        mkdir($customVendorDir, 0777, true);
        mkdir($customVendorDir . '/composer', 0777, true);
        file_put_contents($customVendorDir . '/autoload.php', '<?php // Composer autoload');

        // Create composer.json with custom vendor-dir
        $composerJson = [
            'name' => 'test/integration-project',
            'config' => [
                'vendor-dir' => 'deps'
            ]
        ];
        file_put_contents($this->tempProjectRoot . '/composer.json', json_encode($composerJson));

        // Create YAML configuration
        $configContent = <<<YAML
quality-tools:
  project:
    name: "custom-vendor-test"
    php_version: "8.3"
YAML;
        file_put_contents($this->tempProjectRoot . '/.quality-tools.yaml', $configContent);

        // Load configuration
        $config = $this->loader->load($this->tempProjectRoot);

        // Test custom vendor directory detection
        self::assertTrue($config->hasVendorDirectory());
        self::assertEquals(realpath($customVendorDir), $config->getVendorPath());
        self::assertEquals(realpath($customVendorDir) . '/bin', $config->getVendorBinPath());
    }

    public function testConfigurationWithoutVendorDirectory(): void
    {
        // Create YAML configuration without creating vendor directory
        $configContent = <<<YAML
quality-tools:
  project:
    name: "no-vendor-test"
YAML;
        file_put_contents($this->tempProjectRoot . '/.quality-tools.yaml', $configContent);

        // Load configuration
        $config = $this->loader->load($this->tempProjectRoot);

        // Test behavior without vendor directory
        self::assertFalse($config->hasVendorDirectory());
        self::assertNull($config->getVendorPath());
        self::assertNull($config->getVendorBinPath());
        self::assertEquals($this->tempProjectRoot, $config->getProjectRoot());
    }

    public function testVendorDetectionDebugInfo(): void
    {
        // Create vendor directory
        $vendorDir = $this->tempProjectRoot . '/vendor';
        mkdir($vendorDir, 0777, true);
        mkdir($vendorDir . '/composer', 0777, true);
        file_put_contents($vendorDir . '/autoload.php', '<?php // Composer autoload');

        // Create composer.json for more complete debug info
        $composerJson = ['name' => 'test/debug-project'];
        file_put_contents($this->tempProjectRoot . '/composer.json', json_encode($composerJson));

        // Create configuration
        $configContent = <<<YAML
quality-tools:
  project:
    name: "debug-test"
YAML;
        file_put_contents($this->tempProjectRoot . '/.quality-tools.yaml', $configContent);

        // Load configuration
        $config = $this->loader->load($this->tempProjectRoot);

        // Get debug info
        $debugInfo = $config->getVendorDetectionDebugInfo();

        self::assertIsArray($debugInfo);
        self::assertArrayHasKey('project_root', $debugInfo);
        self::assertArrayHasKey('methods', $debugInfo);
        self::assertEquals($this->tempProjectRoot, $debugInfo['project_root']);

        // Verify all detection methods are included
        self::assertArrayHasKey('composer_api', $debugInfo['methods']);
        self::assertArrayHasKey('composer_json', $debugInfo['methods']);
        self::assertArrayHasKey('environment', $debugInfo['methods']);
        self::assertArrayHasKey('fallbacks', $debugInfo['methods']);

        // composer_json method should have detected the file
        self::assertTrue($debugInfo['methods']['composer_json']['file_exists']);
    }

    public function testConfigurationWithEnvironmentVendorDir(): void
    {
        // Create environment-specified vendor directory
        $envVendorDir = $this->tempProjectRoot . '/env-vendor';
        mkdir($envVendorDir, 0777, true);
        mkdir($envVendorDir . '/composer', 0777, true);
        file_put_contents($envVendorDir . '/autoload.php', '<?php // Composer autoload');

        // Set environment variable
        $originalEnv = $_ENV['COMPOSER_VENDOR_DIR'] ?? null;
        $_ENV['COMPOSER_VENDOR_DIR'] = 'env-vendor';

        try {
            // Create configuration
            $configContent = <<<YAML
quality-tools:
  project:
    name: "env-vendor-test"
YAML;
            file_put_contents($this->tempProjectRoot . '/.quality-tools.yaml', $configContent);

            // Load configuration
            $config = $this->loader->load($this->tempProjectRoot);

            // Test environment-based detection
            self::assertTrue($config->hasVendorDirectory());
            self::assertEquals(realpath($envVendorDir), $config->getVendorPath());

        } finally {
            // Restore environment
            if ($originalEnv !== null) {
                $_ENV['COMPOSER_VENDOR_DIR'] = $originalEnv;
            } else {
                unset($_ENV['COMPOSER_VENDOR_DIR']);
            }
        }
    }

    public function testConfigurationReusesDetectionResults(): void
    {
        // Create vendor directory
        $vendorDir = $this->tempProjectRoot . '/vendor';
        mkdir($vendorDir, 0777, true);
        mkdir($vendorDir . '/composer', 0777, true);
        file_put_contents($vendorDir . '/autoload.php', '<?php // Composer autoload');

        // Create configuration
        $configContent = <<<YAML
quality-tools:
  project:
    name: "reuse-test"
YAML;
        file_put_contents($this->tempProjectRoot . '/.quality-tools.yaml', $configContent);

        // Load configuration
        $config = $this->loader->load($this->tempProjectRoot);

        // Multiple calls should return same results (testing caching)
        $path1 = $config->getVendorPath();
        $path2 = $config->getVendorPath();
        $binPath1 = $config->getVendorBinPath();
        $binPath2 = $config->getVendorBinPath();

        self::assertEquals($path1, $path2);
        self::assertEquals($binPath1, $binPath2);
        self::assertEquals($path1 . '/bin', $binPath1);
    }

    public function testConfigurationProjectRootUpdate(): void
    {
        // Create initial vendor directory
        $vendorDir1 = $this->tempProjectRoot . '/vendor';
        mkdir($vendorDir1, 0777, true);
        mkdir($vendorDir1 . '/composer', 0777, true);
        file_put_contents($vendorDir1 . '/autoload.php', '<?php // Composer autoload');

        // Create configuration
        $configContent = <<<YAML
quality-tools:
  project:
    name: "project-root-test"
YAML;
        file_put_contents($this->tempProjectRoot . '/.quality-tools.yaml', $configContent);

        // Load configuration
        $config = $this->loader->load($this->tempProjectRoot);
        $originalPath = $config->getVendorPath();

        // Create second project root with different vendor
        $tempProjectRoot2 = TestHelper::createTempDirectory('vendor_integration_test2_');
        $vendorDir2 = $tempProjectRoot2 . '/vendor';
        mkdir($vendorDir2, 0777, true);
        mkdir($vendorDir2 . '/composer', 0777, true);
        file_put_contents($vendorDir2 . '/autoload.php', '<?php // Composer autoload');

        // Update project root
        $config->setProjectRoot($tempProjectRoot2);
        $newPath = $config->getVendorPath();

        // Paths should be different
        self::assertNotEquals($originalPath, $newPath);
        self::assertEquals(realpath($vendorDir2), $newPath);
        self::assertEquals($tempProjectRoot2, $config->getProjectRoot());

        // Clean up second temp directory
        TestHelper::removeDirectory($tempProjectRoot2);
    }
}
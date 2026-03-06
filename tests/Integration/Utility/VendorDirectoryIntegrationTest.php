<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Tests\Integration\Utility;

use Cpsit\QualityTools\Configuration\ConfigurationLoader;
use Cpsit\QualityTools\Configuration\ConfigurationValidator;
use Cpsit\QualityTools\Service\FilesystemService;
use Cpsit\QualityTools\Service\PathResolutionService;
use Cpsit\QualityTools\Service\SecurityService;
use Cpsit\QualityTools\Service\ToolConfigurationValidationService;
use Cpsit\QualityTools\Tests\Unit\TestHelper;
use Cpsit\QualityTools\Utility\VendorDirectoryDetector;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Integration tests for vendor directory detection with configuration system.
 */
final class VendorDirectoryIntegrationTest extends TestCase
{
    private string $tempProjectRoot;
    private SecurityService $securityService;
    private FilesystemService $filesystemService;
    private ConfigurationLoader $loader;

    protected function setUp(): void
    {
        $this->tempProjectRoot = TestHelper::createTempDirectory('vendor_integration_test_');
        $this->securityService = new SecurityService();
        $this->filesystemService = new FilesystemService(new Filesystem(), $this->securityService);
        $pathResolutionService = new PathResolutionService(
            $this->filesystemService,
            new VendorDirectoryDetector(),
        );
        $this->loader = new ConfigurationLoader(
            new ConfigurationValidator(),
            $this->securityService,
            $this->filesystemService,
            new ToolConfigurationValidationService(),
            pathResolutionService: $pathResolutionService,
        );
    }

    protected function tearDown(): void
    {
        TestHelper::removeDirectory($this->tempProjectRoot);
    }

    public function testConfigurationIntegratesVendorDetection(): void
    {
        $vendorDir = $this->tempProjectRoot . '/vendor';
        mkdir($vendorDir, 0o777, true);
        mkdir($vendorDir . '/composer', 0o777, true);
        file_put_contents($vendorDir . '/autoload.php', '<?php // Composer autoload');

        $configContent = <<<YAML
            quality-tools:
              project:
                name: "vendor-integration-test"
            YAML;
        file_put_contents($this->tempProjectRoot . '/.quality-tools.yaml', $configContent);

        $config = $this->loader->load($this->tempProjectRoot);

        self::assertTrue($config->hasVendorDirectory());
        self::assertEquals(realpath($vendorDir), $config->getVendorPath());
        self::assertEquals(realpath($vendorDir) . '/bin', $config->getVendorBinPath());
        self::assertEquals($this->tempProjectRoot, $config->getProjectRoot());
    }

    public function testConfigurationWithCustomVendorDir(): void
    {
        $customVendorDir = $this->tempProjectRoot . '/deps';
        mkdir($customVendorDir, 0o777, true);
        mkdir($customVendorDir . '/composer', 0o777, true);
        file_put_contents($customVendorDir . '/autoload.php', '<?php // Composer autoload');

        $composerJson = [
            'name' => 'test/integration-project',
            'config' => [
                'vendor-dir' => 'deps',
            ],
        ];
        file_put_contents($this->tempProjectRoot . '/composer.json', json_encode($composerJson));

        $configContent = <<<YAML
            quality-tools:
              project:
                name: "custom-vendor-test"
                php_version: "8.3"
            YAML;
        file_put_contents($this->tempProjectRoot . '/.quality-tools.yaml', $configContent);

        $config = $this->loader->load($this->tempProjectRoot);

        self::assertTrue($config->hasVendorDirectory());
        self::assertEquals(realpath($customVendorDir), $config->getVendorPath());
        self::assertEquals(realpath($customVendorDir) . '/bin', $config->getVendorBinPath());
    }

    public function testConfigurationWithoutVendorDirectory(): void
    {
        $configContent = <<<YAML
            quality-tools:
              project:
                name: "no-vendor-test"
            YAML;
        file_put_contents($this->tempProjectRoot . '/.quality-tools.yaml', $configContent);

        $config = $this->loader->load($this->tempProjectRoot);

        self::assertFalse($config->hasVendorDirectory());
        self::assertNull($config->getVendorPath());
        self::assertNull($config->getVendorBinPath());
        self::assertEquals($this->tempProjectRoot, $config->getProjectRoot());
    }

    public function testVendorDetectionDebugInfo(): void
    {
        $vendorDir = $this->tempProjectRoot . '/vendor';
        mkdir($vendorDir, 0o777, true);
        mkdir($vendorDir . '/composer', 0o777, true);
        file_put_contents($vendorDir . '/autoload.php', '<?php // Composer autoload');

        $composerJson = ['name' => 'test/debug-project'];
        file_put_contents($this->tempProjectRoot . '/composer.json', json_encode($composerJson));

        $configContent = <<<YAML
            quality-tools:
              project:
                name: "debug-test"
            YAML;
        file_put_contents($this->tempProjectRoot . '/.quality-tools.yaml', $configContent);

        $config = $this->loader->load($this->tempProjectRoot);

        $debugInfo = $config->getVendorDetectionDebugInfo();

        self::assertIsArray($debugInfo);
        self::assertArrayHasKey('project_root', $debugInfo);
        self::assertEquals($this->tempProjectRoot, $debugInfo['project_root']);
        self::assertArrayHasKey('vendor_path', $debugInfo);
        self::assertArrayHasKey('vendor_bin_path', $debugInfo);
        self::assertArrayHasKey('detection_method', $debugInfo);
    }

    public function testConfigurationWithEnvironmentVendorDir(): void
    {
        $envVendorDir = $this->tempProjectRoot . '/env-vendor';
        mkdir($envVendorDir, 0o777, true);
        mkdir($envVendorDir . '/composer', 0o777, true);
        file_put_contents($envVendorDir . '/autoload.php', '<?php // Composer autoload');

        $originalEnv = $_ENV['COMPOSER_VENDOR_DIR'] ?? null;
        $_ENV['COMPOSER_VENDOR_DIR'] = 'env-vendor';

        try {
            $configContent = <<<YAML
                quality-tools:
                  project:
                    name: "env-vendor-test"
                YAML;
            file_put_contents($this->tempProjectRoot . '/.quality-tools.yaml', $configContent);

            $config = $this->loader->load($this->tempProjectRoot);

            self::assertTrue($config->hasVendorDirectory());
            self::assertEquals(realpath($envVendorDir), $config->getVendorPath());
        } finally {
            if ($originalEnv !== null) {
                $_ENV['COMPOSER_VENDOR_DIR'] = $originalEnv;
            } else {
                unset($_ENV['COMPOSER_VENDOR_DIR']);
            }
        }
    }

    public function testConfigurationReusesDetectionResults(): void
    {
        $vendorDir = $this->tempProjectRoot . '/vendor';
        mkdir($vendorDir, 0o777, true);
        mkdir($vendorDir . '/composer', 0o777, true);
        file_put_contents($vendorDir . '/autoload.php', '<?php // Composer autoload');

        $configContent = <<<YAML
            quality-tools:
              project:
                name: "reuse-test"
            YAML;
        file_put_contents($this->tempProjectRoot . '/.quality-tools.yaml', $configContent);

        $config = $this->loader->load($this->tempProjectRoot);

        $path1 = $config->getVendorPath();
        $path2 = $config->getVendorPath();
        $binPath1 = $config->getVendorBinPath();
        $binPath2 = $config->getVendorBinPath();

        self::assertEquals($path1, $path2);
        self::assertEquals($binPath1, $binPath2);
        self::assertEquals($path1 . '/bin', $binPath1);
    }
}

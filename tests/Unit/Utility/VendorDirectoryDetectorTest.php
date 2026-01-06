<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Tests\Unit\Utility;

use Cpsit\QualityTools\Exception\VendorDirectoryNotFoundException;
use Cpsit\QualityTools\Tests\Unit\TestHelper;
use Cpsit\QualityTools\Utility\VendorDirectoryDetector;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(VendorDirectoryDetector::class)]
final class VendorDirectoryDetectorTest extends TestCase
{
    private VendorDirectoryDetector $detector;
    private string $tempDir;

    protected function setUp(): void
    {
        $this->detector = new VendorDirectoryDetector();
        $this->tempDir = TestHelper::createTempDirectory('vendor_detector_test_');
        VendorDirectoryDetector::clearCache();
    }

    protected function tearDown(): void
    {
        TestHelper::removeDirectory($this->tempDir);
        VendorDirectoryDetector::clearCache();
    }

    public function testDetectVendorPathFromStandardLocation(): void
    {
        // Create standard vendor directory
        $vendorDir = $this->tempDir . '/vendor';
        mkdir($vendorDir, 0777, true);
        mkdir($vendorDir . '/composer', 0777, true);
        file_put_contents($vendorDir . '/autoload.php', '<?php // Composer autoload');

        $result = $this->detector->detectVendorPath($this->tempDir);

        self::assertEquals(realpath($vendorDir), $result);
    }

    public function testDetectVendorPathFromComposerJson(): void
    {
        // Create custom vendor directory
        $customVendorDir = $this->tempDir . '/custom-vendor';
        mkdir($customVendorDir, 0777, true);
        mkdir($customVendorDir . '/composer', 0777, true);
        file_put_contents($customVendorDir . '/autoload.php', '<?php // Composer autoload');

        // Create composer.json with custom vendor-dir
        $composerJson = [
            'name' => 'test/project',
            'config' => [
                'vendor-dir' => 'custom-vendor'
            ]
        ];
        file_put_contents($this->tempDir . '/composer.json', json_encode($composerJson));

        $result = $this->detector->detectVendorPath($this->tempDir);

        self::assertEquals(realpath($customVendorDir), $result);
    }

    public function testDetectVendorPathFromAbsoluteVendorDir(): void
    {
        // Create custom vendor directory outside project root
        $outsideVendorDir = $this->tempDir . '_outside/vendor';
        mkdir($outsideVendorDir, 0777, true);
        mkdir($outsideVendorDir . '/composer', 0777, true);
        file_put_contents($outsideVendorDir . '/autoload.php', '<?php // Composer autoload');

        // Create composer.json with absolute vendor-dir
        $composerJson = [
            'name' => 'test/project',
            'config' => [
                'vendor-dir' => $outsideVendorDir
            ]
        ];
        file_put_contents($this->tempDir . '/composer.json', json_encode($composerJson));

        $result = $this->detector->detectVendorPath($this->tempDir);

        self::assertEquals(realpath($outsideVendorDir), $result);
    }

    public function testDetectVendorPathFromEnvironmentVariable(): void
    {
        // Create vendor directory
        $envVendorDir = $this->tempDir . '/env-vendor';
        mkdir($envVendorDir, 0777, true);
        mkdir($envVendorDir . '/composer', 0777, true);
        file_put_contents($envVendorDir . '/autoload.php', '<?php // Composer autoload');

        // Set environment variable
        $envVar = 'COMPOSER_VENDOR_DIR';
        $originalValue = $_ENV[$envVar] ?? null;
        $_ENV[$envVar] = 'env-vendor';

        try {
            $result = $this->detector->detectVendorPath($this->tempDir);
            self::assertEquals(realpath($envVendorDir), $result);
        } finally {
            // Restore original environment
            if ($originalValue !== null) {
                $_ENV[$envVar] = $originalValue;
            } else {
                unset($_ENV[$envVar]);
            }
        }
    }

    public function testDetectVendorPathFromFallbacks(): void
    {
        // Create vendor directory in parent directory (fallback)
        $parentVendorDir = dirname($this->tempDir) . '/vendor';
        if (!is_dir($parentVendorDir)) {
            mkdir($parentVendorDir, 0777, true);
            mkdir($parentVendorDir . '/composer', 0777, true);
            file_put_contents($parentVendorDir . '/autoload.php', '<?php // Composer autoload');

            $result = $this->detector->detectVendorPath($this->tempDir);

            self::assertEquals(realpath($parentVendorDir), $result);

            // Clean up
            TestHelper::removeDirectory($parentVendorDir);
        } else {
            // If parent vendor already exists, test different fallback
            $subVendorDir = $this->tempDir . '/sub/vendor';
            mkdir(dirname($subVendorDir), 0777, true);
            mkdir($subVendorDir, 0777, true);
            mkdir($subVendorDir . '/composer', 0777, true);
            file_put_contents($subVendorDir . '/autoload.php', '<?php // Composer autoload');

            $subProjectDir = $this->tempDir . '/sub';
            $result = $this->detector->detectVendorPath($subProjectDir);

            self::assertEquals(realpath($subVendorDir), $result);
        }
    }

    public function testDetectVendorPathThrowsExceptionWhenNotFound(): void
    {
        $this->expectException(VendorDirectoryNotFoundException::class);
        $this->expectExceptionMessage('Could not detect vendor directory for project');

        $this->detector->detectVendorPath($this->tempDir);
    }

    public function testDetectVendorPathWithInvalidComposerJson(): void
    {
        // Create invalid composer.json
        file_put_contents($this->tempDir . '/composer.json', 'invalid json content');

        // Create fallback vendor directory
        $vendorDir = $this->tempDir . '/vendor';
        mkdir($vendorDir, 0777, true);
        mkdir($vendorDir . '/composer', 0777, true);
        file_put_contents($vendorDir . '/autoload.php', '<?php // Composer autoload');

        // Should fall back to standard location
        $result = $this->detector->detectVendorPath($this->tempDir);

        self::assertEquals(realpath($vendorDir), $result);
    }

    public function testDetectVendorPathWithNonExistentCustomVendorDir(): void
    {
        // Create composer.json with non-existent vendor-dir
        $composerJson = [
            'name' => 'test/project',
            'config' => [
                'vendor-dir' => 'non-existent-vendor'
            ]
        ];
        file_put_contents($this->tempDir . '/composer.json', json_encode($composerJson));

        // Create fallback vendor directory
        $vendorDir = $this->tempDir . '/vendor';
        mkdir($vendorDir, 0777, true);
        mkdir($vendorDir . '/composer', 0777, true);
        file_put_contents($vendorDir . '/autoload.php', '<?php // Composer autoload');

        // Should fall back to standard location
        $result = $this->detector->detectVendorPath($this->tempDir);

        self::assertEquals(realpath($vendorDir), $result);
    }

    public function testCaching(): void
    {
        // Create vendor directory
        $vendorDir = $this->tempDir . '/vendor';
        mkdir($vendorDir, 0777, true);
        mkdir($vendorDir . '/composer', 0777, true);
        file_put_contents($vendorDir . '/autoload.php', '<?php // Composer autoload');

        // First call should detect and cache
        $result1 = $this->detector->detectVendorPath($this->tempDir);

        // Remove the vendor directory
        TestHelper::removeDirectory($vendorDir);

        // Second call should use cache and return same result
        $result2 = $this->detector->detectVendorPath($this->tempDir);

        self::assertEquals($result1, $result2);

        // Clear cache and try again - should fail now
        VendorDirectoryDetector::clearCache();

        $this->expectException(VendorDirectoryNotFoundException::class);
        $this->detector->detectVendorPath($this->tempDir);
    }

    public function testValidateVendorDirectory(): void
    {
        // Test valid vendor directory
        $validVendorDir = $this->tempDir . '/valid-vendor';
        mkdir($validVendorDir, 0777, true);
        mkdir($validVendorDir . '/composer', 0777, true);
        file_put_contents($validVendorDir . '/autoload.php', '<?php // Composer autoload');

        self::assertTrue($this->detector->validateVendorDirectory($validVendorDir));

        // Test invalid vendor directory (missing composer directory)
        $invalidVendorDir = $this->tempDir . '/invalid-vendor';
        mkdir($invalidVendorDir, 0777, true);
        file_put_contents($invalidVendorDir . '/autoload.php', '<?php // Composer autoload');

        self::assertFalse($this->detector->validateVendorDirectory($invalidVendorDir));

        // Test invalid vendor directory (missing autoload.php)
        $incompleteVendorDir = $this->tempDir . '/incomplete-vendor';
        mkdir($incompleteVendorDir, 0777, true);
        mkdir($incompleteVendorDir . '/composer', 0777, true);

        self::assertFalse($this->detector->validateVendorDirectory($incompleteVendorDir));

        // Test non-existent directory
        self::assertFalse($this->detector->validateVendorDirectory('/non/existent/path'));
    }

    public function testGetVendorBinPath(): void
    {
        // Create vendor directory
        $vendorDir = $this->tempDir . '/vendor';
        mkdir($vendorDir, 0777, true);
        mkdir($vendorDir . '/composer', 0777, true);
        mkdir($vendorDir . '/bin', 0777, true);
        file_put_contents($vendorDir . '/autoload.php', '<?php // Composer autoload');

        $result = $this->detector->getVendorBinPath($this->tempDir);

        self::assertEquals(realpath($vendorDir) . '/bin', $result);
    }

    public function testGetDetectionDebugInfo(): void
    {
        // Create vendor directory
        $vendorDir = $this->tempDir . '/vendor';
        mkdir($vendorDir, 0777, true);
        mkdir($vendorDir . '/composer', 0777, true);
        file_put_contents($vendorDir . '/autoload.php', '<?php // Composer autoload');

        $debugInfo = $this->detector->getDetectionDebugInfo($this->tempDir);

        self::assertIsArray($debugInfo);
        self::assertArrayHasKey('project_root', $debugInfo);
        self::assertArrayHasKey('methods', $debugInfo);
        self::assertEquals($this->tempDir, $debugInfo['project_root']);

        // Check that all detection methods are included
        self::assertArrayHasKey('composer_api', $debugInfo['methods']);
        self::assertArrayHasKey('composer_json', $debugInfo['methods']);
        self::assertArrayHasKey('environment', $debugInfo['methods']);
        self::assertArrayHasKey('fallbacks', $debugInfo['methods']);
    }

    public function testNormalizePathWithSymlinks(): void
    {
        if (!function_exists('symlink')) {
            self::markTestSkipped('Symlinks not supported on this system');
        }

        // Create real vendor directory
        $realVendorDir = $this->tempDir . '/real-vendor';
        mkdir($realVendorDir, 0777, true);
        mkdir($realVendorDir . '/composer', 0777, true);
        file_put_contents($realVendorDir . '/autoload.php', '<?php // Composer autoload');

        // Create symlink to vendor directory
        $symlinkVendorDir = $this->tempDir . '/vendor';
        symlink($realVendorDir, $symlinkVendorDir);

        $result = $this->detector->detectVendorPath($this->tempDir);

        // Result should be the resolved real path
        self::assertEquals(realpath($realVendorDir), $result);
    }

    public function testDetectFromComposerApiWhenAvailable(): void
    {
        if (!class_exists('Composer\InstalledVersions')) {
            self::markTestSkipped('Composer\InstalledVersions not available');
        }

        // This test is more about code coverage since we can't easily mock Composer's API
        // The actual detection will depend on the real Composer installation

        $vendorDir = $this->tempDir . '/vendor';
        mkdir($vendorDir, 0777, true);
        mkdir($vendorDir . '/composer', 0777, true);
        file_put_contents($vendorDir . '/autoload.php', '<?php // Composer autoload');

        // This should not throw an exception
        $result = $this->detector->detectVendorPath($this->tempDir);

        self::assertIsString($result);
        self::assertDirectoryExists($result);
    }

    public function testResolveNonExistentPath(): void
    {
        $this->expectException(VendorDirectoryNotFoundException::class);
        $this->expectExceptionMessage('Vendor directory path could not be resolved');

        // Try to trigger the realpath failure by using a non-existent but structured path
        $reflection = new \ReflectionClass($this->detector);
        $method = $reflection->getMethod('normalizePath');
        $method->setAccessible(true);

        $method->invoke($this->detector, '/this/path/should/not/exist/vendor');
    }

    public function testIsAbsolutePath(): void
    {
        $reflection = new \ReflectionClass($this->detector);
        $method = $reflection->getMethod('isAbsolutePath');
        $method->setAccessible(true);

        // Unix absolute paths
        self::assertTrue($method->invoke($this->detector, '/path/to/vendor'));
        self::assertTrue($method->invoke($this->detector, '/vendor'));

        // Relative paths
        self::assertFalse($method->invoke($this->detector, 'vendor'));
        self::assertFalse($method->invoke($this->detector, './vendor'));
        self::assertFalse($method->invoke($this->detector, '../vendor'));

        // Windows absolute paths (if on Windows or for coverage)
        if (DIRECTORY_SEPARATOR === '\\') {
            self::assertTrue($method->invoke($this->detector, 'C:\\path\\to\\vendor'));
            self::assertTrue($method->invoke($this->detector, 'D:/path/to/vendor'));
        } else {
            // Test Windows paths on Unix for coverage
            self::assertTrue($method->invoke($this->detector, 'C:\\path\\to\\vendor'));
            self::assertTrue($method->invoke($this->detector, 'D:/path/to/vendor'));
        }
    }
}

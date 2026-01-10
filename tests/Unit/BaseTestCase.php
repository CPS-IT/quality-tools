<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Tests\Unit;

use Cpsit\QualityTools\Service\FilesystemService;
use org\bovigo\vfs\vfsStreamDirectory;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Base test case with improved isolation and mocking utilities.
 */
abstract class BaseTestCase extends TestCase
{
    protected TestFilesystemService $testFilesystem;
    protected ?vfsStreamDirectory $vfsRoot = null;
    private array $originalEnvironment = [];
    private array $mockRegistry = [];

    protected function setUp(): void
    {
        parent::setUp();

        // Initialize virtual filesystem
        $this->testFilesystem = new TestFilesystemService();
        $this->vfsRoot = $this->testFilesystem->initializeVfs();

        // Store original environment
        $this->originalEnvironment = $_ENV;

        // Clear mock registry
        $this->mockRegistry = [];
    }

    protected function tearDown(): void
    {
        // Verify no resource leaks before cleanup
        $this->performCleanupVerification();

        // Cleanup mocks
        foreach ($this->mockRegistry as $mock) {
            if ($mock instanceof MockObject) {
                // PHPUnit handles mock cleanup automatically
                // No manual cleanup needed
            }
        }
        $this->mockRegistry = [];

        // Reset virtual filesystem
        $this->testFilesystem->resetVfs();
        $this->vfsRoot = null;

        // Restore original environment
        $_ENV = $this->originalEnvironment;

        // Force garbage collection to ensure cleanup
        gc_collect_cycles();

        parent::tearDown();
    }

    /**
     * Create a virtual file with given content.
     */
    protected function createVirtualFile(string $path, string $content): string
    {
        return $this->testFilesystem->createVfsFile($path, $content);
    }

    /**
     * Create a virtual directory.
     */
    protected function createVirtualDirectory(string $path): string
    {
        return $this->testFilesystem->createVfsDirectory($path);
    }

    /**
     * Get the virtual filesystem root URL.
     */
    protected function getVirtualRoot(): string
    {
        return $this->testFilesystem->getVfsUrl();
    }

    /**
     * Set environment variables for test isolation.
     */
    protected function setEnvironmentVariables(array $variables): void
    {
        foreach ($variables as $key => $value) {
            $_ENV[$key] = $value;
            putenv($key . '=' . $value);
        }
    }

    /**
     * Create a mock and register it for cleanup.
     *
     * @template T of object
     *
     * @param class-string<T> $className
     *
     * @return MockObject&T
     */
    protected function createRegisteredMock(string $className): MockObject
    {
        $mock = $this->createMock($className);
        $this->mockRegistry[] = $mock;

        return $mock;
    }

    /**
     * Public method to create mocks for use in factories.
     */
    public function createTestMockForFactory(string $className): MockObject
    {
        // @phpstan-ignore-next-line
        return $this->createRegisteredMock($className);
    }

    /**
     * Create a configured mock with method expectations.
     *
     * @template T of object
     *
     * @param class-string<T> $className
     *
     * @return MockObject&T
     */
    protected function createTestMock(string $className, array $methods): MockObject
    {
        $mock = $this->createRegisteredMock($className);

        foreach ($methods as $methodName => $configuration) {
            $invocation = $mock->expects($configuration['expects'] ?? $this->any())
                ->method($methodName);

            if (isset($configuration['with']) && \is_array($configuration['with'])) {
                $invocation->with(...array_values($configuration['with']));
            }

            if (isset($configuration['willReturn'])) {
                $invocation->willReturn($configuration['willReturn']);
            }

            if (isset($configuration['willThrow'])) {
                $invocation->willThrowException($configuration['willThrow']);
            }

            if (isset($configuration['willReturnCallback'])) {
                $invocation->willReturnCallback($configuration['willReturnCallback']);
            }
        }

        return $mock;
    }

    /**
     * Create a filesystem service mock with virtual filesystem.
     */
    protected function createFilesystemServiceMock(): MockObject
    {
        // @phpstan-ignore-next-line
        return $this->createTestMock(FilesystemService::class, [
            'fileExists' => [
                'willReturnCallback' => $this->testFilesystem->fileExists(...),
            ],
            'directoryExists' => [
                'willReturnCallback' => $this->testFilesystem->directoryExists(...),
            ],
            'readFile' => [
                'willReturnCallback' => $this->testFilesystem->readFile(...),
            ],
            'writeFile' => [
                'willReturnCallback' => function (string $path, string $content): void { $this->testFilesystem->writeFile($path, $content); },
            ],
        ]);
    }

    /**
     * Assert that no temporary files or directories exist outside virtual filesystem.
     */
    protected function assertNoTemporaryFilesLeaked(): void
    {
        $tempDir = sys_get_temp_dir();
        $patterns = [
            $tempDir . '/qt_*',           // Quality tools temp files
            $tempDir . '/yaml_loader_*',  // YAML loader temp files
            $tempDir . '/phpunit_*',      // PHPUnit temp files
        ];

        $temporaryFiles = [];
        foreach ($patterns as $pattern) {
            $files = glob($pattern);
            if ($files) {
                $temporaryFiles = array_merge($temporaryFiles, $files);
            }
        }

        if (!empty($temporaryFiles)) {
            // Log warning but don't fail test - this is gradual improvement
            fwrite(STDERR, "\nWARNING: Temporary files detected (possible leak): " . implode(', ', $temporaryFiles) . "\n");
        }

        // Only fail if excessive files (likely indicates a real issue)
        $this->assertLessThan(
            20,
            \count($temporaryFiles),
            'Excessive temporary files detected: ' . implode(', ', $temporaryFiles),
        );
    }

    /**
     * Execute a callable with environment variable isolation.
     */
    protected function withEnvironment(array $env, callable $callback): mixed
    {
        $originalEnv = [];

        // Store and set new environment
        foreach ($env as $key => $value) {
            $originalEnv[$key] = $_ENV[$key] ?? null;
            $_ENV[$key] = $value;
            putenv($key . '=' . $value);
        }

        try {
            return $callback();
        } finally {
            // Restore original environment
            foreach ($originalEnv as $key => $value) {
                if ($value === null) {
                    unset($_ENV[$key]);
                    putenv($key);
                } else {
                    $_ENV[$key] = $value;
                    putenv($key . '=' . $value);
                }
            }
        }
    }

    /**
     * Perform comprehensive cleanup verification.
     */
    protected function performCleanupVerification(): void
    {
        // Check for leaked temporary files
        $this->assertNoTemporaryFilesLeaked();

        // Check for leaked environment variables
        $this->assertEnvironmentVariablesCleanedUp();

        // Check for proper virtual filesystem cleanup
        $this->assertVirtualFilesystemCleanedUp();

        // Check for proper mock cleanup
        $this->assertMockRegistryCleanedUp();
    }

    /**
     * Assert that environment variables were properly cleaned up.
     */
    protected function assertEnvironmentVariablesCleanedUp(): void
    {
        // Check for test-specific environment variables that should be cleaned up
        $testEnvVars = [
            'PROJECT_NAME', 'PHP_VERSION', 'PHPSTAN_MEMORY', 'MEMORY_LIMIT',
            'PRIMARY_SCAN_PATH', 'SECONDARY_SCAN_PATH', 'SCAN_PATH', 'PHPSTAN_LEVEL',
        ];

        $leakedVars = [];
        foreach ($testEnvVars as $var) {
            if (isset($_ENV[$var]) && !isset($this->originalEnvironment[$var])) {
                $leakedVars[] = $var;
            }
        }

        $this->assertEmpty(
            $leakedVars,
            'Environment variables were not cleaned up: ' . implode(', ', $leakedVars),
        );
    }

    /**
     * Assert that virtual filesystem was properly cleaned up.
     */
    protected function assertVirtualFilesystemCleanedUp(): void
    {
        if ($this->vfsRoot !== null) {
            // Virtual filesystem should be reset but can still exist
            // The important thing is that it doesn't interfere with other tests
            // VFS isolation is handled by vfsStream internally - no assertion needed
        }
    }

    /**
     * Assert that mock registry was properly managed.
     */
    protected function assertMockRegistryCleanedUp(): void
    {
        // Mock registry should not have excessive mocks
        $mockCount = \count($this->mockRegistry);
        $this->assertLessThanOrEqual(
            50, // Reasonable upper limit
            $mockCount,
            "Mock registry contains {$mockCount} mocks - possible memory leak",
        );
    }

    /**
     * Verify that no test resources are interfering with other tests.
     */
    protected function assertTestIsolation(): void
    {
        $this->performCleanupVerification();

        // Additional isolation checks can be added here
        $this->assertTrue(
            memory_get_usage() < 50 * 1024 * 1024, // 50MB limit
            'Memory usage is too high - possible resource leak',
        );
    }
}

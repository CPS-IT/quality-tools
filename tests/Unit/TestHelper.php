<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Tests\Unit;

/**
 * Helper class for unit tests providing common utilities and test data.
 */
final class TestHelper
{
    /**
     * Create a temporary directory for testing.
     */
    public static function createTempDirectory(string $prefix = 'qt_test_'): string
    {
        $tempDir = sys_get_temp_dir() . '/' . $prefix . uniqid();

        if (!mkdir($tempDir, 0o777, true)) {
            throw new \RuntimeException("Failed to create temp directory: {$tempDir}");
        }

        return $tempDir;
    }

    /**
     * Remove a directory and all its contents recursively.
     */
    public static function removeDirectory(string $directory): void
    {
        if (!file_exists($directory)) {
            return;
        }

        // Handle symbolic links
        if (is_link($directory)) {
            unlink($directory);

            return;
        }

        if (!is_dir($directory)) {
            unlink($directory);

            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($iterator as $file) {
            if ($file->isLink()) {
                unlink($file->getPathname());
            } elseif ($file->isDir()) {
                rmdir($file->getPathname());
            } else {
                unlink($file->getPathname());
            }
        }

        rmdir($directory);
    }

    /**
     * Create a composer.json file with specified content.
     */
    public static function createComposerJson(string $directory, array $content): string
    {
        $composerFile = $directory . '/composer.json';
        $jsonContent = json_encode($content, JSON_PRETTY_PRINT);

        if (file_put_contents($composerFile, $jsonContent) === false) {
            throw new \RuntimeException("Failed to create composer.json in {$directory}");
        }

        return $composerFile;
    }

    /**
     * Get sample composer.json content for different project types.
     */
    public static function getComposerContent(string $type): array
    {
        return match ($type) {
            'typo3-core' => [
                'name' => 'test/typo3-core-project',
                'type' => 'project',
                'require' => [
                    'typo3/cms-core' => '^13.4',
                ],
            ],
            'typo3-minimal' => [
                'name' => 'test/typo3-minimal-project',
                'type' => 'project',
                'require' => [
                    'typo3/minimal' => '^13.4',
                ],
            ],
            'typo3-cms' => [
                'name' => 'test/typo3-cms-project',
                'type' => 'project',
                'require' => [
                    'typo3/cms' => '^13.4',
                ],
            ],
            'typo3-dev' => [
                'name' => 'test/typo3-dev-project',
                'type' => 'project',
                'require' => [
                    'symfony/console' => '^7.0',
                ],
                'require-dev' => [
                    'typo3/cms-core' => '^13.4',
                ],
            ],
            'non-typo3' => [
                'name' => 'test/non-typo3-project',
                'type' => 'project',
                'require' => [
                    'symfony/console' => '^7.0',
                    'doctrine/orm' => '^3.0',
                ],
            ],
            'empty' => [],
            default => throw new \InvalidArgumentException("Unknown project type: {$type}"),
        };
    }

    /**
     * Create a nested directory structure for testing traversal.
     */
    public static function createNestedStructure(string $basePath, int $depth): array
    {
        $paths = [$basePath];
        $currentPath = $basePath;

        mkdir($currentPath, 0o777, true);

        for ($i = 1; $i <= $depth; ++$i) {
            $currentPath .= '/level' . $i;
            mkdir($currentPath, 0o777, true);
            $paths[] = $currentPath;
        }

        return $paths;
    }

    /**
     * Assert that a string contains all expected substrings.
     */
    public static function assertStringContainsAll(array $needles, string $haystack, string $message = ''): void
    {
        foreach ($needles as $needle) {
            if (!str_contains($haystack, (string) $needle)) {
                throw new \PHPUnit\Framework\AssertionFailedError($message ?: "Failed asserting that '{$haystack}' contains '{$needle}'");
            }
        }
    }

    /**
     * Get the project root path for tests.
     */
    public static function getProjectRoot(): string
    {
        return \dirname(__DIR__, 2);
    }

    /**
     * Get the fixtures directory path.
     */
    public static function getFixturesPath(): string
    {
        return __DIR__ . '/../Fixtures';
    }

    /**
     * Backup and restore environment variables for testing.
     */
    public static function withEnvironment(array $variables, callable $callback): mixed
    {
        $originalValues = [];

        // Backup original values
        foreach ($variables as $key => $value) {
            $originalValues[$key] = getenv($key);
            putenv($key . '=' . $value);
        }

        try {
            return $callback();
        } finally {
            // Restore original values
            foreach ($originalValues as $key => $originalValue) {
                if ($originalValue === false) {
                    putenv($key);
                } else {
                    putenv($key . '=' . $originalValue);
                }
            }
        }
    }

    /**
     * Create vendor directory structure with cpsit/quality-tools package
     * This supports both app/vendor and vendor patterns for dynamic detection.
     */
    public static function createVendorStructure(string $projectRoot, bool $useAppVendor = false): string
    {
        $vendorDir = $useAppVendor ? $projectRoot . '/app/vendor' : $projectRoot . '/vendor';
        $qualityToolsDir = $vendorDir . '/cpsit/quality-tools';
        $configDir = $qualityToolsDir . '/config';
        $binDir = $vendorDir . '/bin';

        // Create directories
        mkdir($configDir, 0o777, true);
        mkdir($binDir, 0o777, true);

        return $vendorDir;
    }

    /**
     * Create mock executables in vendor/bin directory.
     */
    public static function createMockExecutables(string $vendorBinDir, array $executables): void
    {
        foreach ($executables as $executable) {
            $executablePath = $vendorBinDir . '/' . $executable;
            // Use the specific message format expected by tests
            $message = $executable === 'composer-normalize'
                ? 'Composer normalize executed successfully'
                : ucfirst((string) $executable) . ' executed successfully';
            file_put_contents($executablePath, "#!/bin/bash\necho '{$message}'\nexit 0\n");
            chmod($executablePath, 0o755);
        }
    }
}

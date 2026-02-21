<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Tests\Unit\Configuration;

use Cpsit\QualityTools\Configuration\ConfigurationLoader;
use Cpsit\QualityTools\Configuration\ConfigurationLoaderWrapper;
use Cpsit\QualityTools\Configuration\ConfigurationValidator;
use Cpsit\QualityTools\Configuration\HierarchicalConfigurationLoader;
use Cpsit\QualityTools\Configuration\SimpleConfigurationLoader;
use Cpsit\QualityTools\Service\FilesystemService;
use Cpsit\QualityTools\Service\PathResolutionService;
use Cpsit\QualityTools\Service\ProjectConfigService;
use Cpsit\QualityTools\Service\SecurityService;
use Cpsit\QualityTools\Service\ToolConfigService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Unit test to isolate configuration loader behavior differences.
 *
 * This test reproduces the exact setup from CommandExitCodeConsistencyTest to identify
 * why ConfigurationLoaderWrapper and ConfigurationLoader return different paths.
 */
final class LoaderPathResolutionTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/loader_test_' . uniqid();
        mkdir($this->tempDir, 0o755, true);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tempDir)) {
            $this->removeDirectory($this->tempDir);
        }
    }

    private function removeDirectory(string $dir): void
    {
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = "$dir/$file";
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }

    /**
     * Reproduce the exact loader creation from CommandExitCodeConsistencyTest.
     */
    private function createWrapperLoader(): ConfigurationLoaderWrapper
    {
        $simpleLoader = new SimpleConfigurationLoader(
            new ConfigurationValidator(),
            new SecurityService(),
            new FilesystemService(new Filesystem()),
        );

        $hierarchicalLoader = new HierarchicalConfigurationLoader(
            new ConfigurationValidator(),
            new SecurityService(),
            new FilesystemService(),
        );

        return new ConfigurationLoaderWrapper(
            $simpleLoader,
            $hierarchicalLoader,
            'hierarchical',
        );
    }

    private function createUnifiedLoader(): ConfigurationLoader
    {
        return new ConfigurationLoader(
            new ConfigurationValidator(),
            new SecurityService(),
            new FilesystemService(),
            new ProjectConfigService(),
            new ToolConfigService(),
            new PathResolutionService(),
        );
    }

    #[DataProvider('directoryStructureProvider')]
    public function testLoaderBehaviorWithDifferentDirectoryStructures(
        array $directoriesToCreate,
        array $configFilesToCreate,
        array $expectedWrapperPaths,
        ?array $expectedUnifiedPaths,
    ): void {
        // Create a test directory structure
        foreach ($directoriesToCreate as $dir) {
            mkdir($this->tempDir . '/' . $dir, 0o755, true);
        }

        // Create configuration files
        foreach ($configFilesToCreate as $file => $content) {
            file_put_contents($this->tempDir . '/' . $file, $content);
        }

        $wrapperLoader = $this->createWrapperLoader();
        $unifiedLoader = $this->createUnifiedLoader();

        // Load configuration using both loaders
        $wrapperConfig = $wrapperLoader->load($this->tempDir);
        $unifiedConfig = $unifiedLoader->load($this->tempDir);

        // Test path resolution for the composer tool
        $wrapperPaths = $wrapperConfig->getResolvedPathsForTool('composer');
        $unifiedPaths = $unifiedConfig->getResolvedPathsForTool('composer');

        /*
         * Note: the unified loader is considered correct
         * Unified loader: Uses PathResolutionService with existence checks -> returns absolute paths to existing dirs
         * Wrapper loader: Falls back to getScanPaths() -> returns default relative paths regardless of existence
         **/
        // Verify types and basic behaviour
        $this->assertIsArray($wrapperPaths);
        $this->assertIsArray($unifiedPaths);

        // Verify wrapper paths match expected values
        $this->assertEquals($expectedWrapperPaths, $wrapperPaths, 'Wrapper loader paths should match expected values');

        // Convert unified absolute paths to relative for comparison
        $relativeUnifiedPaths = array_map(function ($path) {
            // Handle both /var and /private/var paths (macOS symlink)
            $realTempDir = realpath($this->tempDir);
            $relativePath = str_replace($realTempDir . '/', '', $path);
            // Handle case where the path doesn't have a trailing slash
            if ($relativePath === $path) {
                $relativePath = str_replace($realTempDir, '', $path);
                $relativePath = ltrim($relativePath, '/');
            }

            return $relativePath;
        }, $unifiedPaths);

        // Verify unified paths match expected values (relative form)
        $this->assertEquals($expectedUnifiedPaths, $relativeUnifiedPaths, 'Unified loader should find existing directories');

        // The unified loader should return absolute paths when directories exist
        foreach ($unifiedPaths as $path) {
            $this->assertStringStartsWith('/', $path, 'Unified loader should return absolute paths');
        }
    }

    public static function directoryStructureProvider(): array
    {
        return [
            // Test case 1: Empty directory (no packages/, no config/, no config files)
            'empty_directory' => [
                'directoriesToCreate' => [],
                'configFilesToCreate' => [],
                'expectedWrapperPaths' => ['packages/', 'config/system/'], // EnhancedConfig fallback
                'expectedUnifiedPaths' => [], // PathScanner finds nothing
            ],

            // Test case 2: Only packages/ directory exists
            'packages_only' => [
                'directoriesToCreate' => ['packages'],
                'configFilesToCreate' => [],
                'expectedWrapperPaths' => ['packages/', 'config/system/'], // EnhancedConfig fallback
                'expectedUnifiedPaths' => ['packages'], // PathScanner finds existing packages dir
            ],

            // Test case 3: Only config/system/ directory exists
            'config_only' => [
                'directoriesToCreate' => ['config/system'],
                'configFilesToCreate' => [],
                'expectedWrapperPaths' => ['packages/', 'config/system/'], // EnhancedConfig fallback
                'expectedUnifiedPaths' => ['config/system'], // PathScanner finds existing config/system dir
            ],

            // Test case 4: Both directories exist
            'both_directories' => [
                'directoriesToCreate' => ['packages', 'config/system'],
                'configFilesToCreate' => [],
                'expectedWrapperPaths' => ['packages/', 'config/system/'], // EnhancedConfig fallback
                'expectedUnifiedPaths' => ['config/system', 'packages'], // PathScanner finds both existing dirs
            ],

            // Test case 5: With .quality-tools.yaml config file
            'with_config_file' => [
                'directoriesToCreate' => ['packages', 'config/system'],
                'configFilesToCreate' => [
                    '.quality-tools.yaml' => "quality-tools:\n  paths:\n    scan:\n      - packages/\n      - config/system/\n",
                ],
                'expectedWrapperPaths' => ['packages/', 'config/system/'], // Config paths merged with defaults
                'expectedUnifiedPaths' => ['config/system', 'packages'], // Hierarchical finds both existing dirs
            ],

            // Test case 6: Custom paths in config
            'custom_paths' => [
                'directoriesToCreate' => ['src', 'lib'],
                'configFilesToCreate' => [
                    '.quality-tools.yaml' => "quality-tools:\n  paths:\n    scan:\n      - src/\n      - lib/\n",
                ],
                'expectedWrapperPaths' => ['packages/', 'config/system/', 'src/', 'lib/'], // Config paths merged with defaults
                'expectedUnifiedPaths' => ['lib', 'src'], // Hierarchical finds both custom existing dirs
            ],
        ];
    }
}

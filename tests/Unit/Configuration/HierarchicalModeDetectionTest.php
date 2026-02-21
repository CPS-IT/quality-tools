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
use Cpsit\QualityTools\Tests\Unit\TestHelper;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

/**
 * Tests hierarchical mode detection and activation between wrapper and unified approaches.
 *
 * Problem: Unified loader doesn't auto-detect hierarchical configurations like wrapper does.
 */
final class HierarchicalModeDetectionTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = TestHelper::createTempDirectory('hierarchical_detection_test_');
    }

    protected function tearDown(): void
    {
        TestHelper::removeDirectory($this->tempDir);
    }

    public function testHierarchicalModeDetectionAndActivation(): void
    {
        // Create hierarchical configuration scenario
        $parentDir = $this->tempDir . '/parent';
        $childDir = $parentDir . '/child';
        mkdir($childDir, 0o777, true);

        // Parent configuration
        $parentConfig = [
            'quality-tools' => [
                'project' => [
                    'name' => 'parent-project',
                    'php_version' => '8.3',
                ],
                'tools' => [
                    'rector' => ['enabled' => true, 'level' => 'basic'],
                ],
            ],
        ];

        // Child configuration (overrides parent)
        $childConfig = [
            'quality-tools' => [
                'project' => [
                    'name' => 'child-project',
                    'php_version' => '8.4',
                ],
                'tools' => [
                    'rector' => ['level' => 'typo3-13'],
                ],
            ],
        ];

        file_put_contents($parentDir . '/quality-tools.yaml', Yaml::dump($parentConfig, 4, 2));
        file_put_contents($childDir . '/quality-tools.yaml', Yaml::dump($childConfig, 4, 2));

        // Test 1: Wrapper approach with auto-detection
        $simpleLoader = new SimpleConfigurationLoader(
            new ConfigurationValidator(),
            new SecurityService(),
            new FilesystemService(new \Symfony\Component\Filesystem\Filesystem()),
        );

        $hierarchicalLoader = new HierarchicalConfigurationLoader(
            new ConfigurationValidator(),
            new SecurityService(),
            new FilesystemService(),
        );
        $wrapperLoader = new ConfigurationLoaderWrapper(
            $simpleLoader,
            $hierarchicalLoader,
            'hierarchical', // Use hierarchical mode instead of 'auto'
        );

        // Test 2: Unified approach (should detect hierarchical structure automatically)
        $unifiedLoader = new ConfigurationLoader(
            new ConfigurationValidator(),
            new SecurityService(),
            new FilesystemService(),
            new ProjectConfigService(),
            new ToolConfigService(),
            new PathResolutionService(),
        );

        // Load from child directory - both should detect hierarchical structure
        $configFromWrapper = $wrapperLoader->load($childDir);
        $configFromUnified = $unifiedLoader->load($childDir);

        // Both should detect hierarchical structure and merge parent + child
        $this->assertEquals('child-project', $configFromWrapper->getProjectName());
        $this->assertEquals('child-project', $configFromUnified->getProjectName());

        // Both should merge tool configurations
        $wrapperRectorConfig = $configFromWrapper->getToolConfig('rector');
        $unifiedRectorConfig = $configFromUnified->getToolConfig('rector');

        // Should have merged: enabled=true from parent, level=typo3-13 from child
        $this->assertTrue($wrapperRectorConfig['enabled']);
        $this->assertTrue($unifiedRectorConfig['enabled']);
        $this->assertEquals('typo3-13', $wrapperRectorConfig['level']);
        $this->assertEquals('typo3-13', $unifiedRectorConfig['level']);

        // Both should provide identical merge results
        $this->assertEquals($wrapperRectorConfig, $unifiedRectorConfig);
    }

    public function testSourceTrackingConsistency(): void
    {
        // Create multi-level hierarchy for source tracking
        $rootDir = $this->tempDir . '/root';
        $middleDir = $rootDir . '/middle';
        $leafDir = $middleDir . '/leaf';
        mkdir($leafDir, 0o777, true);

        // Root configuration
        $rootConfig = [
            'quality-tools' => [
                'project' => ['name' => 'root-project'],
                'tools' => ['rector' => ['enabled' => true]],
            ],
        ];

        // Middle configuration
        $middleConfig = [
            'quality-tools' => [
                'project' => ['php_version' => '8.3'],
                'tools' => ['phpstan' => ['enabled' => true, 'level' => 6]],
            ],
        ];

        // Leaf configuration
        $leafConfig = [
            'quality-tools' => [
                'project' => ['name' => 'leaf-project'],
                'tools' => ['rector' => ['level' => 'typo3-13']],
            ],
        ];

        file_put_contents($rootDir . '/quality-tools.yaml', Yaml::dump($rootConfig, 4, 2));
        file_put_contents($middleDir . '/quality-tools.yaml', Yaml::dump($middleConfig, 4, 2));
        file_put_contents($leafDir . '/quality-tools.yaml', Yaml::dump($leafConfig, 4, 2));

        // Create loaders
        $wrapperLoader = $this->createWrapperLoader();
        $unifiedLoader = $this->createUnifiedLoader();

        // Load from leaf directory
        $configFromWrapper = $wrapperLoader->load($leafDir);
        $configFromUnified = $unifiedLoader->load($leafDir);

        // Both should provide identical merged results
        $this->assertEquals('leaf-project', $configFromWrapper->getProjectName()); // From leaf
        $this->assertEquals('leaf-project', $configFromUnified->getProjectName());

        $this->assertEquals('8.3', $configFromWrapper->getProjectPhpVersion()); // From middle
        $this->assertEquals('8.3', $configFromUnified->getProjectPhpVersion());

        // Tool configurations should be identical
        $this->assertEquals(
            $configFromWrapper->isToolEnabled('rector'),
            $configFromUnified->isToolEnabled('rector'),
        );
        $this->assertEquals(
            $configFromWrapper->isToolEnabled('phpstan'),
            $configFromUnified->isToolEnabled('phpstan'),
        );
    }

    public function testFallbackToSimpleModeWhenNoHierarchy(): void
    {
        // Create single configuration file (no hierarchy)
        $singleConfig = [
            'quality-tools' => [
                'project' => [
                    'name' => 'simple-project',
                    'php_version' => '8.4',
                ],
            ],
        ];

        file_put_contents($this->tempDir . '/quality-tools.yaml', Yaml::dump($singleConfig, 4, 2));

        // Both loaders should fall back to simple mode
        $wrapperLoader = $this->createWrapperLoader();
        $unifiedLoader = $this->createUnifiedLoader();

        $configFromWrapper = $wrapperLoader->load($this->tempDir);
        $configFromUnified = $unifiedLoader->load($this->tempDir);

        // Should work identically in simple mode
        $this->assertEquals('simple-project', $configFromWrapper->getProjectName());
        $this->assertEquals('simple-project', $configFromUnified->getProjectName());
        $this->assertEquals('8.4', $configFromWrapper->getProjectPhpVersion());
        $this->assertEquals('8.4', $configFromUnified->getProjectPhpVersion());
    }

    public function testHierarchicalDetectionWithMissingParentConfigs(): void
    {
        // Create child directory with config, but no parent configs
        $childDir = $this->tempDir . '/parent/child';
        mkdir($childDir, 0o777, true);

        $childConfig = [
            'quality-tools' => [
                'project' => ['name' => 'orphan-child'],
            ],
        ];

        file_put_contents($childDir . '/quality-tools.yaml', Yaml::dump($childConfig, 4, 2));

        $wrapperLoader = $this->createWrapperLoader();
        $unifiedLoader = $this->createUnifiedLoader();

        // Both should handle missing parent gracefully
        $configFromWrapper = $wrapperLoader->load($childDir);
        $configFromUnified = $unifiedLoader->load($childDir);

        $this->assertEquals('orphan-child', $configFromWrapper->getProjectName());
        $this->assertEquals('orphan-child', $configFromUnified->getProjectName());

        // Both should behave identically with missing hierarchy
        $this->assertEquals(
            $configFromWrapper->toArray(),
            $configFromUnified->toArray(),
        );
    }

    private function createWrapperLoader(): ConfigurationLoaderWrapper
    {
        $simpleLoader = new SimpleConfigurationLoader(
            new ConfigurationValidator(),
            new SecurityService(),
            new FilesystemService(new \Symfony\Component\Filesystem\Filesystem()),
        );

        $hierarchicalLoader = new HierarchicalConfigurationLoader(
            new ConfigurationValidator(),
            new SecurityService(),
            new FilesystemService(),
        );

        return new ConfigurationLoaderWrapper(
            $simpleLoader,
            $hierarchicalLoader,
            'hierarchical', // Use hierarchical mode
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
}

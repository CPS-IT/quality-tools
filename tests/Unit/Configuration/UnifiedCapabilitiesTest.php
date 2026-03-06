<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Tests\Unit\Configuration;

use Cpsit\QualityTools\Configuration\EnhancedConfiguration;
use Cpsit\QualityTools\Configuration\SimpleConfiguration;
use Cpsit\QualityTools\Service\FilesystemService;
use Cpsit\QualityTools\Service\PathResolutionService;
use Cpsit\QualityTools\Service\SecurityService;
use Cpsit\QualityTools\Tests\Unit\TestHelper;
use Cpsit\QualityTools\Utility\VendorDirectoryDetector;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Test for Step 4.3: Verify unified capabilities across both configuration variants.
 *
 * Tests that both SimpleConfiguration and EnhancedConfiguration provide
 * consistent behavior for all ConfigurationInterface methods.
 */
final class UnifiedCapabilitiesTest extends TestCase
{
    private array $testData;
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = TestHelper::createTempDirectory('unified_test_');

        $this->testData = [
            'quality-tools' => [
                'project' => [
                    'name' => 'unified-test-project',
                    'php_version' => '8.4',
                    'typo3_version' => '13.4',
                ],
                'tools' => [
                    'rector' => [
                        'enabled' => true,
                        'level' => 'typo3-13',
                    ],
                    'phpstan' => [
                        'enabled' => true,
                        'level' => 8,
                    ],
                ],
                'paths' => [
                    'scan' => ['src/', 'packages/'],
                    'exclude' => ['var/', 'tmp/'],
                ],
                'output' => [
                    'verbosity' => 'debug',
                    'colors' => false,
                ],
                'performance' => [
                    'parallel' => false,
                    'max_processes' => 2,
                ],
            ],
        ];
    }

    protected function tearDown(): void
    {
        TestHelper::removeDirectory($this->tempDir);
        parent::tearDown();
    }

    public function testBothVariantsProvideBasicProjectConfiguration(): void
    {
        $simpleConfig = new SimpleConfiguration($this->testData);
        $enhancedConfig = new EnhancedConfiguration($this->testData);

        // Both should provide same basic project configuration
        self::assertSame('unified-test-project', $simpleConfig->getProjectName());
        self::assertSame('unified-test-project', $enhancedConfig->getProjectName());

        self::assertSame('8.4', $simpleConfig->getProjectPhpVersion());
        self::assertSame('8.4', $enhancedConfig->getProjectPhpVersion());

        self::assertSame('13.4', $simpleConfig->getProjectTypo3Version());
        self::assertSame('13.4', $enhancedConfig->getProjectTypo3Version());
    }

    public function testBothVariantsProvidePathConfiguration(): void
    {
        $simpleConfig = new SimpleConfiguration($this->testData);
        $enhancedConfig = new EnhancedConfiguration($this->testData);

        // Both should provide same path configuration
        self::assertSame(['src/', 'packages/'], $simpleConfig->getScanPaths());
        self::assertSame(['src/', 'packages/'], $enhancedConfig->getScanPaths());

        self::assertSame(['var/', 'tmp/'], $simpleConfig->getExcludePaths());
        self::assertSame(['var/', 'tmp/'], $enhancedConfig->getExcludePaths());
    }

    public function testBothVariantsProvideToolConfiguration(): void
    {
        $simpleConfig = new SimpleConfiguration($this->testData);
        $enhancedConfig = new EnhancedConfiguration($this->testData);

        // Both should provide same tool configuration
        self::assertTrue($simpleConfig->isToolEnabled('rector'));
        self::assertTrue($enhancedConfig->isToolEnabled('rector'));

        self::assertTrue($simpleConfig->isToolEnabled('phpstan'));
        self::assertTrue($enhancedConfig->isToolEnabled('phpstan'));

        $simpleRectorConfig = $simpleConfig->getToolConfig('rector');
        $enhancedRectorConfig = $enhancedConfig->getToolConfig('rector');

        self::assertTrue($simpleRectorConfig['enabled']);
        self::assertTrue($enhancedRectorConfig['enabled']);

        self::assertSame('typo3-13', $simpleRectorConfig['level']);
        self::assertSame('typo3-13', $enhancedRectorConfig['level']);
    }

    public function testBothVariantsProvideOutputConfiguration(): void
    {
        $simpleConfig = new SimpleConfiguration($this->testData);
        $enhancedConfig = new EnhancedConfiguration($this->testData);

        // Both should provide same output configuration
        self::assertSame('debug', $simpleConfig->getVerbosity());
        self::assertSame('debug', $enhancedConfig->getVerbosity());

        self::assertFalse($simpleConfig->isColorsEnabled());
        self::assertFalse($enhancedConfig->isColorsEnabled());

        self::assertTrue($simpleConfig->isProgressEnabled()); // Default
        self::assertTrue($enhancedConfig->isProgressEnabled()); // Default
    }

    public function testBothVariantsProvidePerformanceConfiguration(): void
    {
        $simpleConfig = new SimpleConfiguration($this->testData);
        $enhancedConfig = new EnhancedConfiguration($this->testData);

        // Both should provide same performance configuration
        self::assertFalse($simpleConfig->isParallelEnabled());
        self::assertFalse($enhancedConfig->isParallelEnabled());

        self::assertSame(2, $simpleConfig->getMaxProcesses());
        self::assertSame(2, $enhancedConfig->getMaxProcesses());

        self::assertTrue($simpleConfig->isCacheEnabled()); // Default
        self::assertTrue($enhancedConfig->isCacheEnabled()); // Default
    }

    public function testBothVariantsProvideVendorDirectoryMethods(): void
    {
        $simpleConfig = new SimpleConfiguration($this->testData);
        $enhancedConfig = new EnhancedConfiguration($this->testData);

        $simpleConfig->setProjectRoot($this->tempDir);
        $enhancedConfig->setProjectRoot($this->tempDir);

        // Both should provide vendor directory methods (may return null if not found)
        $simpleVendorPath = $simpleConfig->getVendorPath();
        $enhancedVendorPath = $enhancedConfig->getVendorPath();

        // Both should return same result (null in temp dir without vendor)
        self::assertNull($simpleVendorPath);
        self::assertNull($enhancedVendorPath);

        self::assertFalse($simpleConfig->hasVendorDirectory());
        self::assertFalse($enhancedConfig->hasVendorDirectory());

        // Both should provide debug info
        $simpleDebugInfo = $simpleConfig->getVendorDetectionDebugInfo();
        $enhancedDebugInfo = $enhancedConfig->getVendorDetectionDebugInfo();

        self::assertIsArray($simpleDebugInfo);
        self::assertIsArray($enhancedDebugInfo);
    }

    public function testBothVariantsProvidePathResolutionMethods(): void
    {
        $simpleConfig = new SimpleConfiguration($this->testData);
        $enhancedConfig = new EnhancedConfiguration($this->testData);

        $simpleConfig->setProjectRoot($this->tempDir);
        $enhancedConfig->setProjectRoot($this->tempDir);

        // Both should provide path resolution methods
        $simpleResolvedPaths = $simpleConfig->getResolvedPathsForTool('rector');
        $enhancedResolvedPaths = $enhancedConfig->getResolvedPathsForTool('rector');

        self::assertIsArray($simpleResolvedPaths);
        self::assertIsArray($enhancedResolvedPaths);

        // Both should provide debug info for path scanning
        $simplePathDebug = $simpleConfig->getPathScanningDebugInfo('rector');
        $enhancedPathDebug = $enhancedConfig->getPathScanningDebugInfo('rector');

        self::assertIsArray($simplePathDebug);
        self::assertIsArray($enhancedPathDebug);

        // Both should include basic debug info
        self::assertArrayHasKey('tool', $simplePathDebug);
        self::assertArrayHasKey('tool', $enhancedPathDebug);
        self::assertSame('rector', $simplePathDebug['tool']);
        self::assertSame('rector', $enhancedPathDebug['tool']);
    }

    public function testEnhancedConfigurationProvidesTotalConfigurationMethods(): void
    {
        $enhancedConfig = new EnhancedConfiguration($this->testData);

        // Enhanced configuration should provide source tracking methods
        self::assertNull($enhancedConfig->getConfigurationSource('some.key'));
        self::assertIsArray($enhancedConfig->getConfigurationSources());
        self::assertIsArray($enhancedConfig->getConfigurationConflicts());
        self::assertFalse($enhancedConfig->hasConfigurationConflicts());
        self::assertIsArray($enhancedConfig->getConflictsForKey('some.key'));
        self::assertIsArray($enhancedConfig->getMergeSummary());

        // Enhanced configuration should provide tool-specific methods
        self::assertFalse($enhancedConfig->usesCustomConfigFile('rector'));
        self::assertNull($enhancedConfig->getCustomConfigFilePath('rector'));
        self::assertIsArray($enhancedConfig->getConfigurationWithSources());
        self::assertIsArray($enhancedConfig->getToolConfigurationResolved('rector'));

        // Enhanced configuration should provide hierarchy methods
        self::assertNull($enhancedConfig->getHierarchyInfo());
        self::assertNull($enhancedConfig->getDiscoveryInfo());
        self::assertFalse($enhancedConfig->isHierarchicalConfiguration());
        self::assertIsArray($enhancedConfig->getToolsWithCustomConfigs());

        // Enhanced configuration should provide debug methods
        self::assertIsArray($enhancedConfig->getComprehensiveDebugInfo());
        self::assertIsArray($enhancedConfig->exportWithMetadata());
        self::assertFalse($enhancedConfig->wasValueOverridden('some.key'));
        self::assertIsArray($enhancedConfig->getConfigurationChain('some.key'));
    }

    public function testSimpleConfigurationProvidesEnhancedMethodsWithDefaults(): void
    {
        $simpleConfig = new SimpleConfiguration($this->testData);

        // Simple configuration should provide enhanced methods with sensible defaults
        self::assertNull($simpleConfig->getConfigurationSource('some.key'));
        self::assertSame([], $simpleConfig->getConfigurationSources());
        self::assertSame([], $simpleConfig->getConfigurationConflicts());
        self::assertFalse($simpleConfig->hasConfigurationConflicts());
        self::assertSame([], $simpleConfig->getConflictsForKey('some.key'));
        self::assertSame([], $simpleConfig->getMergeSummary());

        // Simple configuration should provide tool-specific methods with defaults
        self::assertFalse($simpleConfig->usesCustomConfigFile('rector'));
        self::assertNull($simpleConfig->getCustomConfigFilePath('rector'));
        self::assertSame($this->testData, $simpleConfig->getConfigurationWithSources());
        self::assertIsArray($simpleConfig->getToolConfigurationResolved('rector'));

        // Simple configuration should provide hierarchy methods with defaults
        self::assertNull($simpleConfig->getHierarchyInfo());
        self::assertNull($simpleConfig->getDiscoveryInfo());
        self::assertFalse($simpleConfig->isHierarchicalConfiguration());
        self::assertSame([], $simpleConfig->getToolsWithCustomConfigs());

        // Simple configuration should provide debug methods
        self::assertIsArray($simpleConfig->getComprehensiveDebugInfo());
        self::assertIsArray($simpleConfig->exportWithMetadata());
        self::assertFalse($simpleConfig->wasValueOverridden('some.key'));
        self::assertSame([], $simpleConfig->getConfigurationChain('some.key'));
    }

    public function testBothVariantsProvideToolConfigWithDefaults(): void
    {
        $simpleConfig = new SimpleConfiguration($this->testData);
        $enhancedConfig = new EnhancedConfiguration($this->testData);

        // SimpleConfiguration has specific methods for tool configs with defaults
        $simpleRectorConfig = $simpleConfig->getRectorConfig();
        $simplePhpStanConfig = $simpleConfig->getPhpStanConfig();
        $simpleFractorConfig = $simpleConfig->getFractorConfig();

        // EnhancedConfiguration uses getToolConfig() which also applies defaults
        $enhancedRectorConfig = $enhancedConfig->getToolConfig('rector');
        $enhancedPhpStanConfig = $enhancedConfig->getToolConfig('phpstan');
        $enhancedFractorConfig = $enhancedConfig->getToolConfig('fractor');

        // Both should provide similar structure for Rector
        self::assertTrue($simpleRectorConfig['enabled']);
        self::assertTrue($enhancedRectorConfig['enabled']);

        self::assertSame('typo3-13', $simpleRectorConfig['level']);
        self::assertSame('typo3-13', $enhancedRectorConfig['level']);

        // Both should have PHP version (SimpleConfiguration adds it, EnhancedConfiguration too)
        self::assertSame('8.4', $simpleRectorConfig['php_version']);
        self::assertSame('8.4', $enhancedRectorConfig['php_version']);

        // Both should provide PHPStan with defaults
        self::assertTrue($simplePhpStanConfig['enabled']);
        self::assertTrue($enhancedPhpStanConfig['enabled']);

        self::assertSame('1G', $simplePhpStanConfig['memory_limit']);
        self::assertSame('1G', $enhancedPhpStanConfig['memory_limit']);

        // Both should provide Fractor with defaults
        self::assertTrue($simpleFractorConfig['enabled']);
        self::assertTrue($enhancedFractorConfig['enabled']);
    }

    public function testEnhancedConfigurationWithPathResolutionService(): void
    {
        $fileSystem = new Filesystem();
        $securityService = new SecurityService();
        $filesystemService = new FilesystemService($fileSystem, $securityService);
        $pathResolutionService = new PathResolutionService($filesystemService, new VendorDirectoryDetector());
        $enhancedConfig = new EnhancedConfiguration(
            data: $this->testData,
            pathResolutionService: $pathResolutionService,
        );

        $enhancedConfig->setProjectRoot($this->tempDir);

        // Should use injected service for path resolution
        $resolvedPaths = $enhancedConfig->getResolvedPathsForTool('rector');
        $pathDebug = $enhancedConfig->getPathScanningDebugInfo('rector');

        self::assertIsArray($resolvedPaths);
        self::assertIsArray($pathDebug);

        // Debug should include service status
        self::assertArrayHasKey('tool', $pathDebug);
        self::assertSame('rector', $pathDebug['tool']);
        self::assertArrayHasKey('service_status', $pathDebug);
        self::assertSame('path_resolution_service_used', $pathDebug['service_status']);
    }
}

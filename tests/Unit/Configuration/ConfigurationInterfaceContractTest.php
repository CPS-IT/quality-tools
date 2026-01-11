<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Tests\Unit\Configuration;

use Cpsit\QualityTools\Configuration\ConfigurationInterface;
use Cpsit\QualityTools\Configuration\ConfigurationWrapper;
use Cpsit\QualityTools\Configuration\EnhancedConfiguration;
use Cpsit\QualityTools\Configuration\SimpleConfiguration;
use PHPUnit\Framework\TestCase;

/**
 * Contract test ensuring all ConfigurationInterface implementations
 * provide consistent behavior and return types.
 */
final class ConfigurationInterfaceContractTest extends TestCase
{
    /**
     * @return array<string, array{ConfigurationInterface}>
     */
    public static function configurationImplementationsProvider(): array
    {
        $testData = [
            'quality-tools' => [
                'project' => [
                    'name' => 'test-project',
                    'php_version' => '8.4',
                    'typo3_version' => '12.4',
                ],
                'paths' => [
                    'scan' => ['src/', 'tests/'],
                    'exclude' => ['build/', 'var/'],
                ],
                'tools' => [
                    'rector' => [
                        'enabled' => true,
                        'level' => 'typo3-13',
                    ],
                    'phpstan' => [
                        'enabled' => false,
                        'level' => 8,
                    ],
                ],
                'output' => [
                    'verbosity' => 'verbose',
                    'colors' => false,
                    'progress' => true,
                ],
                'performance' => [
                    'parallel' => true,
                    'max_processes' => 8,
                    'cache_enabled' => false,
                ],
            ],
        ];

        return [
            'SimpleConfiguration' => [new SimpleConfiguration($testData)],
            'EnhancedConfiguration' => [new EnhancedConfiguration($testData)],
            'ConfigurationWrapper with SimpleConfiguration' => [
                new ConfigurationWrapper(new SimpleConfiguration($testData), 'simple'),
            ],
            'ConfigurationWrapper with EnhancedConfiguration' => [
                new ConfigurationWrapper(new EnhancedConfiguration($testData), 'enhanced'),
            ],
        ];
    }

    /**
     * @dataProvider configurationImplementationsProvider
     */
    public function testCoreDataAccess(ConfigurationInterface $configuration): void
    {
        // Test toArray returns valid array
        $data = $configuration->toArray();
        self::assertIsArray($data);
        self::assertArrayHasKey('quality-tools', $data);

        // Test project root setters/getters
        $configuration->setProjectRoot('/test/path');
        self::assertSame('/test/path', $configuration->getProjectRoot());
    }

    /**
     * @dataProvider configurationImplementationsProvider
     */
    public function testProjectConfigurationMethods(ConfigurationInterface $configuration): void
    {
        self::assertSame('8.4', $configuration->getProjectPhpVersion());
        self::assertSame('12.4', $configuration->getProjectTypo3Version());
        self::assertSame('test-project', $configuration->getProjectName());
    }

    /**
     * @dataProvider configurationImplementationsProvider
     */
    public function testPathConfigurationMethods(ConfigurationInterface $configuration): void
    {
        self::assertSame(['src/', 'tests/'], $configuration->getScanPaths());
        self::assertSame(['build/', 'var/'], $configuration->getExcludePaths());

        // getToolPaths should return array (behavior may vary by implementation)
        $toolPaths = $configuration->getToolPaths('rector');
        self::assertIsArray($toolPaths);
    }

    /**
     * @dataProvider configurationImplementationsProvider
     */
    public function testToolConfigurationMethods(ConfigurationInterface $configuration): void
    {
        self::assertTrue($configuration->isToolEnabled('rector'));
        self::assertFalse($configuration->isToolEnabled('phpstan'));

        $rectorConfig = $configuration->getToolConfig('rector');
        self::assertIsArray($rectorConfig);
        self::assertSame('typo3-13', $rectorConfig['level']);
    }

    /**
     * @dataProvider configurationImplementationsProvider
     */
    public function testOutputConfigurationMethods(ConfigurationInterface $configuration): void
    {
        self::assertSame('verbose', $configuration->getVerbosity());
        self::assertFalse($configuration->isColorsEnabled());
        self::assertTrue($configuration->isProgressEnabled());
    }

    /**
     * @dataProvider configurationImplementationsProvider
     */
    public function testPerformanceConfigurationMethods(ConfigurationInterface $configuration): void
    {
        self::assertTrue($configuration->isParallelEnabled());
        self::assertSame(8, $configuration->getMaxProcesses());
        self::assertFalse($configuration->isCacheEnabled());
    }

    /**
     * @dataProvider configurationImplementationsProvider
     */
    public function testVendorDirectoryMethods(ConfigurationInterface $configuration): void
    {
        // These may return null for uninitialized configurations
        $vendorPath = $configuration->getVendorPath();
        $vendorBinPath = $configuration->getVendorBinPath();

        if ($vendorPath !== null) {
            self::assertIsString($vendorPath);
        }

        if ($vendorBinPath !== null) {
            self::assertIsString($vendorBinPath);
        }

        self::assertIsBool($configuration->hasVendorDirectory());
        self::assertIsArray($configuration->getVendorDetectionDebugInfo());
    }

    /**
     * @dataProvider configurationImplementationsProvider
     */
    public function testPathResolutionMethods(ConfigurationInterface $configuration): void
    {
        $resolvedPaths = $configuration->getResolvedPathsForTool('rector');
        self::assertIsArray($resolvedPaths);

        $debugInfo = $configuration->getPathScanningDebugInfo('rector');
        self::assertIsArray($debugInfo);
    }

    /**
     * @dataProvider configurationImplementationsProvider
     */
    public function testEnhancedConfigurationMethods(ConfigurationInterface $configuration): void
    {
        // Test methods that may return null for non-enhanced configurations
        $source = $configuration->getConfigurationSource('project.name');
        if ($source !== null) {
            self::assertIsString($source);
        }

        self::assertIsArray($configuration->getConfigurationSources());
        self::assertIsArray($configuration->getConfigurationConflicts());
        self::assertIsBool($configuration->hasConfigurationConflicts());
        self::assertIsArray($configuration->getConflictsForKey('project.name'));
        self::assertIsArray($configuration->getMergeSummary());

        self::assertIsBool($configuration->usesCustomConfigFile('rector'));

        $customConfigPath = $configuration->getCustomConfigFilePath('rector');
        if ($customConfigPath !== null) {
            self::assertIsString($customConfigPath);
        }

        self::assertIsArray($configuration->getConfigurationWithSources());
        self::assertIsArray($configuration->getToolConfigurationResolved('rector'));

        $hierarchyInfo = $configuration->getHierarchyInfo();
        if ($hierarchyInfo !== null) {
            self::assertIsArray($hierarchyInfo);
        }

        $discoveryInfo = $configuration->getDiscoveryInfo();
        if ($discoveryInfo !== null) {
            self::assertIsArray($discoveryInfo);
        }

        self::assertIsBool($configuration->isHierarchicalConfiguration());
        self::assertIsArray($configuration->getToolsWithCustomConfigs());
        self::assertIsArray($configuration->getComprehensiveDebugInfo());
        self::assertIsArray($configuration->exportWithMetadata());
        self::assertIsBool($configuration->wasValueOverridden('project.name'));
        self::assertIsArray($configuration->getConfigurationChain('project.name'));
    }

    /**
     * @dataProvider configurationImplementationsProvider
     */
    public function testMergeFunctionality(ConfigurationInterface $configuration): void
    {
        $otherData = [
            'quality-tools' => [
                'project' => [
                    'name' => 'other-project',
                ],
                'tools' => [
                    'fractor' => [
                        'enabled' => true,
                    ],
                ],
            ],
        ];

        $other = new SimpleConfiguration($otherData);
        $merged = $configuration->merge($other);

        self::assertInstanceOf(ConfigurationInterface::class, $merged);
        self::assertNotSame($configuration, $merged);

        // Verify merge behavior
        $mergedData = $merged->toArray();
        self::assertArrayHasKey('quality-tools', $mergedData);
    }

    /**
     * Test all implementations return consistent types for optional values.
     */
    public function testOptionalValueConsistency(): void
    {
        $implementations = self::configurationImplementationsProvider();

        foreach ($implementations as $name => [$config]) {
            // Test consistent null/string returns
            $projectName = $config->getProjectName();
            self::assertTrue(
                \is_string($projectName) || $projectName === null,
                "getProjectName() must return string|null in $name",
            );

            $vendorPath = $config->getVendorPath();
            self::assertTrue(
                \is_string($vendorPath) || $vendorPath === null,
                "getVendorPath() must return string|null in $name",
            );

            $vendorBinPath = $config->getVendorBinPath();
            self::assertTrue(
                \is_string($vendorBinPath) || $vendorBinPath === null,
                "getVendorBinPath() must return string|null in $name",
            );

            $projectRoot = $config->getProjectRoot();
            self::assertTrue(
                \is_string($projectRoot) || $projectRoot === null,
                "getProjectRoot() must return string|null in $name",
            );
        }
    }
}

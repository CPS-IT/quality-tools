<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Tests\Unit\Configuration;

use Cpsit\QualityTools\Configuration\Configuration;
use Cpsit\QualityTools\Configuration\ConfigurationInterface;
use Cpsit\QualityTools\Service\PathResolutionService;
use Cpsit\QualityTools\Service\ProjectConfigService;
use Cpsit\QualityTools\Service\ToolConfigService;
use PHPUnit\Framework\TestCase;

/**
 * Simplified tests for unified Configuration class that don't require mocking.
 * 
 * @covers \Cpsit\QualityTools\Configuration\Configuration
 */
final class UnifiedConfigurationSimpleTest extends TestCase
{
    public function testImplementsConfigurationInterface(): void
    {
        $config = new Configuration();
        
        self::assertInstanceOf(ConfigurationInterface::class, $config);
    }

    public function testCreateSimpleFactory(): void
    {
        $config = Configuration::createSimple(
            data: ['quality-tools' => ['project' => ['name' => 'test']]],
            projectConfigService: new ProjectConfigService(),
        );

        self::assertFalse($config->isHierarchicalConfiguration());
        self::assertSame('test', $config->getProjectName());
    }

    public function testCreateHierarchicalFactory(): void
    {
        $config = Configuration::createHierarchical(
            data: ['quality-tools' => ['project' => ['name' => 'test']]],
            sourceMap: ['quality-tools.project.name' => 'project-config'],
            projectConfigService: new ProjectConfigService(),
        );

        self::assertTrue($config->isHierarchicalConfiguration());
        self::assertSame('test', $config->getProjectName());
        self::assertSame('project-config', $config->getConfigurationSource('quality-tools.project.name'));
    }

    public function testProjectConfigurationWithServices(): void
    {
        $data = [
            'quality-tools' => [
                'project' => [
                    'name' => 'my-project',
                    'php_version' => '8.4',
                    'typo3_version' => '13.5',
                ],
            ],
        ];

        $config = Configuration::createSimple(
            data: $data,
            projectConfigService: new ProjectConfigService(),
        );

        self::assertSame('my-project', $config->getProjectName());
        self::assertSame('8.4', $config->getProjectPhpVersion());
        self::assertSame('13.5', $config->getProjectTypo3Version());
    }

    public function testToolConfigurationWithServices(): void
    {
        $data = [
            'quality-tools' => [
                'tools' => [
                    'rector' => ['enabled' => true, 'level' => 'typo3-13'],
                    'phpstan' => ['enabled' => false, 'level' => 6],
                ],
            ],
        ];

        $config = Configuration::createSimple(
            data: $data,
            toolConfigService: new ToolConfigService(),
        );

        self::assertTrue($config->isToolEnabled('rector'));
        self::assertFalse($config->isToolEnabled('phpstan'));
        self::assertSame(['enabled' => true, 'level' => 'typo3-13'], $config->getToolConfig('rector'));
    }

    public function testPathConfigurationWithServices(): void
    {
        $data = [
            'quality-tools' => [
                'paths' => [
                    'scan' => ['src/', 'config/'],
                    'exclude' => ['build/', 'tmp/'],
                ],
                'tools' => [
                    'rector' => ['paths' => ['packages/', 'extensions/']],
                ],
            ],
        ];

        $config = Configuration::createSimple(
            data: $data,
            pathResolutionService: new PathResolutionService(),
        );

        self::assertSame(['src/', 'config/'], $config->getScanPaths());
        self::assertSame(['build/', 'tmp/'], $config->getExcludePaths());
        self::assertSame(['packages/', 'extensions/'], $config->getToolPaths('rector'));
    }

    public function testOutputConfiguration(): void
    {
        $data = [
            'quality-tools' => [
                'output' => [
                    'verbosity' => 'verbose',
                    'colors' => false,
                    'progress' => false,
                ],
            ],
        ];

        $config = new Configuration($data);

        self::assertSame('verbose', $config->getVerbosity());
        self::assertFalse($config->isColorsEnabled());
        self::assertFalse($config->isProgressEnabled());
    }

    public function testPerformanceConfiguration(): void
    {
        $data = [
            'quality-tools' => [
                'performance' => [
                    'parallel' => true,
                    'max_processes' => 8,
                    'cache' => false,
                ],
            ],
        ];

        $config = new Configuration($data);

        self::assertTrue($config->isParallelEnabled());
        self::assertSame(8, $config->getMaxProcesses());
        self::assertFalse($config->isCacheEnabled());
    }

    public function testDefaultValues(): void
    {
        $config = new Configuration();

        // Project defaults
        self::assertSame('8.3', $config->getProjectPhpVersion());
        self::assertSame('13.4', $config->getProjectTypo3Version());
        self::assertNull($config->getProjectName());

        // Path defaults
        self::assertSame(['packages/', 'config/system/'], $config->getScanPaths());
        self::assertContains('vendor/', $config->getExcludePaths());
        self::assertSame([], $config->getToolPaths('rector'));

        // Tool defaults
        self::assertTrue($config->isToolEnabled('rector'));
        self::assertSame([], $config->getToolConfig('rector'));

        // Output defaults
        self::assertSame('normal', $config->getVerbosity());
        self::assertTrue($config->isColorsEnabled());
        self::assertTrue($config->isProgressEnabled());

        // Performance defaults
        self::assertFalse($config->isParallelEnabled());
        self::assertSame(4, $config->getMaxProcesses());
        self::assertTrue($config->isCacheEnabled());
    }

    public function testProjectRootManagement(): void
    {
        $config = new Configuration();
        
        self::assertNull($config->getProjectRoot());
        
        $config->setProjectRoot('/project/path');
        
        self::assertSame('/project/path', $config->getProjectRoot());
    }

    public function testHierarchicalModeToggle(): void
    {
        // Simple mode
        $simpleConfig = Configuration::createSimple();
        self::assertFalse($simpleConfig->isHierarchicalConfiguration());
        self::assertSame([], $simpleConfig->getConfigurationSources());
        self::assertSame([], $simpleConfig->getConfigurationConflicts());

        // Hierarchical mode
        $hierarchicalConfig = Configuration::createHierarchical(
            sourceMap: ['quality-tools.project.name' => 'test-source'],
            conflicts: [['key' => 'test-conflict']],
        );
        self::assertTrue($hierarchicalConfig->isHierarchicalConfiguration());
        self::assertSame(['quality-tools.project.name' => 'test-source'], $hierarchicalConfig->getConfigurationSources());
        self::assertSame([['key' => 'test-conflict']], $hierarchicalConfig->getConfigurationConflicts());
    }

    public function testMergeConfigurations(): void
    {
        $config1 = Configuration::createSimple(
            data: ['quality-tools' => ['project' => ['name' => 'project1']]],
        );

        $config2 = Configuration::createSimple(
            data: ['quality-tools' => ['tools' => ['rector' => ['enabled' => true]]]],
        );

        $merged = $config1->merge($config2);

        self::assertInstanceOf(Configuration::class, $merged);
        
        $mergedData = $merged->toArray();
        self::assertSame('project1', $mergedData['quality-tools']['project']['name']);
        self::assertTrue($mergedData['quality-tools']['tools']['rector']['enabled']);
    }

    public function testMergeInvalidTypeThrowsException(): void
    {
        $config1 = Configuration::createSimple();
        $invalidConfig = $this->createMock(ConfigurationInterface::class);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Can only merge with another Configuration instance');

        $config1->merge($invalidConfig);
    }

    public function testDebugInfo(): void
    {
        $config = Configuration::createHierarchical(
            data: ['quality-tools' => ['project' => ['name' => 'test']]],
            sourceMap: ['quality-tools.project.name' => 'source'],
            projectConfigService: new ProjectConfigService(),
            toolConfigService: new ToolConfigService(),
            pathResolutionService: new PathResolutionService(),
        );
        $config->setProjectRoot('/project');

        $debugInfo = $config->getComprehensiveDebugInfo();

        self::assertSame('hierarchical', $debugInfo['mode']);
        self::assertSame('/project', $debugInfo['project_root']);
        self::assertTrue($debugInfo['services']['project_config']);
        self::assertTrue($debugInfo['services']['tool_config']);
        self::assertTrue($debugInfo['services']['path_resolution']);

        $pathDebugInfo = $config->getPathScanningDebugInfo('rector');
        self::assertSame('rector', $pathDebugInfo['tool']);
        self::assertSame('/project', $pathDebugInfo['project_root']);

        $vendorDebugInfo = $config->getVendorDetectionDebugInfo();
        self::assertSame('/project', $vendorDebugInfo['project_root']);
    }

    public function testExportWithMetadata(): void
    {
        $data = ['quality-tools' => ['project' => ['name' => 'test']]];

        // Simple export
        $simpleConfig = Configuration::createSimple($data);
        $export = $simpleConfig->exportWithMetadata();

        self::assertSame($data, $export['configuration']);
        self::assertSame('simple', $export['mode']);
        self::assertArrayNotHasKey('metadata', $export);

        // Hierarchical export
        $sourceMap = ['quality-tools.project.name' => 'source'];
        $hierarchicalConfig = Configuration::createHierarchical(
            data: $data,
            sourceMap: $sourceMap,
        );
        $export = $hierarchicalConfig->exportWithMetadata();

        self::assertSame($data, $export['configuration']);
        self::assertSame('hierarchical', $export['mode']);
        self::assertArrayHasKey('metadata', $export);
        self::assertSame($sourceMap, $export['metadata']['source_map']);
    }

    public function testArrayAccess(): void
    {
        $data = ['quality-tools' => ['project' => ['name' => 'test']]];
        $config = new Configuration($data);

        self::assertSame($data, $config->toArray());
    }
}
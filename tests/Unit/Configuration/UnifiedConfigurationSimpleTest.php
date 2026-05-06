<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Tests\Unit\Configuration;

use Cpsit\QualityTools\Configuration\Configuration;
use Cpsit\QualityTools\Configuration\ConfigurationInterface;
use Cpsit\QualityTools\Service\FilesystemService;
use Cpsit\QualityTools\Service\PathResolutionService;
use Cpsit\QualityTools\Service\ProjectConfigService;
use Cpsit\QualityTools\Service\SecurityService;
use Cpsit\QualityTools\Service\ToolConfigService;
use Cpsit\QualityTools\Utility\VendorDirectoryDetector;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Simplified tests for unified Configuration class that don't require mocking.
 *
 * @covers \Cpsit\QualityTools\Configuration\Configuration
 */
final class UnifiedConfigurationSimpleTest extends TestCase
{
    private PathResolutionService $pathResolutionService;

    public function setUp(): void
    {
        $this->pathResolutionService = new PathResolutionService(
            new FilesystemService(
                new Filesystem(),
                new SecurityService(),
            ),
            new VendorDirectoryDetector(),
        );
    }

    public function testImplementsConfigurationInterface(): void
    {
        $config = new Configuration(getcwd());

        self::assertIsArray($config->toArray());
    }

    public function testCreateSimpleFactory(): void
    {
        $config = Configuration::createSimple(
            projectRoot: getcwd(),
            data: ['quality-tools' => ['project' => ['name' => 'test']]],
            projectConfigService: new ProjectConfigService(),
        );

        self::assertFalse($config->isHierarchicalConfiguration());
        self::assertSame('test', $config->getProjectName());
    }

    public function testCreateHierarchicalFactory(): void
    {
        $config = Configuration::createHierarchical(
            projectRoot: getcwd(),
            data: ['quality-tools' => ['project' => ['name' => 'test']]],
            sourceMap: ['quality-tools.project.name' => 'project-config'],
            projectConfigService: new ProjectConfigService(),
        );

        self::assertTrue($config->isHierarchicalConfiguration());
        self::assertSame('test', $config->getProjectName());
        self::assertSame('project-config', $config->getConfigurationSource('quality-tools.project.name'));
    }

    public function testCreateDefault(): void
    {
        $config = Configuration::createDefault(projectRoot: getcwd());

        // Test that it's properly initialized
        self::assertFalse($config->isHierarchicalConfiguration());

        // Test some key defaults to ensure it's properly initialized
        self::assertSame(ConfigurationInterface::DEFAULT_PHP_VERSION, $config->getProjectPhpVersion());
        self::assertSame(ConfigurationInterface::DEFAULT_TYPO3_VERSION, $config->getProjectTypo3Version());
        self::assertSame(ConfigurationInterface::DEFAULT_SCAN_PATHS, $config->getScanPaths());

        // Test that tools are enabled by default
        self::assertTrue($config->isToolEnabled('rector'));
        self::assertTrue($config->isToolEnabled('phpstan'));
        self::assertTrue($config->isToolEnabled('fractor'));

        // Test tool configurations from raw data (since no services injected)
        $data = $config->toArray();
        $rectorConfig = $data['quality-tools']['tools']['rector'] ?? [];
        self::assertTrue($rectorConfig['enabled'] ?? false);
        self::assertSame(ConfigurationInterface::DEFAULT_RECTOR_LEVEL, $rectorConfig['level'] ?? null);

        $phpstanConfig = $data['quality-tools']['tools']['phpstan'] ?? [];
        self::assertSame(ConfigurationInterface::DEFAULT_PHPSTAN_LEVEL, $phpstanConfig['level'] ?? null);
        self::assertSame(ConfigurationInterface::DEFAULT_PHPSTAN_MEMORY_LIMIT, $phpstanConfig['memory_limit'] ?? null);

        // Test output defaults
        self::assertSame(ConfigurationInterface::DEFAULT_VERBOSITY, $config->getVerbosity());
        self::assertSame(ConfigurationInterface::DEFAULT_COLORS_ENABLED, $config->isColorsEnabled());
        self::assertSame(ConfigurationInterface::DEFAULT_PROGRESS_ENABLED, $config->isProgressEnabled());

        // Test performance defaults
        self::assertSame(ConfigurationInterface::DEFAULT_PARALLEL_ENABLED, $config->isParallelEnabled());
        self::assertSame(ConfigurationInterface::DEFAULT_MAX_PROCESSES, $config->getMaxProcesses());
        self::assertSame(ConfigurationInterface::DEFAULT_CACHE_ENABLED, $config->isCacheEnabled());
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
            projectRoot: getcwd(),
            data: $data,
            projectConfigService: new ProjectConfigService(),
        );

        self::assertSame('my-project', $config->getProjectName());
        self::assertSame('8.4', $config->getProjectPhpVersion());
        self::assertSame('13.5', $config->getProjectTypo3Version());
    }

    #[DataProvider('toolConfigurationProvider')]
    public function testToolConfiguration(string $tool, array $inputConfig, array $expectedConfig, bool $expectedEnabled): void
    {
        $data = [
            'quality-tools' => [
                'tools' => [
                    $tool => $inputConfig,
                ],
            ],
        ];

        $config = Configuration::createSimple(
            projectRoot: getcwd(),
            data: $data,
            toolConfigService: new ToolConfigService(),
        );

        self::assertSame($expectedEnabled, $config->isToolEnabled($tool));
        self::assertSame($expectedConfig, $config->getToolConfig($tool));
    }

    public static function toolConfigurationProvider(): array
    {
        return [
            // Rector - defaults
            'rector_defaults' => [
                'tool' => 'rector',
                'inputConfig' => [],
                'expectedConfig' => [
                    'enabled' => true,
                    'level' => 'typo3-14',
                    'php_version' => '8.3', // Dynamic value from getProjectPhpVersion()
                ],
                'expectedEnabled' => true,
            ],
            // Rector - with overrides
            'rector_overrides' => [
                'tool' => 'rector',
                'inputConfig' => ['enabled' => false, 'level' => 'typo3-12'],
                'expectedConfig' => [
                    'enabled' => false,
                    'level' => 'typo3-12',
                    'php_version' => '8.3', // Dynamic value from getProjectPhpVersion()
                ],
                'expectedEnabled' => false,
            ],
            // PHPStan - defaults
            'phpstan_defaults' => [
                'tool' => 'phpstan',
                'inputConfig' => [],
                'expectedConfig' => [
                    'enabled' => true,
                    'level' => 6,
                    'memory_limit' => '1G',
                ],
                'expectedEnabled' => true,
            ],
            // PHPStan - with overrides
            'phpstan_overrides' => [
                'tool' => 'phpstan',
                'inputConfig' => ['enabled' => false, 'level' => 8, 'memory_limit' => '2G'],
                'expectedConfig' => [
                    'enabled' => false,
                    'level' => 8,
                    'memory_limit' => '2G',
                ],
                'expectedEnabled' => false,
            ],
            // Fractor - defaults
            'fractor_defaults' => [
                'tool' => 'fractor',
                'inputConfig' => [],
                'expectedConfig' => [
                    'enabled' => true,
                    'indentation' => 2,
                ],
                'expectedEnabled' => true,
            ],
            // Fractor - with overrides
            'fractor_overrides' => [
                'tool' => 'fractor',
                'inputConfig' => ['enabled' => false, 'indentation' => 4],
                'expectedConfig' => [
                    'enabled' => false,
                    'indentation' => 4,
                ],
                'expectedEnabled' => false,
            ],
            // PHP CS Fixer - defaults
            'php-cs-fixer_defaults' => [
                'tool' => 'php-cs-fixer',
                'inputConfig' => [],
                'expectedConfig' => [
                    'enabled' => true,
                    'preset' => 'typo3',
                ],
                'expectedEnabled' => true,
            ],
            // PHP CS Fixer - with overrides
            'php-cs-fixer_overrides' => [
                'tool' => 'php-cs-fixer',
                'inputConfig' => ['enabled' => false, 'preset' => 'psr12'],
                'expectedConfig' => [
                    'enabled' => false,
                    'preset' => 'psr12',
                ],
                'expectedEnabled' => false,
            ],
            // TypoScript Lint - defaults
            'typoscript-lint_defaults' => [
                'tool' => 'typoscript-lint',
                'inputConfig' => [],
                'expectedConfig' => [
                    'enabled' => true,
                    'indentation' => 2,
                ],
                'expectedEnabled' => true,
            ],
            // TypoScript Lint - with overrides
            'typoscript-lint_overrides' => [
                'tool' => 'typoscript-lint',
                'inputConfig' => ['enabled' => false, 'indentation' => 4],
                'expectedConfig' => [
                    'enabled' => false,
                    'indentation' => 4,
                ],
                'expectedEnabled' => false,
            ],
        ];
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

        $fileSystem = new Filesystem();
        $securityService = new SecurityService();
        $filesystemService = new FilesystemService($fileSystem, $securityService);
        $config = Configuration::createSimple(
            projectRoot: getcwd(),
            data: $data,
            pathResolutionService: $this->pathResolutionService,
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

        $config = new Configuration(getcwd(), $data);

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

        $config = new Configuration(getcwd(), $data);

        self::assertTrue($config->isParallelEnabled());
        self::assertSame(8, $config->getMaxProcesses());
        self::assertFalse($config->isCacheEnabled());
    }

    public function testDefaultValues(): void
    {
        $config = new Configuration(getcwd());

        // Project defaults
        self::assertSame(ConfigurationInterface::DEFAULT_PHP_VERSION, $config->getProjectPhpVersion());
        self::assertSame(ConfigurationInterface::DEFAULT_TYPO3_VERSION, $config->getProjectTypo3Version());
        self::assertNull($config->getProjectName());

        // Path defaults
        self::assertSame(ConfigurationInterface::DEFAULT_SCAN_PATHS, $config->getScanPaths());
        self::assertContains('vendor/', $config->getExcludePaths());
        self::assertSame([], $config->getToolPaths('rector'));

        // Tool defaults
        self::assertTrue($config->isToolEnabled('rector'));
        self::assertSame(['enabled' => true, 'level' => 'typo3-14', 'php_version' => '8.3'], $config->getToolConfig('rector'));

        // Output defaults
        self::assertSame(ConfigurationInterface::DEFAULT_VERBOSITY, $config->getVerbosity());
        self::assertSame(ConfigurationInterface::DEFAULT_COLORS_ENABLED, $config->isColorsEnabled());
        self::assertSame(ConfigurationInterface::DEFAULT_PROGRESS_ENABLED, $config->isProgressEnabled());

        // Performance defaults
        self::assertFalse($config->isParallelEnabled());
        self::assertSame(ConfigurationInterface::DEFAULT_MAX_PROCESSES, $config->getMaxProcesses());
        self::assertSame(ConfigurationInterface::DEFAULT_CACHE_ENABLED, $config->isCacheEnabled());
    }

    public function testProjectRootManagement(): void
    {
        $projectRoot = getcwd();
        $config = new Configuration($projectRoot);

        self::assertSame($projectRoot, $config->getProjectRoot());

        // Test that setting the same project root is idempotent
        $config->setProjectRoot($projectRoot);

        self::assertSame($projectRoot, $config->getProjectRoot());
    }

    public function testHierarchicalModeToggle(): void
    {
        // Simple mode
        $simpleConfig = Configuration::createSimple(projectRoot: getcwd());
        self::assertFalse($simpleConfig->isHierarchicalConfiguration());
        self::assertSame([], $simpleConfig->getConfigurationSources());
        self::assertSame([], $simpleConfig->getConfigurationConflicts());

        // Hierarchical mode
        $hierarchicalConfig = Configuration::createHierarchical(
            projectRoot: getcwd(),
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
            projectRoot: getcwd(),
            data: ['quality-tools' => ['project' => ['name' => 'project1']]],
        );

        $config2 = Configuration::createSimple(
            projectRoot: getcwd(),
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
        $config1 = Configuration::createSimple(projectRoot: getcwd());
        $invalidConfig = $this->createMock(ConfigurationInterface::class);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Can only merge with another Configuration instance');

        $config1->merge($invalidConfig);
    }

    public function testDebugInfo(): void
    {
        $fileSystem = new Filesystem();
        $securityService = new SecurityService();
        $filesystemService = new FilesystemService($fileSystem, $securityService);
        $config = Configuration::createHierarchical(
            projectRoot: '/project',
            data: ['quality-tools' => ['project' => ['name' => 'test']]],
            sourceMap: ['quality-tools.project.name' => 'source'],
            projectConfigService: new ProjectConfigService(),
            toolConfigService: new ToolConfigService(),
            pathResolutionService: $this->pathResolutionService,
        );

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
        $simpleConfig = Configuration::createSimple(
            projectRoot: getcwd(),
            data: $data,
        );
        $export = $simpleConfig->exportWithMetadata();

        self::assertSame($data, $export['configuration']);
        self::assertSame('simple', $export['mode']);
        self::assertArrayNotHasKey('metadata', $export);

        // Hierarchical export
        $sourceMap = ['quality-tools.project.name' => 'source'];
        $hierarchicalConfig = Configuration::createHierarchical(
            projectRoot: getcwd(),
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
        $config = new Configuration(getcwd(), $data);

        self::assertSame($data, $config->toArray());
    }

    public function testDefaultConfigurationConstant(): void
    {
        // Test that the DEFAULT_CONFIGURATION constant contains all expected structure
        $defaultConfig = ConfigurationInterface::DEFAULT_CONFIGURATION;

        self::assertArrayHasKey('quality-tools', $defaultConfig);

        $qualityTools = $defaultConfig['quality-tools'];
        self::assertArrayHasKey('project', $qualityTools);
        self::assertArrayHasKey('paths', $qualityTools);
        self::assertArrayHasKey('tools', $qualityTools);
        self::assertArrayHasKey('output', $qualityTools);
        self::assertArrayHasKey('performance', $qualityTools);

        // Verify that createDefault() uses the same structure
        $config = Configuration::createDefault(projectRoot: getcwd());
        self::assertSame($defaultConfig, $config->toArray());
    }
}

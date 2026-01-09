<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Tests\Unit\Configuration;

use Cpsit\QualityTools\Configuration\Configuration;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Cpsit\QualityTools\Configuration\Configuration
 */
final class ConfigurationTest extends TestCase
{
    public function testDefaultConfiguration(): void
    {
        $config = new Configuration();

        // Test default project values
        self::assertSame('8.3', $config->getProjectPhpVersion());
        self::assertSame('13.4', $config->getProjectTypo3Version());
        self::assertNull($config->getProjectName());

        // Test default paths
        self::assertSame(['packages/', 'config/system/'], $config->getScanPaths());
        self::assertSame(['var/', 'vendor/', 'public/', '_assets/', 'fileadmin/', 'typo3/', 'Tests/', 'tests/', 'typo3conf/'], $config->getExcludePaths());

        // Test tool enablement defaults
        self::assertTrue($config->isToolEnabled('rector'));
        self::assertTrue($config->isToolEnabled('fractor'));
        self::assertTrue($config->isToolEnabled('phpstan'));
        self::assertTrue($config->isToolEnabled('php-cs-fixer'));
        self::assertTrue($config->isToolEnabled('typoscript-lint'));

        // Test output defaults
        self::assertSame('normal', $config->getVerbosity());
        self::assertTrue($config->isColorsEnabled());
        self::assertTrue($config->isProgressEnabled());

        // Test performance defaults
        self::assertTrue($config->isParallelEnabled());
        self::assertSame(4, $config->getMaxProcesses());
        self::assertTrue($config->isCacheEnabled());
    }

    public function testConfigurationWithCustomData(): void
    {
        $data = [
            'quality-tools' => [
                'project' => [
                    'name' => 'test-project',
                    'php_version' => '8.4',
                    'typo3_version' => '12.4',
                ],
                'paths' => [
                    'scan' => ['src/', 'lib/'],
                    'exclude' => ['build/', 'docs/'],
                ],
                'tools' => [
                    'rector' => [
                        'enabled' => false,
                        'level' => 'typo3-12',
                    ],
                    'phpstan' => [
                        'enabled' => true,
                        'level' => 8,
                        'memory_limit' => '2G',
                    ],
                ],
                'output' => [
                    'verbosity' => 'verbose',
                    'colors' => false,
                    'progress' => false,
                ],
                'performance' => [
                    'parallel' => false,
                    'max_processes' => 2,
                    'cache_enabled' => false,
                ],
            ],
        ];

        $config = new Configuration($data);

        // Test custom project values
        self::assertSame('8.4', $config->getProjectPhpVersion());
        self::assertSame('12.4', $config->getProjectTypo3Version());
        self::assertSame('test-project', $config->getProjectName());

        // Test custom paths
        self::assertSame(['src/', 'lib/'], $config->getScanPaths());
        self::assertSame(['build/', 'docs/'], $config->getExcludePaths());

        // Test tool configuration
        self::assertFalse($config->isToolEnabled('rector'));
        self::assertTrue($config->isToolEnabled('phpstan'));

        // Test custom output settings
        self::assertSame('verbose', $config->getVerbosity());
        self::assertFalse($config->isColorsEnabled());
        self::assertFalse($config->isProgressEnabled());

        // Test custom performance settings
        self::assertFalse($config->isParallelEnabled());
        self::assertSame(2, $config->getMaxProcesses());
        self::assertFalse($config->isCacheEnabled());
    }

    public function testGetToolConfig(): void
    {
        $data = [
            'quality-tools' => [
                'tools' => [
                    'rector' => [
                        'enabled' => true,
                        'level' => 'typo3-13',
                        'custom_option' => 'test_value',
                    ],
                ],
            ],
        ];

        $config = new Configuration($data);

        $rectorConfig = $config->getToolConfig('rector');
        self::assertSame([
            'enabled' => true,
            'level' => 'typo3-13',
            'custom_option' => 'test_value',
        ], $rectorConfig);

        $nonExistentConfig = $config->getToolConfig('non-existent');
        self::assertSame([], $nonExistentConfig);
    }

    public function testGetRectorConfig(): void
    {
        $config = new Configuration();

        $rectorConfig = $config->getRectorConfig();

        $expected = [
            'enabled' => true,
            'level' => 'typo3-13',
            'php_version' => '8.3',
        ];

        self::assertSame($expected, $rectorConfig);
    }

    public function testGetRectorConfigWithCustomOptions(): void
    {
        $data = [
            'quality-tools' => [
                'project' => [
                    'php_version' => '8.4',
                ],
                'tools' => [
                    'rector' => [
                        'enabled' => false,
                        'level' => 'typo3-12',
                        'dry_run' => true,
                    ],
                ],
            ],
        ];

        $config = new Configuration($data);
        $rectorConfig = $config->getRectorConfig();

        $expected = [
            'enabled' => false,
            'level' => 'typo3-12',
            'php_version' => '8.4',
            'dry_run' => true,
        ];

        self::assertSame($expected, $rectorConfig);
    }

    public function testGetFractorConfig(): void
    {
        $config = new Configuration();

        $fractorConfig = $config->getFractorConfig();

        $expected = [
            'enabled' => true,
            'indentation' => 2,
        ];

        self::assertSame($expected, $fractorConfig);
    }

    public function testGetPhpStanConfig(): void
    {
        $config = new Configuration();

        $phpStanConfig = $config->getPhpStanConfig();

        $expected = [
            'enabled' => true,
            'level' => 6,
            'memory_limit' => '1G',
        ];

        self::assertSame($expected, $phpStanConfig);
    }

    public function testGetPhpCsFixerConfig(): void
    {
        $config = new Configuration();

        $phpCsFixerConfig = $config->getPhpCsFixerConfig();

        $expected = [
            'enabled' => true,
            'preset' => 'typo3',
        ];

        self::assertSame($expected, $phpCsFixerConfig);
    }

    public function testGetTypoScriptLintConfig(): void
    {
        $config = new Configuration();

        $typoscriptLintConfig = $config->getTypoScriptLintConfig();

        $expected = [
            'enabled' => true,
            'indentation' => 2,
        ];

        self::assertSame($expected, $typoscriptLintConfig);
    }

    public function testIsToolEnabledDefault(): void
    {
        $config = new Configuration();

        // Default is true for all tools
        self::assertTrue($config->isToolEnabled('unknown-tool'));
    }

    public function testIsToolEnabledCustom(): void
    {
        $data = [
            'quality-tools' => [
                'tools' => [
                    'rector' => ['enabled' => false],
                    'phpstan' => ['enabled' => true],
                ],
            ],
        ];

        $config = new Configuration($data);

        self::assertFalse($config->isToolEnabled('rector'));
        self::assertTrue($config->isToolEnabled('phpstan'));
        self::assertTrue($config->isToolEnabled('fractor')); // not specified, defaults to true
    }

    public function testToArray(): void
    {
        $data = [
            'quality-tools' => [
                'project' => [
                    'name' => 'test',
                ],
            ],
        ];

        $config = new Configuration($data);

        self::assertSame($data, $config->toArray());
    }

    public function testMerge(): void
    {
        $config1 = new Configuration([
            'quality-tools' => [
                'project' => [
                    'name' => 'test',
                    'php_version' => '8.3',
                ],
                'tools' => [
                    'rector' => ['enabled' => true],
                ],
            ],
        ]);

        $config2 = new Configuration([
            'quality-tools' => [
                'project' => [
                    'typo3_version' => '13.4', // This should be added (avoid php_version conflict)
                ],
                'tools' => [
                    'phpstan' => ['enabled' => false], // This should be added
                ],
                'output' => [
                    'verbosity' => 'verbose', // New section
                ],
            ],
        ]);

        $merged = $config1->merge($config2);

        self::assertNotSame($config1, $merged);
        self::assertNotSame($config2, $merged);

        // Test merged values
        self::assertSame('test', $merged->getProjectName());
        self::assertSame('8.3', $merged->getProjectPhpVersion()); // from config1
        self::assertSame('13.4', $merged->getProjectTypo3Version()); // added from config2

        // Test tool configs are merged
        self::assertTrue($merged->isToolEnabled('rector')); // from config1
        self::assertFalse($merged->isToolEnabled('phpstan')); // from config2

        // Test new section is added
        self::assertSame('verbose', $merged->getVerbosity()); // from config2
    }

    public function testCreateDefault(): void
    {
        $config = Configuration::createDefault();

        // Test some key defaults to ensure it's properly initialized
        self::assertSame('8.3', $config->getProjectPhpVersion());
        self::assertSame('13.4', $config->getProjectTypo3Version());
        self::assertTrue($config->isToolEnabled('rector'));
        self::assertSame(['packages/', 'config/system/'], $config->getScanPaths());
        self::assertSame(['var/', 'vendor/', 'public/', '_assets/', 'fileadmin/', 'typo3/', 'Tests/', 'tests/', 'typo3conf/'], $config->getExcludePaths());

        // Verify the structure contains expected defaults
        $data = $config->toArray();
        self::assertArrayHasKey('quality-tools', $data);
        self::assertArrayHasKey('project', $data['quality-tools']);
        self::assertArrayHasKey('paths', $data['quality-tools']);
        self::assertArrayHasKey('tools', $data['quality-tools']);
        self::assertArrayHasKey('output', $data['quality-tools']);
        self::assertArrayHasKey('performance', $data['quality-tools']);
    }

    public function testEmptyDataHandling(): void
    {
        $config = new Configuration([]);

        // Should still provide defaults
        self::assertSame('8.3', $config->getProjectPhpVersion());
        self::assertSame('13.4', $config->getProjectTypo3Version());
        self::assertNull($config->getProjectName());
        self::assertSame(['packages/', 'config/system/'], $config->getScanPaths());
        self::assertTrue($config->isToolEnabled('any-tool'));
    }

    public function testPartialConfigurationHandling(): void
    {
        $data = [
            'quality-tools' => [
                'project' => [
                    'name' => 'test',
                    // php_version and typo3_version missing - should use defaults
                ],
                // paths missing - should use defaults
                'tools' => [
                    'rector' => [
                        // enabled missing - should use default true via isToolEnabled
                        'level' => 'typo3-12',
                    ],
                ],
                // output and performance missing - should use defaults
            ],
        ];

        $config = new Configuration($data);

        self::assertSame('test', $config->getProjectName());
        self::assertSame('8.3', $config->getProjectPhpVersion()); // default
        self::assertSame('13.4', $config->getProjectTypo3Version()); // default
        self::assertSame(['packages/', 'config/system/'], $config->getScanPaths()); // default
        self::assertTrue($config->isToolEnabled('rector')); // default
        self::assertSame('normal', $config->getVerbosity()); // default
        self::assertTrue($config->isParallelEnabled()); // default
    }
}

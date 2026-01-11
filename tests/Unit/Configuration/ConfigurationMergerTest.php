<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Tests\Unit\Configuration;

use Cpsit\QualityTools\Configuration\ConfigurationHierarchy;
use Cpsit\QualityTools\Configuration\ConfigurationMerger;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for configuration merging algorithm.
 */
#[CoversClass(ConfigurationMerger::class)]
final class ConfigurationMergerTest extends TestCase
{
    private ConfigurationMerger $merger;
    private ConfigurationHierarchy $hierarchy;

    protected function setUp(): void
    {
        $this->hierarchy = new ConfigurationHierarchy('/test/project');
        $this->merger = new ConfigurationMerger();
    }

    public function testMergeEmptyConfigurations(): void
    {
        $result = $this->merger->mergeConfigurations([]);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('data', $result);
        $this->assertArrayHasKey('source_map', $result);
        $this->assertArrayHasKey('conflicts', $result);
        $this->assertArrayHasKey('merge_summary', $result);

        $this->assertEmpty($result['data']);
        $this->assertEmpty($result['source_map']);
        $this->assertEmpty($result['conflicts']);
    }

    public function testMergeSingleConfiguration(): void
    {
        $configurations = [
            [
                'source' => 'test',
                'precedence' => 1,
                'data' => [
                    'quality-tools' => [
                        'project' => ['name' => 'test-project'],
                        'tools' => ['rector' => ['enabled' => true]],
                    ],
                ],
            ],
        ];

        $result = $this->merger->mergeConfigurations($configurations);

        $expected = [
            'quality-tools' => [
                'project' => ['name' => 'test-project'],
                'tools' => ['rector' => ['enabled' => true]],
            ],
        ];

        $this->assertEquals($expected, $result['data']);
        $this->assertEmpty($result['conflicts']);
    }

    public function testMergeTwoConfigurationsWithoutConflicts(): void
    {
        $configurations = [
            [
                'source' => 'base',
                'precedence' => 2,
                'data' => [
                    'quality-tools' => [
                        'project' => ['name' => 'test-project'],
                        'tools' => ['rector' => ['enabled' => true]],
                    ],
                ],
            ],
            [
                'source' => 'override',
                'precedence' => 1,
                'data' => [
                    'quality-tools' => [
                        'tools' => ['phpstan' => ['level' => 6]],
                    ],
                ],
            ],
        ];

        $result = $this->merger->mergeConfigurations($configurations);

        $expected = [
            'quality-tools' => [
                'project' => ['name' => 'test-project'],
                'tools' => [
                    'rector' => ['enabled' => true],
                    'phpstan' => ['level' => 6],
                ],
            ],
        ];

        $this->assertEquals($expected, $result['data']);
        $this->assertEmpty($result['conflicts']);
    }

    public function testMergeTwoConfigurationsWithConflicts(): void
    {
        $configurations = [
            [
                'source' => 'base',
                'precedence' => 2,
                'data' => [
                    'quality-tools' => [
                        'project' => ['name' => 'base-project'],
                        'tools' => ['rector' => ['enabled' => false]],
                    ],
                ],
            ],
            [
                'source' => 'override',
                'precedence' => 1,
                'data' => [
                    'quality-tools' => [
                        'project' => ['name' => 'override-project'],
                        'tools' => ['rector' => ['enabled' => true]],
                    ],
                ],
            ],
        ];

        $result = $this->merger->mergeConfigurations($configurations);

        $expected = [
            'quality-tools' => [
                'project' => ['name' => 'override-project'],
                'tools' => ['rector' => ['enabled' => true]],
            ],
        ];

        $this->assertEquals($expected, $result['data']);
        $this->assertNotEmpty($result['conflicts']);

        // Check conflicts were recorded
        $conflicts = $result['conflicts'];
        $this->assertCount(2, $conflicts);

        $projectNameConflict = $conflicts[0];
        $this->assertEquals('quality-tools.project.name', $projectNameConflict['key_path']);
        $this->assertEquals('base-project', $projectNameConflict['existing_value']);
        $this->assertEquals('override-project', $projectNameConflict['new_value']);
        $this->assertEquals('base', $projectNameConflict['existing_source']);
        $this->assertEquals('override', $projectNameConflict['new_source']);
    }

    public function testMergeArraysWithUniqueStrategy(): void
    {
        $configurations = [
            [
                'source' => 'base',
                'precedence' => 2,
                'data' => [
                    'quality-tools' => [
                        'paths' => [
                            'scan' => ['packages/', 'src/'],
                        ],
                    ],
                ],
            ],
            [
                'source' => 'override',
                'precedence' => 1,
                'data' => [
                    'quality-tools' => [
                        'paths' => [
                            'scan' => ['lib/', 'packages/'],
                        ],
                    ],
                ],
            ],
        ];

        $result = $this->merger->mergeConfigurations($configurations);

        // The merge should properly combine the paths
        $this->assertArrayHasKey('data', $result);
        $this->assertArrayHasKey('quality-tools', $result['data']);
        $this->assertArrayHasKey('paths', $result['data']['quality-tools']);
        $this->assertArrayHasKey('scan', $result['data']['quality-tools']['paths']);

        $expectedScanPaths = ['packages/', 'src/', 'lib/'];
        $actualScanPaths = $result['data']['quality-tools']['paths']['scan'] ?? [];

        // Order might differ, so sort both arrays for comparison
        sort($expectedScanPaths);
        sort($actualScanPaths);

        $this->assertEquals($expectedScanPaths, $actualScanPaths);
    }

    public function testMergePathArraysRemovesDuplicates(): void
    {
        $configurations = [
            [
                'source' => 'base',
                'precedence' => 2,
                'data' => [
                    'quality-tools' => [
                        'paths' => [
                            'exclude' => ['vendor/', 'var/'],
                        ],
                    ],
                ],
            ],
            [
                'source' => 'override',
                'precedence' => 1,
                'data' => [
                    'quality-tools' => [
                        'paths' => [
                            'exclude' => ['vendor/', 'public/'],
                        ],
                    ],
                ],
            ],
        ];

        $result = $this->merger->mergeConfigurations($configurations);

        $expectedExcludePaths = ['public/', 'var/', 'vendor/'];
        $actualExcludePaths = $result['data']['quality-tools']['paths']['exclude'] ?? [];

        sort($actualExcludePaths);

        $this->assertEquals($expectedExcludePaths, $actualExcludePaths);
        $this->assertCount(3, $actualExcludePaths); // No duplicates
    }

    public function testMergeSummaryInformation(): void
    {
        $configurations = [
            [
                'source' => 'base',
                'file_path' => '/test/base.yaml',
                'file_type' => 'yaml',
                'tool' => null,
                'precedence' => 2,
                'data' => ['test' => 'value1'],
            ],
            [
                'source' => 'override',
                'file_path' => '/test/override.yaml',
                'file_type' => 'yaml',
                'tool' => null,
                'precedence' => 1,
                'data' => ['test' => 'value2'],
            ],
        ];

        $result = $this->merger->mergeConfigurations($configurations);

        $summary = $result['merge_summary'];

        $this->assertEquals(2, $summary['total_configurations']);
        $this->assertArrayHasKey('configurations_by_source', $summary);
        $this->assertArrayHasKey('base', $summary['configurations_by_source']);
        $this->assertArrayHasKey('override', $summary['configurations_by_source']);

        $this->assertEquals('/test/base.yaml', $summary['configurations_by_source']['base']['file_path']);
        $this->assertEquals('/test/override.yaml', $summary['configurations_by_source']['override']['file_path']);
    }

    #[DataProvider('mergeStrategyProvider')]
    public function testMergeStrategies(array $base, array $override, array $expected): void
    {
        $configurations = [
            ['source' => 'base', 'precedence' => 2, 'data' => $base],
            ['source' => 'override', 'precedence' => 1, 'data' => $override],
        ];

        $result = $this->merger->mergeConfigurations($configurations);

        $this->assertEquals($expected, $result['data']);
    }

    public static function mergeStrategyProvider(): array
    {
        return [
            'scalar override' => [
                ['key' => 'value1'],
                ['key' => 'value2'],
                ['key' => 'value2'],
            ],
            'array replace (for simple lists)' => [
                ['list' => ['a', 'b']],
                ['list' => ['c', 'd']],
                ['list' => ['c', 'd']],
            ],
            'object deep merge' => [
                ['object' => ['key1' => 'value1', 'key2' => 'value2']],
                ['object' => ['key2' => 'new_value2', 'key3' => 'value3']],
                ['object' => ['key1' => 'value1', 'key2' => 'new_value2', 'key3' => 'value3']],
            ],
            'mixed types override' => [
                ['key' => ['array']],
                ['key' => 'scalar'],
                ['key' => 'scalar'],
            ],
        ];
    }

    public function testMergeTwoUtilityMethod(): void
    {
        $base = ['key1' => 'value1', 'shared' => 'base_value'];
        $override = ['key2' => 'value2', 'shared' => 'override_value'];

        $result = ConfigurationMerger::mergeTwo($base, $override);

        $expected = [
            'key1' => 'value1',
            'key2' => 'value2',
            'shared' => 'override_value',
        ];

        $this->assertEquals($expected, $result);
    }

    public function testGetConflictsAndHasConflicts(): void
    {
        $configurations = [
            [
                'source' => 'base',
                'precedence' => 2,
                'data' => ['key' => 'value1'],
            ],
            [
                'source' => 'override',
                'precedence' => 1,
                'data' => ['key' => 'value2'],
            ],
        ];

        $this->merger->mergeConfigurations($configurations);

        $this->assertTrue($this->merger->hasConflicts());
        $this->assertNotEmpty($this->merger->getConflicts());

        $conflicts = $this->merger->getConflicts();
        $this->assertCount(1, $conflicts);

        $conflict = $conflicts[0];
        $this->assertEquals('key', $conflict['key_path']);
        $this->assertEquals('value1', $conflict['existing_value']);
        $this->assertEquals('value2', $conflict['new_value']);
    }

    public function testGetConflictsForSpecificKey(): void
    {
        $configurations = [
            [
                'source' => 'base',
                'precedence' => 2,
                'data' => ['key1' => 'value1', 'key2' => 'value2'],
            ],
            [
                'source' => 'override',
                'precedence' => 1,
                'data' => ['key1' => 'new_value1', 'key2' => 'new_value2'],
            ],
        ];

        $this->merger->mergeConfigurations($configurations);

        $key1Conflicts = $this->merger->getConflictsForKey('key1');
        $this->assertCount(1, $key1Conflicts);
        $this->assertEquals('key1', $key1Conflicts[0]['key_path']);

        $key2Conflicts = $this->merger->getConflictsForKey('key2');
        $this->assertCount(1, $key2Conflicts);
        $this->assertEquals('key2', $key2Conflicts[0]['key_path']);

        $nonExistentKeyConflicts = $this->merger->getConflictsForKey('key3');
        $this->assertEmpty($nonExistentKeyConflicts);
    }

    public function testCreateDebugMerger(): void
    {
        $debugMerger = ConfigurationMerger::createDebugMerger($this->hierarchy);

        $this->assertFalse($debugMerger->hasConflicts());
        $this->assertEmpty($debugMerger->getConflicts());
    }

    public function testComplexNestedMerging(): void
    {
        $configurations = [
            [
                'source' => 'base',
                'precedence' => 3,
                'data' => [
                    'quality-tools' => [
                        'project' => ['name' => 'test'],
                        'tools' => [
                            'rector' => ['enabled' => true, 'level' => 'typo3-12'],
                            'phpstan' => ['enabled' => true, 'level' => 5],
                        ],
                        'paths' => [
                            'scan' => ['packages/', 'src/'],
                            'exclude' => ['vendor/', 'var/'],
                        ],
                    ],
                ],
            ],
            [
                'source' => 'project',
                'precedence' => 2,
                'data' => [
                    'quality-tools' => [
                        'tools' => [
                            'rector' => ['level' => 'typo3-13'],
                            'fractor' => ['enabled' => true],
                        ],
                        'paths' => [
                            'scan' => ['lib/'],
                            'exclude' => ['public/'],
                        ],
                    ],
                ],
            ],
            [
                'source' => 'command_line',
                'precedence' => 1,
                'data' => [
                    'quality-tools' => [
                        'tools' => [
                            'phpstan' => ['level' => 6],
                        ],
                    ],
                ],
            ],
        ];

        $result = $this->merger->mergeConfigurations($configurations);

        $expected = [
            'quality-tools' => [
                'project' => ['name' => 'test'],
                'tools' => [
                    'rector' => ['enabled' => true, 'level' => 'typo3-13'],
                    'phpstan' => ['enabled' => true, 'level' => 6],
                    'fractor' => ['enabled' => true],
                ],
                'paths' => [
                    'scan' => ['lib/', 'packages/', 'src/'],
                    'exclude' => ['public/', 'var/', 'vendor/'],
                ],
            ],
        ];

        // Sort arrays for consistent comparison
        sort($expected['quality-tools']['paths']['scan']);
        sort($expected['quality-tools']['paths']['exclude']);

        $resultScanPaths = $result['data']['quality-tools']['paths']['scan'] ?? [];
        $resultExcludePaths = $result['data']['quality-tools']['paths']['exclude'] ?? [];

        sort($resultScanPaths);
        sort($resultExcludePaths);

        $result['data']['quality-tools']['paths']['scan'] = $resultScanPaths;
        $result['data']['quality-tools']['paths']['exclude'] = $resultExcludePaths;

        $this->assertEquals($expected, $result['data']);

        // Verify conflicts were recorded for overridden values
        $this->assertTrue($this->merger->hasConflicts());
        $conflicts = $this->merger->getConflicts();

        // Should have conflicts for rector.level and phpstan.level
        $this->assertGreaterThan(0, \count($conflicts));
    }
}

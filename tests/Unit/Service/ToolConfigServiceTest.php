<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Tests\Unit\Service;

use Cpsit\QualityTools\Service\ToolConfigService;
use PHPUnit\Framework\TestCase;

/**
 * Unit test for ToolConfigService.
 *
 * Tests the extracted business logic for tool configuration (Step 4.1).
 */
final class ToolConfigServiceTest extends TestCase
{
    private ToolConfigService $service;

    protected function setUp(): void
    {
        $this->service = new ToolConfigService();
    }

    public function testGetToolConfigWithExistingTool(): void
    {
        $data = [
            'quality-tools' => [
                'tools' => [
                    'rector' => [
                        'enabled' => true,
                        'level' => 'typo3-13',
                    ],
                ],
            ],
        ];

        $result = $this->service->getToolConfig($data, 'rector');

        $expected = [
            'enabled' => true,
            'level' => 'typo3-13',
        ];

        self::assertSame($expected, $result);
    }

    public function testGetToolConfigWithNonExistingTool(): void
    {
        $data = [];

        $result = $this->service->getToolConfig($data, 'rector');

        self::assertSame([], $result);
    }

    public function testIsToolEnabledWithEnabledTool(): void
    {
        $data = [
            'quality-tools' => [
                'tools' => [
                    'rector' => [
                        'enabled' => true,
                    ],
                ],
            ],
        ];

        $result = $this->service->isToolEnabled($data, 'rector');

        self::assertTrue($result);
    }

    public function testIsToolEnabledWithDisabledTool(): void
    {
        $data = [
            'quality-tools' => [
                'tools' => [
                    'rector' => [
                        'enabled' => false,
                    ],
                ],
            ],
        ];

        $result = $this->service->isToolEnabled($data, 'rector');

        self::assertFalse($result);
    }

    public function testIsToolEnabledWithDefaultValue(): void
    {
        $data = [];

        $result = $this->service->isToolEnabled($data, 'rector');

        self::assertTrue($result); // Default is enabled
    }

    public function testGetToolPathsWithConfiguredPaths(): void
    {
        $data = [
            'quality-tools' => [
                'tools' => [
                    'rector' => [
                        'paths' => ['src/', 'packages/'],
                    ],
                ],
            ],
        ];

        $result = $this->service->getToolPaths($data, 'rector');

        self::assertSame(['src/', 'packages/'], $result);
    }

    public function testGetToolPathsWithDefaultValue(): void
    {
        $data = [];

        $result = $this->service->getToolPaths($data, 'rector');

        self::assertSame([], $result);
    }

    public function testGetRectorConfigWithDefaults(): void
    {
        $data = [];

        $result = $this->service->getRectorConfig($data, '8.4');

        $expected = [
            'enabled' => true,
            'level' => 'typo3-13',
            'php_version' => '8.4',
        ];

        self::assertSame($expected, $result);
    }

    public function testGetRectorConfigWithCustomValues(): void
    {
        $data = [
            'quality-tools' => [
                'tools' => [
                    'rector' => [
                        'enabled' => false,
                        'level' => 'typo3-12',
                    ],
                ],
            ],
        ];

        $result = $this->service->getRectorConfig($data, '8.3');

        $expected = [
            'enabled' => false,
            'level' => 'typo3-12',
            'php_version' => '8.3',
        ];

        self::assertSame($expected, $result);
    }

    public function testGetPhpStanConfigWithDefaults(): void
    {
        $data = [];

        $result = $this->service->getPhpStanConfig($data);

        $expected = [
            'enabled' => true,
            'level' => 6,
            'memory_limit' => '1G',
        ];

        self::assertSame($expected, $result);
    }

    public function testGetPhpStanConfigWithCustomValues(): void
    {
        $data = [
            'quality-tools' => [
                'tools' => [
                    'phpstan' => [
                        'level' => 8,
                        'memory_limit' => '2G',
                    ],
                ],
            ],
        ];

        $result = $this->service->getPhpStanConfig($data);

        $expected = [
            'enabled' => true,
            'level' => 8,
            'memory_limit' => '2G',
        ];

        self::assertSame($expected, $result);
    }

    public function testGetEnabledTools(): void
    {
        $data = [
            'quality-tools' => [
                'tools' => [
                    'rector' => [
                        'enabled' => true,
                    ],
                    'phpstan' => [
                        'enabled' => false,
                    ],
                    'php-cs-fixer' => [
                        'enabled' => true,
                    ],
                    'fractor' => [
                        // Default enabled = true
                    ],
                ],
            ],
        ];

        $result = $this->service->getEnabledTools($data);

        self::assertSame(['rector', 'php-cs-fixer', 'fractor'], $result);
    }

    public function testGetToolConfigWithDefaults(): void
    {
        $data = [
            'quality-tools' => [
                'tools' => [
                    'rector' => [
                        'level' => 'typo3-13',
                    ],
                ],
            ],
        ];

        $defaults = [
            'enabled' => true,
            'php_version' => '8.3',
        ];

        $result = $this->service->getToolConfigWithDefaults($data, 'rector', $defaults);

        $expected = [
            'enabled' => true,
            'php_version' => '8.3',
            'level' => 'typo3-13',
        ];

        self::assertSame($expected, $result);
    }

    public function testGetToolsConfig(): void
    {
        $data = [
            'quality-tools' => [
                'tools' => [
                    'rector' => [
                        'enabled' => true,
                        'level' => 'typo3-13',
                    ],
                    'phpstan' => [
                        'level' => 6,
                    ],
                ],
            ],
        ];

        $result = $this->service->getToolsConfig($data);

        $expected = [
            'rector' => [
                'enabled' => true,
                'level' => 'typo3-13',
            ],
            'phpstan' => [
                'level' => 6,
            ],
        ];

        self::assertSame($expected, $result);
    }
}

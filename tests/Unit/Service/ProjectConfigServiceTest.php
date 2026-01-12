<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Tests\Unit\Service;

use Cpsit\QualityTools\Service\ProjectConfigService;
use PHPUnit\Framework\TestCase;

/**
 * Unit test for ProjectConfigService.
 *
 * Tests the extracted business logic for project configuration (Step 4.1).
 */
final class ProjectConfigServiceTest extends TestCase
{
    private ProjectConfigService $service;

    protected function setUp(): void
    {
        $this->service = new ProjectConfigService();
    }

    public function testGetPhpVersionWithConfiguredValue(): void
    {
        $data = [
            'quality-tools' => [
                'project' => [
                    'php_version' => '8.4',
                ],
            ],
        ];

        $result = $this->service->getPhpVersion($data);

        self::assertSame('8.4', $result);
    }

    public function testGetPhpVersionWithDefaultValue(): void
    {
        $data = [];

        $result = $this->service->getPhpVersion($data);

        self::assertSame('8.3', $result);
    }

    public function testGetTypo3VersionWithConfiguredValue(): void
    {
        $data = [
            'quality-tools' => [
                'project' => [
                    'typo3_version' => '13.4',
                ],
            ],
        ];

        $result = $this->service->getTypo3Version($data);

        self::assertSame('13.4', $result);
    }

    public function testGetTypo3VersionWithDefaultValue(): void
    {
        $data = [];

        $result = $this->service->getTypo3Version($data);

        self::assertSame('13.4', $result);
    }

    public function testGetProjectNameWithConfiguredValue(): void
    {
        $data = [
            'quality-tools' => [
                'project' => [
                    'name' => 'test-project',
                ],
            ],
        ];

        $result = $this->service->getProjectName($data);

        self::assertSame('test-project', $result);
    }

    public function testGetProjectNameWithDefaultValue(): void
    {
        $data = [];

        $result = $this->service->getProjectName($data);

        self::assertNull($result);
    }

    public function testGetVerbosityWithConfiguredValue(): void
    {
        $data = [
            'quality-tools' => [
                'output' => [
                    'verbosity' => 'debug',
                ],
            ],
        ];

        $result = $this->service->getVerbosity($data);

        self::assertSame('debug', $result);
    }

    public function testGetVerbosityWithDefaultValue(): void
    {
        $data = [];

        $result = $this->service->getVerbosity($data);

        self::assertSame('normal', $result);
    }

    public function testIsColorsEnabledWithConfiguredValue(): void
    {
        $data = [
            'quality-tools' => [
                'output' => [
                    'colors' => false,
                ],
            ],
        ];

        $result = $this->service->isColorsEnabled($data);

        self::assertFalse($result);
    }

    public function testIsColorsEnabledWithDefaultValue(): void
    {
        $data = [];

        $result = $this->service->isColorsEnabled($data);

        self::assertTrue($result);
    }

    public function testIsParallelEnabledWithConfiguredValue(): void
    {
        $data = [
            'quality-tools' => [
                'performance' => [
                    'parallel' => false,
                ],
            ],
        ];

        $result = $this->service->isParallelEnabled($data);

        self::assertFalse($result);
    }

    public function testGetMaxProcessesWithConfiguredValue(): void
    {
        $data = [
            'quality-tools' => [
                'performance' => [
                    'max_processes' => 8,
                ],
            ],
        ];

        $result = $this->service->getMaxProcesses($data);

        self::assertSame(8, $result);
    }

    public function testGetMaxProcessesWithDefaultValue(): void
    {
        $data = [];

        $result = $this->service->getMaxProcesses($data);

        self::assertSame(4, $result);
    }

    public function testGetProjectConfig(): void
    {
        $data = [
            'quality-tools' => [
                'project' => [
                    'name' => 'test-project',
                    'php_version' => '8.4',
                    'typo3_version' => '13.4',
                ],
            ],
        ];

        $result = $this->service->getProjectConfig($data);

        $expected = [
            'name' => 'test-project',
            'php_version' => '8.4',
            'typo3_version' => '13.4',
        ];

        self::assertSame($expected, $result);
    }

    public function testGetOutputConfig(): void
    {
        $data = [
            'quality-tools' => [
                'output' => [
                    'verbosity' => 'debug',
                    'colors' => false,
                ],
            ],
        ];

        $result = $this->service->getOutputConfig($data);

        $expected = [
            'verbosity' => 'debug',
            'colors' => false,
        ];

        self::assertSame($expected, $result);
    }

    public function testGetPerformanceConfig(): void
    {
        $data = [
            'quality-tools' => [
                'performance' => [
                    'parallel' => true,
                    'max_processes' => 8,
                ],
            ],
        ];

        $result = $this->service->getPerformanceConfig($data);

        $expected = [
            'parallel' => true,
            'max_processes' => 8,
        ];

        self::assertSame($expected, $result);
    }
}

<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Tests\Unit\Service;

use Cpsit\QualityTools\Service\FilesystemService;
use Cpsit\QualityTools\Service\PathResolutionService;
use Cpsit\QualityTools\Service\SecurityService;
use Cpsit\QualityTools\Utility\VendorDirectoryDetector;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Unit test for PathResolutionService.
 *
 * Tests the extracted business logic for path resolution (Step 4.1).
 */
final class PathResolutionServiceTest extends TestCase
{
    private PathResolutionService $pathResolutionService;

    protected function setUp(): void
    {
        $securityService = new SecurityService();
        $filesystem = new Filesystem();
        $filesystemService = new FilesystemService($filesystem, $securityService);
        $this->pathResolutionService = new PathResolutionService($filesystemService, new VendorDirectoryDetector());
    }

    public function testGetScanPathsWithConfiguredValue(): void
    {
        $data = [
            'quality-tools' => [
                'paths' => [
                    'scan' => ['src/', 'lib/'],
                ],
            ],
        ];

        $result = $this->pathResolutionService->getScanPaths($data);

        self::assertSame(['src/', 'lib/'], $result);
    }

    public function testGetScanPathsWithDefaultValue(): void
    {
        $data = [];

        $result = $this->pathResolutionService->getScanPaths($data);

        self::assertSame(['packages/', 'config/system/'], $result);
    }

    public function testGetExcludePathsWithConfiguredValue(): void
    {
        $data = [
            'quality-tools' => [
                'paths' => [
                    'exclude' => ['tmp/', 'cache/'],
                ],
            ],
        ];

        $result = $this->pathResolutionService->getExcludePaths($data);

        self::assertSame(['tmp/', 'cache/'], $result);
    }

    public function testGetExcludePathsWithDefaultValue(): void
    {
        $data = [];

        $result = $this->pathResolutionService->getExcludePaths($data);

        $expected = ['var/', 'vendor/', 'public/', '_assets/', 'fileadmin/', 'typo3/', 'Tests/', 'tests/', 'typo3conf/'];
        self::assertSame($expected, $result);
    }

    public function testGetToolPathsWithConfiguredPaths(): void
    {
        $data = [
            'quality-tools' => [
                'tools' => [
                    'rector' => [
                        'paths' => ['scan' => ['src/', 'packages/']],
                    ],
                ],
            ],
        ];

        $result = $this->pathResolutionService->getToolPaths($data, 'rector');

        self::assertSame(['src/', 'packages/'], $result);
    }

    public function testGetToolPathsWithDefaultValue(): void
    {
        $data = [];

        $result = $this->pathResolutionService->getToolPaths($data, 'rector');

        self::assertSame([], $result);
    }

    public function testGetToolPathsWithValidNestedStructure(): void
    {
        $data = [
            'quality-tools' => [
                'tools' => [
                    'rector' => [
                        'paths' => ['scan' => ['custom/']],
                    ],
                ],
            ],
        ];

        $result = $this->pathResolutionService->getToolPaths($data, 'rector');

        self::assertSame(['custom/'], $result);
    }

    public function testGetToolPathsWithEmptyScanKey(): void
    {
        $data = [
            'quality-tools' => [
                'tools' => [
                    'rector' => [
                        'paths' => ['scan' => []],
                    ],
                ],
            ],
        ];

        $result = $this->pathResolutionService->getToolPaths($data, 'rector');

        self::assertSame([], $result);
    }

    public function testGetToolPathsWithMissingPathsKey(): void
    {
        $data = [
            'quality-tools' => [
                'tools' => [
                    'rector' => ['enabled' => true],
                ],
            ],
        ];

        $result = $this->pathResolutionService->getToolPaths($data, 'rector');

        self::assertSame([], $result);
    }

    public function testGetResolvedPathsForToolWithConfiguredPaths(): void
    {
        $data = [
            'quality-tools' => [
                'tools' => [
                    'rector' => [
                        'paths' => ['scan' => ['custom/', 'special/']],
                    ],
                ],
            ],
        ];

        $result = $this->pathResolutionService->getResolvedPathsForTool($data, 'rector', '/project/root');

        // Should return configured paths directly without scanning
        self::assertSame(['custom/', 'special/'], $result);
    }

    public function testGetResolvedPathsForToolWithDefaultScanning(): void
    {
        $data = [
            'quality-tools' => [
                'paths' => [
                    'scan' => ['src/', 'app/'],
                ],
            ],
        ];

        $projectRoot = __DIR__ . '/../../..';
        $result = $this->pathResolutionService->getResolvedPathsForTool($data, 'rector', $projectRoot);

        // Should scan and resolve paths
        self::assertIsArray($result);
        // The exact result depends on what PathScanner finds in the actual project structure
    }

    public function testGetVendorPathWithCurrentProject(): void
    {
        // Use the actual project directory for testing
        $result = $this->pathResolutionService->getVendorPath(__DIR__ . '/../../..');

        // Should find vendor directory in the actual project
        self::assertNotNull($result);
        self::assertStringEndsWith('vendor', $result);
    }

    public function testGetVendorPathWithNonExistentDirectory(): void
    {
        // Use a non-existent directory
        $result = $this->pathResolutionService->getVendorPath('/non/existent/directory');

        self::assertNull($result);
    }

    public function testGetVendorBinPathWithCurrentProject(): void
    {
        // Use the actual project directory for testing
        $result = $this->pathResolutionService->getVendorBinPath(__DIR__ . '/../../..');

        // Should find vendor/bin directory in the actual project
        self::assertNotNull($result);
        self::assertStringEndsWith('vendor/bin', $result);
    }

    public function testGetVendorBinPathWithNonExistentDirectory(): void
    {
        // Use a non-existent directory
        $result = $this->pathResolutionService->getVendorBinPath('/non/existent/directory');

        self::assertNull($result);
    }

    public function testGetPathsConfig(): void
    {
        $data = [
            'quality-tools' => [
                'paths' => [
                    'scan' => ['src/'],
                    'exclude' => ['tmp/'],
                ],
            ],
        ];

        $result = $this->pathResolutionService->getPathsConfig($data);

        $expected = [
            'scan' => ['src/'],
            'exclude' => ['tmp/'],
        ];

        self::assertSame($expected, $result);
    }

    public function testGetAdditionalPaths(): void
    {
        $data = [
            'quality-tools' => [
                'paths' => [
                    'additional' => ['custom/', 'extra/'],
                ],
            ],
        ];

        $result = $this->pathResolutionService->getAdditionalPaths($data);

        self::assertSame(['custom/', 'extra/'], $result);
    }

    public function testGetExcludePatterns(): void
    {
        $data = [
            'quality-tools' => [
                'paths' => [
                    'exclude_patterns' => ['*.backup', 'temp/*'],
                ],
            ],
        ];

        $result = $this->pathResolutionService->getExcludePatterns($data);

        self::assertSame(['*.backup', 'temp/*'], $result);
    }

    public function testGetToolPathOverrides(): void
    {
        $data = [
            'quality-tools' => [
                'paths' => [
                    'tool_overrides' => [
                        'rector' => [
                            'additional' => ['custom/'],
                        ],
                    ],
                ],
            ],
        ];

        $result = $this->pathResolutionService->getToolPathOverrides($data, 'rector');

        $expected = [
            'additional' => ['custom/'],
        ];

        self::assertSame($expected, $result);
    }

    public function testClearVendorPathCache(): void
    {
        $projectRoot = __DIR__ . '/../../..';

        // First call
        $result1 = $this->pathResolutionService->getVendorPath($projectRoot);

        // Clear cache
        $this->pathResolutionService->clearVendorPathCache();

        // Second call should work - can't easily test caching without mocking,
        // but we can test that the method exists and doesn't break anything
        $result2 = $this->pathResolutionService->getVendorPath($projectRoot);

        self::assertSame($result1, $result2);
    }

    public function testClearAllCaches(): void
    {
        $projectRoot = __DIR__ . '/../../..';

        // Call some methods to potentially populate cache
        $this->pathResolutionService->getVendorPath($projectRoot);

        // Clear all caches - should not throw any errors
        $this->pathResolutionService->clearAllCaches();

        // Methods should still work after clearing cache
        $result = $this->pathResolutionService->getVendorPath($projectRoot);
        self::assertNotNull($result);
    }
}

<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Tests\Unit\Configuration;

use Cpsit\QualityTools\Configuration\Configuration;
use Cpsit\QualityTools\Configuration\ConfigurationWrapper;
use Cpsit\QualityTools\Configuration\SimpleConfiguration;
use Cpsit\QualityTools\Service\PathResolutionService;
use Cpsit\QualityTools\Service\ProjectConfigService;
use Cpsit\QualityTools\Service\ToolConfigService;
use Cpsit\QualityTools\Tests\Unit\TestHelper;
use PHPUnit\Framework\TestCase;

/**
 * Tests path resolution consistency between wrapper and unified approaches.
 * 
 * Problem: Different path algorithms may discover different files.
 */
final class PathResolutionConsistencyTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = TestHelper::createTempDirectory('path_resolution_test_');
        TestHelper::createComposerJson($this->tempDir, TestHelper::getComposerContent('typo3-core'));
        TestHelper::createVendorStructure($this->tempDir);
        $this->createProjectStructure();
    }

    protected function tearDown(): void
    {
        TestHelper::removeDirectory($this->tempDir);
    }

    private function createProjectStructure(): void
    {
        // Create typical TYPO3 project structure
        $directories = [
            'config/system',
            'packages/my_extension/Classes',
            'packages/my_extension/Configuration/TypoScript',
            'packages/other_extension/Classes',
            'custom-extensions/legacy/Classes',
            'src/Core',
            'var/cache',
            'vendor/cpsit/quality-tools/src',
            'vendor/fr/typo3-coding-standards/src',
        ];

        foreach ($directories as $dir) {
            $fullPath = $this->tempDir . '/' . $dir;
            if (!is_dir($fullPath)) {
                mkdir($fullPath, 0o777, true);
            }

            // Create dummy files
            file_put_contents($fullPath . '/dummy.php', "<?php\nclass Dummy {}\n");
        }

        // Create rector executable
        $rectorScript = "#!/bin/bash\necho 'Rector dry-run completed'\nexit 0\n";
        file_put_contents($this->tempDir . '/vendor/bin/rector', $rectorScript);
        chmod($this->tempDir . '/vendor/bin/rector', 0o755);

        // Create phpstan executable  
        $phpstanScript = "#!/bin/bash\necho 'PHPStan analysis completed'\nexit 0\n";
        file_put_contents($this->tempDir . '/vendor/bin/phpstan', $phpstanScript);
        chmod($this->tempDir . '/vendor/bin/phpstan', 0o755);
    }

    public function testToolPathResolutionConsistency(): void
    {
        $configData = [
            'quality-tools' => [
                'project' => ['name' => 'path-test'],
                'paths' => [
                    'scan' => ['config/system', 'packages'],
                    'exclude' => ['var/', 'vendor/'],
                    'additional' => ['src/**/*.php', 'custom-extensions/*/Classes'],
                ],
                'tools' => [
                    'rector' => ['enabled' => true],
                    'phpstan' => ['enabled' => true],
                    'php-cs-fixer' => ['enabled' => true],
                ],
            ],
        ];

        // Create wrapper configuration
        $simpleConfig = new SimpleConfiguration($configData);
        $simpleConfig->setProjectRoot($this->tempDir);
        $wrapper = new ConfigurationWrapper($simpleConfig, 'simple');

        // Create unified configuration
        $unified = Configuration::createSimple(
            data: $configData,
            projectConfigService: new ProjectConfigService(),
            toolConfigService: new ToolConfigService(),
            pathResolutionService: new PathResolutionService()
        );
        $unified->setProjectRoot($this->tempDir);

        $tools = ['rector', 'phpstan', 'php-cs-fixer'];

        foreach ($tools as $tool) {
            // Test basic path resolution
            $wrapperScanPaths = $wrapper->getScanPaths();
            $unifiedScanPaths = $unified->getScanPaths();

            $this->assertEquals(
                $wrapperScanPaths,
                $unifiedScanPaths,
                "Scan paths should be identical for $tool"
            );

            $wrapperExcludePaths = $wrapper->getExcludePaths();
            $unifiedExcludePaths = $unified->getExcludePaths();

            $this->assertEquals(
                $wrapperExcludePaths,
                $unifiedExcludePaths,
                "Exclude paths should be identical for $tool"
            );

            // Test tool-specific path resolution if methods exist
            if (method_exists($wrapper, 'getResolvedPathsForTool')) {
                $wrapperResolvedPaths = $wrapper->getResolvedPathsForTool($tool);
                $unifiedResolvedPaths = $unified->getResolvedPathsForTool($tool);

                $this->assertEquals(
                    $wrapperResolvedPaths,
                    $unifiedResolvedPaths,
                    "Resolved paths should be identical for $tool"
                );
            }
        }
    }

    public function testVendorPathDiscoveryConsistency(): void
    {
        $configData = [
            'quality-tools' => [
                'project' => ['name' => 'vendor-test'],
                'paths' => [
                    'additional' => [
                        'vendor/cpsit/*',
                        'vendor/fr/*/Classes',
                    ],
                ],
            ],
        ];

        // Create configurations
        $simpleConfig = new SimpleConfiguration($configData);
        $simpleConfig->setProjectRoot($this->tempDir);
        $wrapper = new ConfigurationWrapper($simpleConfig, 'simple');

        $unified = Configuration::createSimple(
            data: $configData,
            pathResolutionService: new PathResolutionService()
        );
        $unified->setProjectRoot($this->tempDir);

        // Test vendor path discovery
        $wrapperVendorPath = $wrapper->getVendorPath();
        $unifiedVendorPath = $unified->getVendorPath();

        if ($wrapperVendorPath !== null) {
            $this->assertEquals(
                $wrapperVendorPath,
                $unifiedVendorPath,
                'Vendor path discovery should be consistent'
            );
        } else {
            // Both should return null if vendor path not discoverable
            $this->assertNull($unifiedVendorPath);
        }
    }

    public function testGlobPatternResolutionConsistency(): void
    {
        $configWithGlobs = [
            'quality-tools' => [
                'project' => ['name' => 'glob-test'],
                'paths' => [
                    'scan' => ['packages/**/*.php'],
                    'exclude' => ['**/Tests/**', 'var/**'],
                    'additional' => ['src/**/*.php', 'custom-extensions/*/Classes/**/*.php'],
                ],
            ],
        ];

        // Create configurations
        $simpleConfig = new SimpleConfiguration($configWithGlobs);
        $simpleConfig->setProjectRoot($this->tempDir);
        $wrapper = new ConfigurationWrapper($simpleConfig, 'simple');

        $unified = Configuration::createSimple(
            data: $configWithGlobs,
            pathResolutionService: new PathResolutionService()
        );
        $unified->setProjectRoot($this->tempDir);

        // Path resolution should be identical
        $this->assertEquals(
            $wrapper->getScanPaths(),
            $unified->getScanPaths(),
            'Glob pattern resolution should be consistent for scan paths'
        );

        $this->assertEquals(
            $wrapper->getExcludePaths(),
            $unified->getExcludePaths(),
            'Glob pattern resolution should be consistent for exclude paths'
        );
    }

    public function testPathNormalizationConsistency(): void
    {
        $configWithMixedPaths = [
            'quality-tools' => [
                'project' => ['name' => 'normalization-test'],
                'paths' => [
                    'scan' => [
                        './packages',           // Relative with ./
                        'config/system/',       // Trailing slash
                        'src',                  // No trailing slash
                    ],
                    'exclude' => [
                        './var/',               // Mixed formats
                        'vendor',
                        'tmp/',
                    ],
                ],
            ],
        ];

        // Create configurations
        $simpleConfig = new SimpleConfiguration($configWithMixedPaths);
        $simpleConfig->setProjectRoot($this->tempDir);
        $wrapper = new ConfigurationWrapper($simpleConfig, 'simple');

        $unified = Configuration::createSimple(
            data: $configWithMixedPaths,
            pathResolutionService: new PathResolutionService()
        );
        $unified->setProjectRoot($this->tempDir);

        // Path normalization should be consistent
        $wrapperPaths = $wrapper->getScanPaths();
        $unifiedPaths = $unified->getScanPaths();

        $this->assertEquals(
            $wrapperPaths,
            $unifiedPaths,
            'Path normalization should be identical'
        );

        // All paths should be normalized (no ./ prefixes, consistent trailing slashes)
        foreach ($wrapperPaths as $path) {
            $this->assertStringStartsNotWith('./', $path, 'Paths should not start with ./');
        }

        foreach ($unifiedPaths as $path) {
            $this->assertStringStartsNotWith('./', $path, 'Paths should not start with ./');
        }
    }

    public function testAbsolutePathHandling(): void
    {
        $absolutePath = $this->tempDir . '/custom-absolute';
        mkdir($absolutePath, 0o777, true);
        file_put_contents($absolutePath . '/test.php', "<?php\nclass AbsoluteTest {}\n");

        $configWithAbsolutePaths = [
            'quality-tools' => [
                'project' => ['name' => 'absolute-test'],
                'paths' => [
                    'scan' => [
                        'packages',           // Relative
                        $absolutePath,        // Absolute
                    ],
                ],
            ],
        ];

        // Create configurations
        $simpleConfig = new SimpleConfiguration($configWithAbsolutePaths);
        $simpleConfig->setProjectRoot($this->tempDir);
        $wrapper = new ConfigurationWrapper($simpleConfig, 'simple');

        $unified = Configuration::createSimple(
            data: $configWithAbsolutePaths,
            pathResolutionService: new PathResolutionService()
        );
        $unified->setProjectRoot($this->tempDir);

        // Absolute path handling should be consistent
        $this->assertEquals(
            $wrapper->getScanPaths(),
            $unified->getScanPaths(),
            'Absolute path handling should be identical'
        );
    }

    public function testPathResolutionServiceInjection(): void
    {
        $configData = [
            'quality-tools' => [
                'project' => ['name' => 'injection-test'],
                'paths' => [
                    'scan' => ['packages'],
                ],
            ],
        ];

        // Test without PathResolutionService injection
        $unifiedWithoutService = Configuration::createSimple($configData);
        $unifiedWithoutService->setProjectRoot($this->tempDir);

        // Test with PathResolutionService injection
        $pathService = new PathResolutionService();
        $unifiedWithService = Configuration::createSimple(
            data: $configData,
            pathResolutionService: $pathService
        );
        $unifiedWithService->setProjectRoot($this->tempDir);

        // Basic path resolution should work in both cases
        $pathsWithoutService = $unifiedWithoutService->getScanPaths();
        $pathsWithService = $unifiedWithService->getScanPaths();

        // At minimum, basic paths should be available
        $this->assertNotEmpty($pathsWithoutService);
        $this->assertNotEmpty($pathsWithService);

        // Advanced path resolution methods might require service
        $vendorPathWithoutService = $unifiedWithoutService->getVendorPath();
        $vendorPathWithService = $unifiedWithService->getVendorPath();

        // With service should be at least as capable as without service
        if ($vendorPathWithoutService !== null) {
            $this->assertEquals($vendorPathWithoutService, $vendorPathWithService);
        }
    }
}
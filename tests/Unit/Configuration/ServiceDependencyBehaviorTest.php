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
 * Tests service dependency behavior parity between wrapper and unified approaches.
 * 
 * Problem: Different default value handling when services missing/present.
 */
final class ServiceDependencyBehaviorTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = TestHelper::createTempDirectory('service_dependency_test_');
        TestHelper::createComposerJson($this->tempDir, TestHelper::getComposerContent('typo3-core'));
        TestHelper::createVendorStructure($this->tempDir);
    }

    protected function tearDown(): void
    {
        TestHelper::removeDirectory($this->tempDir);
    }

    public function testServiceDependencyBehaviorParity(): void
    {
        $configData = [
            'quality-tools' => [
                'project' => [
                    'name' => 'test-project',
                    'php_version' => '8.3',
                ],
                'tools' => [
                    'rector' => ['enabled' => true],
                    'phpstan' => ['enabled' => true, 'level' => 8],
                ],
            ],
        ];

        // Test 1: Wrapper without explicit services (relies on defaults)
        $simpleConfig = new SimpleConfiguration($configData);
        $simpleConfig->setProjectRoot($this->tempDir);
        $wrapperWithoutServices = new ConfigurationWrapper($simpleConfig, 'simple');

        // Test 2: Unified without services (should provide same defaults)
        $unifiedWithoutServices = Configuration::createSimple(
            projectRoot: $this->tempDir,
            data: $configData
        );

        // Test 3: Unified with explicit services
        $unifiedWithServices = Configuration::createSimple(
            projectRoot: $this->tempDir,
            data: $configData,
            projectConfigService: new ProjectConfigService(),
            toolConfigService: new ToolConfigService(),
            pathResolutionService: new PathResolutionService()
        );

        // All should provide identical basic functionality
        $this->assertEquals('test-project', $wrapperWithoutServices->getProjectName());
        $this->assertEquals('test-project', $unifiedWithoutServices->getProjectName());
        $this->assertEquals('test-project', $unifiedWithServices->getProjectName());

        // Tool configuration access should work identically
        $this->assertTrue($wrapperWithoutServices->isToolEnabled('rector'));
        $this->assertTrue($unifiedWithoutServices->isToolEnabled('rector'));
        $this->assertTrue($unifiedWithServices->isToolEnabled('rector'));

        // Tool config retrieval should provide same defaults
        $wrapperRectorConfig = $wrapperWithoutServices->getToolConfig('rector');
        $unifiedRectorConfig = $unifiedWithoutServices->getToolConfig('rector');
        $unifiedWithServicesRectorConfig = $unifiedWithServices->getToolConfig('rector');

        $this->assertEquals($wrapperRectorConfig['enabled'], $unifiedRectorConfig['enabled']);
        $this->assertEquals($wrapperRectorConfig['enabled'], $unifiedWithServicesRectorConfig['enabled']);
    }

    public function testPathResolutionServiceBehaviorConsistency(): void
    {
        $configData = [
            'quality-tools' => [
                'project' => ['name' => 'path-test'],
                'paths' => [
                    'scan' => ['src/', 'packages/'],
                    'exclude' => ['var/', 'vendor/'],
                ],
            ],
        ];

        // Wrapper approach
        $simpleConfig = new SimpleConfiguration($configData);
        $simpleConfig->setProjectRoot($this->tempDir);
        $wrapper = new ConfigurationWrapper($simpleConfig, 'simple');

        // Unified without PathResolutionService
        $unifiedWithoutService = Configuration::createSimple(
            projectRoot: $this->tempDir,
            data: $configData
        );

        // Unified with PathResolutionService
        $unifiedWithService = Configuration::createSimple(
            projectRoot: $this->tempDir,
            data: $configData,
            pathResolutionService: new PathResolutionService()
        );

        // Path resolution should be consistent
        $wrapperScanPaths = $wrapper->getScanPaths();
        $unifiedScanPaths = $unifiedWithoutService->getScanPaths();
        $unifiedWithServiceScanPaths = $unifiedWithService->getScanPaths();

        $this->assertEquals($wrapperScanPaths, $unifiedScanPaths);
        $this->assertEquals($wrapperScanPaths, $unifiedWithServiceScanPaths);

        // Vendor path resolution behavior
        $wrapperVendorPath = $wrapper->getVendorPath();
        $unifiedVendorPath = $unifiedWithoutService->getVendorPath();
        $unifiedWithServiceVendorPath = $unifiedWithService->getVendorPath();

        // Without service: should be null or fallback behavior
        // With service: should provide actual vendor path
        if ($wrapperVendorPath === null) {
            $this->assertNull($unifiedVendorPath);
        } else {
            // If wrapper provides vendor path, unified should match
            $this->assertEquals($wrapperVendorPath, $unifiedWithServiceVendorPath);
        }
    }

    public function testProjectConfigServiceBehaviorConsistency(): void
    {
        $configData = [
            'quality-tools' => [
                'project' => [
                    'name' => 'project-test',
                    'php_version' => '8.3',
                    'typo3_version' => '13.4',
                ],
            ],
        ];

        // Wrapper approach
        $simpleConfig = new SimpleConfiguration($configData);
        $wrapper = new ConfigurationWrapper($simpleConfig, 'simple');

        // Unified approaches
        $unifiedWithoutService = Configuration::createSimple(
            projectRoot: getcwd(),
            data: $configData
        );
        $unifiedWithService = Configuration::createSimple(
            projectRoot: getcwd(),
            data: $configData,
            projectConfigService: new ProjectConfigService()
        );

        // Project information should be identical
        $this->assertEquals($wrapper->getProjectPhpVersion(), $unifiedWithoutService->getProjectPhpVersion());
        $this->assertEquals($wrapper->getProjectPhpVersion(), $unifiedWithService->getProjectPhpVersion());

        $this->assertEquals($wrapper->getProjectTypo3Version(), $unifiedWithoutService->getProjectTypo3Version());
        $this->assertEquals($wrapper->getProjectTypo3Version(), $unifiedWithService->getProjectTypo3Version());
    }

    public function testToolConfigServiceBehaviorConsistency(): void
    {
        $configData = [
            'quality-tools' => [
                'project' => ['name' => 'tool-test'],
                'tools' => [
                    'rector' => [
                        'enabled' => true,
                        'level' => 'typo3-13',
                    ],
                    'phpstan' => [
                        'enabled' => true,
                        'level' => 8,
                        'memory_limit' => '1G',
                    ],
                ],
            ],
        ];

        // Wrapper approach
        $simpleConfig = new SimpleConfiguration($configData);
        $wrapper = new ConfigurationWrapper($simpleConfig, 'simple');

        // Unified approaches
        $unifiedWithoutService = Configuration::createSimple(
            projectRoot: getcwd(),
            data: $configData
        );
        $unifiedWithService = Configuration::createSimple(
            projectRoot: getcwd(),
            data: $configData,
            toolConfigService: new ToolConfigService()
        );

        // Tool configurations should be identical
        foreach (['rector', 'phpstan'] as $tool) {
            $wrapperConfig = $wrapper->getToolConfig($tool);
            $unifiedConfig = $unifiedWithoutService->getToolConfig($tool);
            $unifiedWithServiceConfig = $unifiedWithService->getToolConfig($tool);

            // Basic tool configuration should match
            $this->assertEquals($wrapperConfig['enabled'], $unifiedConfig['enabled']);
            $this->assertEquals($wrapperConfig['enabled'], $unifiedWithServiceConfig['enabled']);

            // Tool-specific settings should match
            if (isset($wrapperConfig['level'])) {
                $this->assertEquals($wrapperConfig['level'], $unifiedConfig['level'] ?? null);
                $this->assertEquals($wrapperConfig['level'], $unifiedWithServiceConfig['level'] ?? null);
            }
        }
    }

    public function testServiceInjectionFallbackBehavior(): void
    {
        $configData = [
            'quality-tools' => [
                'project' => ['name' => 'fallback-test'],
                'tools' => ['rector' => ['enabled' => true]],
            ],
        ];

        // Test that unified config works even when no services are provided
        $unifiedConfig = Configuration::createSimple(
            projectRoot: $this->tempDir,
            data: $configData
        );

        // Basic functionality should work without services
        $this->assertEquals('fallback-test', $unifiedConfig->getProjectName());
        $this->assertTrue($unifiedConfig->isToolEnabled('rector'));
        $this->assertIsArray($unifiedConfig->getToolConfig('rector'));
        $this->assertIsArray($unifiedConfig->getScanPaths());

        // Compare with wrapper behavior
        $simpleConfig = new SimpleConfiguration($configData);
        $simpleConfig->setProjectRoot($this->tempDir);
        $wrapper = new ConfigurationWrapper($simpleConfig, 'simple');

        $this->assertEquals($wrapper->getProjectName(), $unifiedConfig->getProjectName());
        $this->assertEquals($wrapper->isToolEnabled('rector'), $unifiedConfig->isToolEnabled('rector'));
        $this->assertEquals($wrapper->getScanPaths(), $unifiedConfig->getScanPaths());
    }

    public function testServiceDependencyErrors(): void
    {
        $configData = [
            'quality-tools' => [
                'project' => ['name' => 'error-test'],
            ],
        ];

        // Both approaches should handle service dependency issues gracefully
        $simpleConfig = new SimpleConfiguration($configData);
        $wrapper = new ConfigurationWrapper($simpleConfig, 'simple');

        $unifiedConfig = Configuration::createSimple(
            projectRoot: getcwd(),
            data: $configData
        );

        // Methods that don't require services should work in both
        $this->assertEquals('error-test', $wrapper->getProjectName());
        $this->assertEquals('error-test', $unifiedConfig->getProjectName());

        // Methods that might require services should behave consistently
        // (either both work or both fail in same way)
        $wrapperWorked = true;
        $unifiedWorked = true;

        try {
            $wrapper->getVendorPath();
        } catch (\Exception $e) {
            $wrapperWorked = false;
        }

        try {
            $unifiedConfig->getVendorPath();
        } catch (\Exception $e) {
            $unifiedWorked = false;
        }

        // Both should succeed or both should fail
        $this->assertEquals($wrapperWorked, $unifiedWorked, 
            'Service-dependent methods should behave consistently between wrapper and unified');
    }
}
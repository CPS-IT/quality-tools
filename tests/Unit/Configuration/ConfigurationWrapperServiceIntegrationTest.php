<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Tests\Unit\Configuration;

use Cpsit\QualityTools\Configuration\ConfigurationWrapper;
use Cpsit\QualityTools\Configuration\SimpleConfiguration;
use Cpsit\QualityTools\Service\ProjectConfigService;
use Cpsit\QualityTools\Service\ToolConfigService;
use Cpsit\QualityTools\Service\PathResolutionService;
use Cpsit\QualityTools\Tests\Unit\TestHelper;
use PHPUnit\Framework\TestCase;

/**
 * Test for Step 4.2: Verify service integration in ConfigurationWrapper.
 *
 * Tests that ConfigurationWrapper with services provides equivalent results
 * to ConfigurationWrapper without services (backward compatibility).
 */
final class ConfigurationWrapperServiceIntegrationTest extends TestCase
{
    private array $testData;
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = TestHelper::createTempDirectory('wrapper_service_test_');
        
        $this->testData = [
            'quality-tools' => [
                'project' => [
                    'name' => 'service-integration-test',
                    'php_version' => '8.4',
                    'typo3_version' => '13.4',
                ],
                'tools' => [
                    'rector' => [
                        'enabled' => true,
                        'level' => 'typo3-13',
                    ],
                    'phpstan' => [
                        'enabled' => true,
                        'level' => 8,
                    ],
                ],
                'paths' => [
                    'scan' => ['src/', 'packages/'],
                    'exclude' => ['var/', 'tmp/'],
                ],
            ],
        ];
    }

    protected function tearDown(): void
    {
        TestHelper::removeDirectory($this->tempDir);
    }

    public function testWrapperWithServicesProvidesToolConfigWithDefaults(): void
    {
        $simpleConfig = new SimpleConfiguration($this->testData);
        $simpleConfig->setProjectRoot($this->tempDir);

        // Create wrapper with services
        $projectService = new ProjectConfigService();
        $toolService = new ToolConfigService();
        $pathService = new PathResolutionService();

        $wrapperWithServices = new ConfigurationWrapper(
            $simpleConfig, 
            'simple', 
            $projectService, 
            $toolService, 
            $pathService
        );

        // Create wrapper without services (for comparison)
        $wrapperWithoutServices = new ConfigurationWrapper($simpleConfig, 'simple');

        // Test that tool configs include proper defaults when using services
        $rectorWithServices = $wrapperWithServices->getRectorConfig();
        $rectorWithoutServices = $wrapperWithoutServices->getRectorConfig();

        // Both should have enabled and level, but the one with services should have proper defaults
        self::assertTrue($rectorWithServices['enabled']);
        self::assertSame('typo3-13', $rectorWithServices['level']);
        self::assertSame('8.4', $rectorWithServices['php_version']); // Service adds PHP version

        // Without services should also work but might not have all defaults
        self::assertTrue($rectorWithoutServices['enabled']);
        self::assertSame('typo3-13', $rectorWithoutServices['level']);
    }

    public function testWrapperWithServicesProvidesPHPStanConfigWithDefaults(): void
    {
        $simpleConfig = new SimpleConfiguration($this->testData);
        $simpleConfig->setProjectRoot($this->tempDir);

        // Create wrapper with services
        $projectService = new ProjectConfigService();
        $toolService = new ToolConfigService();
        $pathService = new PathResolutionService();

        $wrapperWithServices = new ConfigurationWrapper(
            $simpleConfig, 
            'simple', 
            $projectService, 
            $toolService, 
            $pathService
        );

        $phpstanConfig = $wrapperWithServices->getPhpStanConfig();

        // Should have service-provided defaults
        self::assertTrue($phpstanConfig['enabled']);
        self::assertSame(8, $phpstanConfig['level']); // Configured value
        self::assertSame('1G', $phpstanConfig['memory_limit']); // Service default
    }

    public function testWrapperWithServicesProvidesFractorConfigWithDefaults(): void
    {
        $simpleConfig = new SimpleConfiguration($this->testData);
        $simpleConfig->setProjectRoot($this->tempDir);

        // Create wrapper with services
        $toolService = new ToolConfigService();
        $wrapperWithServices = new ConfigurationWrapper(
            $simpleConfig, 
            'simple', 
            null, 
            $toolService, 
            null
        );

        $fractorConfig = $wrapperWithServices->getFractorConfig();

        // Should have service-provided defaults
        self::assertTrue($fractorConfig['enabled']); // Service default
        self::assertSame(2, $fractorConfig['indentation']); // Service default
    }

    public function testWrapperWithServicesHandlesPathResolution(): void
    {
        $simpleConfig = new SimpleConfiguration($this->testData);
        $simpleConfig->setProjectRoot($this->tempDir);

        // Create wrapper with path service
        $pathService = new PathResolutionService();
        $wrapperWithServices = new ConfigurationWrapper(
            $simpleConfig, 
            'simple', 
            null, 
            null, 
            $pathService
        );

        // Should be able to resolve paths using service
        $paths = $wrapperWithServices->getResolvedPathsForTool('rector');
        self::assertIsArray($paths);
    }

    public function testWrapperWithoutServicesStillWorks(): void
    {
        $simpleConfig = new SimpleConfiguration($this->testData);
        $simpleConfig->setProjectRoot($this->tempDir);

        // Create wrapper without services
        $wrapper = new ConfigurationWrapper($simpleConfig, 'simple');

        // Should still provide basic functionality
        self::assertSame('service-integration-test', $wrapper->getProjectName());
        self::assertSame('8.4', $wrapper->getProjectPhpVersion());
        self::assertTrue($wrapper->isToolEnabled('rector'));
        
        $rectorConfig = $wrapper->getToolConfig('rector');
        self::assertTrue($rectorConfig['enabled']);
        self::assertSame('typo3-13', $rectorConfig['level']);
    }

    public function testWrapperServiceInjectionIsOptional(): void
    {
        $simpleConfig = new SimpleConfiguration($this->testData);

        // Test various combinations of service injection
        $wrapperNoServices = new ConfigurationWrapper($simpleConfig);
        $wrapperWithProject = new ConfigurationWrapper($simpleConfig, 'simple', new ProjectConfigService());
        $wrapperWithTool = new ConfigurationWrapper($simpleConfig, 'simple', null, new ToolConfigService());

        // All should work
        self::assertSame('service-integration-test', $wrapperNoServices->getProjectName());
        self::assertSame('service-integration-test', $wrapperWithProject->getProjectName());
        self::assertSame('service-integration-test', $wrapperWithTool->getProjectName());

        // Tool configs should work with and without service
        $rectorNoService = $wrapperNoServices->getRectorConfig();
        $rectorWithService = $wrapperWithTool->getRectorConfig();

        self::assertIsArray($rectorNoService);
        self::assertIsArray($rectorWithService);

        // The one with service should have PHP version
        self::assertArrayHasKey('php_version', $rectorWithService);
    }

    public function testWrapperMaintainsBasicDelegationBehavior(): void
    {
        $simpleConfig = new SimpleConfiguration($this->testData);
        $simpleConfig->setProjectRoot($this->tempDir);

        $wrapper = new ConfigurationWrapper(
            $simpleConfig, 
            'simple',
            new ProjectConfigService(),
            new ToolConfigService(),
            new PathResolutionService()
        );

        // Basic delegation should still work
        self::assertSame($simpleConfig->toArray(), $wrapper->toArray());
        self::assertSame($simpleConfig->getProjectRoot(), $wrapper->getProjectRoot());
        self::assertSame($simpleConfig->getScanPaths(), $wrapper->getScanPaths());
        self::assertSame($simpleConfig->getExcludePaths(), $wrapper->getExcludePaths());
        self::assertSame($simpleConfig->isToolEnabled('rector'), $wrapper->isToolEnabled('rector'));
    }
}
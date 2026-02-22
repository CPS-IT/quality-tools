<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Tests\Unit\Configuration;

use Cpsit\QualityTools\Configuration\Configuration;
use Cpsit\QualityTools\Configuration\ConfigurationDiscovery;
use Cpsit\QualityTools\Configuration\ConfigurationHierarchy;
use Cpsit\QualityTools\Configuration\ConfigurationValidator;
use Cpsit\QualityTools\Configuration\ConfigurationWrapper;
use Cpsit\QualityTools\Configuration\EnhancedConfiguration;
use Cpsit\QualityTools\Service\FilesystemService;
use Cpsit\QualityTools\Service\SecurityService;
use Cpsit\QualityTools\Service\ToolConfigurationValidationService;
use Cpsit\QualityTools\Tests\Unit\TestHelper;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

/**
 * Tests to validate each step of the unified configuration compatibility implementation.
 *
 * These tests should pass once the compatibility fixes are implemented:
 * - Step 1: Parameter compatibility
 * - Step 2: Project root storage
 * - Step 3: Return value wrapping
 * - Step 4: Validation deferral
 * - Step 5: Service auto-injection
 */
final class UnifiedCompatibilityTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = TestHelper::createTempDirectory('unified_compatibility_test_');
    }

    protected function tearDown(): void
    {
        TestHelper::removeDirectory($this->tempDir);
    }

    /**
     * Test Step 1: Configuration::createHierarchical() parameter compatibility.
     *
     * Should pass after implementing parameter order fix in createHierarchical().
     */
    public function testCreateHierarchicalParameterCompatibility(): void
    {
        $data = ['quality-tools' => ['project' => ['name' => 'test-project']]];
        $sourceMap = ['quality-tools.project.name' => '/test/config.yaml'];
        $projectRoot = '/test/project';

        // Create via wrapper approach (reference behavior)
        $enhanced = new EnhancedConfiguration(
            $data,
            $sourceMap,
            [],
            [],
            null,
            null,
            $projectRoot,
            null,
        );
        $wrapper = new ConfigurationWrapper($enhanced, 'enhanced');

        // Create via unified approach (fix parameter order)
        $unified = Configuration::createHierarchical(
            projectRoot: $projectRoot,
            data: $data,
            sourceMap: $sourceMap,
            conflicts: [],
            mergeSummary: [],
            validator: null,
            projectConfigService: null,
            toolConfigService: null,
            pathResolutionService: null,
            hierarchy: null,
            discovery: null,
        );

        // Assert identical behavior
        $this->assertEquals($wrapper->getProjectName(), $unified->getProjectName());
        $this->assertEquals($wrapper->getProjectRoot(), $unified->getProjectRoot());
        $this->assertEquals($wrapper->toArray(), $unified->toArray());

        $this->assertNotNull($unified->getProjectName(), 'Project name should not be null');
        $this->assertEquals('test-project', $unified->getProjectName());
    }

    /**
     * Test Step 2: Project root storage compatibility.
     *
     * Should pass after implementing actualProjectRoot storage like EnhancedConfiguration.
     */
    public function testProjectRootStorageCompatibility(): void
    {
        $data = ['quality-tools' => ['project' => ['name' => 'test-project']]];
        $projectRoot = $this->tempDir;

        // Create via EnhancedConfiguration (reference behavior)
        $enhanced = new EnhancedConfiguration($data, [], [], [], null, null, $projectRoot);
        $enhancedProjectRoot = $enhanced->getProjectRoot();

        // Create via unified Configuration (should match)
        $unified = Configuration::createHierarchical(
            projectRoot: $projectRoot,
            data: $data,
            sourceMap: [],
            conflicts: [],
            mergeSummary: [],
            validator: null,
            projectConfigService: null,
            toolConfigService: null,
            pathResolutionService: null,
            hierarchy: null,
            discovery: null,
        );
        $unifiedProjectRoot = $unified->getProjectRoot();

        $this->assertEquals($enhancedProjectRoot, $unifiedProjectRoot);
        $this->assertNotNull($unifiedProjectRoot, 'Project root should not be null');
        $this->assertEquals($projectRoot, $unifiedProjectRoot);
    }

    /**
     * Test Step 3: Service auto-injection compatibility.
     *
     * Should pass after implementing auto-injection in factory methods.
     */
    public function testServiceAutoInjectionCompatibility(): void
    {
        $data = [
            'quality-tools' => [
                'project' => ['name' => 'test-project'],
                'tools' => ['rector' => ['enabled' => true]],
            ],
        ];

        // Should work without explicitly providing services (auto-injection)
        $configuration = Configuration::createSimple(
            projectRoot: getcwd(),
            data: $data,
        );

        // These methods should work due to auto-injected services
        $this->assertEquals('test-project', $configuration->getProjectName());
        $this->assertTrue($configuration->isToolEnabled('rector'));
        $this->assertIsArray($configuration->getToolConfig('rector'));
    }

    /**
     * Test Step 4: Validation deferral compatibility.
     *
     * Should pass after implementing deferred validation.
     */
    public function testValidationDeferralCompatibility(): void
    {
        // Configuration with properties that strict validation might reject
        $dataWithUnknownProperties = [
            'quality-tools' => [
                'project' => ['name' => 'test-project'],
                'performance' => [
                    'parallel' => false,
                    'custom_option' => 'value', // Unknown property
                ],
            ],
        ];

        $validator = new ConfigurationValidator();

        // Should not throw validation exception during creation (deferred validation)
        $configuration = Configuration::createSimple(
            projectRoot: getcwd(),
            data: $dataWithUnknownProperties,
            validator: null,
        ); // Skip validator to test deferred behavior

        $this->assertEquals('test-project', $configuration->getProjectName());
        $this->assertEquals($dataWithUnknownProperties, $configuration->toArray());
    }

    /**
     * Test Step 5: Complete behavioral equivalence after all fixes.
     *
     * Should pass after all compatibility steps are implemented.
     */
    public function testAllInterfaceMethodsEquivalence(): void
    {
        $testData = $this->createTestConfiguration();

        $enhanced = new EnhancedConfiguration($testData['data'], $testData['sourceMap'], [], [], null, null, '/test', null);
        $wrapper = new ConfigurationWrapper($enhanced, 'enhanced');

        $unified = Configuration::createHierarchical(
            projectRoot: '/test',
            data: $testData['data'],
            sourceMap: $testData['sourceMap'],
            conflicts: [],
            mergeSummary: [],
            validator: null,
            projectConfigService: null,
            toolConfigService: null,
            pathResolutionService: null,
            hierarchy: null,
            discovery: null,
        );

        // Test all ConfigurationInterface methods
        $this->assertEquals($wrapper->getProjectPhpVersion(), $unified->getProjectPhpVersion());
        $this->assertEquals($wrapper->getProjectTypo3Version(), $unified->getProjectTypo3Version());
        $this->assertEquals($wrapper->getScanPaths(), $unified->getScanPaths());
        $this->assertEquals($wrapper->getExcludePaths(), $unified->getExcludePaths());
        $this->assertEquals($wrapper->isToolEnabled('rector'), $unified->isToolEnabled('rector'));
        $this->assertEquals($wrapper->isToolEnabled('phpstan'), $unified->isToolEnabled('phpstan'));
        $this->assertEquals($wrapper->getToolConfig('rector'), $unified->getToolConfig('rector'));
        $this->assertEquals($wrapper->toArray(), $unified->toArray());
    }

    /**
     * Test hierarchical configuration loading without validation errors.
     *
     * Should pass after implementing validation deferral and parameter compatibility.
     */
    public function testHierarchicalConfigurationLoading(): void
    {
        // Create parent/child directory structure
        $parentDir = $this->tempDir . '/parent';
        $childDir = $parentDir . '/child';
        mkdir($childDir, 0o777, true);

        // Parent configuration
        $parentConfig = [
            'quality-tools' => [
                'project' => [
                    'name' => 'parent-project',
                    'php_version' => '8.3',
                ],
            ],
        ];

        // Child configuration (overrides parent)
        $childConfig = [
            'quality-tools' => [
                'project' => [
                    'name' => 'child-project',
                    'php_version' => '8.4',
                ],
            ],
        ];

        file_put_contents($parentDir . '/quality-tools.yaml', Yaml::dump($parentConfig, 4, 2));
        file_put_contents($childDir . '/quality-tools.yaml', Yaml::dump($childConfig, 4, 2));

        // Should work without throwing validation errors
        $hierarchy = new ConfigurationHierarchy($childDir);
        $discovery = new ConfigurationDiscovery(
            $hierarchy,
            new FilesystemService(),
            new SecurityService(),
            new ConfigurationValidator(),
            new ToolConfigurationValidationService(),
        );

        $configurations = $discovery->discoverConfigurations();
        $this->assertNotEmpty($configurations);

        // Should be able to create unified configuration from hierarchy
        $mergedData = ['quality-tools' => ['project' => ['name' => 'child-project', 'php_version' => '8.4']]];
        $configuration = Configuration::createHierarchical(
            projectRoot: $childDir,
            data: $mergedData,
            sourceMap: [],
            conflicts: [],
            mergeSummary: [],
            validator: null,
            projectConfigService: null,
            toolConfigService: null,
            pathResolutionService: null,
            hierarchy: $hierarchy,
            discovery: $discovery,
        );

        $this->assertEquals('child-project', $configuration->getProjectName());
        $this->assertEquals('8.4', $configuration->getProjectPhpVersion());
        $this->assertEquals($childDir, $configuration->getProjectRoot());
    }

    private function createTestConfiguration(): array
    {
        return [
            'data' => [
                'quality-tools' => [
                    'project' => [
                        'name' => 'test-project',
                        'php_version' => '8.3',
                        'typo3_version' => '13.4',
                    ],
                    'paths' => [
                        'scan' => ['src/', 'config/'],
                        'exclude' => ['vendor/', 'var/'],
                    ],
                    'tools' => [
                        'rector' => ['enabled' => true],
                        'phpstan' => ['enabled' => true],
                    ],
                ],
            ],
            'sourceMap' => [
                'quality-tools.project.name' => '/test/config.yaml',
            ],
        ];
    }
}

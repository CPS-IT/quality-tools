<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Tests\Unit\Configuration;

use Cpsit\QualityTools\Configuration\Configuration;
use Cpsit\QualityTools\Configuration\ConfigurationLoader;
use Cpsit\QualityTools\Configuration\ConfigurationLoaderWrapper;
use Cpsit\QualityTools\Configuration\ConfigurationValidator;
use Cpsit\QualityTools\Configuration\ConfigurationWrapper;
use Cpsit\QualityTools\Configuration\HierarchicalConfigurationLoader;
use Cpsit\QualityTools\Configuration\SimpleConfiguration;
use Cpsit\QualityTools\Configuration\SimpleConfigurationLoader;
use Cpsit\QualityTools\Console\Command\ComposerFixCommand;
use Cpsit\QualityTools\Console\QualityToolsApplication;
use Cpsit\QualityTools\Service\FilesystemService;
use Cpsit\QualityTools\Service\PathResolutionService;
use Cpsit\QualityTools\Service\ProjectConfigService;
use Cpsit\QualityTools\Service\SecurityService;
use Cpsit\QualityTools\Service\ToolConfigService;
use Cpsit\QualityTools\Tests\Unit\TestHelper;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Yaml\Yaml;

/**
 * Test to identify behavioral differences between wrapper and unified approaches.
 * 
 * This test compares the behavior of:
 * 1. ConfigurationWrapper with SimpleConfiguration/EnhancedConfiguration
 * 2. Unified Configuration class
 * 3. ConfigurationLoaderWrapper vs Unified ConfigurationLoader
 * 
 * The purpose is to identify specific test coverage gaps that prevent
 * replacing wrapper implementations with unified implementations.
 */
final class WrapperVsUnifiedBehaviorTest extends TestCase
{
    private string $tempDir;
    private array $testConfigData;

    protected function setUp(): void
    {
        $this->tempDir = TestHelper::createTempDirectory('wrapper_vs_unified_test_');
        
        // Create project structure for proper testing
        TestHelper::createComposerJson($this->tempDir, TestHelper::getComposerContent('typo3-core'));
        TestHelper::createVendorStructure($this->tempDir);
        
        // Create a test configuration that exercises various edge cases
        $this->testConfigData = [
            'quality-tools' => [
                'project' => [
                    'name' => 'test-project',
                    'php_version' => '8.4',
                    'typo3_version' => '13.4',
                ],
                'paths' => [
                    'scan' => ['src/', 'packages/'],
                    'exclude' => ['var/', 'tmp/'],
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
                'output' => [
                    'verbosity' => 'normal',
                    'colors' => true,
                    'progress' => true,
                ],
                'performance' => [
                    'parallel' => false,
                    'max_processes' => 4,
                ],
            ],
        ];
        
        // Save test configuration file
        file_put_contents(
            $this->tempDir . '/quality-tools.yaml', 
            Yaml::dump($this->testConfigData, 4, 2)
        );
    }

    protected function tearDown(): void
    {
        TestHelper::removeDirectory($this->tempDir);
    }

    public function testSimpleConfigurationVsUnifiedConfiguration(): void
    {
        // Test 1: SimpleConfiguration behavior
        $simpleConfig = new SimpleConfiguration($this->testConfigData);
        $simpleConfig->setProjectRoot($this->tempDir);
        
        // Test 2: ConfigurationWrapper wrapping SimpleConfiguration
        $wrappedSimpleConfig = new ConfigurationWrapper($simpleConfig, 'simple');
        
        // Test 3: Unified Configuration in simple mode (without validator to avoid schema issues)
        $unifiedConfig = Configuration::createSimple(
            data: $this->testConfigData,
            validator: null,  // Skip validation to match wrapper permissiveness
            projectConfigService: new ProjectConfigService(),
            toolConfigService: new ToolConfigService(),
            pathResolutionService: new PathResolutionService(),
        );
        $unifiedConfig->setProjectRoot($this->tempDir);
        
        // Compare basic project properties
        $this->assertProjectPropertiesMatch($simpleConfig, $wrappedSimpleConfig, $unifiedConfig);
        
        // Compare tool configurations
        $this->assertToolConfigurationsMatch($simpleConfig, $wrappedSimpleConfig, $unifiedConfig);
        
        // Compare path configurations
        $this->assertPathConfigurationsMatch($simpleConfig, $wrappedSimpleConfig, $unifiedConfig);
        
        // Compare output configurations
        $this->assertOutputConfigurationsMatch($simpleConfig, $wrappedSimpleConfig, $unifiedConfig);
    }

    public function testConfigurationLoadingBehavior(): void
    {
        // Test 1: SimpleConfigurationLoader with wrapper
        $simpleLoader = new SimpleConfigurationLoader(
            new ConfigurationValidator(),
            new SecurityService(),
            new FilesystemService(new \Symfony\Component\Filesystem\Filesystem())
        );
        
        $hierarchicalLoader = new HierarchicalConfigurationLoader(
            new ConfigurationValidator(),
            new SecurityService(),
            new FilesystemService()
        );
        
        $wrapperLoader = new ConfigurationLoaderWrapper(
            $simpleLoader,
            $hierarchicalLoader,
            'simple'
        );
        
        // Test 2: Unified ConfigurationLoader
        $unifiedLoader = new ConfigurationLoader(
            new ConfigurationValidator(),
            new SecurityService(),
            new FilesystemService(),
            new ProjectConfigService(),
            new ToolConfigService(),
            new PathResolutionService()
        );
        
        // Load configurations
        $configFromWrapper = $wrapperLoader->load($this->tempDir);
        $configFromUnified = $unifiedLoader->load($this->tempDir);
        
        // Compare loaded configurations
        $this->assertLoadedConfigurationsMatch($configFromWrapper, $configFromUnified);
    }

    public function testBaseCommandBehaviorWithDifferentConfigurations(): void
    {
        // Create fake composer executable for testing
        $vendorBinDir = $this->tempDir . '/vendor/bin';
        if (!is_dir($vendorBinDir)) {
            mkdir($vendorBinDir, 0o777, true);
        }
        
        $composerScript = "#!/bin/bash\necho 'composer.json has been normalized.'\nexit 0\n";
        $composerExecutable = $vendorBinDir . '/composer';
        file_put_contents($composerExecutable, $composerScript);
        chmod($composerExecutable, 0o755);
        
        file_put_contents($this->tempDir . '/composer.json', '{}');
        
        // Test 1: Command with wrapper-based configuration loader
        $simpleLoader = new SimpleConfigurationLoader(
            new ConfigurationValidator(),
            new SecurityService(),
            new FilesystemService(new \Symfony\Component\Filesystem\Filesystem())
        );
        
        $hierarchicalLoader = new HierarchicalConfigurationLoader(
            new ConfigurationValidator(),
            new SecurityService(),
            new FilesystemService()
        );
        
        $wrapperLoader = new ConfigurationLoaderWrapper(
            $simpleLoader,
            $hierarchicalLoader,
            'simple'
        );
        
        TestHelper::withEnvironment(
            ['QT_PROJECT_ROOT' => $this->tempDir],
            function () use ($wrapperLoader): void {
                $app = new QualityToolsApplication();
                $command1 = new ComposerFixCommand(configurationLoader: $wrapperLoader);
                $command1->setApplication($app);
                
                $tester1 = new CommandTester($command1);
                $exitCode1 = $tester1->execute([]);
                
                // Test 2: Command with unified configuration loader
                $unifiedLoader = new ConfigurationLoader(
                    new ConfigurationValidator(),
                    new SecurityService(),
                    new FilesystemService(),
                    new ProjectConfigService(),
                    new ToolConfigService(),
                    new PathResolutionService()
                );
                
                $command2 = new ComposerFixCommand(configurationLoader: $unifiedLoader);
                $command2->setApplication($app);
                
                $tester2 = new CommandTester($command2);
                $exitCode2 = $tester2->execute([]);
                
                // This is where we expect to see exit code differences
                $this->assertSame($exitCode1, $exitCode2, 
                    "Exit codes should match between wrapper and unified approaches. " .
                    "Wrapper output: {$tester1->getDisplay()}, Unified output: {$tester2->getDisplay()}"
                );
            }
        );
    }

    public function testHierarchicalConfigurationBehaviorDifferences(): void
    {
        // Create hierarchical configuration scenario
        $parentDir = $this->tempDir . '/parent';
        $childDir = $this->tempDir . '/parent/child';
        mkdir($childDir, 0o777, true);
        
        // Parent configuration
        $parentConfig = [
            'quality-tools' => [
                'project' => [
                    'name' => 'parent-project',
                    'php_version' => '8.3',
                ],
                'tools' => [
                    'rector' => ['enabled' => true, 'level' => 'basic'],
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
                'tools' => [
                    'rector' => ['level' => 'typo3-13'],
                ],
            ],
        ];
        
        file_put_contents($parentDir . '/quality-tools.yaml', Yaml::dump($parentConfig, 4, 2));
        file_put_contents($childDir . '/quality-tools.yaml', Yaml::dump($childConfig, 4, 2));
        
        // Test 1: HierarchicalConfigurationLoader with wrapper
        $hierarchicalLoader = new HierarchicalConfigurationLoader(
            new ConfigurationValidator(),
            new SecurityService(),
            new FilesystemService()
        );
        
        $simpleLoader = new SimpleConfigurationLoader(
            new ConfigurationValidator(),
            new SecurityService(),
            new FilesystemService(new \Symfony\Component\Filesystem\Filesystem())
        );
        
        $wrapperLoader = new ConfigurationLoaderWrapper(
            $simpleLoader,
            $hierarchicalLoader,
            'hierarchical'
        );
        
        // Test 2: Unified loader in hierarchical mode
        $unifiedLoader = new ConfigurationLoader(
            new ConfigurationValidator(),
            new SecurityService(),
            new FilesystemService(),
            new ProjectConfigService(),
            new ToolConfigService(),
            new PathResolutionService()
        );
        
        // Load configurations from child directory (should merge parent + child)
        $configFromWrapper = $wrapperLoader->load($childDir);
        $configFromUnified = $unifiedLoader->load($childDir);
        
        // Compare project names - this is where we might see null vs expected value
        $this->assertSame(
            $configFromWrapper->getProjectName(),
            $configFromUnified->getProjectName(),
            "Project names should match. Wrapper: '{$configFromWrapper->getProjectName()}', Unified: '{$configFromUnified->getProjectName()}'"
        );
        
        // Compare PHP versions - this is where we might see version differences
        $this->assertSame(
            $configFromWrapper->getProjectPhpVersion(),
            $configFromUnified->getProjectPhpVersion(),
            "PHP versions should match. Wrapper: '{$configFromWrapper->getProjectPhpVersion()}', Unified: '{$configFromUnified->getProjectPhpVersion()}'"
        );
    }

    private function assertProjectPropertiesMatch($config1, $config2, $config3): void
    {
        $properties = ['getProjectName', 'getProjectPhpVersion', 'getProjectTypo3Version'];
        
        foreach ($properties as $property) {
            $value1 = $config1->$property();
            $value2 = $config2->$property();
            $value3 = $config3->$property();
            
            $this->assertSame($value1, $value2, "SimpleConfig vs Wrapper mismatch for $property");
            $this->assertSame($value2, $value3, "Wrapper vs Unified mismatch for $property");
        }
    }
    
    private function assertToolConfigurationsMatch($config1, $config2, $config3): void
    {
        $tools = ['rector', 'phpstan'];
        
        foreach ($tools as $tool) {
            $enabled1 = $config1->isToolEnabled($tool);
            $enabled2 = $config2->isToolEnabled($tool);
            $enabled3 = $config3->isToolEnabled($tool);
            
            $this->assertSame($enabled1, $enabled2, "SimpleConfig vs Wrapper mismatch for $tool enabled");
            $this->assertSame($enabled2, $enabled3, "Wrapper vs Unified mismatch for $tool enabled");
            
            $toolConfig1 = $config1->getToolConfig($tool);
            $toolConfig2 = $config2->getToolConfig($tool);
            $toolConfig3 = $config3->getToolConfig($tool);
            
            // Use loose comparison for tool configs as service-enhanced configs may have additional defaults
            $this->assertArrayHasKey('enabled', $toolConfig1);
            $this->assertArrayHasKey('enabled', $toolConfig2);
            $this->assertSame($toolConfig1['enabled'], $toolConfig2['enabled'], "SimpleConfig vs Wrapper tool config enabled mismatch for $tool");
        }
    }
    
    private function assertPathConfigurationsMatch($config1, $config2, $config3): void
    {
        $this->assertSame($config1->getScanPaths(), $config2->getScanPaths(), "SimpleConfig vs Wrapper scan paths mismatch");
        $this->assertSame($config2->getScanPaths(), $config3->getScanPaths(), "Wrapper vs Unified scan paths mismatch");
        
        $this->assertSame($config1->getExcludePaths(), $config2->getExcludePaths(), "SimpleConfig vs Wrapper exclude paths mismatch");
        $this->assertSame($config2->getExcludePaths(), $config3->getExcludePaths(), "Wrapper vs Unified exclude paths mismatch");
    }
    
    private function assertOutputConfigurationsMatch($config1, $config2, $config3): void
    {
        $outputMethods = ['getVerbosity', 'isColorsEnabled', 'isProgressEnabled'];
        
        foreach ($outputMethods as $method) {
            $value1 = $config1->$method();
            $value2 = $config2->$method();
            $value3 = $config3->$method();
            
            $this->assertSame($value1, $value2, "SimpleConfig vs Wrapper mismatch for $method");
            $this->assertSame($value2, $value3, "Wrapper vs Unified mismatch for $method");
        }
    }
    
    private function assertLoadedConfigurationsMatch($config1, $config2): void
    {
        // Compare basic interface compliance
        $this->assertSame($config1->getProjectName(), $config2->getProjectName());
        $this->assertSame($config1->getProjectPhpVersion(), $config2->getProjectPhpVersion());
        $this->assertSame($config1->getProjectTypo3Version(), $config2->getProjectTypo3Version());
        
        // Compare tool enablement
        $tools = ['rector', 'phpstan', 'fractor', 'php-cs-fixer', 'typoscript-lint'];
        foreach ($tools as $tool) {
            $this->assertSame(
                $config1->isToolEnabled($tool), 
                $config2->isToolEnabled($tool),
                "Tool enablement mismatch for $tool"
            );
        }
    }
}
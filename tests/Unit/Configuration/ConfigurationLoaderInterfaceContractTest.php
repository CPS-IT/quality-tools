<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Tests\Unit\Configuration;

use Cpsit\QualityTools\Configuration\ConfigurationLoaderInterface;
use Cpsit\QualityTools\Configuration\ConfigurationInterface;
use Cpsit\QualityTools\Configuration\SimpleConfigurationLoader;
use Cpsit\QualityTools\Configuration\HierarchicalConfigurationLoader;
use Cpsit\QualityTools\Configuration\ConfigurationLoaderWrapper;
use Cpsit\QualityTools\Configuration\ConfigurationValidator;
use Cpsit\QualityTools\Service\SecurityService;
use Cpsit\QualityTools\Service\FilesystemService;
use Cpsit\QualityTools\Tests\Unit\FilesystemTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Contract test ensuring all ConfigurationLoaderInterface implementations
 * provide consistent behavior and return types.
 */
#[CoversClass(ConfigurationLoaderInterface::class)]
final class ConfigurationLoaderInterfaceContractTest extends FilesystemTestCase
{
    private string $projectRoot;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();
        $this->projectRoot = $this->createConfigurationStructure();
    }

    /**
     * @return array<string, array{ConfigurationLoaderInterface}>
     */
    public static function configurationLoaderImplementationsProvider(): array
    {
        $validator = new ConfigurationValidator();
        $securityService = new SecurityService();
        $filesystemService = new FilesystemService();

        $simpleLoader = new SimpleConfigurationLoader($validator, $securityService, $filesystemService);
        $hierarchicalLoader = new HierarchicalConfigurationLoader($validator, $securityService, $filesystemService);

        return [
            'SimpleConfigurationLoader' => [$simpleLoader],
            'HierarchicalConfigurationLoader' => [$hierarchicalLoader],
            'ConfigurationLoaderWrapper (simple mode)' => [
                new ConfigurationLoaderWrapper($simpleLoader, $hierarchicalLoader, 'simple')
            ],
            'ConfigurationLoaderWrapper (hierarchical mode)' => [
                new ConfigurationLoaderWrapper($simpleLoader, $hierarchicalLoader, 'hierarchical')
            ],
        ];
    }

    /**
     * @dataProvider configurationLoaderImplementationsProvider
     */
    public function testBasicLoadingMethods(ConfigurationLoaderInterface $loader): void
    {
        // Test basic load method
        $configuration = $loader->load($this->projectRoot);
        self::assertInstanceOf(ConfigurationInterface::class, $configuration);

        // Test load with overrides - use quality-tools wrapper for valid structure
        $overrides = ['quality-tools' => ['project' => ['name' => 'override-test']]];
        $configWithOverrides = $loader->load($this->projectRoot, $overrides);
        self::assertInstanceOf(ConfigurationInterface::class, $configWithOverrides);
    }

    /**
     * @dataProvider configurationLoaderImplementationsProvider
     */
    public function testConfigurationDiscoveryMethods(ConfigurationLoaderInterface $loader): void
    {
        // Test findConfigurationFile
        $configFile = $loader->findConfigurationFile($this->projectRoot);
        self::assertTrue(is_string($configFile) || $configFile === null);

        // Test supportsConfiguration
        $supportsConfig = $loader->supportsConfiguration($this->projectRoot);
        self::assertIsBool($supportsConfig);
    }

    /**
     * @dataProvider configurationLoaderImplementationsProvider
     */
    public function testToolSpecificLoading(ConfigurationLoaderInterface $loader): void
    {
        // Test loadForTool method
        $toolConfig = $loader->loadForTool($this->projectRoot, 'rector');
        self::assertInstanceOf(ConfigurationInterface::class, $toolConfig);

        // Test loadForTool with overrides - use quality-tools wrapper for valid structure
        $overrides = ['quality-tools' => ['tools' => ['rector' => ['level' => 'typo3-12']]]];
        $toolConfigWithOverrides = $loader->loadForTool($this->projectRoot, 'rector', $overrides);
        self::assertInstanceOf(ConfigurationInterface::class, $toolConfigWithOverrides);
    }

    /**
     * @dataProvider configurationLoaderImplementationsProvider
     */
    public function testConfigurationAnalysisMethods(ConfigurationLoaderInterface $loader): void
    {
        // Test hasHierarchicalConfiguration
        $hasHierarchical = $loader->hasHierarchicalConfiguration($this->projectRoot);
        self::assertIsBool($hasHierarchical);

        // Test getConfigurationErrors
        $errors = $loader->getConfigurationErrors($this->projectRoot);
        self::assertIsArray($errors);

        // Test getConfigurationDebugInfo
        $debugInfo = $loader->getConfigurationDebugInfo($this->projectRoot);
        self::assertIsArray($debugInfo);

        // Test getConfigurationSources
        $sources = $loader->getConfigurationSources($this->projectRoot);
        self::assertIsArray($sources);
    }

    /**
     * @dataProvider configurationLoaderImplementationsProvider
     */
    public function testPreviewMethods(ConfigurationLoaderInterface $loader): void
    {
        // Test previewMergedConfiguration
        $preview = $loader->previewMergedConfiguration($this->projectRoot);
        self::assertIsArray($preview);

        // Test previewMergedConfiguration with overrides - use quality-tools wrapper
        $overrides = ['quality-tools' => ['project' => ['name' => 'preview-test']]];
        $previewWithOverrides = $loader->previewMergedConfiguration($this->projectRoot, $overrides);
        self::assertIsArray($previewWithOverrides);
    }

    /**
     * @dataProvider configurationLoaderImplementationsProvider
     */
    public function testBackwardCompatibilityMethods(ConfigurationLoaderInterface $loader): void
    {
        // Test createSimpleConfiguration
        $simpleConfig = $loader->createSimpleConfiguration($this->projectRoot);
        self::assertInstanceOf(ConfigurationInterface::class, $simpleConfig);
    }

    /**
     * Test all implementations handle empty project roots consistently
     */
    public function testEmptyProjectRootHandling(): void
    {
        $emptyRoot = $this->createTemporaryStructure();
        $implementations = self::configurationLoaderImplementationsProvider();

        foreach ($implementations as $name => [$loader]) {
            // Should not throw exceptions
            $config = $loader->load($emptyRoot);
            self::assertInstanceOf(ConfigurationInterface::class, $config, 
                "load() must return ConfigurationInterface in $name");

            $supportsConfig = $loader->supportsConfiguration($emptyRoot);
            self::assertIsBool($supportsConfig, 
                "supportsConfiguration() must return bool in $name");

            $configFile = $loader->findConfigurationFile($emptyRoot);
            self::assertTrue(is_string($configFile) || $configFile === null, 
                "findConfigurationFile() must return string|null in $name");
        }
    }

    /**
     * Test consistency of configuration sources across implementations
     */
    public function testConfigurationSourcesConsistency(): void
    {
        $implementations = self::configurationLoaderImplementationsProvider();

        foreach ($implementations as $name => [$loader]) {
            $sources = $loader->getConfigurationSources($this->projectRoot);
            self::assertIsArray($sources, "getConfigurationSources() must return array in $name");

            foreach ($sources as $source) {
                self::assertIsArray($source, "Each source must be an array in $name");
                self::assertArrayHasKey('source', $source, "Source must have 'source' key in $name");
            }
        }
    }

    /**
     * Test error handling consistency across implementations
     */
    public function testErrorHandlingConsistency(): void
    {
        $implementations = self::configurationLoaderImplementationsProvider();

        foreach ($implementations as $name => [$loader]) {
            $errors = $loader->getConfigurationErrors($this->projectRoot);
            self::assertIsArray($errors, "getConfigurationErrors() must return array in $name");

            foreach ($errors as $filePath => $error) {
                self::assertIsString($filePath, "Error keys must be file paths (strings) in $name");
                self::assertIsString($error, "Error values must be strings in $name");
            }
        }
    }

    /**
     * Test that all loaders produce valid configuration data structure
     */
    public function testValidConfigurationDataStructure(): void
    {
        // Create test configuration file
        $yamlContent = <<<YAML
            quality-tools:
              project:
                name: "contract-test"
                php_version: "8.3"
              tools:
                rector:
                  enabled: true
              paths:
                scan:
                  - "src/"
            YAML;

        $this->createVirtualFile('project/.quality-tools.yaml', $yamlContent);

        $implementations = self::configurationLoaderImplementationsProvider();

        foreach ($implementations as $name => [$loader]) {
            $config = $loader->load($this->projectRoot);
            $data = $config->toArray();
            
            self::assertIsArray($data, "Configuration data must be array in $name");
            self::assertArrayHasKey('quality-tools', $data, 
                "Configuration must have 'quality-tools' key in $name");
            
            $qtConfig = $data['quality-tools'];
            self::assertIsArray($qtConfig, "quality-tools section must be array in $name");
            
            // Verify project name is loaded correctly
            self::assertSame('contract-test', $config->getProjectName(), 
                "Project name should be loaded correctly in $name");
        }
    }

    /**
     * Test that wrapper implementations can switch modes correctly
     */
    public function testWrapperModeHandling(): void
    {
        $validator = new ConfigurationValidator();
        $securityService = new SecurityService();
        $filesystemService = new FilesystemService();

        $simpleLoader = new SimpleConfigurationLoader($validator, $securityService, $filesystemService);
        $hierarchicalLoader = new HierarchicalConfigurationLoader($validator, $securityService, $filesystemService);

        // Test simple mode
        $simpleWrapper = new ConfigurationLoaderWrapper($simpleLoader, $hierarchicalLoader, 'simple');
        $config = $simpleWrapper->load($this->projectRoot);
        self::assertInstanceOf(ConfigurationInterface::class, $config);
        
        // Test hierarchical mode
        $hierarchicalWrapper = new ConfigurationLoaderWrapper($simpleLoader, $hierarchicalLoader, 'hierarchical');
        $config = $hierarchicalWrapper->load($this->projectRoot);
        self::assertInstanceOf(ConfigurationInterface::class, $config);
        
        // Test mode switching
        $switchedWrapper = $simpleWrapper->withMode('hierarchical');
        self::assertInstanceOf(ConfigurationLoaderWrapper::class, $switchedWrapper);
        self::assertNotSame($simpleWrapper, $switchedWrapper);
        
        $config = $switchedWrapper->load($this->projectRoot);
        self::assertInstanceOf(ConfigurationInterface::class, $config);
    }
}
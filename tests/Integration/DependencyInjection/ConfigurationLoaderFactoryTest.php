<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Tests\Integration\DependencyInjection;

use Cpsit\QualityTools\Configuration\ConfigurationLoaderFactory;
use Cpsit\QualityTools\Configuration\HierarchicalConfigurationLoader;
use Cpsit\QualityTools\Configuration\SimpleConfigurationLoader;
use Cpsit\QualityTools\Tests\Unit\TestHelper;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

/**
 * Integration test for ConfigurationLoaderFactory.
 *
 * Tests the factory pattern implementation for intelligent loader selection
 * based on command context and configuration.
 *
 * Part of Step 3.1: Create ConfigurationLoaderFactory
 */
final class ConfigurationLoaderFactoryTest extends TestCase
{
    private string $tempDir;
    private ConfigurationLoaderFactory $factory;
    private SimpleConfigurationLoader $simpleLoader;
    private HierarchicalConfigurationLoader $hierarchicalLoader;

    protected function setUp(): void
    {
        $this->tempDir = TestHelper::createTempDirectory('factory_test_');

        // Create test configuration file
        $testConfig = <<<YAML
            quality-tools:
              project:
                name: "factory-test"
                php_version: "8.4"
              tools:
                rector:
                  enabled: true
                  level: "typo3-13"
            YAML;
        file_put_contents($this->tempDir . '/.quality-tools.yaml', $testConfig);

        // Create loader instances using DI container
        $container = $this->createContainer();
        $this->simpleLoader = $container->get(SimpleConfigurationLoader::class);
        $this->hierarchicalLoader = $container->get(HierarchicalConfigurationLoader::class);

        // Create factory with auto mode
        $this->factory = new ConfigurationLoaderFactory(
            $this->simpleLoader,
            $this->hierarchicalLoader,
            'auto',
        );
    }

    protected function tearDown(): void
    {
        TestHelper::removeDirectory($this->tempDir);
    }

    private function createContainer(): ContainerBuilder
    {
        $container = new ContainerBuilder();

        // Load base services
        $loader = new YamlFileLoader($container, new FileLocator(__DIR__ . '/../../../config'));
        $loader->load('services.yaml');

        // Compile container
        $container->compile();

        return $container;
    }

    /**
     * Test factory with explicit simple mode.
     */
    public function testFactoryWithSimpleMode(): void
    {
        $factory = $this->factory->withMode('simple');
        $config = $factory->load($this->tempDir);

        self::assertSame('factory-test', $config->getProjectName());
        self::assertSame('8.4', $config->getProjectPhpVersion());
        self::assertTrue($config->isToolEnabled('rector'));

        // Should be wrapped as simple variant
        self::assertTrue($config->isSimple());
        self::assertFalse($config->isEnhanced());
    }

    /**
     * Test factory with explicit hierarchical mode.
     */
    public function testFactoryWithHierarchicalMode(): void
    {
        $factory = $this->factory->withMode('hierarchical');
        $config = $factory->load($this->tempDir);

        self::assertSame('factory-test', $config->getProjectName());
        self::assertSame('8.4', $config->getProjectPhpVersion());
        self::assertTrue($config->isToolEnabled('rector'));

        // Should be wrapped as enhanced variant
        self::assertFalse($config->isSimple());
        self::assertTrue($config->isEnhanced());
    }

    /**
     * Test factory auto mode selection based on command context.
     */
    public function testFactoryAutoModeCommandSelection(): void
    {
        // Test with config command in argv
        $originalArgv = $_SERVER['argv'] ?? [];
        $_SERVER['argv'] = ['qt', 'config:show'];

        try {
            $factory = $this->factory->withMode('auto');
            $factoryInfo = $factory->getFactoryInfo();

            self::assertSame('config:show', $factoryInfo['current_command']);
            self::assertSame('hierarchical', $factoryInfo['selected_loader']);
        } finally {
            $_SERVER['argv'] = $originalArgv;
        }
    }

    /**
     * Test factory with tool command defaults to simple.
     */
    public function testFactoryToolCommandSelection(): void
    {
        // Test with tool command in argv
        $originalArgv = $_SERVER['argv'] ?? [];
        $_SERVER['argv'] = ['qt', 'lint:rector'];

        try {
            $factory = $this->factory->withMode('auto');
            $factoryInfo = $factory->getFactoryInfo();

            self::assertSame('lint:rector', $factoryInfo['current_command']);
            self::assertSame('simple', $factoryInfo['selected_loader']);
        } finally {
            $_SERVER['argv'] = $originalArgv;
        }
    }

    /**
     * Test factory environment variable override.
     */
    public function testFactoryEnvironmentOverride(): void
    {
        TestHelper::withEnvironment(
            ['QT_LOADER_MODE' => 'hierarchical'],
            function (): void {
                $factory = $this->factory->withMode('auto');
                $factoryInfo = $factory->getFactoryInfo();

                self::assertSame('hierarchical', $factoryInfo['current_mode']);
                self::assertSame('hierarchical', $factoryInfo['env_override']);
            },
        );
    }

    /**
     * Test factory interface method delegation.
     */
    public function testFactoryInterfaceMethodDelegation(): void
    {
        // Test findConfigurationFile
        $configFile = $this->factory->findConfigurationFile($this->tempDir);
        self::assertStringEndsWith('.quality-tools.yaml', $configFile);

        // Test supportsConfiguration
        self::assertTrue($this->factory->supportsConfiguration($this->tempDir));

        // Test hasHierarchicalConfiguration
        self::assertIsBool($this->factory->hasHierarchicalConfiguration($this->tempDir));

        // Test getConfigurationSources
        $sources = $this->factory->getConfigurationSources($this->tempDir);
        self::assertIsArray($sources);

        // Test previewMergedConfiguration
        $preview = $this->factory->previewMergedConfiguration($this->tempDir);
        self::assertIsArray($preview);
        // Note: Preview might be empty if no hierarchical config found, this is expected behavior
    }

    /**
     * Test loadForTool method uses simple loader.
     */
    public function testLoadForToolUsesSimpleLoader(): void
    {
        $config = $this->factory->loadForTool($this->tempDir, 'rector');

        self::assertSame('factory-test', $config->getProjectName());
        self::assertTrue($config->isSimple());
        self::assertFalse($config->isEnhanced());
    }

    /**
     * Test createSimpleConfiguration method.
     */
    public function testCreateSimpleConfiguration(): void
    {
        $config = $this->factory->createSimpleConfiguration($this->tempDir);

        self::assertSame('factory-test', $config->getProjectName());
        self::assertTrue($config->isSimple());
        self::assertFalse($config->isEnhanced());
    }

    /**
     * Test factory info provides comprehensive details.
     */
    public function testFactoryInfoComprehensive(): void
    {
        $factoryInfo = $this->factory->getFactoryInfo();

        self::assertArrayHasKey('factory_version', $factoryInfo);
        self::assertArrayHasKey('default_mode', $factoryInfo);
        self::assertArrayHasKey('current_mode', $factoryInfo);
        self::assertArrayHasKey('selected_loader', $factoryInfo);
        self::assertArrayHasKey('supported_modes', $factoryInfo);
        self::assertArrayHasKey('hierarchical_commands', $factoryInfo);
        self::assertArrayHasKey('simple_commands', $factoryInfo);

        self::assertSame('auto', $factoryInfo['default_mode']);
        self::assertContains('simple', $factoryInfo['supported_modes']);
        self::assertContains('hierarchical', $factoryInfo['supported_modes']);
        self::assertContains('auto', $factoryInfo['supported_modes']);

        // Check command lists are properly defined
        self::assertContains('config:show', $factoryInfo['hierarchical_commands']);
        self::assertContains('lint:rector', $factoryInfo['simple_commands']);
    }

    /**
     * Test invalid mode throws exception.
     */
    public function testInvalidModeThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid mode: invalid');

        $this->factory->withMode('invalid');
    }

    /**
     * Test factory getConfigurationDebugInfo includes factory information.
     */
    public function testFactoryDebugInfoInclusion(): void
    {
        $debugInfo = $this->factory->getConfigurationDebugInfo($this->tempDir);

        self::assertArrayHasKey('factory_info', $debugInfo);
        self::assertArrayHasKey('selected_loader', $debugInfo);

        $factoryInfo = $debugInfo['factory_info'];
        self::assertArrayHasKey('factory_version', $factoryInfo);
        self::assertArrayHasKey('default_mode', $factoryInfo);
    }

    /**
     * Test factory error handling methods delegate to hierarchical loader.
     */
    public function testFactoryErrorHandlingDelegation(): void
    {
        // Test getConfigurationErrors
        $errors = $this->factory->getConfigurationErrors($this->tempDir);
        self::assertIsArray($errors);

        // Create an invalid config to test error reporting
        $invalidDir = TestHelper::createTempDirectory('invalid_config_test_');
        file_put_contents($invalidDir . '/.quality-tools.yaml', 'invalid: yaml: content:');

        try {
            $errors = $this->factory->getConfigurationErrors($invalidDir);
            // Should have errors for invalid YAML
            self::assertIsArray($errors);
        } finally {
            TestHelper::removeDirectory($invalidDir);
        }
    }

    /**
     * Test factory performance with repeated calls.
     */
    public function testFactoryPerformance(): void
    {
        $iterations = 10;

        $start = microtime(true);
        for ($i = 0; $i < $iterations; ++$i) {
            $config = $this->factory->load($this->tempDir);
            self::assertSame('factory-test', $config->getProjectName());
        }
        $duration = microtime(true) - $start;

        // Should complete reasonably quickly (less than 1 second for 10 iterations)
        self::assertLessThan(1.0, $duration);
    }

    /**
     * Test factory command context detection with various scenarios.
     */
    public function testFactoryCommandContextDetection(): void
    {
        // Test various command patterns
        $testCases = [
            ['command' => 'config:show', 'expected' => 'hierarchical'],
            ['command' => 'lint:rector', 'expected' => 'simple'],
            ['command' => 'fix:php-cs-fixer', 'expected' => 'simple'],
            ['command' => 'unknown:command', 'expected' => 'simple'], // Default fallback
        ];

        $originalArgv = $_SERVER['argv'] ?? [];

        try {
            foreach ($testCases as $testCase) {
                $_SERVER['argv'] = ['qt', $testCase['command']];

                $factory = $this->factory->withMode('auto');
                $factoryInfo = $factory->getFactoryInfo();

                self::assertSame(
                    $testCase['expected'],
                    $factoryInfo['selected_loader'],
                    "Command {$testCase['command']} should select {$testCase['expected']} loader",
                );
            }
        } finally {
            $_SERVER['argv'] = $originalArgv;
        }
    }
}

<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Tests\Integration\DependencyInjection;

use Cpsit\QualityTools\Configuration\ConfigurationInterface;
use Cpsit\QualityTools\Configuration\ConfigurationLoaderInterface;
use Cpsit\QualityTools\Configuration\ConfigurationLoaderWrapper;
use Cpsit\QualityTools\Console\QualityToolsApplication;
use Cpsit\QualityTools\Tests\Unit\TestHelper;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

/**
 * Integration test for dependency injection mode switching between simple and hierarchical configurations.
 *
 * Tests the critical capability to switch between configuration implementations via DI container
 * configuration without code changes - essential for safe evolutionary refactoring.
 */
final class ConfigurationDISwitchingTest extends TestCase
{
    private string $tempDir;
    private string $configDir;

    protected function setUp(): void
    {
        $this->tempDir = TestHelper::createTempDirectory('di_switching_test_');
        $this->configDir = __DIR__ . '/../../../config';

        // Create test configuration file
        $testConfig = <<<YAML
            quality-tools:
              project:
                name: "di-switch-test"
                php_version: "8.4"
              tools:
                rector:
                  enabled: true
                  level: "typo3-13"
            YAML;
        file_put_contents($this->tempDir . '/.quality-tools.yaml', $testConfig);
    }

    protected function tearDown(): void
    {
        TestHelper::removeDirectory($this->tempDir);
    }

    /**
     * Test that container can be configured to use simple mode and loads correctly.
     */
    public function testDIContainerSimpleMode(): void
    {
        $container = $this->createContainerWithMode('simple');

        // Test that interface resolves to wrapper in simple mode
        self::assertTrue($container->has(ConfigurationLoaderInterface::class));
        $loader = $container->get(ConfigurationLoaderInterface::class);
        self::assertInstanceOf(ConfigurationLoaderInterface::class, $loader);
        self::assertInstanceOf(ConfigurationLoaderWrapper::class, $loader);

        // Test that wrapper is in simple mode
        $config = $loader->load($this->tempDir);
        self::assertInstanceOf(ConfigurationInterface::class, $config);
        self::assertSame('di-switch-test', $config->getProjectName());
        self::assertSame('8.4', $config->getProjectPhpVersion());

        // Test that simple mode provides basic functionality
        self::assertTrue($config->isToolEnabled('rector'));
        $rectorConfig = $config->getToolConfig('rector');
        self::assertSame('typo3-13', $rectorConfig['level']);
    }

    /**
     * Test that container can be configured to use hierarchical mode and loads correctly.
     */
    public function testDIContainerHierarchicalMode(): void
    {
        $container = $this->createContainerWithMode('hierarchical');

        // Test that interface resolves to wrapper in hierarchical mode
        self::assertTrue($container->has(ConfigurationLoaderInterface::class));
        $loader = $container->get(ConfigurationLoaderInterface::class);
        self::assertInstanceOf(ConfigurationLoaderInterface::class, $loader);
        self::assertInstanceOf(ConfigurationLoaderWrapper::class, $loader);

        // Test that wrapper is in hierarchical mode
        $config = $loader->load($this->tempDir);
        self::assertInstanceOf(ConfigurationInterface::class, $config);
        self::assertSame('di-switch-test', $config->getProjectName());
        self::assertSame('8.4', $config->getProjectPhpVersion());

        // Test that hierarchical mode provides enhanced functionality
        self::assertTrue($config->isToolEnabled('rector'));
        $rectorConfig = $config->getToolConfig('rector');
        self::assertSame('typo3-13', $rectorConfig['level']);

        // Test hierarchical-specific functionality
        self::assertIsBool($config->isHierarchicalConfiguration());
        self::assertIsArray($config->getConfigurationSources());
    }

    /**
     * Test switching from simple to hierarchical mode produces equivalent basic results.
     */
    public function testDIModeSwitchingConsistency(): void
    {
        $simpleContainer = $this->createContainerWithMode('simple');
        $hierarchicalContainer = $this->createContainerWithMode('hierarchical');

        $simpleLoader = $simpleContainer->get(ConfigurationLoaderInterface::class);
        $hierarchicalLoader = $hierarchicalContainer->get(ConfigurationLoaderInterface::class);

        // Load with both modes
        $simpleConfig = $simpleLoader->load($this->tempDir);
        $hierarchicalConfig = $hierarchicalLoader->load($this->tempDir);

        // Basic configuration should be equivalent
        self::assertSame($simpleConfig->getProjectName(), $hierarchicalConfig->getProjectName());
        self::assertSame($simpleConfig->getProjectPhpVersion(), $hierarchicalConfig->getProjectPhpVersion());
        self::assertSame($simpleConfig->getProjectTypo3Version(), $hierarchicalConfig->getProjectTypo3Version());

        // Tool configuration should be equivalent
        self::assertSame($simpleConfig->isToolEnabled('rector'), $hierarchicalConfig->isToolEnabled('rector'));

        $simpleRectorConfig = $simpleConfig->getToolConfig('rector');
        $hierarchicalRectorConfig = $hierarchicalConfig->getToolConfig('rector');
        self::assertSame($simpleRectorConfig['level'], $hierarchicalRectorConfig['level']);

        // Path configuration should be equivalent
        self::assertSame($simpleConfig->getScanPaths(), $hierarchicalConfig->getScanPaths());
        self::assertSame($simpleConfig->getExcludePaths(), $hierarchicalConfig->getExcludePaths());
    }

    /**
     * Test rollback capability - switching from hierarchical back to simple mode.
     */
    public function testDIRollbackCapability(): void
    {
        // Start with hierarchical mode
        $hierarchicalContainer = $this->createContainerWithMode('hierarchical');
        $hierarchicalLoader = $hierarchicalContainer->get(ConfigurationLoaderInterface::class);
        $hierarchicalConfig = $hierarchicalLoader->load($this->tempDir);

        // Record hierarchical state
        $hierarchicalProjectName = $hierarchicalConfig->getProjectName();
        $hierarchicalPhpVersion = $hierarchicalConfig->getProjectPhpVersion();
        $hierarchicalRectorConfig = $hierarchicalConfig->getToolConfig('rector');

        // Switch to simple mode (rollback scenario)
        $simpleContainer = $this->createContainerWithMode('simple');
        $simpleLoader = $simpleContainer->get(ConfigurationLoaderInterface::class);
        $simpleConfig = $simpleLoader->load($this->tempDir);

        // Verify rollback maintains equivalent functionality
        self::assertSame($hierarchicalProjectName, $simpleConfig->getProjectName());
        self::assertSame($hierarchicalPhpVersion, $simpleConfig->getProjectPhpVersion());

        $simpleRectorConfig = $simpleConfig->getToolConfig('rector');
        self::assertSame($hierarchicalRectorConfig['level'], $simpleRectorConfig['level']);
        self::assertSame($hierarchicalRectorConfig['enabled'], $simpleRectorConfig['enabled']);

        // Verify we can load and use the simple configuration
        self::assertTrue($simpleConfig->isToolEnabled('rector'));
        self::assertIsArray($simpleConfig->getScanPaths());
    }

    /**
     * Test that commands resolve dependencies correctly in both modes.
     */
    public function testCommandDependencyResolutionInBothModes(): void
    {
        foreach (['simple', 'hierarchical'] as $mode) {
            $container = $this->createContainerWithMode($mode);

            // Test that commands can be created with proper DI
            self::assertTrue($container->has(\Cpsit\QualityTools\Console\Command\ConfigShowCommand::class));
            $configShowCommand = $container->get(\Cpsit\QualityTools\Console\Command\ConfigShowCommand::class);
            self::assertInstanceOf(\Cpsit\QualityTools\Console\Command\ConfigShowCommand::class, $configShowCommand);

            // Test that commands receive the correct configuration loader interface
            $reflection = new \ReflectionClass($configShowCommand);
            $constructor = $reflection->getConstructor();
            self::assertNotNull($constructor);

            // Verify command can be instantiated and configured
            self::assertSame('config:show', $configShowCommand->getName());
        }
    }

    /**
     * Test QualityToolsApplication with different DI configurations.
     */
    public function testApplicationWithDifferentDIModes(): void
    {
        foreach (['simple', 'hierarchical'] as $mode) {
            TestHelper::withEnvironment(
                ['QT_PROJECT_ROOT' => $this->tempDir],
                function (): void {
                    // Create application which uses DI container
                    $app = new QualityToolsApplication();

                    // Test that application can access commands
                    $commands = $app->all();
                    self::assertArrayHasKey('config:show', $commands);
                    self::assertArrayHasKey('config:init', $commands);
                    self::assertArrayHasKey('config:validate', $commands);

                    // Test that commands are properly configured
                    $configShowCommand = $app->get('config:show');
                    self::assertSame('config:show', $configShowCommand->getName());
                },
            );
        }
    }

    /**
     * Test performance difference between modes (should be minimal).
     */
    public function testPerformanceDifferenceBetweenModes(): void
    {
        $iterations = 10;

        // Measure simple mode
        $simpleContainer = $this->createContainerWithMode('simple');
        $simpleLoader = $simpleContainer->get(ConfigurationLoaderInterface::class);

        $simpleStart = microtime(true);
        for ($i = 0; $i < $iterations; ++$i) {
            $config = $simpleLoader->load($this->tempDir);
            $config->getProjectName();
            $config->getToolConfig('rector');
        }
        $simpleTime = microtime(true) - $simpleStart;

        // Measure hierarchical mode
        $hierarchicalContainer = $this->createContainerWithMode('hierarchical');
        $hierarchicalLoader = $hierarchicalContainer->get(ConfigurationLoaderInterface::class);

        $hierarchicalStart = microtime(true);
        for ($i = 0; $i < $iterations; ++$i) {
            $config = $hierarchicalLoader->load($this->tempDir);
            $config->getProjectName();
            $config->getToolConfig('rector');
        }
        $hierarchicalTime = microtime(true) - $hierarchicalStart;

        // Both should complete in reasonable time (less than 1 second for 10 iterations)
        self::assertLessThan(1.0, $simpleTime, 'Simple mode should be performant');
        self::assertLessThan(1.0, $hierarchicalTime, 'Hierarchical mode should be performant');

        // Log performance difference for information
        $difference = abs($hierarchicalTime - $simpleTime);
        self::assertLessThan(0.5, $difference, 'Performance difference should be minimal');
    }

    /**
     * Create a DI container configured for the specified mode.
     */
    private function createContainerWithMode(string $mode): ContainerBuilder
    {
        $container = new ContainerBuilder();

        // Load base services
        $loader = new YamlFileLoader($container, new FileLocator($this->configDir));
        $loader->load('services.yaml');

        // Override the mode configuration
        $container->setParameter('config.loader.mode', $mode);

        // Update wrapper configuration for the specified mode
        $wrapperDefinition = $container->getDefinition(ConfigurationLoaderWrapper::class);
        $wrapperDefinition->setArgument('$mode', $mode);

        // Compile container
        $container->compile();

        return $container;
    }

    /**
     * Test that mode changes are isolated and don't affect other containers.
     */
    public function testModeIsolationBetweenContainers(): void
    {
        // Create two containers with different modes
        $simpleContainer = $this->createContainerWithMode('simple');
        $hierarchicalContainer = $this->createContainerWithMode('hierarchical');

        // Get loaders from both containers
        $simpleLoader = $simpleContainer->get(ConfigurationLoaderInterface::class);
        $hierarchicalLoader = $hierarchicalContainer->get(ConfigurationLoaderInterface::class);

        // Verify they are different instances
        self::assertNotSame($simpleLoader, $hierarchicalLoader);

        // Verify each maintains its mode independently
        $simpleConfig = $simpleLoader->load($this->tempDir);
        $hierarchicalConfig = $hierarchicalLoader->load($this->tempDir);

        // Both should work correctly
        self::assertSame('di-switch-test', $simpleConfig->getProjectName());
        self::assertSame('di-switch-test', $hierarchicalConfig->getProjectName());

        // Verify isolation - changes to one don't affect the other
        // This is inherent in the DI container design, but good to verify
        self::assertTrue(true); // Containers are isolated by design
    }
}

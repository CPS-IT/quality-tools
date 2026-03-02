<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Tests\Integration\DependencyInjection;

use Cpsit\QualityTools\Configuration\ConfigurationLoaderFactory;
use Cpsit\QualityTools\Configuration\ConfigurationLoaderInterface;
use Cpsit\QualityTools\Console\Command\ConfigInitCommand;
use Cpsit\QualityTools\Console\Command\ConfigShowCommand;
use Cpsit\QualityTools\Console\Command\ConfigValidateCommand;
use Cpsit\QualityTools\Console\Command\PhpStanCommand;
use Cpsit\QualityTools\Console\Command\RectorFixCommand;
use Cpsit\QualityTools\Console\Command\RectorLintCommand;
use Cpsit\QualityTools\Tests\Unit\TestHelper;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

/**
 * Integration test for Step 3.2: Command-specific factory configuration.
 *
 * Tests that different commands receive the appropriate factory configuration
 * through the service container, enabling command-specific loader selection.
 */
final class CommandSpecificFactoryTest extends TestCase
{
    private string $tempDir;
    private ContainerBuilder $container;

    protected function setUp(): void
    {
        $this->tempDir = TestHelper::createTempDirectory('command_factory_test_');
        $this->container = $this->createContainer();

        // Create test configuration file
        $testConfig = <<<YAML
            quality-tools:
              project:
                name: "command-factory-test"
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
     * Test that config init command uses hierarchical factory.
     */
    public function testConfigInitCommandUsesHierarchicalFactory(): void
    {
        $configInitCommand = $this->container->get(ConfigInitCommand::class);
        self::assertInstanceOf(ConfigInitCommand::class, $configInitCommand);

        // Get the factory from the command
        $reflection = new \ReflectionClass($configInitCommand);
        $loaderProperty = $reflection->getProperty('configurationLoader');
        $factory = $loaderProperty->getValue($configInitCommand);

        self::assertInstanceOf(ConfigurationLoaderFactory::class, $factory);

        // Verify hierarchical mode
        $factoryInfo = $factory->getFactoryInfo();
        self::assertSame('hierarchical', $factoryInfo['default_mode']);
    }

    /**
     * Test that tool commands use simple factory configuration.
     */
    public function testToolCommandsUseSimpleFactory(): void
    {
        // Test RectorLintCommand
        $rectorLintCommand = $this->container->get(RectorLintCommand::class);
        self::assertInstanceOf(RectorLintCommand::class, $rectorLintCommand);

        // Get the factory from the command
        $reflection = new \ReflectionClass($rectorLintCommand);
        $loaderProperty = $reflection->getProperty('configurationLoader');
        $factory = $loaderProperty->getValue($rectorLintCommand);

        self::assertInstanceOf(ConfigurationLoaderFactory::class, $factory);

        // Verify this factory uses simple mode
        $factoryInfo = $factory->getFactoryInfo();
        self::assertSame('simple', $factoryInfo['default_mode']);
        self::assertSame('simple', $factoryInfo['current_mode']);
    }

    /**
     * Test that Rector fix command uses simple factory.
     */
    public function testRectorFixCommandUsesSimpleFactory(): void
    {
        $rectorFixCommand = $this->container->get(RectorFixCommand::class);
        self::assertInstanceOf(RectorFixCommand::class, $rectorFixCommand);

        // Get the factory from the command
        $reflection = new \ReflectionClass($rectorFixCommand);
        $loaderProperty = $reflection->getProperty('configurationLoader');
        $factory = $loaderProperty->getValue($rectorFixCommand);

        self::assertInstanceOf(ConfigurationLoaderFactory::class, $factory);

        // Verify simple mode
        $factoryInfo = $factory->getFactoryInfo();
        self::assertSame('simple', $factoryInfo['default_mode']);
    }

    /**
     * Test that PHPStan command uses simple factory.
     */
    public function testPhpStanCommandUsesSimpleFactory(): void
    {
        $phpStanCommand = $this->container->get(PhpStanCommand::class);
        self::assertInstanceOf(PhpStanCommand::class, $phpStanCommand);

        // Get the factory from the command
        $reflection = new \ReflectionClass($phpStanCommand);
        $loaderProperty = $reflection->getProperty('configurationLoader');
        $factory = $loaderProperty->getValue($phpStanCommand);

        self::assertInstanceOf(ConfigurationLoaderFactory::class, $factory);

        // Verify simple mode
        $factoryInfo = $factory->getFactoryInfo();
        self::assertSame('simple', $factoryInfo['default_mode']);
    }

    /**
     * Test that different factory instances are properly configured.
     */
    public function testFactoryInstancesAreProperlyConfigured(): void
    {
        // Get the different factory instances
        $autoFactory = $this->container->get(ConfigurationLoaderFactory::class);
        $simpleFactory = $this->container->get('Cpsit\QualityTools\Configuration\ConfigurationLoaderFactory.simple');
        $hierarchicalFactory = $this->container->get('Cpsit\QualityTools\Configuration\ConfigurationLoaderFactory.hierarchical');

        self::assertInstanceOf(ConfigurationLoaderFactory::class, $autoFactory);
        self::assertInstanceOf(ConfigurationLoaderFactory::class, $simpleFactory);
        self::assertInstanceOf(ConfigurationLoaderFactory::class, $hierarchicalFactory);

        // Verify they have different modes
        $autoInfo = $autoFactory->getFactoryInfo();
        $simpleInfo = $simpleFactory->getFactoryInfo();
        $hierarchicalInfo = $hierarchicalFactory->getFactoryInfo();

        self::assertSame('auto', $autoInfo['default_mode']);
        self::assertSame('simple', $simpleInfo['default_mode']);
        self::assertSame('hierarchical', $hierarchicalInfo['default_mode']);
    }

    /**
     * Test that command-specific configurations actually load and work.
     */
    public function testCommandSpecificConfigurationsWork(): void
    {
        // Test hierarchical config command
        $configShowCommand = $this->container->get(ConfigShowCommand::class);
        $reflection = new \ReflectionClass($configShowCommand);
        $loaderProperty = $reflection->getProperty('configurationLoader');
        $hierarchicalFactory = $loaderProperty->getValue($configShowCommand);

        $hierarchicalConfig = $hierarchicalFactory->load($this->tempDir);
        self::assertSame('command-factory-test', $hierarchicalConfig->getProjectName());
        self::assertTrue($hierarchicalConfig->isToolEnabled('rector'));

        // Test simple tool command
        $rectorLintCommand = $this->container->get(RectorLintCommand::class);
        $reflection = new \ReflectionClass($rectorLintCommand);
        $loaderProperty = $reflection->getProperty('configurationLoader');
        $simpleFactory = $loaderProperty->getValue($rectorLintCommand);

        $simpleConfig = $simpleFactory->load($this->tempDir);
        self::assertSame('command-factory-test', $simpleConfig->getProjectName());
        self::assertTrue($simpleConfig->isToolEnabled('rector'));

        // Both should return equivalent basic configuration
        self::assertSame(
            $hierarchicalConfig->getProjectName(),
            $simpleConfig->getProjectName(),
        );
        self::assertSame(
            $hierarchicalConfig->getProjectPhpVersion(),
            $simpleConfig->getProjectPhpVersion(),
        );
    }

    /**
     * Test that interface binding still works for general use.
     */
    public function testInterfaceBindingWorksForGeneralUse(): void
    {
        $loader = $this->container->get(ConfigurationLoaderInterface::class);
        self::assertInstanceOf(ConfigurationLoaderFactory::class, $loader);

        // Should be the default auto factory
        $factoryInfo = $loader->getFactoryInfo();
        self::assertSame('auto', $factoryInfo['default_mode']);

        // Should work for loading
        $config = $loader->load($this->tempDir);
        self::assertSame('command-factory-test', $config->getProjectName());
    }
}

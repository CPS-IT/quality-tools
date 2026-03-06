<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Tests\Integration\Console\Command;

use Cpsit\QualityTools\Configuration\ConfigurationLoaderInterface;
use Cpsit\QualityTools\Console\Command\AbstractToolCommand;
use Cpsit\QualityTools\Console\QualityToolsApplication;
use Cpsit\QualityTools\Tests\Unit\TestHelper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Tests that AbstractToolCommand resolves config_file from .quality-tools.yaml.
 *
 * The fix is in ConfigurationDiscovery, which now reads config_file
 * properties from YAML configurations. AbstractToolCommand delegates
 * to ConfigurationDiscovery via discoverToolConfigurationFile().
 */
#[CoversClass(AbstractToolCommand::class)]
final class AbstractToolCommandConfigFileTest extends TestCase
{
    private string $tempProjectRoot;
    private QualityToolsApplication $application;

    protected function setUp(): void
    {
        $this->tempProjectRoot = TestHelper::createTempDirectory('tool_config_file_test_');
        $this->setupProjectStructure();

        TestHelper::withEnvironment(
            ['QT_PROJECT_ROOT' => $this->tempProjectRoot],
            function (): void {
                $this->application = new QualityToolsApplication();
            },
        );
    }

    protected function tearDown(): void
    {
        TestHelper::removeDirectory($this->tempProjectRoot);
    }

    private function setupProjectStructure(): void
    {
        TestHelper::createComposerJson($this->tempProjectRoot, TestHelper::getComposerContent('typo3-core'));

        // Create vendor structure with package defaults
        $configDir = $this->tempProjectRoot . '/vendor/cpsit/quality-tools/config';
        mkdir($configDir, 0o777, true);
        file_put_contents($configDir . '/rector.php', '<?php return ["default" => true];');

        mkdir($this->tempProjectRoot . '/packages', 0o777, true);
    }

    /**
     * When .quality-tools.yaml sets config_file for a tool, resolveConfigPath
     * must use that path instead of falling through to package defaults.
     */
    #[Test]
    public function resolveConfigPathUsesConfigFileFromYaml(): void
    {
        // Create .quality-tools.yaml with config_file setting
        $yamlContent = <<<'YAML'
            quality-tools:
              tools:
                rector:
                  enabled: true
                  config_file: "custom/rector.php"
            YAML;
        file_put_contents($this->tempProjectRoot . '/.quality-tools.yaml', $yamlContent);

        // Create the custom config file
        $customDir = $this->tempProjectRoot . '/custom';
        mkdir($customDir, 0o777, true);
        $customConfigPath = $customDir . '/rector.php';
        file_put_contents($customConfigPath, '<?php return ["custom" => true];');

        // Use a real ConfigurationLoader -- the fix is in ConfigurationDiscovery
        $mockLoader = $this->createMock(ConfigurationLoaderInterface::class);
        $command = new ConfigFileTestToolCommand($mockLoader);
        $this->application->add($command);

        $resolvedPath = $command->testResolveConfigPath('rector.php');

        self::assertSame(
            basename($customConfigPath),
            basename($resolvedPath),
            'resolveConfigPath must use config_file from .quality-tools.yaml'
        );
        self::assertSame(
            file_get_contents($customConfigPath),
            file_get_contents($resolvedPath),
            'Resolved config must be the custom file, not the package default'
        );
    }

    /**
     * CLI --config option must still override config_file from YAML.
     */
    #[Test]
    public function cliConfigOptionOverridesYamlConfigFile(): void
    {
        // Create .quality-tools.yaml with config_file setting
        $yamlContent = <<<'YAML'
            quality-tools:
              tools:
                rector:
                  enabled: true
                  config_file: "custom/rector.php"
            YAML;
        file_put_contents($this->tempProjectRoot . '/.quality-tools.yaml', $yamlContent);

        // Create both YAML-configured and CLI-configured config files
        $customDir = $this->tempProjectRoot . '/custom';
        mkdir($customDir, 0o777, true);
        file_put_contents($customDir . '/rector.php', '<?php return ["yaml" => true];');

        $cliConfigPath = $this->tempProjectRoot . '/cli-rector.php';
        file_put_contents($cliConfigPath, '<?php return ["cli" => true];');

        $mockLoader = $this->createMock(ConfigurationLoaderInterface::class);
        $command = new ConfigFileTestToolCommand($mockLoader);
        $this->application->add($command);

        // Pass --config via CLI
        $resolvedPath = $command->testResolveConfigPath('rector.php', $cliConfigPath);

        self::assertSame(
            basename($cliConfigPath),
            basename($resolvedPath),
            'CLI --config must override config_file from YAML'
        );
        self::assertSame(
            file_get_contents($cliConfigPath),
            file_get_contents($resolvedPath),
            'Resolved config must be the CLI file, not the YAML config_file'
        );
    }

    /**
     * When config_file in YAML points to a non-existent file, the command
     * must fall through to auto-discovery or package defaults.
     */
    #[Test]
    public function invalidYamlConfigFileFallsBackToDefaults(): void
    {
        // Create .quality-tools.yaml with a non-existent config_file
        $yamlContent = <<<'YAML'
            quality-tools:
              tools:
                rector:
                  enabled: true
                  config_file: "non-existent/rector.php"
            YAML;
        file_put_contents($this->tempProjectRoot . '/.quality-tools.yaml', $yamlContent);

        $mockLoader = $this->createMock(ConfigurationLoaderInterface::class);
        $command = new ConfigFileTestToolCommand($mockLoader);
        $this->application->add($command);

        $resolvedPath = $command->testResolveConfigPath('rector.php');

        // Should fall back to package default
        $expectedDefault = $this->tempProjectRoot . '/vendor/cpsit/quality-tools/config/rector.php';
        self::assertSame(
            file_get_contents($expectedDefault),
            file_get_contents($resolvedPath),
            'Invalid YAML config_file must fall back to package defaults'
        );
    }

    /**
     * YAML config_file takes precedence over auto-discovered files.
     */
    #[Test]
    public function yamlConfigFileOverridesAutoDiscoveredFile(): void
    {
        // Create .quality-tools.yaml pointing to custom location
        $yamlContent = <<<'YAML'
            quality-tools:
              tools:
                rector:
                  enabled: true
                  config_file: "custom/rector.php"
            YAML;
        file_put_contents($this->tempProjectRoot . '/.quality-tools.yaml', $yamlContent);

        // Create an auto-discoverable rector.php in project root
        file_put_contents(
            $this->tempProjectRoot . '/rector.php',
            '<?php return ["auto-discovered" => true];',
        );

        // Create the YAML-configured custom config
        $customDir = $this->tempProjectRoot . '/custom';
        mkdir($customDir, 0o777, true);
        file_put_contents($customDir . '/rector.php', '<?php return ["yaml-configured" => true];');

        $mockLoader = $this->createMock(ConfigurationLoaderInterface::class);
        $command = new ConfigFileTestToolCommand($mockLoader);
        $this->application->add($command);

        $resolvedPath = $command->testResolveConfigPath('rector.php');

        self::assertSame(
            '<?php return ["yaml-configured" => true];',
            file_get_contents($resolvedPath),
            'YAML config_file must take precedence over auto-discovered rector.php'
        );
    }
}

/**
 * Test command that exposes resolveConfigPath for testing.
 */
final class ConfigFileTestToolCommand extends AbstractToolCommand
{
    public function __construct(ConfigurationLoaderInterface $loader)
    {
        parent::__construct($loader);
        $this->setDescription('Test tool command for config_file YAML testing');
    }

    public function getToolName(): string
    {
        return 'rector';
    }

    protected function getDefaultConfigFileName(): string
    {
        return 'rector.php';
    }

    protected function buildToolCommand(
        InputInterface $input,
        OutputInterface $output,
        string $configPath,
        array $targetPaths,
    ): array {
        return ['echo', 'test'];
    }

    #[\Override]
    protected function configure(): void
    {
        $this->setName('test:config-file');
        parent::configure();
    }

    public function testResolveConfigPath(string $configFile, ?string $customConfigPath = null): string
    {
        return $this->resolveConfigPath($configFile, $customConfigPath);
    }
}

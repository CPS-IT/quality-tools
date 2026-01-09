<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Tests\Unit\Console\Command;

use Cpsit\QualityTools\Console\Command\ConfigShowCommand;
use Cpsit\QualityTools\Console\QualityToolsApplication;
use Cpsit\QualityTools\Tests\Unit\TestHelper;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @covers \Cpsit\QualityTools\Console\Command\ConfigShowCommand
 */
final class ConfigShowCommandTest extends TestCase
{
    private ConfigShowCommand $command;
    private CommandTester $commandTester;
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = TestHelper::createTempDirectory('config_show_test_');

        // Set up command with application
        TestHelper::withEnvironment(
            ['QT_PROJECT_ROOT' => $this->tempDir],
            function (): void {
                $app = new QualityToolsApplication();
                $this->command = new ConfigShowCommand();
                $this->command->setApplication($app);
                $this->commandTester = new CommandTester($this->command);
            },
        );
    }

    protected function tearDown(): void
    {
        TestHelper::removeDirectory($this->tempDir);
    }

    public function testConfigureCommand(): void
    {
        self::assertSame('config:show', $this->command->getName());
        self::assertSame('Show resolved configuration', $this->command->getDescription());
        self::assertStringContainsString('shows the resolved configuration after merging all sources', $this->command->getHelp());

        // Check format option
        $definition = $this->command->getDefinition();
        self::assertTrue($definition->hasOption('format'));

        $formatOption = $definition->getOption('format');
        self::assertSame('yaml', $formatOption->getDefault());
    }

    public function testExecuteWithDefaultFormat(): void
    {
        $exitCode = $this->commandTester->execute([]);

        self::assertSame(Command::SUCCESS, $exitCode);

        $output = $this->commandTester->getDisplay();
        self::assertStringContainsString('Resolved Configuration', $output);
        self::assertStringContainsString('quality-tools:', $output);

        // Should be in YAML format by default
        self::assertStringContainsString('project:', $output);
        self::assertStringContainsString('php_version: ', $output);
        self::assertStringContainsString('typo3_version: ', $output);
    }

    public function testExecuteWithYamlFormat(): void
    {
        $config = <<<YAML
            quality-tools:
              project:
                name: "test-project"
                php_version: "8.4"
              tools:
                rector:
                  enabled: false
            YAML;

        file_put_contents($this->tempDir . '/.quality-tools.yaml', $config);

        $exitCode = $this->commandTester->execute(['--format' => 'yaml']);

        self::assertSame(Command::SUCCESS, $exitCode);

        $output = $this->commandTester->getDisplay();
        self::assertStringContainsString('Resolved Configuration', $output);
        self::assertStringContainsString('quality-tools:', $output);
        self::assertStringContainsString('name: test-project', $output);
        self::assertStringContainsString('php_version: ', $output);
        self::assertStringContainsString('8.4', $output);
    }

    public function testExecuteWithJsonFormat(): void
    {
        $config = <<<YAML
            quality-tools:
              project:
                name: "test-project"
                php_version: "8.4"
              tools:
                rector:
                  enabled: false
            YAML;

        file_put_contents($this->tempDir . '/.quality-tools.yaml', $config);

        $exitCode = $this->commandTester->execute(['--format' => 'json']);

        self::assertSame(Command::SUCCESS, $exitCode);

        $output = $this->commandTester->getDisplay();
        self::assertStringContainsString('Resolved Configuration', $output);

        // Should contain valid JSON
        $outputLines = explode("\n", $output);
        $jsonOutput = '';
        $foundJson = false;

        foreach ($outputLines as $line) {
            if (str_starts_with($line, '{')) {
                $foundJson = true;
            }
            if ($foundJson) {
                $jsonOutput .= $line . "\n";
            }
        }

        $jsonOutput = trim($jsonOutput);
        self::assertNotEmpty($jsonOutput);

        $decoded = json_decode($jsonOutput, true);
        self::assertNotNull($decoded, 'Output should be valid JSON');
        self::assertArrayHasKey('quality-tools', $decoded);
        self::assertArrayHasKey('project', $decoded['quality-tools']);
        self::assertSame('test-project', $decoded['quality-tools']['project']['name']);
        self::assertSame('8.4', $decoded['quality-tools']['project']['php_version']);
    }

    public function testExecuteWithInvalidFormat(): void
    {
        $exitCode = $this->commandTester->execute(['--format' => 'xml']);

        self::assertSame(Command::FAILURE, $exitCode);

        $output = $this->commandTester->getDisplay();
        self::assertStringContainsString('Format must be either "yaml" or "json"', $output);
    }

    public function testExecuteWithVerboseShowsSources(): void
    {
        $homeDir = $this->tempDir . '/home';
        mkdir($homeDir, 0o777, true);

        // Create global configuration
        $globalConfig = <<<YAML
            quality-tools:
              project:
                php_version: "8.4"
            YAML;
        file_put_contents($homeDir . '/.quality-tools.yaml', $globalConfig);

        // Create project configuration
        $projectConfig = <<<YAML
            quality-tools:
              project:
                name: "test-project"
            YAML;
        file_put_contents($this->tempDir . '/.quality-tools.yaml', $projectConfig);

        $exitCode = TestHelper::withEnvironment(
            ['HOME' => $homeDir],
            fn (): int => $this->commandTester->execute([], ['verbosity' => OutputInterface::VERBOSITY_VERBOSE]),
        );

        self::assertSame(Command::SUCCESS, $exitCode);

        $output = $this->commandTester->getDisplay();
        self::assertStringContainsString('Configuration Sources', $output);
        self::assertStringContainsString('Global:', $output);
        self::assertStringContainsString('Project:', $output);
        self::assertStringContainsString('Package defaults', $output);
        self::assertStringContainsString($homeDir . '/.quality-tools.yaml', $output);
        self::assertStringContainsString($this->tempDir . '/.quality-tools.yaml', $output);
    }

    public function testExecuteWithVerboseNoGlobalConfig(): void
    {
        $projectConfig = <<<YAML
            quality-tools:
              project:
                name: "test-project"
            YAML;
        file_put_contents($this->tempDir . '/.quality-tools.yaml', $projectConfig);

        $exitCode = TestHelper::withEnvironment(
            ['HOME' => '/nonexistent'],
            fn (): int => $this->commandTester->execute([], ['verbosity' => OutputInterface::VERBOSITY_VERBOSE]),
        );

        self::assertSame(Command::SUCCESS, $exitCode);

        $output = $this->commandTester->getDisplay();
        self::assertStringContainsString('Configuration Sources', $output);
        self::assertStringContainsString('Project:', $output);
        self::assertStringContainsString('Package defaults', $output);
        self::assertStringNotContainsString('Global:', $output);
    }

    public function testExecuteWithVerboseNoProjectConfig(): void
    {
        $exitCode = $this->commandTester->execute([], ['verbosity' => OutputInterface::VERBOSITY_VERBOSE]);

        self::assertSame(Command::SUCCESS, $exitCode);

        $output = $this->commandTester->getDisplay();
        self::assertStringContainsString('Configuration Sources', $output);
        self::assertStringContainsString('Package defaults', $output);
        self::assertStringNotContainsString('Project:', $output);
        self::assertStringNotContainsString('Global:', $output);
    }

    public function testExecuteWithEnvironmentVariables(): void
    {
        $configWithEnvVars = <<<YAML
            quality-tools:
              project:
                name: "\${PROJECT_NAME:-default-project}"
                php_version: "\${PHP_VERSION:-8.3}"
              tools:
                phpstan:
                  memory_limit: "\${MEMORY_LIMIT:-1G}"
            YAML;

        file_put_contents($this->tempDir . '/.quality-tools.yaml', $configWithEnvVars);

        $exitCode = TestHelper::withEnvironment([
            'PROJECT_NAME' => 'env-test',
            'MEMORY_LIMIT' => '2G',
            // PHP_VERSION not set, should use default
        ], fn (): int => $this->commandTester->execute(['--format' => 'yaml']));

        self::assertSame(Command::SUCCESS, $exitCode);

        $output = $this->commandTester->getDisplay();
        self::assertStringContainsString('name: env-test', $output);
        self::assertStringContainsString('php_version: ', $output);
        self::assertStringContainsString('8.3', $output); // default
        self::assertStringContainsString('memory_limit: ', $output);
        self::assertStringContainsString('2G', $output);
    }

    public function testExecuteWithComplexMerging(): void
    {
        $homeDir = $this->tempDir . '/home';
        mkdir($homeDir, 0o777, true);

        // Global config sets some defaults
        $globalConfig = <<<YAML
            quality-tools:
              project:
                php_version: "8.4"
              tools:
                rector:
                  enabled: true
                  level: "typo3-12"
                phpstan:
                  level: 5
                  memory_limit: "512M"
              output:
                colors: false
            YAML;
        file_put_contents($homeDir . '/.quality-tools.yaml', $globalConfig);

        // Project config overrides some settings
        $projectConfig = <<<YAML
            quality-tools:
              project:
                name: "merge-test"
              tools:
                rector:
                  level: "typo3-13"
                phpstan:
                  level: 8
              output:
                verbosity: "verbose"
            YAML;
        file_put_contents($this->tempDir . '/.quality-tools.yaml', $projectConfig);

        $exitCode = TestHelper::withEnvironment(
            ['HOME' => $homeDir],
            fn (): int => $this->commandTester->execute(['--format' => 'json']),
        );

        self::assertSame(Command::SUCCESS, $exitCode);

        $output = $this->commandTester->getDisplay();

        // Extract and parse JSON
        $outputLines = explode("\n", $output);
        $jsonOutput = '';
        $foundJson = false;

        foreach ($outputLines as $line) {
            if (str_starts_with($line, '{')) {
                $foundJson = true;
            }
            if ($foundJson) {
                $jsonOutput .= $line . "\n";
            }
        }

        $config = json_decode(trim($jsonOutput), true);

        // Verify merged configuration
        self::assertSame('merge-test', $config['quality-tools']['project']['name']); // from project
        self::assertSame('8.4', $config['quality-tools']['project']['php_version']); // from global

        // Rector config should be merged
        self::assertTrue($config['quality-tools']['tools']['rector']['enabled']); // from global
        self::assertSame('typo3-13', $config['quality-tools']['tools']['rector']['level']); // from project (override)

        // PHPStan config should be merged
        self::assertSame(8, $config['quality-tools']['tools']['phpstan']['level']); // from project (override)
        self::assertSame('512M', $config['quality-tools']['tools']['phpstan']['memory_limit']); // from global

        // Output config should be merged
        self::assertFalse($config['quality-tools']['output']['colors']); // from global
        self::assertSame('verbose', $config['quality-tools']['output']['verbosity']); // from project
    }

    public function testExecuteWithLoadError(): void
    {
        $invalidYaml = <<<YAML
            quality-tools:
              project:
                name: "\${MISSING_ENV_VAR}"
            YAML;

        file_put_contents($this->tempDir . '/.quality-tools.yaml', $invalidYaml);

        $exitCode = $this->commandTester->execute([]);

        self::assertSame(Command::FAILURE, $exitCode);

        $output = $this->commandTester->getDisplay();
        self::assertStringContainsString('Failed to load configuration', $output);
        self::assertStringContainsString('MISSING_ENV_VAR', $output);
    }

    public function testShortFormatOption(): void
    {
        $exitCode = $this->commandTester->execute(['-f' => 'json']);

        self::assertSame(Command::SUCCESS, $exitCode);

        $output = $this->commandTester->getDisplay();
        self::assertStringContainsString('{', $output); // Should be JSON format
    }

    public function testHelpOutput(): void
    {
        // Test command help directly from command definition instead of executing with --help
        self::assertSame('Show resolved configuration', $this->command->getDescription());
        self::assertStringContainsString('shows the resolved configuration after merging all sources', $this->command->getHelp());

        // Check that format option is properly defined
        $definition = $this->command->getDefinition();
        self::assertTrue($definition->hasOption('format'));

        $formatOption = $definition->getOption('format');
        self::assertSame('f', $formatOption->getShortcut());
        self::assertStringContainsString('yaml, json', $formatOption->getDescription());
    }

    public function testDefaultConfigurationOutput(): void
    {
        // Test with no configuration files - should show defaults
        $exitCode = $this->commandTester->execute([]);

        self::assertSame(Command::SUCCESS, $exitCode);

        $output = $this->commandTester->getDisplay();

        // Should contain default configuration values (YAML format)
        self::assertStringContainsString('php_version: \'8.3\'', $output);
        self::assertStringContainsString('typo3_version: \'13.4\'', $output);
        self::assertStringContainsString('packages/', $output);
        self::assertStringContainsString('config/system/', $output);
        self::assertStringContainsString('enabled: true', $output);
    }

    public function testAllConfigurationFileTypesRecognized(): void
    {
        $configs = [
            '.quality-tools.yaml',
            'quality-tools.yaml',
            'quality-tools.yml',
        ];

        foreach ($configs as $configFile) {
            $testDir = TestHelper::createTempDirectory('config_file_test_');

            $config = <<<YAML
                quality-tools:
                  project:
                    name: "test-{$configFile}"
                YAML;
            file_put_contents($testDir . '/' . $configFile, $config);

            TestHelper::withEnvironment(
                ['QT_PROJECT_ROOT' => $testDir],
                function () use ($configFile): void {
                    $app = new QualityToolsApplication();
                    $command = new ConfigShowCommand();
                    $command->setApplication($app);
                    $commandTester = new CommandTester($command);

                    $exitCode = $commandTester->execute([], ['verbosity' => OutputInterface::VERBOSITY_VERBOSE]);

                    self::assertSame(Command::SUCCESS, $exitCode);

                    $output = $commandTester->getDisplay();
                    self::assertStringContainsString($configFile, $output);
                    self::assertStringContainsString("test-{$configFile}", $output);
                },
            );

            TestHelper::removeDirectory($testDir);
        }
    }
}

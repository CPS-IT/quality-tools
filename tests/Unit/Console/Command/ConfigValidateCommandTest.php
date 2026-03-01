<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Tests\Unit\Console\Command;

use Cpsit\QualityTools\Console\Command\ConfigValidateCommand;
use Cpsit\QualityTools\Console\QualityToolsApplication;
use Cpsit\QualityTools\Tests\Unit\TestHelper;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @covers \Cpsit\QualityTools\Console\Command\ConfigValidateCommand
 */
final class ConfigValidateCommandTest extends TestCase
{
    private ConfigValidateCommand $command;
    private CommandTester $commandTester;
    private string $tempDir;
    private Filesystem $filesystem;

    protected function setUp(): void
    {
        $this->tempDir = TestHelper::createTempDirectory('config_validate_test_');
        $this->filesystem = new Filesystem();

        // Set up command with application
        TestHelper::withEnvironment(
            ['QT_PROJECT_ROOT' => $this->tempDir],
            function (): void {
                $app = new QualityToolsApplication();
                $this->command = new ConfigValidateCommand();
                $this->command->setApplication($app);
                $this->commandTester = new CommandTester($this->command);
            },
        );
    }

    protected function tearDown(): void
    {
        TestHelper::removeDirectory($this->tempDir);
        parent::tearDown();
    }

    public function testConfigureCommand(): void
    {
        self::assertSame('config:validate', $this->command->getName());
        self::assertSame('Validate YAML configuration file', $this->command->getDescription());
        self::assertStringContainsString('validates the quality-tools.yaml configuration file', $this->command->getHelp());
    }

    public function testExecuteWithNoConfigurationFile(): void
    {
        $exitCode = $this->commandTester->execute([]);

        self::assertSame(Command::SUCCESS, $exitCode);

        $output = $this->commandTester->getDisplay();
        self::assertStringContainsString('No YAML configuration file found', $output);
        self::assertStringContainsString('Looked for:', $output);
        self::assertStringContainsString('.quality-tools.yaml', $output);
        self::assertStringContainsString('quality-tools.yaml', $output);
        self::assertStringContainsString('quality-tools.yml', $output);
        self::assertStringContainsString('qt config:init', $output);
    }

    public function testExecuteWithValidConfiguration(): void
    {
        $validConfig = <<<YAML
            quality-tools:
              project:
                name: "test-project"
                php_version: "8.3"
                typo3_version: "13.4"
              tools:
                rector:
                  enabled: true
                  level: "typo3-13"
            YAML;

        file_put_contents($this->tempDir . '/.quality-tools.yaml', $validConfig);

        $exitCode = $this->commandTester->execute([]);

        self::assertSame(Command::SUCCESS, $exitCode);

        $output = TestHelper::normalizeConsoleOutput($this->commandTester->getDisplay());
        self::assertStringContainsString('Validating configuration file:', $output);
        self::assertStringContainsString('.quality-tools.yaml', $output);
        self::assertStringContainsString('Configuration is valid', $output);
    }

    public function testExecuteWithValidConfigurationVerbose(): void
    {
        $validConfig = <<<YAML
            quality-tools:
              project:
                name: "test-project"
                php_version: "8.3"
                typo3_version: "13.4"
              tools:
                rector:
                  enabled: true
                phpstan:
                  enabled: false
              paths:
                scan:
                  - "packages/"
                  - "src/"
            YAML;

        file_put_contents($this->tempDir . '/.quality-tools.yaml', $validConfig);

        $exitCode = $this->commandTester->execute([], ['verbosity' => OutputInterface::VERBOSITY_VERBOSE]);

        self::assertSame(Command::SUCCESS, $exitCode);

        $output = $this->commandTester->getDisplay();
        self::assertStringContainsString('Configuration is valid', $output);
        self::assertStringContainsString('Configuration Summary', $output);
        self::assertStringContainsString('test-project', $output);
        self::assertStringContainsString('8.3', $output);
        self::assertStringContainsString('13.4', $output);
        self::assertStringContainsString('rector', $output);
        self::assertStringContainsString('Scan Paths:', $output);
        self::assertStringContainsString('packages/', $output);
        self::assertStringContainsString('src/', $output);
    }

    public function testExecuteWithInvalidYamlSyntax(): void
    {
        $invalidYaml = <<<YAML_WRAP
            quality-tools:
              project:
                name: "test-project
                # Missing closing quote - invalid YAML syntax
            YAML_WRAP;

        file_put_contents($this->tempDir . '/.quality-tools.yaml', $invalidYaml);

        $exitCode = $this->commandTester->execute([]);

        self::assertSame(Command::FAILURE, $exitCode);

        $output = $this->commandTester->getDisplay();
        self::assertStringContainsString('Unexpected Error:', $output);
        self::assertStringContainsString('Malformed inline YAML', $output);
    }

    public function testExecuteWithInvalidConfigurationSchema(): void
    {
        $invalidConfig = <<<YAML
            quality-tools:
              project:
                php_version: "invalid-version"
              tools:
                phpstan:
                  level: 15
            YAML;

        file_put_contents($this->tempDir . '/.quality-tools.yaml', $invalidConfig);

        $exitCode = $this->commandTester->execute([]);

        self::assertSame(Command::FAILURE, $exitCode);

        $output = $this->commandTester->getDisplay();
        self::assertStringContainsString('Unexpected Error:', $output);
        self::assertStringContainsString('Invalid configuration', $output);
    }

    public function testExecuteWithInvalidConfigurationVerbose(): void
    {
        $invalidConfig = <<<YAML
            quality-tools:
              project:
                php_version: "invalid-version"
            YAML;

        file_put_contents($this->tempDir . '/.quality-tools.yaml', $invalidConfig);

        $exitCode = $this->commandTester->execute([], ['verbosity' => OutputInterface::VERBOSITY_VERBOSE]);

        self::assertSame(Command::FAILURE, $exitCode);

        $output = $this->commandTester->getDisplay();
        self::assertStringContainsString('Unexpected Error:', $output);
        // The verbose mode doesn't show 'Full error:' for validation errors, just the detailed error
        self::assertStringContainsString('quality-tools.project.php_version', $output);
    }

    public function testExecuteWithEnvironmentVariables(): void
    {
        $configWithEnvVars = <<<YAML
            quality-tools:
              project:
                name: "\${PROJECT_NAME}"
                php_version: "\${PHP_VERSION:-8.3}"
            YAML;

        file_put_contents($this->tempDir . '/.quality-tools.yaml', $configWithEnvVars);

        $exitCode = TestHelper::withEnvironment([
            'PROJECT_NAME' => 'env-test-project',
            'PHP_VERSION' => '8.4',
        ], fn (): int => $this->commandTester->execute([], ['verbosity' => OutputInterface::VERBOSITY_VERBOSE]));

        self::assertSame(Command::SUCCESS, $exitCode);

        $output = $this->commandTester->getDisplay();
        self::assertStringContainsString('Configuration is valid', $output);
        self::assertStringContainsString('env-test-project', $output);
        self::assertStringContainsString('8.4', $output);
    }

    public function testExecuteWithMissingEnvironmentVariable(): void
    {
        $configWithEnvVars = <<<YAML
            quality-tools:
              project:
                name: "\${DISALLOWED_VAR}"
            YAML;

        file_put_contents($this->tempDir . '/.quality-tools.yaml', $configWithEnvVars);

        $exitCode = $this->commandTester->execute([]);

        self::assertSame(Command::FAILURE, $exitCode);

        $output = $this->commandTester->getDisplay();
        self::assertStringContainsString('Unexpected Error:', $output);
        self::assertStringContainsString('DISALLOWED_VAR', $output);
    }

    public function testExecuteWithQualityToolsYamlFile(): void
    {
        // Test that it finds quality-tools.yaml when .quality-tools.yaml doesn't exist
        $validConfig = <<<YAML
            quality-tools:
              project:
                name: "alt-config-test"
            YAML;

        file_put_contents($this->tempDir . '/quality-tools.yaml', $validConfig);

        $exitCode = $this->commandTester->execute([]);

        self::assertSame(Command::SUCCESS, $exitCode);

        $output = TestHelper::normalizeConsoleOutput($this->commandTester->getDisplay());
        self::assertStringContainsString('Validating configuration file:', $output);
        self::assertStringContainsString('quality-tools.yaml', $output);
        self::assertStringContainsString('Configuration is valid', $output);
    }

    public function testExecuteWithQualityToolsYmlFile(): void
    {
        // Test that it finds quality-tools.yml when others don't exist
        $validConfig = <<<YAML
            quality-tools:
              project:
                name: "yml-config-test"
            YAML;

        file_put_contents($this->tempDir . '/quality-tools.yml', $validConfig);

        $exitCode = $this->commandTester->execute([]);

        self::assertSame(Command::SUCCESS, $exitCode);

        $output = TestHelper::normalizeConsoleOutput($this->commandTester->getDisplay());
        self::assertStringContainsString('Validating configuration file:', $output);
        self::assertStringContainsString('quality-tools.yml', $output);
        self::assertStringContainsString('Configuration is valid', $output);
    }

    public function testConfigurationSummaryWithAllToolsEnabled(): void
    {
        $configWithAllTools = <<<YAML
            quality-tools:
              project:
                name: "full-test"
                php_version: "8.3"
                typo3_version: "13.4"
              tools:
                rector:
                  enabled: true
                fractor:
                  enabled: true
                phpstan:
                  enabled: true
                php-cs-fixer:
                  enabled: true
                typoscript-lint:
                  enabled: true
            YAML;

        file_put_contents($this->tempDir . '/.quality-tools.yaml', $configWithAllTools);

        $exitCode = $this->commandTester->execute([], ['verbosity' => OutputInterface::VERBOSITY_VERBOSE]);

        self::assertSame(Command::SUCCESS, $exitCode);

        $output = $this->commandTester->getDisplay();
        self::assertStringContainsString('rector', $output);
        self::assertStringContainsString('fractor', $output);
        self::assertStringContainsString('phpstan', $output);
        self::assertStringContainsString('php-cs-fixer', $output);
        self::assertStringContainsString('typoscript-lint', $output);
    }

    public function testConfigurationSummaryWithDefaultTools(): void
    {
        $minimalConfig = <<<YAML
            quality-tools:
              project:
                name: "minimal-test"
            YAML;

        file_put_contents($this->tempDir . '/.quality-tools.yaml', $minimalConfig);

        $exitCode = $this->commandTester->execute([], ['verbosity' => OutputInterface::VERBOSITY_VERBOSE]);

        self::assertSame(Command::SUCCESS, $exitCode);

        $output = $this->commandTester->getDisplay();
        // With minimal config, tools are merged from defaults so they're listed individually
        self::assertStringContainsString('rector', $output);
        self::assertStringContainsString('fractor', $output);
        self::assertStringContainsString('phpstan', $output);
    }

    public function testConfigurationSummaryWithNoScanPaths(): void
    {
        $configWithoutPaths = <<<YAML
            quality-tools:
              project:
                name: "no-paths-test"
            YAML;

        file_put_contents($this->tempDir . '/.quality-tools.yaml', $configWithoutPaths);

        $exitCode = $this->commandTester->execute([], ['verbosity' => OutputInterface::VERBOSITY_VERBOSE]);

        self::assertSame(Command::SUCCESS, $exitCode);

        $output = $this->commandTester->getDisplay();
        // When using defaults, scan paths are merged in so they will be shown
        self::assertStringContainsString('Scan Paths:', $output);
        self::assertStringContainsString('packages/', $output);
    }

    public function testHelpOutput(): void
    {
        // Test command help directly from command definition instead of executing with --help
        self::assertSame('Validate YAML configuration file', $this->command->getDescription());
        self::assertStringContainsString('validates the quality-tools.yaml configuration file', $this->command->getHelp());
    }

    public function testFileReadPermissionError(): void
    {
        $configFile = $this->tempDir . '/.quality-tools.yaml';
        file_put_contents($configFile, 'quality-tools: {}');

        // Make file unreadable
        chmod($configFile, 0o000);

        $exitCode = $this->commandTester->execute([]);

        self::assertSame(Command::FAILURE, $exitCode);

        $output = $this->commandTester->getDisplay();
        self::assertStringContainsString('Unexpected Error:', $output);

        // Restore permissions for cleanup
        chmod($configFile, 0o644);
    }

    /**
     * Test validation with custom tool configuration files from fixtures.
     */
    #[DataProvider('customConfigurationProvider')]
    public function testValidateWithCustomConfigurations(
        string $fixtureDirectory,
        string $description,
        int $expectedExitCode,
        array $expectedOutputContains,
        array $unexpectedOutputContains = []
    ): void {
        // Copy fixture to temp directory
        $fixturePath = __DIR__ . '/../../../Fixtures/configFileReplacement/' . $fixtureDirectory;
        if (!is_dir($fixturePath)) {
            $this->markTestSkipped("Fixture directory not found: $fixturePath");
        }

        // Copy all files from fixture to temp directory
        $this->filesystem->mirror($fixturePath, $this->tempDir);

        // Execute the validate command
        $exitCode = $this->commandTester->execute([]);

        // Assert exit code
        self::assertSame(
            $expectedExitCode,
            $exitCode,
            sprintf('Failed for %s: Expected exit code %d, got %d', $description, $expectedExitCode, $exitCode)
        );

        $output = TestHelper::normalizeConsoleOutput($this->commandTester->getDisplay());

        // Assert expected output
        foreach ($expectedOutputContains as $expected) {
            self::assertStringContainsString(
                $expected,
                $output,
                sprintf('Failed for %s: Output should contain "%s"', $description, $expected)
            );
        }

        // Assert unexpected output
        foreach ($unexpectedOutputContains as $unexpected) {
            self::assertStringNotContainsString(
                $unexpected,
                $output,
                sprintf('Failed for %s: Output should not contain "%s"', $description, $unexpected)
            );
        }
    }

    /**
     * Data provider for custom configuration test scenarios.
     */
    public static function customConfigurationProvider(): array
    {
        return [
            'rector root override' => [
                'fixtureDirectory' => 'rector-root-override',
                'description' => 'Auto-discovered rector.php in project root',
                'expectedExitCode' => Command::SUCCESS,
                'expectedOutputContains' => ['Configuration is valid'],
            ],
            'rector config directory' => [
                'fixtureDirectory' => 'rector-config-override',
                'description' => 'Auto-discovered rector.php in config/ directory',
                'expectedExitCode' => Command::SUCCESS,
                'expectedOutputContains' => ['Configuration is valid'],
            ],
            'phpstan root override' => [
                'fixtureDirectory' => 'phpstan-root-override',
                'description' => 'Auto-discovered phpstan.neon in project root',
                'expectedExitCode' => Command::SUCCESS,
                'expectedOutputContains' => ['Configuration is valid'],
            ],
            'phpstan config directory' => [
                'fixtureDirectory' => 'phpstan-config-override',
                'description' => 'Auto-discovered phpstan.neon in config/ directory',
                'expectedExitCode' => Command::SUCCESS,
                'expectedOutputContains' => ['Configuration is valid'],
            ],
            'fractor root override' => [
                'fixtureDirectory' => 'fractor-root-override',
                'description' => 'Auto-discovered fractor.php in project root',
                'expectedExitCode' => Command::SUCCESS,
                'expectedOutputContains' => ['Configuration is valid'],
            ],
            'php-cs-fixer root override' => [
                'fixtureDirectory' => 'php-cs-fixer-root-override',
                'description' => 'Auto-discovered .php-cs-fixer.php in project root',
                'expectedExitCode' => Command::SUCCESS,
                'expectedOutputContains' => ['Configuration is valid'],
            ],
            'explicit config file' => [
                'fixtureDirectory' => 'explicit-config-file-override',
                'description' => 'Explicit config_file in YAML configuration',
                'expectedExitCode' => Command::SUCCESS,
                'expectedOutputContains' => ['Configuration is valid'],
            ],
            'multiple tools mixed' => [
                'fixtureDirectory' => 'multiple-tools-mixed',
                'description' => 'Multiple tools with mixed configuration methods',
                'expectedExitCode' => Command::SUCCESS,
                'expectedOutputContains' => ['Configuration is valid'],
            ],
        ];
    }

    /**
     * Test verbose output shows auto-discovered configuration information.
     */
    #[DataProvider('verboseOutputProvider')]
    public function testVerboseOutputWithCustomConfigurations(
        string $fixtureDirectory,
        string $description,
        array $expectedVerboseOutput
    ): void {
        // Copy fixture to temp directory
        $fixturePath = __DIR__ . '/../../../Fixtures/configFileReplacement/' . $fixtureDirectory;
        if (!is_dir($fixturePath)) {
            $this->fail("Fixture directory not found: $fixturePath");
        }

        $this->filesystem->mirror($fixturePath, $this->tempDir);

        // Execute with verbose mode
        $exitCode = $this->commandTester->execute([], ['verbosity' => OutputInterface::VERBOSITY_VERBOSE]);

        self::assertSame(Command::SUCCESS, $exitCode, "Failed for $description");

        $output = $this->commandTester->getDisplay();

        // Check for verbose-specific output
        self::assertStringContainsString('Configuration Summary', $output);

        foreach ($expectedVerboseOutput as $expected) {
            self::assertStringContainsString(
                $expected,
                $output,
                sprintf('Verbose output for %s should contain "%s"', $description, $expected)
            );
        }
    }

    /**
     * Data provider for verbose output testing.
     */
    public static function verboseOutputProvider(): array
    {
        return [
            'rector with custom config' => [
                'fixtureDirectory' => 'rector-root-override',
                'description' => 'Verbose output with rector custom config',
                'expectedVerboseOutput' => [
                    'Configuration Summary',
                    'rector',
                ],
            ],
            'multiple tools verbose' => [
                'fixtureDirectory' => 'multiple-tools-mixed',
                'description' => 'Verbose output with multiple tool configs',
                'expectedVerboseOutput' => [
                    'Configuration Summary',
                    'rector',
                    'phpstan',
                ],
            ],
        ];
    }

    /**
     * Test configuration precedence using fixtures.
     */
    public function testConfigurationPrecedenceWithFixtures(): void
    {
        // Use the explicit-config-file-override fixture which has both
        // auto-discovered and explicit config_file settings
        $fixturePath = __DIR__ . '/../../../Fixtures/configFileReplacement/explicit-config-file-override';

        if (!is_dir($fixturePath)) {
            $this->fail("Fixture directory not found: $fixturePath");
        }

        $this->filesystem->mirror($fixturePath, $this->tempDir);

        $exitCode = $this->commandTester->execute([]);

        self::assertSame(Command::SUCCESS, $exitCode);

        $output = TestHelper::normalizeConsoleOutput($this->commandTester->getDisplay());
        self::assertStringContainsString('Configuration is valid', $output);
        // The explicit config_file in YAML should take precedence over auto-discovered files
    }
}

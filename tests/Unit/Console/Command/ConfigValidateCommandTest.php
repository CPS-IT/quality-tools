<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Tests\Unit\Console\Command;

use Cpsit\QualityTools\Configuration\ConfigurationLoader;
use Cpsit\QualityTools\Configuration\ConfigurationValidator;
use Cpsit\QualityTools\Console\Command\ConfigValidateCommand;
use Cpsit\QualityTools\Console\Runner\ConfigValidateRunner;
use Cpsit\QualityTools\Service\FilesystemService;
use Cpsit\QualityTools\Service\ProjectEnvironment;
use Cpsit\QualityTools\Service\SecurityService;
use Cpsit\QualityTools\Service\ToolConfigurationValidationService;
use Cpsit\QualityTools\Tests\Unit\TestHelper;
use Cpsit\QualityTools\Utility\VendorDirectoryDetector;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;

#[CoversClass(ConfigValidateCommand::class)]
final class ConfigValidateCommandTest extends TestCase
{
    private ConfigValidateCommand $command;
    private CommandTester $commandTester;
    private string $tempDir;
    private string|false $originalProjectRoot;
    private Filesystem $filesystem;

    protected function setUp(): void
    {
        $this->tempDir = TestHelper::createTempDirectory('config_validate_test_');
        $this->filesystem = new Filesystem();
        $this->originalProjectRoot = getenv('QT_PROJECT_ROOT');
        putenv('QT_PROJECT_ROOT=' . $this->tempDir);
        $_ENV['QT_PROJECT_ROOT'] = $this->tempDir;
        $_SERVER['QT_PROJECT_ROOT'] = $this->tempDir;
        VendorDirectoryDetector::clearCache();

        TestHelper::createComposerJson($this->tempDir, TestHelper::getComposerContent('typo3-core'));
        mkdir($this->tempDir . '/vendor/composer', 0o777, true);
        file_put_contents($this->tempDir . '/vendor/autoload.php', "<?php\nreturn [];\n");

        $runner = $this->createRunner();
        $this->command = new ConfigValidateCommand(
            $runner,
            'config:validate',
            'Validate YAML configuration file',
            'This command validates the quality-tools.yaml configuration file against the schema.',
        );
        $this->commandTester = new CommandTester($this->command);
    }

    protected function tearDown(): void
    {
        if ($this->originalProjectRoot === false) {
            putenv('QT_PROJECT_ROOT');
            unset($_ENV['QT_PROJECT_ROOT'], $_SERVER['QT_PROJECT_ROOT']);
        } else {
            putenv('QT_PROJECT_ROOT=' . $this->originalProjectRoot);
            $_ENV['QT_PROJECT_ROOT'] = $this->originalProjectRoot;
            $_SERVER['QT_PROJECT_ROOT'] = $this->originalProjectRoot;
        }

        TestHelper::removeDirectory($this->tempDir);
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
        self::assertStringContainsString('test-project', $output);
        self::assertStringContainsString('8.3', $output);
        self::assertStringContainsString('13.4', $output);
        self::assertStringContainsString('rector', $output);
        self::assertStringContainsString('Scan paths:', $output);
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
        self::assertStringContainsString('rector', $output);
        self::assertStringContainsString('fractor', $output);
        self::assertStringContainsString('phpstan', $output);
    }

    public function testConfigurationSummaryWithScanPaths(): void
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
        self::assertStringContainsString('Scan paths:', $output);
        self::assertStringContainsString('packages/', $output);
    }

    public function testHelpOutput(): void
    {
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

    #[DataProvider('customConfigurationProvider')]
    public function testValidateWithCustomConfigurations(
        string $fixtureDirectory,
        string $description,
        int $expectedExitCode,
        array $expectedOutputContains,
        array $unexpectedOutputContains = [],
    ): void {
        $this->mirrorFixture($fixtureDirectory);

        $exitCode = $this->commandTester->execute([]);

        self::assertSame(
            $expectedExitCode,
            $exitCode,
            \sprintf('Failed for %s: Expected exit code %d, got %d', $description, $expectedExitCode, $exitCode),
        );

        $output = TestHelper::normalizeConsoleOutput($this->commandTester->getDisplay());

        foreach ($expectedOutputContains as $expected) {
            self::assertStringContainsString(
                $expected,
                $output,
                \sprintf('Failed for %s: Output should contain "%s"', $description, $expected),
            );
        }

        foreach ($unexpectedOutputContains as $unexpected) {
            self::assertStringNotContainsString(
                $unexpected,
                $output,
                \sprintf('Failed for %s: Output should not contain "%s"', $description, $unexpected),
            );
        }
    }

    /**
     * @return array<string, array{string, string, int, list<string>, list<string>}>
     */
    public static function customConfigurationProvider(): array
    {
        return [
            'rector root override' => [
                'rector-root-override',
                'Auto-discovered rector.php in project root',
                Command::SUCCESS,
                ['Configuration is valid'],
                [],
            ],
            'rector config directory' => [
                'rector-config-override',
                'Auto-discovered rector.php in config/ directory',
                Command::SUCCESS,
                ['Configuration is valid'],
                [],
            ],
            'phpstan root override' => [
                'phpstan-root-override',
                'Auto-discovered phpstan.neon in project root',
                Command::SUCCESS,
                ['Configuration is valid'],
                [],
            ],
            'phpstan config directory' => [
                'phpstan-config-override',
                'Auto-discovered phpstan.neon in config/ directory',
                Command::SUCCESS,
                ['Configuration is valid'],
                [],
            ],
            'fractor root override' => [
                'fractor-root-override',
                'Auto-discovered fractor.php in project root',
                Command::SUCCESS,
                ['Configuration is valid'],
                [],
            ],
            'php-cs-fixer root override' => [
                'php-cs-fixer-root-override',
                'Auto-discovered .php-cs-fixer.php in project root',
                Command::SUCCESS,
                ['Configuration is valid'],
                [],
            ],
            'explicit config file' => [
                'explicit-config-file-override',
                'Explicit config_file in YAML configuration',
                Command::SUCCESS,
                ['Configuration is valid'],
                [],
            ],
            'multiple tools mixed' => [
                'multiple-tools-mixed',
                'Multiple tools with mixed configuration methods',
                Command::SUCCESS,
                ['Configuration is valid'],
                [],
            ],
        ];
    }

    #[DataProvider('verboseOutputProvider')]
    public function testVerboseOutputWithCustomConfigurations(
        string $fixtureDirectory,
        string $description,
        array $expectedVerboseOutput,
    ): void {
        $this->mirrorFixture($fixtureDirectory);

        $exitCode = $this->commandTester->execute([], ['verbosity' => OutputInterface::VERBOSITY_VERBOSE]);

        self::assertSame(Command::SUCCESS, $exitCode, "Failed for $description");

        $output = $this->commandTester->getDisplay();

        foreach ($expectedVerboseOutput as $expected) {
            self::assertStringContainsString(
                $expected,
                $output,
                \sprintf('Verbose output for %s should contain "%s"', $description, $expected),
            );
        }
    }

    /**
     * @return array<string, array{string, string, list<string>}>
     */
    public static function verboseOutputProvider(): array
    {
        return [
            'rector with custom config' => [
                'rector-root-override',
                'Verbose output with rector custom config',
                ['rector'],
            ],
            'multiple tools verbose' => [
                'multiple-tools-mixed',
                'Verbose output with multiple tool configs',
                ['rector', 'phpstan'],
            ],
        ];
    }

    public function testConfigurationPrecedenceWithFixtures(): void
    {
        $this->mirrorFixture('explicit-config-file-override');

        $exitCode = $this->commandTester->execute([]);

        self::assertSame(Command::SUCCESS, $exitCode);

        $output = TestHelper::normalizeConsoleOutput($this->commandTester->getDisplay());
        self::assertStringContainsString('Configuration is valid', $output);
    }

    private function mirrorFixture(string $fixtureDirectory): void
    {
        $fixturePath = __DIR__ . '/../../../Fixtures/configFileReplacement/' . $fixtureDirectory;

        if (!is_dir($fixturePath)) {
            $this->markTestSkipped("Fixture directory not found: $fixturePath");
        }

        $this->filesystem->mirror($fixturePath, $this->tempDir);

        // Ensure vendor structure exists after fixture mirroring
        if (!is_dir($this->tempDir . '/vendor/composer')) {
            mkdir($this->tempDir . '/vendor/composer', 0o777, true);
        }
        if (!file_exists($this->tempDir . '/vendor/autoload.php')) {
            file_put_contents($this->tempDir . '/vendor/autoload.php', "<?php\nreturn [];\n");
        }
    }

    private function createRunner(): ConfigValidateRunner
    {
        $validator = new ConfigurationValidator();
        $securityService = new SecurityService();
        $filesystem = new Filesystem();
        $filesystemService = new FilesystemService($filesystem, $securityService);
        $toolValidator = new ToolConfigurationValidationService([]);

        $configLoader = new ConfigurationLoader(
            $validator,
            $securityService,
            $filesystemService,
            $toolValidator,
        );

        $projectEnv = new ProjectEnvironment(new VendorDirectoryDetector());

        return new ConfigValidateRunner($configLoader, $validator, $projectEnv);
    }
}

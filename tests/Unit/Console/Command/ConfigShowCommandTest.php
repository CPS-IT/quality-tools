<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Tests\Unit\Console\Command;

use Cpsit\QualityTools\Configuration\ConfigurationLoader;
use Cpsit\QualityTools\Configuration\ConfigurationValidator;
use Cpsit\QualityTools\Console\Command\ConfigShowCommand;
use Cpsit\QualityTools\Console\Runner\ConfigShowRunner;
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

#[CoversClass(ConfigShowCommand::class)]
final class ConfigShowCommandTest extends TestCase
{
    private ConfigShowCommand $command;
    private CommandTester $commandTester;
    private string $tempDir;
    private string|false $originalProjectRoot;

    protected function setUp(): void
    {
        $this->tempDir = TestHelper::createTempDirectory('config_show_test_');
        $this->originalProjectRoot = getenv('QT_PROJECT_ROOT');
        putenv('QT_PROJECT_ROOT=' . $this->tempDir);
        $_ENV['QT_PROJECT_ROOT'] = $this->tempDir;
        $_SERVER['QT_PROJECT_ROOT'] = $this->tempDir;
        VendorDirectoryDetector::clearCache();

        TestHelper::createComposerJson($this->tempDir, TestHelper::getComposerContent('typo3-core'));
        mkdir($this->tempDir . '/vendor/composer', 0o777, true);
        file_put_contents($this->tempDir . '/vendor/autoload.php', "<?php\nreturn [];\n");

        $runner = $this->createRunner();
        $this->command = new ConfigShowCommand(
            $runner,
            'config:show',
            'Show resolved configuration',
            'Shows the resolved configuration after merging all sources.',
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
        self::assertSame('config:show', $this->command->getName());
        self::assertSame('Show resolved configuration', $this->command->getDescription());

        $definition = $this->command->getDefinition();
        self::assertTrue($definition->hasOption('format'));
        self::assertSame('yaml', $definition->getOption('format')->getDefault());
    }

    public function testExecuteWithDefaultFormat(): void
    {
        $exitCode = $this->commandTester->execute([]);

        self::assertSame(Command::SUCCESS, $exitCode);

        $output = $this->commandTester->getDisplay();
        self::assertStringContainsString('quality-tools:', $output);
        self::assertStringContainsString('project:', $output);
        self::assertStringContainsString('php_version: ', $output);
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
        self::assertStringContainsString('quality-tools:', $output);
        self::assertStringContainsString('name: test-project', $output);
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

        $output = trim($this->commandTester->getDisplay());
        $decoded = json_decode($output, true);
        self::assertNotNull($decoded, 'Output should be valid JSON');
        self::assertArrayHasKey('quality-tools', $decoded);
        self::assertSame('test-project', $decoded['quality-tools']['project']['name']);
        self::assertSame('8.4', $decoded['quality-tools']['project']['php_version']);
    }

    public function testExecuteWithInvalidFormat(): void
    {
        $exitCode = $this->commandTester->execute(['--format' => 'xml']);

        self::assertSame(Command::FAILURE, $exitCode);

        $output = $this->commandTester->getDisplay();
        self::assertStringContainsString('Format must be either', $output);
    }

    public function testExecuteWithVerboseShowsSources(): void
    {
        $homeDir = $this->tempDir . '/home';
        mkdir($homeDir, 0o777, true);

        $globalConfig = <<<YAML
            quality-tools:
              project:
                php_version: "8.4"
            YAML;
        file_put_contents($homeDir . '/.quality-tools.yaml', $globalConfig);

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
        self::assertStringContainsString('Global', $output);
        self::assertStringContainsString('Project', $output);
        self::assertStringContainsString('Package defaults', $output);
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
        self::assertStringContainsString('Project', $output);
        self::assertStringContainsString('Package defaults', $output);
        self::assertStringNotContainsString('Global', $output);
    }

    public function testExecuteWithVerboseNoProjectConfig(): void
    {
        $exitCode = $this->commandTester->execute([], ['verbosity' => OutputInterface::VERBOSITY_VERBOSE]);

        self::assertSame(Command::SUCCESS, $exitCode);

        $output = $this->commandTester->getDisplay();
        self::assertStringContainsString('Package defaults', $output);
        self::assertStringNotContainsString('Project:', $output);
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
            'PHP_VERSION' => '8.3',
            'MEMORY_LIMIT' => '2G',
        ], fn (): int => $this->commandTester->execute(['--format' => 'yaml']));

        self::assertSame(Command::SUCCESS, $exitCode);

        $output = $this->commandTester->getDisplay();
        self::assertStringContainsString('name: env-test', $output);
        self::assertStringContainsString('2G', $output);
    }

    public function testExecuteWithComplexMerging(): void
    {
        $homeDir = $this->tempDir . '/home';
        mkdir($homeDir, 0o777, true);

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

        $output = trim($this->commandTester->getDisplay());
        $config = json_decode($output, true);

        self::assertSame('merge-test', $config['quality-tools']['project']['name']);
        self::assertSame('8.4', $config['quality-tools']['project']['php_version']);
        self::assertTrue($config['quality-tools']['tools']['rector']['enabled']);
        self::assertSame('typo3-13', $config['quality-tools']['tools']['rector']['level']);
        self::assertSame(8, $config['quality-tools']['tools']['phpstan']['level']);
        self::assertSame('512M', $config['quality-tools']['tools']['phpstan']['memory_limit']);
        self::assertFalse($config['quality-tools']['output']['colors']);
        self::assertSame('verbose', $config['quality-tools']['output']['verbosity']);
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
        self::assertStringContainsString('MISSING_ENV_VAR', $output);
    }

    public function testShortFormatOption(): void
    {
        $exitCode = $this->commandTester->execute(['-f' => 'json']);

        self::assertSame(Command::SUCCESS, $exitCode);

        $output = $this->commandTester->getDisplay();
        self::assertStringContainsString('{', $output);
    }

    public function testDefaultConfigurationOutput(): void
    {
        $exitCode = $this->commandTester->execute([]);

        self::assertSame(Command::SUCCESS, $exitCode);

        $output = $this->commandTester->getDisplay();
        self::assertStringContainsString("php_version: '8.3'", $output);
        self::assertStringContainsString("typo3_version: '14.0'", $output);
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
            TestHelper::createComposerJson($testDir, TestHelper::getComposerContent('typo3-core'));
            mkdir($testDir . '/vendor/composer', 0o777, true);
            file_put_contents($testDir . '/vendor/autoload.php', "<?php\nreturn [];\n");

            $config = <<<YAML
                quality-tools:
                  project:
                    name: "test-{$configFile}"
                YAML;
            file_put_contents($testDir . '/' . $configFile, $config);

            TestHelper::withEnvironment(
                ['QT_PROJECT_ROOT' => $testDir],
                function () use ($configFile): void {
                    VendorDirectoryDetector::clearCache();
                    $runner = $this->createRunner();
                    $command = new ConfigShowCommand(
                        $runner,
                        'config:show',
                        'Show resolved configuration',
                        'Shows the resolved configuration after merging all sources.',
                    );
                    $commandTester = new CommandTester($command);

                    $exitCode = $commandTester->execute([], ['verbosity' => OutputInterface::VERBOSITY_VERBOSE]);

                    self::assertSame(Command::SUCCESS, $exitCode);

                    $output = $commandTester->getDisplay();
                    self::assertStringContainsString("test-{$configFile}", $output);
                },
            );

            TestHelper::removeDirectory($testDir);
        }
    }

    #[DataProvider('autoDiscoveryProvider')]
    public function testVerboseShowsAutoDiscoveredConfigs(
        string $fixtureDirectory,
        string $description,
        array $expectedSources,
    ): void {
        $this->mirrorFixture($fixtureDirectory);

        $exitCode = $this->commandTester->execute([], ['verbosity' => OutputInterface::VERBOSITY_VERBOSE]);

        self::assertSame(Command::SUCCESS, $exitCode, "Failed for $description");

        $output = $this->commandTester->getDisplay();

        foreach ($expectedSources as $source) {
            self::assertStringContainsString(
                $source,
                $output,
                \sprintf('Failed for %s: Should show source "%s"', $description, $source),
            );
        }
    }

    /**
     * @return array<string, array{string, string, list<string>}>
     */
    public static function autoDiscoveryProvider(): array
    {
        return [
            'rector auto-discovered' => [
                'rector-root-override',
                'Rector config auto-discovered from root',
                ['rector.php', 'Tool-specific'],
            ],
            'phpstan auto-discovered' => [
                'phpstan-root-override',
                'PHPStan config auto-discovered from root',
                ['phpstan.neon', 'Tool-specific'],
            ],
            'multiple tools discovered' => [
                'multiple-tools-mixed',
                'Multiple tool configs auto-discovered',
                ['Tool-specific'],
            ],
            'explicit config override' => [
                'explicit-config-file-override',
                'Explicit config_file in YAML',
                ['.quality-tools.yaml'],
            ],
        ];
    }

    #[DataProvider('resolvedConfigurationProvider')]
    public function testShowsResolvedConfiguration(
        string $fixtureDirectory,
        string $description,
        array $expectedConfigKeys,
    ): void {
        $this->mirrorFixture($fixtureDirectory);

        $exitCode = $this->commandTester->execute([]);

        self::assertSame(Command::SUCCESS, $exitCode, "Failed for $description");

        $output = $this->commandTester->getDisplay();
        self::assertStringContainsString('quality-tools:', $output);

        foreach ($expectedConfigKeys as $key) {
            self::assertStringContainsString(
                $key,
                $output,
                \sprintf('Failed for %s: Configuration should contain "%s"', $description, $key),
            );
        }
    }

    /**
     * @return array<string, array{string, string, list<string>}>
     */
    public static function resolvedConfigurationProvider(): array
    {
        return [
            'basic configuration' => [
                'rector-root-override',
                'Basic configuration with rector override',
                ['project:', 'tools:', 'rector:', 'enabled: true'],
            ],
            'multiple tools' => [
                'multiple-tools-mixed',
                'Configuration with multiple tools',
                ['project:', 'tools:', 'rector:', 'phpstan:'],
            ],
        ];
    }

    public function testJsonFormatWithAutoDiscoveredConfigs(): void
    {
        $this->mirrorFixture('rector-root-override');

        $exitCode = $this->commandTester->execute(['--format' => 'json']);

        self::assertSame(Command::SUCCESS, $exitCode);

        $output = trim($this->commandTester->getDisplay());

        // Extract JSON: find first '{' to last '}'
        $start = strpos($output, '{');
        $end = strrpos($output, '}');
        self::assertNotFalse($start, 'No JSON opening brace found');
        self::assertNotFalse($end, 'No JSON closing brace found');
        $jsonOutput = substr($output, $start, $end - $start + 1);

        $json = json_decode($jsonOutput, true);
        self::assertIsArray($json);
        self::assertArrayHasKey('quality-tools', $json);
        self::assertArrayHasKey('project', $json['quality-tools']);
        self::assertArrayHasKey('tools', $json['quality-tools']);
    }

    public function testHandlesMissingCustomConfigFiles(): void
    {
        $config = <<<YAML
            quality-tools:
              project:
                name: "test-missing-config"
              tools:
                rector:
                  enabled: true
                  config_file: "non-existent/rector.php"
            YAML;

        file_put_contents($this->tempDir . '/.quality-tools.yaml', $config);

        $exitCode = $this->commandTester->execute([]);

        self::assertSame(Command::SUCCESS, $exitCode);

        $output = $this->commandTester->getDisplay();
        self::assertStringContainsString('config_file: non-existent/rector.php', $output);
    }

    private function mirrorFixture(string $fixtureDirectory): void
    {
        $filesystem = new Filesystem();
        $fixturePath = __DIR__ . '/../../../Fixtures/configFileReplacement/' . $fixtureDirectory;

        if (!is_dir($fixturePath)) {
            $this->markTestSkipped("Fixture directory not found: $fixturePath");
        }

        $filesystem->mirror($fixturePath, $this->tempDir);

        // Ensure vendor structure exists after fixture mirroring
        if (!is_dir($this->tempDir . '/vendor/composer')) {
            mkdir($this->tempDir . '/vendor/composer', 0o777, true);
        }
        if (!file_exists($this->tempDir . '/vendor/autoload.php')) {
            file_put_contents($this->tempDir . '/vendor/autoload.php', "<?php\nreturn [];\n");
        }
    }

    private function createRunner(): ConfigShowRunner
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

        return new ConfigShowRunner($configLoader, $projectEnv);
    }
}

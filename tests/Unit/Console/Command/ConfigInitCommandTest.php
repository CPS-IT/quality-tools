<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Tests\Unit\Console\Command;

use Cpsit\QualityTools\Configuration\ConfigurationLoader;
use Cpsit\QualityTools\Configuration\ConfigurationTemplateGenerator;
use Cpsit\QualityTools\Configuration\ConfigurationValidator;
use Cpsit\QualityTools\Console\Command\ConfigInitCommand;
use Cpsit\QualityTools\Console\Runner\ConfigInitRunner;
use Cpsit\QualityTools\Service\FilesystemService;
use Cpsit\QualityTools\Service\ProjectEnvironment;
use Cpsit\QualityTools\Service\SecurityService;
use Cpsit\QualityTools\Service\ToolConfigurationValidationService;
use Cpsit\QualityTools\Tests\Unit\TestHelper;
use Cpsit\QualityTools\Utility\VendorDirectoryDetector;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;

#[CoversClass(ConfigInitCommand::class)]
final class ConfigInitCommandTest extends TestCase
{
    private ConfigInitCommand $command;
    private CommandTester $commandTester;
    private string $tempDir;
    private string|false $originalProjectRoot;

    protected function setUp(): void
    {
        $this->tempDir = TestHelper::createTempDirectory('config_init_test_');
        $this->originalProjectRoot = getenv('QT_PROJECT_ROOT');
        putenv('QT_PROJECT_ROOT=' . $this->tempDir);
        $_ENV['QT_PROJECT_ROOT'] = $this->tempDir;
        $_SERVER['QT_PROJECT_ROOT'] = $this->tempDir;
        VendorDirectoryDetector::clearCache();

        // Create a basic project structure
        TestHelper::createComposerJson($this->tempDir, [
            'name' => 'test/project',
            'type' => 'project',
        ]);

        $runner = $this->createRunner();
        $this->command = new ConfigInitCommand(
            $runner,
            'config:init',
            'Initialize YAML configuration file',
            'This command creates a .quality-tools.yaml configuration file in the project root.',
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
        self::assertSame('config:init', $this->command->getName());
        self::assertSame('Initialize YAML configuration file', $this->command->getDescription());

        $definition = $this->command->getDefinition();
        self::assertTrue($definition->hasOption('template'));
        self::assertTrue($definition->hasOption('force'));

        $templateOption = $definition->getOption('template');
        self::assertSame('default', $templateOption->getDefault());
    }

    public function testExecuteDefaultTemplate(): void
    {
        $exitCode = $this->commandTester->execute([]);

        self::assertSame(Command::SUCCESS, $exitCode);

        $output = $this->commandTester->getDisplay();
        self::assertStringContainsString('Created configuration file', $output);
        self::assertStringContainsString('Template used: Default Configuration', $output);

        // Check file was created
        $configFile = $this->tempDir . '/.quality-tools.yaml';
        self::assertFileExists($configFile);

        $content = file_get_contents($configFile);
        self::assertStringContainsString('quality-tools:', $content);
        self::assertStringContainsString('test/project', $content);
        self::assertStringContainsString('php_version: "8.3"', $content);
        self::assertStringContainsString('typo3_version: "13.4"', $content);
    }

    public function testExecuteExtensionTemplate(): void
    {
        $exitCode = $this->commandTester->execute(['--template' => 'typo3-extension']);

        self::assertSame(Command::SUCCESS, $exitCode);

        $output = $this->commandTester->getDisplay();
        self::assertStringContainsString('Template used: TYPO3 Extension', $output);

        $configFile = $this->tempDir . '/.quality-tools.yaml';
        $content = file_get_contents($configFile);

        self::assertStringContainsString('Classes/', $content);
        self::assertStringContainsString('Configuration/', $content);
        self::assertStringContainsString('Tests/', $content);
        self::assertStringContainsString('level: 8', $content);
        self::assertStringContainsString('parallel: false', $content);
    }

    public function testExecuteSitePackageTemplate(): void
    {
        $exitCode = $this->commandTester->execute(['--template' => 'typo3-site-package']);

        self::assertSame(Command::SUCCESS, $exitCode);

        $output = $this->commandTester->getDisplay();
        self::assertStringContainsString('Template used: TYPO3 Site Package', $output);

        $configFile = $this->tempDir . '/.quality-tools.yaml';
        $content = file_get_contents($configFile);

        self::assertStringContainsString('packages/', $content);
        self::assertStringContainsString('config/', $content);
        self::assertStringContainsString('level: 6', $content);
    }

    public function testExecuteDistributionTemplate(): void
    {
        $exitCode = $this->commandTester->execute(['--template' => 'typo3-distribution']);

        self::assertSame(Command::SUCCESS, $exitCode);

        $output = $this->commandTester->getDisplay();
        self::assertStringContainsString('Template used: TYPO3 Distribution', $output);

        $configFile = $this->tempDir . '/.quality-tools.yaml';
        $content = file_get_contents($configFile);

        self::assertStringContainsString('packages/', $content);
        self::assertStringContainsString('config/system/', $content);
        self::assertStringContainsString('config/sites/', $content);
        self::assertStringContainsString('memory_limit: "2G"', $content);
        self::assertStringContainsString('max_processes: 8', $content);
    }

    public function testExecuteInvalidTemplate(): void
    {
        $exitCode = $this->commandTester->execute(['--template' => 'invalid-template']);

        self::assertSame(Command::FAILURE, $exitCode);

        $output = $this->commandTester->getDisplay();
        self::assertStringContainsString('Invalid template "invalid-template"', $output);
        self::assertStringContainsString('Available templates:', $output);

        $configFile = $this->tempDir . '/.quality-tools.yaml';
        self::assertFileDoesNotExist($configFile);
    }

    public function testExecuteWithExistingConfiguration(): void
    {
        $existingConfig = $this->tempDir . '/.quality-tools.yaml';
        file_put_contents($existingConfig, 'existing-content');

        $exitCode = $this->commandTester->execute([]);

        self::assertSame(Command::SUCCESS, $exitCode);

        $output = $this->commandTester->getDisplay();
        self::assertStringContainsString('Configuration file already exists', $output);
        self::assertStringContainsString('Use --force to overwrite', $output);

        self::assertSame('existing-content', file_get_contents($existingConfig));
    }

    public function testExecuteWithForceFlag(): void
    {
        $existingConfig = $this->tempDir . '/.quality-tools.yaml';
        file_put_contents($existingConfig, 'existing-content');

        $exitCode = $this->commandTester->execute(['--force' => true]);

        self::assertSame(Command::SUCCESS, $exitCode);

        $output = $this->commandTester->getDisplay();
        self::assertStringContainsString('Created configuration file', $output);

        $content = file_get_contents($existingConfig);
        self::assertNotSame('existing-content', $content);
        self::assertStringContainsString('quality-tools:', $content);
    }

    public function testExecuteWithExistingQualityToolsYaml(): void
    {
        $existingConfig = $this->tempDir . '/quality-tools.yaml';
        file_put_contents($existingConfig, 'existing-content');

        $exitCode = $this->commandTester->execute([]);

        self::assertSame(Command::SUCCESS, $exitCode);

        $output = $this->commandTester->getDisplay();
        self::assertStringContainsString('Configuration file already exists', $output);
        self::assertStringContainsString('Use --force to overwrite', $output);

        self::assertFileDoesNotExist($this->tempDir . '/.quality-tools.yaml');
    }

    public function testProjectNameDetectionFromComposer(): void
    {
        TestHelper::createComposerJson($this->tempDir, [
            'name' => 'vendor/custom-project',
            'type' => 'project',
        ]);

        $exitCode = $this->commandTester->execute([]);

        self::assertSame(Command::SUCCESS, $exitCode);

        $configFile = $this->tempDir . '/.quality-tools.yaml';
        $content = file_get_contents($configFile);

        self::assertStringContainsString('vendor/custom-project', $content);
    }

    public function testProjectNameDetectionFromDirectoryName(): void
    {
        unlink($this->tempDir . '/composer.json');

        $exitCode = $this->commandTester->execute([]);

        self::assertSame(Command::SUCCESS, $exitCode);

        $configFile = $this->tempDir . '/.quality-tools.yaml';
        $content = file_get_contents($configFile);

        $directoryName = basename($this->tempDir);
        self::assertStringContainsString($directoryName, $content);
    }

    public function testFileWriteError(): void
    {
        if (\function_exists('posix_getuid') && posix_getuid() === 0) {
            $this->markTestSkipped('File permission tests are meaningless when running as root');
        }

        // Make directory read-only to cause write error
        chmod($this->tempDir, 0o555);

        $exitCode = $this->commandTester->execute([]);

        self::assertSame(Command::FAILURE, $exitCode);

        $output = $this->commandTester->getDisplay();
        self::assertStringContainsString('Failed to create configuration file', $output);

        // Restore permissions for cleanup
        chmod($this->tempDir, 0o755);
    }

    public function testHelpOutput(): void
    {
        self::assertSame('Initialize YAML configuration file', $this->command->getDescription());
        self::assertStringContainsString('.quality-tools.yaml', $this->command->getHelp());

        $definition = $this->command->getDefinition();
        self::assertTrue($definition->hasOption('template'));
        self::assertTrue($definition->hasOption('force'));

        $templateOption = $definition->getOption('template');
        self::assertSame('t', $templateOption->getShortcut());

        $forceOption = $definition->getOption('force');
        self::assertSame('f', $forceOption->getShortcut());
    }

    public function testShortOptionAliases(): void
    {
        $exitCode = $this->commandTester->execute(['-t' => 'typo3-extension', '-f' => true]);

        self::assertSame(Command::SUCCESS, $exitCode);

        $output = $this->commandTester->getDisplay();
        self::assertStringContainsString('Template used: TYPO3 Extension', $output);

        $configFile = $this->tempDir . '/.quality-tools.yaml';
        self::assertFileExists($configFile);
    }

    public function testNextStepsInVerboseOutput(): void
    {
        $exitCode = $this->commandTester->execute([], ['verbosity' => OutputInterface::VERBOSITY_VERBOSE]);

        self::assertSame(Command::SUCCESS, $exitCode);

        $output = $this->commandTester->getDisplay();
        self::assertStringContainsString('Next steps:', $output);
        self::assertStringContainsString('qt config:validate', $output);
        self::assertStringContainsString('qt config:show', $output);
    }

    public function testAllTemplateTypesAreValid(): void
    {
        $templates = ['default', 'typo3-extension', 'typo3-site-package', 'typo3-distribution'];

        foreach ($templates as $template) {
            $testDir = TestHelper::createTempDirectory('template_test_');
            TestHelper::createComposerJson($testDir, ['name' => 'test/project']);

            TestHelper::withEnvironment(
                ['QT_PROJECT_ROOT' => $testDir],
                function () use ($template, $testDir): void {
                    VendorDirectoryDetector::clearCache();
                    $runner = $this->createRunner();
                    $command = new ConfigInitCommand(
                        $runner,
                        'config:init',
                        'Initialize YAML configuration file',
                        'This command creates a .quality-tools.yaml configuration file in the project root.',
                    );
                    $commandTester = new CommandTester($command);

                    $exitCode = $commandTester->execute(['--template' => $template]);

                    self::assertSame(
                        Command::SUCCESS,
                        $exitCode,
                        \sprintf('Template %s should execute successfully', $template),
                    );

                    $configFile = $testDir . '/.quality-tools.yaml';
                    self::assertFileExists($configFile);

                    $content = file_get_contents($configFile);
                    self::assertStringContainsString('quality-tools:', $content);
                },
            );

            TestHelper::removeDirectory($testDir);
        }
    }

    private function createRunner(): ConfigInitRunner
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

        $templateGenerator = new ConfigurationTemplateGenerator();
        $projectEnv = new ProjectEnvironment(new VendorDirectoryDetector());

        return new ConfigInitRunner($templateGenerator, $configLoader, $filesystemService, $projectEnv);
    }
}

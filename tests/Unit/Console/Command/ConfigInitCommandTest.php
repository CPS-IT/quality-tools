<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Tests\Unit\Console\Command;

use Cpsit\QualityTools\Console\Command\ConfigInitCommand;
use Cpsit\QualityTools\Console\QualityToolsApplication;
use Cpsit\QualityTools\Service\FilesystemService;
use Cpsit\QualityTools\Tests\Unit\TestHelper;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @covers \Cpsit\QualityTools\Console\Command\ConfigInitCommand
 */
final class ConfigInitCommandTest extends TestCase
{
    private ConfigInitCommand $command;
    private CommandTester $commandTester;
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = TestHelper::createTempDirectory('config_init_test_');

        // Create a basic project structure
        TestHelper::createComposerJson($this->tempDir, [
            'name' => 'test/project',
            'type' => 'project',
        ]);

        // Set up command with application
        TestHelper::withEnvironment(
            ['QT_PROJECT_ROOT' => $this->tempDir],
            function (): void {
                $app = new QualityToolsApplication();
                $this->command = new ConfigInitCommand(new FilesystemService());
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
        self::assertSame('config:init', $this->command->getName());
        self::assertSame('Initialize YAML configuration file', $this->command->getDescription());

        // Check options
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

        // Extension template should have specific paths
        self::assertStringContainsString('Classes/', $content);
        self::assertStringContainsString('Configuration/', $content);
        self::assertStringContainsString('Tests/', $content);
        self::assertStringContainsString('level: 8', $content); // Higher PHPStan level for extensions
        self::assertStringContainsString('parallel: false', $content); // Extensions typically don't use parallel
    }

    public function testExecuteSitePackageTemplate(): void
    {
        $exitCode = $this->commandTester->execute(['--template' => 'typo3-site-package']);

        self::assertSame(Command::SUCCESS, $exitCode);

        $output = $this->commandTester->getDisplay();
        self::assertStringContainsString('Template used: TYPO3 Site Package', $output);

        $configFile = $this->tempDir . '/.quality-tools.yaml';
        $content = file_get_contents($configFile);

        // Site package template should have typical paths
        self::assertStringContainsString('packages/', $content);
        self::assertStringContainsString('config/', $content);
        self::assertStringContainsString('level: 6', $content); // Standard PHPStan level
    }

    public function testExecuteDistributionTemplate(): void
    {
        $exitCode = $this->commandTester->execute(['--template' => 'typo3-distribution']);

        self::assertSame(Command::SUCCESS, $exitCode);

        $output = $this->commandTester->getDisplay();
        self::assertStringContainsString('Template used: TYPO3 Distribution', $output);

        $configFile = $this->tempDir . '/.quality-tools.yaml';
        $content = file_get_contents($configFile);

        // Distribution template should have comprehensive paths
        self::assertStringContainsString('packages/', $content);
        self::assertStringContainsString('config/system/', $content);
        self::assertStringContainsString('config/sites/', $content);
        self::assertStringContainsString('memory_limit: "2G"', $content); // Higher memory for distributions
        self::assertStringContainsString('max_processes: 8', $content); // More processes for distributions
    }

    public function testExecuteInvalidTemplate(): void
    {
        $exitCode = $this->commandTester->execute(['--template' => 'invalid-template']);

        self::assertSame(Command::FAILURE, $exitCode);

        $output = $this->commandTester->getDisplay();
        self::assertStringContainsString('Invalid template "invalid-template"', $output);
        self::assertStringContainsString('Available templates:', $output);

        // No file should be created
        $configFile = $this->tempDir . '/.quality-tools.yaml';
        self::assertFileDoesNotExist($configFile);
    }

    public function testExecuteWithExistingConfiguration(): void
    {
        // Create existing configuration
        $existingConfig = $this->tempDir . '/.quality-tools.yaml';
        file_put_contents($existingConfig, 'existing-content');

        $exitCode = $this->commandTester->execute([]);

        self::assertSame(Command::SUCCESS, $exitCode);

        $output = $this->commandTester->getDisplay();
        self::assertStringContainsString('Configuration file already exists', $output);
        self::assertStringContainsString('Use --force to overwrite', $output);

        // File should not be modified
        self::assertSame('existing-content', file_get_contents($existingConfig));
    }

    public function testExecuteWithForceFlag(): void
    {
        // Create existing configuration
        $existingConfig = $this->tempDir . '/.quality-tools.yaml';
        file_put_contents($existingConfig, 'existing-content');

        $exitCode = $this->commandTester->execute(['--force' => true]);

        self::assertSame(Command::SUCCESS, $exitCode);

        $output = $this->commandTester->getDisplay();
        self::assertStringContainsString('Created configuration file', $output);

        // File should be overwritten
        $content = file_get_contents($existingConfig);
        self::assertNotSame('existing-content', $content);
        self::assertStringContainsString('quality-tools:', $content);
    }

    public function testExecuteWithExistingQualityToolsYaml(): void
    {
        // Create existing quality-tools.yaml (different file)
        $existingConfig = $this->tempDir . '/quality-tools.yaml';
        file_put_contents($existingConfig, 'existing-content');

        $exitCode = $this->commandTester->execute([]);

        self::assertSame(Command::SUCCESS, $exitCode);

        $output = $this->commandTester->getDisplay();
        self::assertStringContainsString('Configuration file already exists', $output);
        self::assertStringContainsString('Use --force to overwrite', $output);

        // New file should not be created
        self::assertFileDoesNotExist($this->tempDir . '/.quality-tools.yaml');
    }

    public function testProjectNameDetectionFromComposer(): void
    {
        // Create composer.json with specific name
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
        // Remove composer.json to test fallback
        unlink($this->tempDir . '/composer.json');

        $exitCode = $this->commandTester->execute([]);

        self::assertSame(Command::SUCCESS, $exitCode);

        $configFile = $this->tempDir . '/.quality-tools.yaml';
        $content = file_get_contents($configFile);

        // Should use directory name as fallback
        $directoryName = basename($this->tempDir);
        self::assertStringContainsString($directoryName, $content);
    }

    public function testFileWriteError(): void
    {
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
        // Test command help directly from command definition instead of executing with --help
        self::assertSame('Initialize YAML configuration file', $this->command->getDescription());
        self::assertStringContainsString('.quality-tools.yaml', $this->command->getHelp());

        // Check that options are properly defined
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

    public function testNextStepsInOutput(): void
    {
        $exitCode = $this->commandTester->execute([]);

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
                    $app = new QualityToolsApplication();
                    $command = new ConfigInitCommand(new FilesystemService());
                    $command->setApplication($app);
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
}

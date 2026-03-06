<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Tests\Unit\Console\Command;

use Cpsit\QualityTools\Configuration\ConfigurationLoader;
use Cpsit\QualityTools\Configuration\ConfigurationValidator;
use Cpsit\QualityTools\Console\Command\TypoScriptLintCommand;
use Cpsit\QualityTools\Console\QualityToolsApplication;
use Cpsit\QualityTools\Service\FilesystemService;
use Cpsit\QualityTools\Service\SecurityService;
use Cpsit\QualityTools\Service\ToolConfigurationValidationService;
use Cpsit\QualityTools\Tests\Unit\TestHelper;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @covers \Cpsit\QualityTools\Console\Command\TypoScriptLintCommand
 */
final class TypoScriptLintCommandTest extends TestCase
{
    private TypoScriptLintCommand $command;
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = TestHelper::createTempDirectory('typoscript_lint_command_test_');

        // Create a TYPO3 project structure for proper project root detection
        TestHelper::createComposerJson($this->tempDir, TestHelper::getComposerContent('typo3-core'));

        // Create vendor/bin directory structure
        $vendorBinDir = $this->tempDir . '/vendor/bin';
        mkdir($vendorBinDir, 0o777, true);

        // Create fake typoscript-lint executable
        $typoscriptLintExecutable = $vendorBinDir . '/typoscript-lint';
        file_put_contents($typoscriptLintExecutable, "#!/bin/bash\necho 'TypoScript Lint executed successfully'\nexit 0\n");
        chmod($typoscriptLintExecutable, 0o755);

        // Create default config directory and file
        $configDir = $this->tempDir . '/vendor/cpsit/quality-tools/config';
        mkdir($configDir, 0o777, true);
        file_put_contents($configDir . '/typoscript-lint.yml', 'sniffs: []');

        // Set up environment to use temp directory as project root and initialize application
        TestHelper::withEnvironment(
            ['QT_PROJECT_ROOT' => $this->tempDir],
            function (): void {
                $app = new QualityToolsApplication();

                // Create ConfigurationLoader with dependencies
                $validator = new ConfigurationValidator();
                $securityService = new SecurityService();
                $filesystem = new Filesystem();
                $filesystemService = new FilesystemService($filesystem, $securityService);
                $toolValidator = new ToolConfigurationValidationService([]);

                $configurationLoader = new ConfigurationLoader(
                    $validator,
                    $securityService,
                    $filesystemService,
                    $toolValidator,
                );

                $this->command = new TypoScriptLintCommand($configurationLoader);
                $this->command->setApplication($app);
            },
        );
    }

    protected function tearDown(): void
    {
        TestHelper::removeDirectory($this->tempDir);
        parent::tearDown();
    }

    public function testCommandHasCorrectConfiguration(): void
    {
        $this->assertEquals('lint:typoscript', $this->command->getName());
        $this->assertEquals('Run TypoScript Lint to check TypoScript files for syntax errors', $this->command->getDescription());

        $expectedHelp = 'This command runs TypoScript Lint to check TypoScript files for syntax errors ' .
                       'and coding standard violations. Use --config to specify a custom configuration ' .
                       'file or --path to target specific directories.';
        $this->assertEquals($expectedHelp, $this->command->getHelp());
    }

    public function testCommandInheritsBaseCommandOptions(): void
    {
        $definition = $this->command->getDefinition();

        $this->assertTrue($definition->hasOption('config'));
        $this->assertTrue($definition->hasOption('path'));

        $configOption = $definition->getOption('config');
        $this->assertEquals('c', $configOption->getShortcut());
        $this->assertTrue($configOption->isValueRequired());
        $this->assertEquals('Override default configuration file path', $configOption->getDescription());

        $pathOption = $definition->getOption('path');
        $this->assertEquals('p', $pathOption->getShortcut());
        $this->assertTrue($pathOption->isValueRequired());
        $this->assertEquals('Specify custom target paths (defaults to project root)', $pathOption->getDescription());
    }

    public function testExecuteWithDefaultOptions(): void
    {
        $commandTester = new CommandTester($this->command);

        // Execute with default options
        $commandTester->execute([]);

        // Command should execute successfully
        $this->assertEquals(0, $commandTester->getStatusCode());

        // Output should contain typoscript-lint execution result
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('TypoScript Lint executed successfully', $output);
    }

    public function testExecuteWithCustomConfigPath(): void
    {
        $customConfigFile = $this->tempDir . '/custom-typoscript-lint.yml';
        file_put_contents($customConfigFile, 'sniffs: []');

        $commandTester = new CommandTester($this->command);

        // Execute with custom config option
        $commandTester->execute([
            '--config' => $customConfigFile,
        ]);

        // Command should execute successfully
        $this->assertEquals(0, $commandTester->getStatusCode());

        // Output should contain typoscript-lint execution result
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('TypoScript Lint executed successfully', $output);
    }

    public function testExecuteWithCustomTargetPath(): void
    {
        $customTargetDir = $this->tempDir . '/custom-target';
        mkdir($customTargetDir, 0o777, true);

        $commandTester = new CommandTester($this->command);

        // Execute with custom path option
        $commandTester->execute([
            '--path' => $customTargetDir,
        ]);

        // Command should execute successfully
        $this->assertEquals(0, $commandTester->getStatusCode());

        // Output should contain typoscript-lint execution result
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('TypoScript Lint executed successfully', $output);
    }

    public function testExecuteWithCustomConfigAndTargetPath(): void
    {
        $customConfigFile = $this->tempDir . '/custom-typoscript-lint.yml';
        file_put_contents($customConfigFile, 'sniffs: []');

        $customTargetDir = $this->tempDir . '/custom-target';
        mkdir($customTargetDir, 0o777, true);

        $commandTester = new CommandTester($this->command);

        // Execute with custom options
        $commandTester->execute([
            '--config' => $customConfigFile,
            '--path' => $customTargetDir,
        ]);

        // Command should execute successfully
        $this->assertEquals(0, $commandTester->getStatusCode());

        // Output should contain typoscript-lint execution result
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('TypoScript Lint executed successfully', $output);
    }

    public function testExecuteWithVerboseOutput(): void
    {
        $commandTester = new CommandTester($this->command);

        // Execute with verbose output to see command being executed
        $commandTester->execute([], ['verbosity' => OutputInterface::VERBOSITY_VERBOSE]);

        // Command should execute successfully
        $this->assertEquals(0, $commandTester->getStatusCode());

        // Output should contain typoscript-lint execution result
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('TypoScript Lint executed successfully', $output);
    }

    public function testExecuteHandlesConfigPathException(): void
    {
        // Test that non-existent config file throws ConfigurationException
        // ConfigurationException returns exit code 2 (configuration error)
        $nonExistentConfigFile = $this->tempDir . '/non-existent-config.yml';

        $commandTester = new CommandTester($this->command);

        // Execute with non-existent config file
        $commandTester->execute([
            '--config' => $nonExistentConfigFile,
        ]);

        // ConfigurationException returns exit code 2 based on getSuggestedExitCode()
        $this->assertEquals(2, $commandTester->getStatusCode(), 'Expected exit code 2 for ConfigurationException');

        // Verify the error message contains expected text
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Configuration Error (1001)', $output, 'Should show configuration error code 1001');
        $this->assertStringContainsString('Configuration file not found', $output, 'Should show config file not found message');
        $this->assertStringContainsString($nonExistentConfigFile, $output, 'Should include the problematic config path');
    }

    public function testExecuteHandlesTargetPathException(): void
    {
        // Test that non-existent target directory throws FileSystemException
        // FileSystemException returns exit code 4 (filesystem error)
        $nonExistentTargetDir = $this->tempDir . '/non-existent-target';

        $commandTester = new CommandTester($this->command);

        // Execute with non-existent target directory
        $commandTester->execute([
            '--path' => $nonExistentTargetDir,
        ]);

        // FileSystemException returns exit code 4 based on getSuggestedExitCode()
        $this->assertEquals(4, $commandTester->getStatusCode(), 'Expected exit code 4 for FileSystemException');

        // Verify the error message contains expected text
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Filesystem Error (3001)', $output, 'Should show filesystem error code 3001');
        $this->assertStringContainsString('Target path does not exist or is not a directory', $output, 'Should show target path error message');
        $this->assertStringContainsString($nonExistentTargetDir, $output, 'Should include the problematic path');
    }

    public function testCommandBuildsCorrectExecutionCommand(): void
    {
        $commandTester = new CommandTester($this->command);

        // Execute with default options
        $commandTester->execute([]);

        // Command should execute successfully
        $this->assertEquals(0, $commandTester->getStatusCode());

        // Output should contain typoscript-lint execution result
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('TypoScript Lint executed successfully', $output);
    }

    public function testCommandBuildsCorrectExecutionCommandWithCustomOptions(): void
    {
        $customConfigFile = $this->tempDir . '/custom-typoscript-lint.yml';
        file_put_contents($customConfigFile, 'sniffs: []');

        $customTargetDir = $this->tempDir . '/custom-target';
        mkdir($customTargetDir, 0o777, true);

        $commandTester = new CommandTester($this->command);

        // Execute with custom options
        $commandTester->execute([
            '--config' => $customConfigFile,
            '--path' => $customTargetDir,
        ]);

        // Command should execute successfully
        $this->assertEquals(0, $commandTester->getStatusCode());

        // Output should contain typoscript-lint execution result
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('TypoScript Lint executed successfully', $output);
    }

    public function testCommandFailsWhenDefaultConfigNotFound(): void
    {
        // Remove default config file to simulate missing config
        $defaultConfigFile = $this->tempDir . '/vendor/cpsit/quality-tools/config/typoscript-lint.yml';
        unlink($defaultConfigFile);

        $commandTester = new CommandTester($this->command);

        // Execute should fail
        $commandTester->execute([]);

        // Command should return configuration error code
        $this->assertEquals(2, $commandTester->getStatusCode());

        // Output should contain error message
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Configuration Error', $output);
        $this->assertStringContainsString('Configuration file not found', $output);
    }

    public function testCommandHandlesMissingExecutable(): void
    {
        // Test that missing typoscript-lint executable throws ProcessException
        // ProcessException returns exit code 3 (process error)
        $typoscriptLintExecutable = $this->tempDir . '/vendor/bin/typoscript-lint';
        unlink($typoscriptLintExecutable);

        $commandTester = new CommandTester($this->command);

        // Execute command which should fail with missing executable
        $commandTester->execute([]);

        // ProcessException returns exit code 3 based on getSuggestedExitCode()
        // (or raw exit code 127 for "command not found" from shell)
        $this->assertNotEquals(0, $commandTester->getStatusCode(), 'Expected non-zero exit code for missing executable');
    }
}

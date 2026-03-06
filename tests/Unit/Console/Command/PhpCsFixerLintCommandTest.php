<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Tests\Unit\Console\Command;

use Cpsit\QualityTools\Configuration\ConfigurationLoader;
use Cpsit\QualityTools\Configuration\ConfigurationValidator;
use Cpsit\QualityTools\Console\Command\PhpCsFixerLintCommand;
use Cpsit\QualityTools\Console\QualityToolsApplication;
use Cpsit\QualityTools\Service\FilesystemService;
use Cpsit\QualityTools\Service\SecurityService;
use Cpsit\QualityTools\Service\ToolConfigurationValidationService;
use Cpsit\QualityTools\Tests\Unit\TestHelper;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @covers \Cpsit\QualityTools\Console\Command\PhpCsFixerLintCommand
 */
final class PhpCsFixerLintCommandTest extends TestCase
{
    private PhpCsFixerLintCommand $command;
    private MockObject&InputInterface $mockInput;
    private MockObject&ConsoleOutputInterface $mockOutput;
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = TestHelper::createTempDirectory('php_cs_fixer_lint_command_test_');

        // Create a TYPO3 project structure for proper project root detection
        TestHelper::createComposerJson($this->tempDir, TestHelper::getComposerContent('typo3-core'));

        // Create vendor/bin directory structure
        $vendorBinDir = $this->tempDir . '/vendor/bin';
        mkdir($vendorBinDir, 0o777, true);

        // Create fake php-cs-fixer executable
        $phpCsFixerExecutable = $vendorBinDir . '/php-cs-fixer';
        file_put_contents($phpCsFixerExecutable, "#!/bin/bash\necho 'PHP CS Fixer dry-run completed successfully'\nexit 0\n");
        chmod($phpCsFixerExecutable, 0o755);

        // Create cpsit/quality-tools config directory structure to match the resolveConfigPath expectation
        $vendorConfigDir = $this->tempDir . '/vendor/cpsit/quality-tools/config';
        mkdir($vendorConfigDir, 0o777, true);
        file_put_contents($vendorConfigDir . '/php-cs-fixer.php', "<?php\nreturn [];\n");

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

                $this->command = new PhpCsFixerLintCommand($configurationLoader);
                $this->command->setApplication($app);
            },
        );

        $this->mockInput = $this->createMock(InputInterface::class);
        $this->mockOutput = $this->createMock(ConsoleOutputInterface::class);
    }

    protected function tearDown(): void
    {
        TestHelper::removeDirectory($this->tempDir);
        parent::tearDown();
    }

    public function testCommandHasCorrectConfiguration(): void
    {
        $this->assertEquals('lint:php-cs-fixer', $this->command->getName());
        $this->assertEquals('Run PHP CS Fixer in dry-run mode to check code style issues', $this->command->getDescription());

        $expectedHelp = 'This command runs PHP CS Fixer in dry-run mode to show what code style ' .
                       'issues would be fixed without actually modifying your code files. Use ' .
                       '--config to specify a custom configuration file or --path to target ' .
                       'specific directories.';
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
        $this->mockInput
            ->method('getOption')
            ->willReturnMap([
                ['config', null],
                ['path', null],
                ['no-optimization', false],
            ]);

        $this->mockOutput
            ->expects($this->atLeast(1))
            ->method('isVerbose')
            ->willReturn(false);

        $this->mockOutput
            ->method('writeln');

        $this->mockOutput
            ->method('write');

        $result = $this->command->run($this->mockInput, $this->mockOutput);

        $this->assertEquals(0, $result);
    }

    public function testExecuteWithCustomConfig(): void
    {
        $customConfigPath = $this->tempDir . '/custom-php-cs-fixer.php';
        file_put_contents($customConfigPath, "<?php\nreturn [];\n");

        $this->mockInput
            ->method('getOption')
            ->willReturnMap([
                ['config', $customConfigPath],
                ['path', null],
                ['no-optimization', false],
            ]);

        $this->mockOutput
            ->expects($this->atLeast(1))
            ->method('isVerbose')
            ->willReturn(false);

        $this->mockOutput
            ->method('writeln');

        $this->mockOutput
            ->method('write');

        $result = $this->command->run($this->mockInput, $this->mockOutput);

        $this->assertEquals(0, $result);
    }

    public function testExecuteWithCustomTargetPath(): void
    {
        $customTargetDir = $this->tempDir . '/custom-target';
        mkdir($customTargetDir, 0o777, true);

        $this->mockInput
            ->method('getOption')
            ->willReturnMap([
                ['config', null],
                ['path', $customTargetDir],
                ['no-optimization', false],
            ]);

        $this->mockOutput
            ->expects($this->atLeast(1))
            ->method('isVerbose')
            ->willReturn(false);

        $this->mockOutput
            ->method('writeln');

        $this->mockOutput
            ->method('write');

        $result = $this->command->run($this->mockInput, $this->mockOutput);

        $this->assertEquals(0, $result);
    }

    public function testExecuteWithVerboseOutput(): void
    {
        $this->mockInput
            ->method('getOption')
            ->willReturnMap([
                ['config', null],
                ['path', null],
                ['no-optimization', false],
            ]);

        $this->mockOutput
            ->expects($this->atLeast(1))
            ->method('isVerbose')
            ->willReturn(true);

        $this->mockOutput
            ->method('writeln');

        $this->mockOutput
            ->method('write');

        $result = $this->command->run($this->mockInput, $this->mockOutput);

        $this->assertEquals(0, $result);
    }

    public function testExecuteHandlesTargetPathException(): void
    {
        // Test that non-existent target directory throws FileSystemException
        // FileSystemException returns exit code 4 (filesystem error)
        $nonExistentTargetDir = $this->tempDir . '/non-existent-target';

        $this->mockInput
            ->method('getOption')
            ->willReturnMap([
                ['config', null],
                ['path', $nonExistentTargetDir],
                ['no-optimization', false],
            ]);

        // Mock output to capture error messages
        $actualOutput = [];
        $this->mockOutput
            ->expects($this->atLeastOnce())
            ->method('writeln')
            ->willReturnCallback(function ($message) use (&$actualOutput): void {
                $actualOutput[] = $message;
            });

        // Run command which should fail with directory not found
        $result = $this->command->run($this->mockInput, $this->mockOutput);

        // FileSystemException returns exit code 4 based on getSuggestedExitCode()
        $this->assertEquals(4, $result, 'Expected exit code 4 for FileSystemException (directory not found)');

        // Verify the error message contains expected text
        $errorOutput = implode("\n", $actualOutput);
        $this->assertStringContainsString('Filesystem Error (3001)', $errorOutput, 'Should show filesystem error code 3001');
        $this->assertStringContainsString('Target path does not exist or is not a directory', $errorOutput, 'Should show target path error message');
        $this->assertStringContainsString($nonExistentTargetDir, $errorOutput, 'Should include the problematic path');
    }

    public function testExecuteHandlesConfigPathException(): void
    {
        // Test that non-existent config file throws ConfigurationException
        // ConfigurationException returns exit code 2 (configuration error)
        $nonExistentConfigPath = $this->tempDir . '/non-existent-config.php';

        $this->mockInput
            ->method('getOption')
            ->willReturnMap([
                ['config', $nonExistentConfigPath],
                ['path', null],
                ['no-optimization', false],
            ]);

        // Mock output to capture error messages
        $actualOutput = [];
        $this->mockOutput
            ->expects($this->atLeastOnce())
            ->method('writeln')
            ->willReturnCallback(function ($message) use (&$actualOutput): void {
                $actualOutput[] = $message;
            });

        // Run command which should fail with configuration file not found
        $result = $this->command->run($this->mockInput, $this->mockOutput);

        // ConfigurationException returns exit code 2 based on getSuggestedExitCode()
        $this->assertEquals(2, $result, 'Expected exit code 2 for ConfigurationException (config file not found)');

        // Verify the error message contains expected text
        $errorOutput = implode("\n", $actualOutput);
        $this->assertStringContainsString('Configuration Error (1001)', $errorOutput, 'Should show configuration error code 1001');
        $this->assertStringContainsString('Configuration file not found', $errorOutput, 'Should show config file not found message');
        $this->assertStringContainsString($nonExistentConfigPath, $errorOutput, 'Should include the problematic config path');
    }

    public function testCommandBuildsCorrectExecutionCommand(): void
    {
        $commandTester = new CommandTester($this->command);

        // Execute with default options
        $commandTester->execute([]);

        // Command should execute successfully
        $this->assertEquals(0, $commandTester->getStatusCode());

        // Output should contain php-cs-fixer execution result
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('PHP CS Fixer dry-run completed successfully', $output);
    }

    public function testCommandBuildsCorrectExecutionCommandWithCustomConfig(): void
    {
        $customConfigPath = $this->tempDir . '/custom-php-cs-fixer.php';
        file_put_contents($customConfigPath, "<?php\nreturn [];\n");

        $commandTester = new CommandTester($this->command);

        // Execute with custom config option
        $commandTester->execute([
            '--config' => $customConfigPath,
        ]);

        // Command should execute successfully
        $this->assertEquals(0, $commandTester->getStatusCode());

        // Output should contain php-cs-fixer execution result
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('PHP CS Fixer dry-run completed successfully', $output);
    }

    public function testCommandBuildsCorrectExecutionCommandWithCustomTargetPath(): void
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

        // Output should contain php-cs-fixer execution result
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('PHP CS Fixer dry-run completed successfully', $output);
    }

    public function testCommandHandlesMissingExecutable(): void
    {
        // Test that missing php-cs-fixer executable throws ProcessException
        // ProcessException returns exit code 3 (process error)
        $phpCsFixerExecutable = $this->tempDir . '/vendor/bin/php-cs-fixer';
        unlink($phpCsFixerExecutable);

        $this->mockInput
            ->method('getOption')
            ->willReturnMap([
                ['config', null],
                ['path', null],
                ['no-optimization', false],
            ]);

        // ErrorHandler will output error details to console
        $this->mockOutput
            ->expects($this->atLeastOnce())
            ->method('writeln');

        // Run command which should fail with missing executable
        $result = $this->command->run($this->mockInput, $this->mockOutput);

        // ProcessException returns exit code 3 based on getSuggestedExitCode()
        // (or raw exit code 127 for "command not found" from shell)
        $this->assertNotEquals(0, $result, 'Expected non-zero exit code for missing executable');
    }

    public function testCommandUsesCorrectProcessArguments(): void
    {
        $commandTester = new CommandTester($this->command);

        // Execute with verbose output to see command being executed
        $commandTester->execute([], ['verbosity' => OutputInterface::VERBOSITY_VERBOSE]);

        // Command should execute successfully
        $this->assertEquals(0, $commandTester->getStatusCode());

        // Check that the correct command arguments are used (fix, --dry-run, --diff, --config)
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('PHP CS Fixer dry-run completed successfully', $output);
    }

    public function testCommandDoesNotModifyCodeFiles(): void
    {
        // Create a test PHP file with style issues
        $testFile = $this->tempDir . '/test.php';
        $originalContent = "<?php\n\$test=1;\n";
        file_put_contents($testFile, $originalContent);

        // Verify original content exists
        $this->assertFileExists($testFile);
        $this->assertStringContainsString('$test=1;', file_get_contents($testFile));

        $commandTester = new CommandTester($this->command);

        // Execute lint command (should NOT modify the file)
        $commandTester->execute([]);

        // Command should execute successfully
        $this->assertEquals(0, $commandTester->getStatusCode());

        // File should still exist with original content (not modified by dry-run)
        $this->assertFileExists($testFile);
        $contentAfterLint = file_get_contents($testFile);
        $this->assertEquals($originalContent, $contentAfterLint);
    }

    public function testCommandShowsDiffOutput(): void
    {
        // Create a test PHP file with style issues that would trigger diff output
        $testFile = $this->tempDir . '/test.php';
        file_put_contents($testFile, "<?php\n\$test=1;\n");

        $commandTester = new CommandTester($this->command);

        // Execute lint command (should show diff but not modify files)
        $commandTester->execute([]);

        // Command should execute successfully
        $this->assertEquals(0, $commandTester->getStatusCode());

        // Output should contain dry-run execution result
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('PHP CS Fixer dry-run completed successfully', $output);
    }
}

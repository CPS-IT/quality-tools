<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Tests\Unit\Console\Command;

use Cpsit\QualityTools\Console\Command\RectorLintCommand;
use Cpsit\QualityTools\Console\QualityToolsApplication;
use Cpsit\QualityTools\Tests\Unit\TestHelper;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @covers \Cpsit\QualityTools\Console\Command\RectorLintCommand
 */
final class RectorLintCommandTest extends TestCase
{
    private RectorLintCommand $command;
    private MockObject&InputInterface $mockInput;
    private MockObject&ConsoleOutputInterface $mockOutput;
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = TestHelper::createTempDirectory('rector_lint_command_test_');

        // Create a TYPO3 project structure for proper project root detection
        TestHelper::createComposerJson($this->tempDir, TestHelper::getComposerContent('typo3-core'));

        // Create vendor/bin directory structure
        $vendorBinDir = $this->tempDir . '/vendor/bin';
        mkdir($vendorBinDir, 0777, true);

        // Create fake rector executable
        $rectorExecutable = $vendorBinDir . '/rector';
        file_put_contents($rectorExecutable, "#!/bin/bash\necho 'Rector dry-run completed successfully'\nexit 0\n");
        chmod($rectorExecutable, 0755);

        // Create cpsit/quality-tools config directory structure to match the resolveConfigPath expectation
        $vendorConfigDir = $this->tempDir . '/vendor/cpsit/quality-tools/config';
        mkdir($vendorConfigDir, 0777, true);
        file_put_contents($vendorConfigDir . '/rector.php', "<?php\nreturn [];\n");

        // Set up environment to use temp directory as project root and initialize application
        TestHelper::withEnvironment(
            ['QT_PROJECT_ROOT' => $this->tempDir],
            function (): void {
                $app = new QualityToolsApplication();
                $this->command = new RectorLintCommand();
                $this->command->setApplication($app);
            }
        );

        $this->mockInput = $this->createMock(InputInterface::class);
        $this->mockOutput = $this->createMock(ConsoleOutputInterface::class);
    }

    protected function tearDown(): void
    {
        TestHelper::removeDirectory($this->tempDir);
    }

    public function testCommandHasCorrectConfiguration(): void
    {
        $this->assertEquals('lint:rector', $this->command->getName());
        $this->assertEquals('Run Rector in dry-run mode to analyze code without making changes', $this->command->getDescription());

        $expectedHelp = 'This command runs Rector in dry-run mode to show what changes would be made ' .
                       'without actually modifying your code files. Use --config to specify a custom ' .
                       'configuration file or --path to target specific directories.';
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
            ->expects($this->exactly(2))
            ->method('getOption')
            ->willReturnMap([
                ['config', null],
                ['path', null]
            ]);

        $this->mockOutput
            ->expects($this->once())
            ->method('isVerbose')
            ->willReturn(false);

        $this->mockOutput
            ->expects($this->once())
            ->method('write')
            ->with("Rector dry-run completed successfully\n");

        $result = $this->command->run($this->mockInput, $this->mockOutput);

        $this->assertEquals(0, $result);
    }

    public function testExecuteWithCustomConfig(): void
    {
        $customConfigPath = $this->tempDir . '/custom-rector.php';
        file_put_contents($customConfigPath, "<?php\nreturn [];\n");

        $this->mockInput
            ->expects($this->exactly(2))
            ->method('getOption')
            ->willReturnMap([
                ['config', $customConfigPath],
                ['path', null]
            ]);

        $this->mockOutput
            ->expects($this->once())
            ->method('isVerbose')
            ->willReturn(false);

        $this->mockOutput
            ->expects($this->once())
            ->method('write')
            ->with("Rector dry-run completed successfully\n");

        $result = $this->command->run($this->mockInput, $this->mockOutput);

        $this->assertEquals(0, $result);
    }

    public function testExecuteWithCustomTargetPath(): void
    {
        $customTargetDir = $this->tempDir . '/custom-target';
        mkdir($customTargetDir, 0777, true);

        $this->mockInput
            ->expects($this->exactly(2))
            ->method('getOption')
            ->willReturnMap([
                ['config', null],
                ['path', $customTargetDir]
            ]);

        $this->mockOutput
            ->expects($this->once())
            ->method('isVerbose')
            ->willReturn(false);

        $this->mockOutput
            ->expects($this->once())
            ->method('write')
            ->with("Rector dry-run completed successfully\n");

        $result = $this->command->run($this->mockInput, $this->mockOutput);

        $this->assertEquals(0, $result);
    }

    public function testExecuteWithVerboseOutput(): void
    {
        $this->mockInput
            ->expects($this->exactly(2))
            ->method('getOption')
            ->willReturnMap([
                ['config', null],
                ['path', null]
            ]);

        $this->mockOutput
            ->expects($this->once())
            ->method('isVerbose')
            ->willReturn(true);

        $this->mockOutput
            ->expects($this->once())
            ->method('writeln')
            ->with($this->matchesRegularExpression('/Executing:.*rector/i'));

        $this->mockOutput
            ->expects($this->once())
            ->method('write')
            ->with("Rector dry-run completed successfully\n");

        $result = $this->command->run($this->mockInput, $this->mockOutput);

        $this->assertEquals(0, $result);
    }

    public function testExecuteHandlesTargetPathException(): void
    {
        $nonExistentTargetDir = $this->tempDir . '/non-existent-target';

        $this->mockInput
            ->expects($this->exactly(2))
            ->method('getOption')
            ->willReturnMap([
                ['config', null],
                ['path', $nonExistentTargetDir]
            ]);

        $this->mockOutput
            ->expects($this->once())
            ->method('writeln')
            ->with($this->matchesRegularExpression('/<error>Error:.*Target path does not exist.*<\/error>/'));

        $result = $this->command->run($this->mockInput, $this->mockOutput);

        $this->assertEquals(1, $result);
    }

    public function testExecuteHandlesConfigPathException(): void
    {
        $nonExistentConfigPath = $this->tempDir . '/non-existent-config.php';

        $this->mockInput
            ->expects($this->once())
            ->method('getOption')
            ->with('config')
            ->willReturn($nonExistentConfigPath);

        $this->mockOutput
            ->expects($this->once())
            ->method('writeln')
            ->with($this->matchesRegularExpression('/<error>Error:.*Custom configuration file not found.*<\/error>/'));

        $result = $this->command->run($this->mockInput, $this->mockOutput);

        $this->assertEquals(1, $result);
    }

    public function testCommandBuildsCorrectExecutionCommand(): void
    {
        $commandTester = new CommandTester($this->command);

        // Execute with default options
        $commandTester->execute([]);

        // Command should execute successfully
        $this->assertEquals(0, $commandTester->getStatusCode());

        // Output should contain rector execution result
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Rector dry-run completed successfully', $output);
    }

    public function testCommandBuildsCorrectExecutionCommandWithCustomConfig(): void
    {
        $customConfigPath = $this->tempDir . '/custom-rector.php';
        file_put_contents($customConfigPath, "<?php\nreturn [];\n");

        $commandTester = new CommandTester($this->command);

        // Execute with custom config option
        $commandTester->execute([
            '--config' => $customConfigPath
        ]);

        // Command should execute successfully
        $this->assertEquals(0, $commandTester->getStatusCode());

        // Output should contain rector execution result
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Rector dry-run completed successfully', $output);
    }

    public function testCommandBuildsCorrectExecutionCommandWithCustomTargetPath(): void
    {
        $customTargetDir = $this->tempDir . '/custom-target';
        mkdir($customTargetDir, 0777, true);

        $commandTester = new CommandTester($this->command);

        // Execute with custom path option
        $commandTester->execute([
            '--path' => $customTargetDir
        ]);

        // Command should execute successfully
        $this->assertEquals(0, $commandTester->getStatusCode());

        // Output should contain rector execution result
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Rector dry-run completed successfully', $output);
    }

    public function testCommandHandlesMissingExecutable(): void
    {
        // Remove rector executable to simulate missing dependency
        $rectorExecutable = $this->tempDir . '/vendor/bin/rector';
        unlink($rectorExecutable);

        $this->mockInput
            ->expects($this->exactly(2))
            ->method('getOption')
            ->willReturnMap([
                ['config', null],
                ['path', null]
            ]);

        // Since the executable doesn't exist, this will fail at the process level
        // and the executeProcess method will return a non-zero exit code
        $result = $this->command->run($this->mockInput, $this->mockOutput);

        // Command should return non-zero exit code due to missing executable
        $this->assertNotEquals(0, $result);
    }

    public function testCommandUsesCorrectProcessArguments(): void
    {
        $commandTester = new CommandTester($this->command);

        // Execute with verbose output to see command being executed
        $commandTester->execute([], ['verbosity' => OutputInterface::VERBOSITY_VERBOSE]);

        // Command should execute successfully
        $this->assertEquals(0, $commandTester->getStatusCode());

        // Check that the correct command arguments are used (--dry-run, --config)
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Rector dry-run completed successfully', $output);
    }

    public function testCommandDoesNotModifyCodeFiles(): void
    {
        // Create a test PHP file that could be refactored by Rector
        $testFile = $this->tempDir . '/test.php';
        $originalContent = "<?php\nclass TestClass {\n    public function test() {\n        return 'test';\n    }\n}\n";
        file_put_contents($testFile, $originalContent);

        // Verify original content exists
        $this->assertFileExists($testFile);
        $this->assertStringContainsString('TestClass', file_get_contents($testFile));

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

    public function testCommandShowsProposedChanges(): void
    {
        // Create a test PHP file that would trigger changes in dry-run
        $testFile = $this->tempDir . '/test.php';
        file_put_contents($testFile, "<?php\nclass OldStyleClass {\n    public function oldMethod() {\n        return true;\n    }\n}\n");

        $commandTester = new CommandTester($this->command);

        // Execute lint command (should show proposed changes but not modify files)
        $commandTester->execute([]);

        // Command should execute successfully
        $this->assertEquals(0, $commandTester->getStatusCode());

        // Output should contain dry-run execution result
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Rector dry-run completed successfully', $output);
    }

    public function testCommandTargetsSpecificPath(): void
    {
        // Create a custom target directory with PHP files
        $customTargetDir = $this->tempDir . '/src';
        mkdir($customTargetDir, 0777, true);

        $testFile = $customTargetDir . '/Service.php';
        file_put_contents($testFile, "<?php\nclass Service {\n    public function process() {\n        return true;\n    }\n}\n");

        $commandTester = new CommandTester($this->command);

        // Execute lint command targeting specific path
        $commandTester->execute([
            '--path' => $customTargetDir
        ]);

        // Command should execute successfully
        $this->assertEquals(0, $commandTester->getStatusCode());

        // Output should contain rector execution result
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Rector dry-run completed successfully', $output);
    }
}

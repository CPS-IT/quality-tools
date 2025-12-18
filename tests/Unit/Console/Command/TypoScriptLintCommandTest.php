<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Tests\Unit\Console\Command;

use Cpsit\QualityTools\Console\Command\TypoScriptLintCommand;
use Cpsit\QualityTools\Console\QualityToolsApplication;
use Cpsit\QualityTools\Tests\Unit\TestHelper;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @covers \Cpsit\QualityTools\Console\Command\TypoScriptLintCommand
 */
final class TypoScriptLintCommandTest extends TestCase
{
    private TypoScriptLintCommand $command;
    private MockObject&InputInterface $mockInput;
    private MockObject&ConsoleOutputInterface $mockOutput;
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = TestHelper::createTempDirectory('typoscript_lint_command_test_');

        // Create a TYPO3 project structure for proper project root detection
        TestHelper::createComposerJson($this->tempDir, TestHelper::getComposerContent('typo3-core'));

        // Create vendor/bin directory structure
        $vendorBinDir = $this->tempDir . '/vendor/bin';
        mkdir($vendorBinDir, 0777, true);

        // Create fake typoscript-lint executable
        $typoscriptLintExecutable = $vendorBinDir . '/typoscript-lint';
        file_put_contents($typoscriptLintExecutable, "#!/bin/bash\necho 'TypoScript Lint executed successfully'\nexit 0\n");
        chmod($typoscriptLintExecutable, 0755);

        // Create default config directory and file
        $configDir = $this->tempDir . '/vendor/cpsit/quality-tools/config';
        mkdir($configDir, 0777, true);
        file_put_contents($configDir . '/typoscript-lint.yml', 'sniffs: []');

        // Set up environment to use temp directory as project root and initialize application
        TestHelper::withEnvironment(
            ['QT_PROJECT_ROOT' => $this->tempDir],
            function (): void {
                $app = new QualityToolsApplication();
                $this->command = new TypoScriptLintCommand();
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
            ->method('writeln')
            ->with('<comment>Using configuration file path discovery (packages/**/Configuration/TypoScript)</comment>');

        $this->mockOutput
            ->expects($this->once())
            ->method('write')
            ->with("TypoScript Lint executed successfully\n");

        $result = $this->command->run($this->mockInput, $this->mockOutput);

        $this->assertEquals(0, $result);
    }

    public function testExecuteWithCustomConfigPath(): void
    {
        $customConfigFile = $this->tempDir . '/custom-typoscript-lint.yml';
        file_put_contents($customConfigFile, 'sniffs: []');

        $this->mockInput
            ->expects($this->exactly(2))
            ->method('getOption')
            ->willReturnMap([
                ['config', $customConfigFile],
                ['path', null]
            ]);

        $this->mockOutput
            ->expects($this->once())
            ->method('isVerbose')
            ->willReturn(false);

        $this->mockOutput
            ->expects($this->once())
            ->method('writeln')
            ->with('<comment>Using configuration file path discovery (packages/**/Configuration/TypoScript)</comment>');

        $this->mockOutput
            ->expects($this->once())
            ->method('write')
            ->with("TypoScript Lint executed successfully\n");

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
            ->method('writeln')
            ->with(sprintf('<comment>Analyzing custom path: %s</comment>', $customTargetDir));

        $this->mockOutput
            ->expects($this->once())
            ->method('write')
            ->with("TypoScript Lint executed successfully\n");

        $result = $this->command->run($this->mockInput, $this->mockOutput);

        $this->assertEquals(0, $result);
    }

    public function testExecuteWithCustomConfigAndTargetPath(): void
    {
        $customConfigFile = $this->tempDir . '/custom-typoscript-lint.yml';
        file_put_contents($customConfigFile, 'sniffs: []');

        $customTargetDir = $this->tempDir . '/custom-target';
        mkdir($customTargetDir, 0777, true);

        $this->mockInput
            ->expects($this->exactly(2))
            ->method('getOption')
            ->willReturnMap([
                ['config', $customConfigFile],
                ['path', $customTargetDir]
            ]);

        $this->mockOutput
            ->expects($this->once())
            ->method('isVerbose')
            ->willReturn(false);

        $this->mockOutput
            ->expects($this->once())
            ->method('writeln')
            ->with(sprintf('<comment>Analyzing custom path: %s</comment>', $customTargetDir));

        $this->mockOutput
            ->expects($this->once())
            ->method('write')
            ->with("TypoScript Lint executed successfully\n");

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
            ->expects($this->atLeast(1))
            ->method('writeln')
            ->with($this->logicalOr(
                '<comment>Using configuration file path discovery (packages/**/Configuration/TypoScript)</comment>',
                $this->matchesRegularExpression('/Executing:.*typoscript-lint/i')
            ));

        $this->mockOutput
            ->expects($this->once())
            ->method('write')
            ->with("TypoScript Lint executed successfully\n");

        $result = $this->command->run($this->mockInput, $this->mockOutput);

        $this->assertEquals(0, $result);
    }

    public function testExecuteHandlesConfigPathException(): void
    {
        $nonExistentConfigFile = $this->tempDir . '/non-existent-config.yml';

        $this->mockInput
            ->expects($this->once())
            ->method('getOption')
            ->with('config')
            ->willReturn($nonExistentConfigFile);

        $this->mockOutput
            ->expects($this->once())
            ->method('writeln')
            ->with($this->matchesRegularExpression('/<error>Error:.*Custom configuration file not found.*<\/error>/'));

        $result = $this->command->run($this->mockInput, $this->mockOutput);

        $this->assertEquals(1, $result);
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
        mkdir($customTargetDir, 0777, true);

        $commandTester = new CommandTester($this->command);

        // Execute with custom options
        $commandTester->execute([
            '--config' => $customConfigFile,
            '--path' => $customTargetDir
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

        // Command should return error code
        $this->assertEquals(1, $commandTester->getStatusCode());

        // Output should contain error message
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Error:', $output);
        $this->assertStringContainsString('Default configuration file not found', $output);
    }

    public function testCommandHandlesMissingExecutable(): void
    {
        // Remove typoscript-lint executable to simulate missing dependency
        $typoscriptLintExecutable = $this->tempDir . '/vendor/bin/typoscript-lint';
        unlink($typoscriptLintExecutable);

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
}

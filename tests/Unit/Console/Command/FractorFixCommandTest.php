<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Tests\Unit\Console\Command;

use Cpsit\QualityTools\Console\Command\FractorFixCommand;
use Cpsit\QualityTools\Console\QualityToolsApplication;
use Cpsit\QualityTools\Tests\Unit\TestHelper;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @covers \Cpsit\QualityTools\Console\Command\FractorFixCommand
 */
final class FractorFixCommandTest extends TestCase
{
    private FractorFixCommand $command;
    private MockObject&InputInterface $mockInput;
    private MockObject&ConsoleOutputInterface $mockOutput;
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = TestHelper::createTempDirectory('fractor_fix_command_test_');

        // Create a TYPO3 project structure for proper project root detection
        TestHelper::createComposerJson($this->tempDir, TestHelper::getComposerContent('typo3-core'));

        // Create vendor/bin directory structure
        $vendorBinDir = $this->tempDir . '/vendor/bin';
        mkdir($vendorBinDir, 0o777, true);

        // Create fake fractor executable
        $fractorExecutable = $vendorBinDir . '/fractor';
        file_put_contents($fractorExecutable, "#!/bin/bash\necho 'Fractor executed successfully'\nexit 0\n");
        chmod($fractorExecutable, 0o755);

        // Create default config directory and file
        $configDir = $this->tempDir . '/vendor/cpsit/quality-tools/config';
        mkdir($configDir, 0o777, true);
        file_put_contents($configDir . '/fractor.php', '<?php return [];');

        // Set up environment to use temp directory as project root and initialize application
        TestHelper::withEnvironment(
            ['QT_PROJECT_ROOT' => $this->tempDir],
            function (): void {
                $app = new QualityToolsApplication();
                $this->command = new FractorFixCommand();
                $this->command->setApplication($app);
            },
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
        $this->assertEquals('fix:fractor', $this->command->getName());
        $this->assertEquals('Run Fractor to apply TypoScript and code changes', $this->command->getDescription());

        $expectedHelp = 'This command runs Fractor to apply TypoScript and code changes to your files. ' .
                       'This will modify your files! Use --config to specify a custom configuration ' .
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

    public function testExecuteWithCustomConfigPath(): void
    {
        $customConfigFile = $this->tempDir . '/custom-fractor.php';
        file_put_contents($customConfigFile, '<?php return [];');

        $this->mockInput
            ->method('getOption')
            ->willReturnMap([
                ['config', $customConfigFile],
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

    public function testExecuteWithCustomConfigAndTargetPath(): void
    {
        $customConfigFile = $this->tempDir . '/custom-fractor.php';
        file_put_contents($customConfigFile, '<?php return [];');

        $customTargetDir = $this->tempDir . '/custom-target';
        mkdir($customTargetDir, 0o777, true);

        $this->mockInput
            ->method('getOption')
            ->willReturnMap([
                ['config', $customConfigFile],
                ['path', $customTargetDir],
                ['no-optimization', false],
            ]);

        $this->mockOutput
            ->expects($this->atLeast(1))
            ->method('isVerbose')
            ->willReturn(false);

        $this->mockOutput
            ->expects($this->once())
            ->method('write')
            ->with("Fractor executed successfully\n");

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

    public function testExecuteHandlesConfigPathException(): void
    {
        $nonExistentConfigFile = $this->tempDir . '/non-existent-config.php';

        $this->mockInput
            ->method('getOption')
            ->willReturnMap([
                ['config', $nonExistentConfigFile],
                ['path', null],
                ['no-optimization', false],
            ]);

        $this->mockOutput
            ->method('writeln');

        $result = $this->command->run($this->mockInput, $this->mockOutput);

        $this->assertEquals(2, $result);
    }

    public function testExecuteHandlesTargetPathException(): void
    {
        $nonExistentTargetDir = $this->tempDir . '/non-existent-target';

        $this->mockInput
            ->method('getOption')
            ->willReturnMap([
                ['config', null],
                ['path', $nonExistentTargetDir],
                ['no-optimization', false],
            ]);

        $this->mockOutput
            ->expects($this->atLeast(1))
            ->method('writeln');

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

        // Output should contain fractor execution result
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Fractor executed successfully', $output);
    }

    public function testCommandBuildsCorrectExecutionCommandWithCustomOptions(): void
    {
        $customConfigFile = $this->tempDir . '/custom-fractor.php';
        file_put_contents($customConfigFile, '<?php return [];');

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

        // Output should contain fractor execution result
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Fractor executed successfully', $output);
    }

    public function testCommandFailsWhenDefaultConfigNotFound(): void
    {
        // Remove default config file to simulate missing config
        $defaultConfigFile = $this->tempDir . '/vendor/cpsit/quality-tools/config/fractor.php';
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
        // Remove fractor executable to simulate missing dependency
        $fractorExecutable = $this->tempDir . '/vendor/bin/fractor';
        unlink($fractorExecutable);

        $this->mockInput
            ->method('getOption')
            ->willReturnMap([
                ['config', null],
                ['path', null],
                ['no-optimization', false],
            ]);

        // Since the executable doesn't exist, this will fail at the process level
        // and the executeProcess method will return a non-zero exit code
        $result = $this->command->run($this->mockInput, $this->mockOutput);

        // Command should return exit code 126 (command not executable) due to missing executable
        $this->assertEquals(126, $result);
    }
}

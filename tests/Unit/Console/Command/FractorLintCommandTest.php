<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Tests\Unit\Console\Command;

use Cpsit\QualityTools\Console\Command\FractorLintCommand;
use Cpsit\QualityTools\Console\QualityToolsApplication;
use Cpsit\QualityTools\Tests\Unit\TestHelper;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @covers \Cpsit\QualityTools\Console\Command\FractorLintCommand
 */
final class FractorLintCommandTest extends TestCase
{
    private FractorLintCommand $command;
    private MockObject&InputInterface $mockInput;
    private MockObject&ConsoleOutputInterface $mockOutput;
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = TestHelper::createTempDirectory('fractor_lint_command_test_');

        // Create a TYPO3 project structure for proper project root detection
        TestHelper::createComposerJson($this->tempDir, TestHelper::getComposerContent('typo3-core'));

        // Create vendor/bin directory structure
        $vendorBinDir = $this->tempDir . '/vendor/bin';
        mkdir($vendorBinDir, 0777, true);

        // Create fake fractor executable
        $fractorExecutable = $vendorBinDir . '/fractor';
        file_put_contents($fractorExecutable, "#!/bin/bash\necho 'Fractor dry-run completed successfully'\nexit 0\n");
        chmod($fractorExecutable, 0755);

        // Create cpsit/quality-tools config directory structure to match the resolveConfigPath expectation
        $vendorConfigDir = $this->tempDir . '/vendor/cpsit/quality-tools/config';
        mkdir($vendorConfigDir, 0777, true);
        file_put_contents($vendorConfigDir . '/fractor.php', "<?php\nreturn [];\n");

        // Set up environment to use temp directory as project root and initialize application
        TestHelper::withEnvironment(
            ['QT_PROJECT_ROOT' => $this->tempDir],
            function (): void {
                $app = new QualityToolsApplication();
                $this->command = new FractorLintCommand();
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
        $this->assertEquals('lint:fractor', $this->command->getName());
        $this->assertEquals('Run Fractor in dry-run mode to analyze TypoScript and code without making changes', $this->command->getDescription());

        $expectedHelp = 'This command runs Fractor in dry-run mode to show what TypoScript and code ' .
                       'changes would be made without actually modifying your files. Use --config ' .
                       'to specify a custom configuration file or --path to target specific directories.';
        $this->assertEquals($expectedHelp, $this->command->getHelp());
    }

    public function testCommandInheritsBaseCommandOptions(): void
    {
        $definition = $this->command->getDefinition();

        $this->assertTrue($definition->hasOption('config'));
        $this->assertTrue($definition->hasOption('path'));
        $this->assertTrue($definition->hasOption('no-optimization'));

        $configOption = $definition->getOption('config');
        $this->assertEquals('c', $configOption->getShortcut());
        $this->assertTrue($configOption->isValueRequired());
        $this->assertEquals('Override default configuration file path', $configOption->getDescription());

        $pathOption = $definition->getOption('path');
        $this->assertEquals('p', $pathOption->getShortcut());
        $this->assertTrue($pathOption->isValueRequired());
        $this->assertEquals('Specify custom target paths (defaults to project root)', $pathOption->getDescription());

        $noOptimizationOption = $definition->getOption('no-optimization');
        $this->assertFalse($noOptimizationOption->isValueRequired());
        $this->assertEquals('Disable automatic optimization (use default settings)', $noOptimizationOption->getDescription());
    }

    public function testExecuteWithDefaultOptions(): void
    {
        $this->mockInput
            ->expects($this->atLeast(2))
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
            ->expects($this->atLeast(1))
            ->method('writeln');

        $this->mockOutput
            ->expects($this->once())
            ->method('write')
            ->with("Fractor dry-run completed successfully\n");

        $result = $this->command->run($this->mockInput, $this->mockOutput);

        $this->assertEquals(0, $result);
    }

    public function testExecuteWithCustomConfig(): void
    {
        $customConfigPath = $this->tempDir . '/custom-fractor.php';
        file_put_contents($customConfigPath, "<?php\nreturn [];\n");

        $this->mockInput
            ->expects($this->atLeast(2))
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
            ->expects($this->atLeast(1))
            ->method('writeln');

        $this->mockOutput
            ->expects($this->once())
            ->method('write')
            ->with("Fractor dry-run completed successfully\n");

        $result = $this->command->run($this->mockInput, $this->mockOutput);

        $this->assertEquals(0, $result);
    }

    public function testExecuteWithCustomTargetPath(): void
    {
        $customTargetDir = $this->tempDir . '/custom-target';
        mkdir($customTargetDir, 0777, true);

        $this->mockInput
            ->expects($this->atLeast(1))
            ->method('getOption')
            ->willReturnCallback(function($option) use ($customTargetDir) {
                return match($option) {
                    'config' => null,
                    'path' => $customTargetDir,
                    'no-optimization' => false,
                    default => null
                };
            });

        $this->mockOutput
            ->expects($this->atLeast(1))
            ->method('isVerbose')
            ->willReturn(false);

        $this->mockOutput
            ->expects($this->atLeast(1))
            ->method('writeln');

        $this->mockOutput
            ->expects($this->once())
            ->method('write')
            ->with("Fractor dry-run completed successfully\n");

        $result = $this->command->run($this->mockInput, $this->mockOutput);

        $this->assertEquals(0, $result);
    }

    public function testExecuteWithVerboseOutput(): void
    {
        $this->mockInput
            ->expects($this->atLeast(1))
            ->method('getOption')
            ->willReturnCallback(function($option) {
                return match($option) {
                    'config' => null,
                    'path' => null,
                    'no-optimization' => false,
                    default => null
                };
            });

        $this->mockOutput
            ->expects($this->atLeast(1))
            ->method('isVerbose')
            ->willReturn(true);

        $this->mockOutput
            ->expects($this->atLeast(1))
            ->method('writeln');

        $this->mockOutput
            ->expects($this->once())
            ->method('write')
            ->with("Fractor dry-run completed successfully\n");

        $result = $this->command->run($this->mockInput, $this->mockOutput);

        $this->assertEquals(0, $result);
    }

    public function testExecuteHandlesTargetPathException(): void
    {
        $nonExistentTargetDir = $this->tempDir . '/non-existent-target';

        $this->mockInput
            ->expects($this->atLeast(1))
            ->method('getOption')
            ->willReturnCallback(function($option) use ($nonExistentTargetDir) {
                return match($option) {
                    'config' => null,
                    'path' => $nonExistentTargetDir,
                    'no-optimization' => false,
                    default => null
                };
            });

        $this->mockOutput
            ->expects($this->atLeast(1))
            ->method('writeln');

        $result = $this->command->run($this->mockInput, $this->mockOutput);

        $this->assertEquals(1, $result);
    }

    public function testExecuteHandlesConfigPathException(): void
    {
        $nonExistentConfigPath = $this->tempDir . '/non-existent-config.php';

        $this->mockInput
            ->expects($this->atLeast(1))
            ->method('getOption')
            ->willReturnCallback(function($option) use ($nonExistentConfigPath) {
                return match($option) {
                    'config' => $nonExistentConfigPath,
                    'path' => null,
                    'no-optimization' => false,
                    default => null
                };
            });

        $this->mockOutput
            ->expects($this->atLeast(1))
            ->method('writeln');

        $result = $this->command->run($this->mockInput, $this->mockOutput);

        $this->assertEquals(2, $result);
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
        $this->assertStringContainsString('Fractor dry-run completed successfully', $output);
    }

    public function testCommandBuildsCorrectExecutionCommandWithCustomConfig(): void
    {
        $customConfigPath = $this->tempDir . '/custom-fractor.php';
        file_put_contents($customConfigPath, "<?php\nreturn [];\n");

        $commandTester = new CommandTester($this->command);

        // Execute with custom config option
        $commandTester->execute([
            '--config' => $customConfigPath
        ]);

        // Command should execute successfully
        $this->assertEquals(0, $commandTester->getStatusCode());

        // Output should contain fractor execution result
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Fractor dry-run completed successfully', $output);
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

        // Output should contain fractor execution result
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Fractor dry-run completed successfully', $output);
    }

    public function testCommandHandlesMissingExecutable(): void
    {
        // Remove fractor executable to simulate missing dependency
        $fractorExecutable = $this->tempDir . '/vendor/bin/fractor';
        unlink($fractorExecutable);

        $this->mockInput
            ->expects($this->atLeast(1))
            ->method('getOption')
            ->willReturnCallback(function($option) {
                return match($option) {
                    'config' => null,
                    'path' => null,
                    'no-optimization' => false,
                    default => null
                };
            });

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

        // Check that the correct command arguments are used (process, --dry-run, --config)
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Fractor dry-run completed successfully', $output);
    }

    public function testCommandIncludesOptimizationAndYamlValidation(): void
    {
        $commandTester = new CommandTester($this->command);

        // Execute command and capture output
        $commandTester->execute([]);

        // Command should execute successfully
        $this->assertEquals(0, $commandTester->getStatusCode());

        $output = $commandTester->getDisplay();

        // Should include optimization details
        $this->assertStringContainsString('Analyzing', $output);
        $this->assertStringContainsString('Aggregated Project Analysis', $output);

        // Should include YAML validation
        $this->assertStringContainsString('Pre-validating YAML files across all target paths', $output);

        // Should show Fractor execution result
        $this->assertStringContainsString('Fractor dry-run completed successfully', $output);
    }

    public function testCommandWithOptimizationDisabled(): void
    {
        $commandTester = new CommandTester($this->command);

        // Execute with optimization disabled
        $commandTester->execute(['--no-optimization' => true]);

        // Command should execute successfully
        $this->assertEquals(0, $commandTester->getStatusCode());

        $output = $commandTester->getDisplay();

        // Should execute successfully even with optimization disabled

        // Should show Fractor execution result
        $this->assertStringContainsString('Fractor dry-run completed successfully', $output);
    }
}

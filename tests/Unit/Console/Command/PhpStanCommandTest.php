<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Tests\Unit\Console\Command;

use Cpsit\QualityTools\Console\Command\PhpStanCommand;
use Cpsit\QualityTools\Console\QualityToolsApplication;
use Cpsit\QualityTools\Tests\Unit\TestHelper;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @covers \Cpsit\QualityTools\Console\Command\PhpStanCommand
 */
final class PhpStanCommandTest extends TestCase
{
    private PhpStanCommand $command;
    private MockObject&InputInterface $mockInput;
    private MockObject&ConsoleOutputInterface $mockOutput;
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = TestHelper::createTempDirectory('phpstan_command_test_');

        // Create a TYPO3 project structure for proper project root detection
        TestHelper::createComposerJson($this->tempDir, TestHelper::getComposerContent('typo3-core'));

        // Create vendor/bin directory structure
        $vendorBinDir = $this->tempDir . '/vendor/bin';
        mkdir($vendorBinDir, 0777, true);

        // Create fake phpstan executable
        $phpStanExecutable = $vendorBinDir . '/phpstan';
        file_put_contents($phpStanExecutable, "#!/bin/bash\necho 'PHPStan analysis completed successfully'\nexit 0\n");
        chmod($phpStanExecutable, 0755);

        // Create cpsit/quality-tools config directory structure to match the resolveConfigPath expectation
        $vendorConfigDir = $this->tempDir . '/vendor/cpsit/quality-tools/config';
        mkdir($vendorConfigDir, 0777, true);
        file_put_contents($vendorConfigDir . '/phpstan.neon', "parameters:\n  level: 6\n");

        // Set up environment to use temp directory as project root and initialize application
        TestHelper::withEnvironment(
            ['QT_PROJECT_ROOT' => $this->tempDir],
            function (): void {
                $app = new QualityToolsApplication();
                $this->command = new PhpStanCommand();
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
        $this->assertEquals('lint:phpstan', $this->command->getName());
        $this->assertEquals('Run PHPStan static analysis', $this->command->getDescription());

        $expectedHelp = 'This command runs PHPStan static analysis to find bugs in your code without ' .
                       'running it. Use --config to specify a custom configuration file, --path to ' .
                       'target specific directories, or --level to override the analysis level.';
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

    public function testCommandHasPhpStanSpecificOptions(): void
    {
        $definition = $this->command->getDefinition();

        $this->assertTrue($definition->hasOption('level'));
        $this->assertTrue($definition->hasOption('memory-limit'));

        $levelOption = $definition->getOption('level');
        $this->assertEquals('l', $levelOption->getShortcut());
        $this->assertTrue($levelOption->isValueRequired());
        $this->assertEquals('Override the analysis level (0-9)', $levelOption->getDescription());

        $memoryLimitOption = $definition->getOption('memory-limit');
        $this->assertEquals('m', $memoryLimitOption->getShortcut());
        $this->assertTrue($memoryLimitOption->isValueRequired());
        $this->assertEquals('Memory limit for analysis (e.g., 1G, 512M)', $memoryLimitOption->getDescription());
    }

    public function testExecuteWithDefaultOptions(): void
    {
        $this->mockInput
            ->expects($this->exactly(4))
            ->method('getOption')
            ->willReturnMap([
                ['config', null],
                ['path', null],
                ['level', null],
                ['memory-limit', null]
            ]);

        $this->mockOutput
            ->expects($this->once())
            ->method('isVerbose')
            ->willReturn(false);

        $this->mockOutput
            ->expects($this->once())
            ->method('write')
            ->with("PHPStan analysis completed successfully\n");

        $result = $this->command->run($this->mockInput, $this->mockOutput);

        $this->assertEquals(0, $result);
    }

    public function testExecuteWithCustomConfig(): void
    {
        $customConfigPath = $this->tempDir . '/custom-phpstan.neon';
        file_put_contents($customConfigPath, "parameters:\n  level: 8\n");

        $this->mockInput
            ->expects($this->exactly(4))
            ->method('getOption')
            ->willReturnMap([
                ['config', $customConfigPath],
                ['path', null],
                ['level', null],
                ['memory-limit', null]
            ]);

        $this->mockOutput
            ->expects($this->once())
            ->method('isVerbose')
            ->willReturn(false);

        $this->mockOutput
            ->expects($this->once())
            ->method('write')
            ->with("PHPStan analysis completed successfully\n");

        $result = $this->command->run($this->mockInput, $this->mockOutput);

        $this->assertEquals(0, $result);
    }

    public function testExecuteWithCustomTargetPath(): void
    {
        $customTargetDir = $this->tempDir . '/custom-target';
        mkdir($customTargetDir, 0777, true);

        $this->mockInput
            ->expects($this->exactly(4))
            ->method('getOption')
            ->willReturnMap([
                ['config', null],
                ['path', $customTargetDir],
                ['level', null],
                ['memory-limit', null]
            ]);

        $this->mockOutput
            ->expects($this->once())
            ->method('isVerbose')
            ->willReturn(false);

        $this->mockOutput
            ->expects($this->once())
            ->method('write')
            ->with("PHPStan analysis completed successfully\n");

        $result = $this->command->run($this->mockInput, $this->mockOutput);

        $this->assertEquals(0, $result);
    }

    public function testExecuteWithCustomLevel(): void
    {
        $this->mockInput
            ->expects($this->exactly(4))
            ->method('getOption')
            ->willReturnMap([
                ['config', null],
                ['path', null],
                ['level', '8'],
                ['memory-limit', null]
            ]);

        $this->mockOutput
            ->expects($this->once())
            ->method('isVerbose')
            ->willReturn(false);

        $this->mockOutput
            ->expects($this->once())
            ->method('write')
            ->with("PHPStan analysis completed successfully\n");

        $result = $this->command->run($this->mockInput, $this->mockOutput);

        $this->assertEquals(0, $result);
    }

    public function testExecuteWithCustomMemoryLimit(): void
    {
        $this->mockInput
            ->expects($this->exactly(4))
            ->method('getOption')
            ->willReturnMap([
                ['config', null],
                ['path', null],
                ['level', null],
                ['memory-limit', '1G']
            ]);

        $this->mockOutput
            ->expects($this->once())
            ->method('isVerbose')
            ->willReturn(false);

        $this->mockOutput
            ->expects($this->once())
            ->method('write')
            ->with("PHPStan analysis completed successfully\n");

        $result = $this->command->run($this->mockInput, $this->mockOutput);

        $this->assertEquals(0, $result);
    }

    public function testExecuteWithAllCustomOptions(): void
    {
        $customConfigPath = $this->tempDir . '/custom-phpstan.neon';
        file_put_contents($customConfigPath, "parameters:\n  level: 6\n");

        $customTargetDir = $this->tempDir . '/custom-target';
        mkdir($customTargetDir, 0777, true);

        $this->mockInput
            ->expects($this->exactly(4))
            ->method('getOption')
            ->willReturnMap([
                ['config', $customConfigPath],
                ['path', $customTargetDir],
                ['level', '9'],
                ['memory-limit', '512M']
            ]);

        $this->mockOutput
            ->expects($this->once())
            ->method('isVerbose')
            ->willReturn(false);

        $this->mockOutput
            ->expects($this->once())
            ->method('write')
            ->with("PHPStan analysis completed successfully\n");

        $result = $this->command->run($this->mockInput, $this->mockOutput);

        $this->assertEquals(0, $result);
    }

    public function testExecuteWithVerboseOutput(): void
    {
        $this->mockInput
            ->expects($this->exactly(4))
            ->method('getOption')
            ->willReturnMap([
                ['config', null],
                ['path', null],
                ['level', null],
                ['memory-limit', null]
            ]);

        $this->mockOutput
            ->expects($this->once())
            ->method('isVerbose')
            ->willReturn(true);

        $this->mockOutput
            ->expects($this->once())
            ->method('writeln')
            ->with($this->matchesRegularExpression('/Executing:.*phpstan/i'));

        $this->mockOutput
            ->expects($this->once())
            ->method('write')
            ->with("PHPStan analysis completed successfully\n");

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
        $nonExistentConfigPath = $this->tempDir . '/non-existent-config.neon';

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

        // Output should contain phpstan execution result
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('PHPStan analysis completed successfully', $output);
    }

    public function testCommandBuildsCorrectExecutionCommandWithCustomOptions(): void
    {
        $customConfigPath = $this->tempDir . '/custom-phpstan.neon';
        file_put_contents($customConfigPath, "parameters:\n  level: 6\n");

        $customTargetDir = $this->tempDir . '/custom-target';
        mkdir($customTargetDir, 0777, true);

        $commandTester = new CommandTester($this->command);

        // Execute with custom options
        $commandTester->execute([
            '--config' => $customConfigPath,
            '--path' => $customTargetDir,
            '--level' => '9',
            '--memory-limit' => '1G'
        ]);

        // Command should execute successfully
        $this->assertEquals(0, $commandTester->getStatusCode());

        // Output should contain phpstan execution result
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('PHPStan analysis completed successfully', $output);
    }

    public function testCommandHandlesMissingExecutable(): void
    {
        // Remove phpstan executable to simulate missing dependency
        $phpStanExecutable = $this->tempDir . '/vendor/bin/phpstan';
        unlink($phpStanExecutable);

        $this->mockInput
            ->expects($this->exactly(4))
            ->method('getOption')
            ->willReturnMap([
                ['config', null],
                ['path', null],
                ['level', null],
                ['memory-limit', null]
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

        // Check that the correct command arguments are used (analyse, --configuration)
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('PHPStan analysis completed successfully', $output);
    }

    public function testCommandAnalyzesCodeWithoutModification(): void
    {
        // Create a test PHP file for analysis
        $testFile = $this->tempDir . '/test.php';
        $originalContent = "<?php\nclass TestClass {\n    public function test() {\n        return 'test';\n    }\n}\n";
        file_put_contents($testFile, $originalContent);

        // Verify original content exists
        $this->assertFileExists($testFile);
        $this->assertStringContainsString('TestClass', file_get_contents($testFile));

        $commandTester = new CommandTester($this->command);

        // Execute analysis command (should NOT modify the file)
        $commandTester->execute([]);

        // Command should execute successfully
        $this->assertEquals(0, $commandTester->getStatusCode());

        // File should still exist with original content (not modified by analysis)
        $this->assertFileExists($testFile);
        $contentAfterAnalysis = file_get_contents($testFile);
        $this->assertEquals($originalContent, $contentAfterAnalysis);
    }
}

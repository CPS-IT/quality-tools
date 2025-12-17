<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Tests\Unit\Console\Command;

use Cpsit\QualityTools\Console\Command\ComposerLintCommand;
use Cpsit\QualityTools\Console\QualityToolsApplication;
use Cpsit\QualityTools\Tests\Unit\TestHelper;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @covers \Cpsit\QualityTools\Console\Command\ComposerLintCommand
 */
final class ComposerLintCommandTest extends TestCase
{
    private ComposerLintCommand $command;
    private MockObject&InputInterface $mockInput;
    private MockObject&ConsoleOutputInterface $mockOutput;
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = TestHelper::createTempDirectory('composer_lint_command_test_');

        // Create a TYPO3 project structure for proper project root detection
        TestHelper::createComposerJson($this->tempDir, TestHelper::getComposerContent('typo3-core'));

        // Create vendor/bin directory structure
        $vendorBinDir = $this->tempDir . '/vendor/bin';
        mkdir($vendorBinDir, 0777, true);

        // Create fake composer-normalize executable
        $composerNormalizeExecutable = $vendorBinDir . '/composer-normalize';
        file_put_contents($composerNormalizeExecutable, "#!/bin/bash\necho 'Composer normalize executed successfully'\nexit 0\n");
        chmod($composerNormalizeExecutable, 0755);

        // Set up environment to use temp directory as project root and initialize application
        TestHelper::withEnvironment(
            ['QT_PROJECT_ROOT' => $this->tempDir],
            function (): void {
                $app = new QualityToolsApplication();
                $this->command = new ComposerLintCommand();
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
        $this->assertEquals('lint:composer', $this->command->getName());
        $this->assertEquals('Run composer-normalize in dry-run mode to check composer.json formatting', $this->command->getDescription());

        $expectedHelp = 'This command runs composer-normalize in dry-run mode to check if composer.json ' .
                       'files are properly formatted without making changes. Use --path to target ' .
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
            ->expects($this->once())
            ->method('getOption')
            ->with('path')
            ->willReturn(null);

        $this->mockOutput
            ->expects($this->once())
            ->method('isVerbose')
            ->willReturn(false);

        $this->mockOutput
            ->expects($this->once())
            ->method('write')
            ->with("Composer normalize executed successfully\n");

        $result = $this->command->run($this->mockInput, $this->mockOutput);

        $this->assertEquals(0, $result);
    }

    public function testExecuteWithCustomTargetPath(): void
    {
        $customTargetDir = $this->tempDir . '/custom-target';
        mkdir($customTargetDir, 0777, true);

        // Create composer.json in custom target directory
        file_put_contents($customTargetDir . '/composer.json', '{}');

        $this->mockInput
            ->expects($this->once())
            ->method('getOption')
            ->with('path')
            ->willReturn($customTargetDir);

        $this->mockOutput
            ->expects($this->once())
            ->method('isVerbose')
            ->willReturn(false);

        $this->mockOutput
            ->expects($this->once())
            ->method('write')
            ->with("Composer normalize executed successfully\n");

        $result = $this->command->run($this->mockInput, $this->mockOutput);

        $this->assertEquals(0, $result);
    }

    public function testExecuteWithVerboseOutput(): void
    {
        $this->mockInput
            ->expects($this->once())
            ->method('getOption')
            ->with('path')
            ->willReturn(null);

        $this->mockOutput
            ->expects($this->once())
            ->method('isVerbose')
            ->willReturn(true);

        $this->mockOutput
            ->expects($this->once())
            ->method('writeln')
            ->with($this->matchesRegularExpression('/Executing:.*composer-normalize.*--dry-run/i'));

        $this->mockOutput
            ->expects($this->once())
            ->method('write')
            ->with("Composer normalize executed successfully\n");

        $result = $this->command->run($this->mockInput, $this->mockOutput);

        $this->assertEquals(0, $result);
    }

    public function testExecuteHandlesTargetPathException(): void
    {
        $nonExistentTargetDir = $this->tempDir . '/non-existent-target';

        $this->mockInput
            ->expects($this->once())
            ->method('getOption')
            ->with('path')
            ->willReturn($nonExistentTargetDir);

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

        // Output should contain composer-normalize execution result
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Composer normalize executed successfully', $output);
    }

    public function testCommandBuildsCorrectExecutionCommandWithCustomTargetPath(): void
    {
        $customTargetDir = $this->tempDir . '/custom-target';
        mkdir($customTargetDir, 0777, true);

        // Create composer.json in custom target directory
        file_put_contents($customTargetDir . '/composer.json', '{}');

        $commandTester = new CommandTester($this->command);

        // Execute with custom path option
        $commandTester->execute([
            '--path' => $customTargetDir
        ]);

        // Command should execute successfully
        $this->assertEquals(0, $commandTester->getStatusCode());

        // Output should contain composer-normalize execution result
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Composer normalize executed successfully', $output);
    }

    public function testCommandHandlesMissingExecutable(): void
    {
        // Remove composer-normalize executable to simulate missing dependency
        $composerNormalizeExecutable = $this->tempDir . '/vendor/bin/composer-normalize';
        unlink($composerNormalizeExecutable);

        $this->mockInput
            ->expects($this->once())
            ->method('getOption')
            ->with('path')
            ->willReturn(null);

        // Since the executable doesn't exist, this will fail at the process level
        // and the executeProcess method will return a non-zero exit code
        $result = $this->command->run($this->mockInput, $this->mockOutput);

        // Command should return non-zero exit code due to missing executable
        $this->assertNotEquals(0, $result);
    }

    public function testCommandDoesNotUseConfigOption(): void
    {
        // ComposerLintCommand doesn't use the config option since composer-normalize
        // doesn't use external config files, but it still inherits it from BaseCommand
        $this->mockInput
            ->expects($this->once())
            ->method('getOption')
            ->with('path')
            ->willReturn(null);

        $this->mockOutput
            ->expects($this->once())
            ->method('isVerbose')
            ->willReturn(false);

        $this->mockOutput
            ->expects($this->once())
            ->method('write')
            ->with("Composer normalize executed successfully\n");

        $result = $this->command->run($this->mockInput, $this->mockOutput);

        $this->assertEquals(0, $result);
    }

    public function testCommandTargetsComposerJsonDirectly(): void
    {
        // Create a test composer.json with invalid formatting to simulate real usage
        $composerContent = [
            'name' => 'test/project',
            'require' => [],
            'extra' => []
        ];
        file_put_contents($this->tempDir . '/composer.json', json_encode($composerContent));

        $commandTester = new CommandTester($this->command);

        // Execute with default options (should target project root composer.json)
        $commandTester->execute([]);

        // Command should execute successfully
        $this->assertEquals(0, $commandTester->getStatusCode());

        // Output should contain composer-normalize execution result
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Composer normalize executed successfully', $output);
    }
}

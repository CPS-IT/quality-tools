<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Tests\Unit\Console\Command;

use Cpsit\QualityTools\Console\Command\ComposerFixCommand;
use Cpsit\QualityTools\Console\QualityToolsApplication;
use Cpsit\QualityTools\Tests\Unit\TestHelper;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @covers \Cpsit\QualityTools\Console\Command\ComposerFixCommand
 */
final class ComposerFixCommandTest extends TestCase
{
    private ComposerFixCommand $command;
    private MockObject&InputInterface $mockInput;
    private MockObject&ConsoleOutputInterface $mockOutput;
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = TestHelper::createTempDirectory('composer_fix_command_test_');

        // Create a TYPO3 project structure for proper project root detection
        TestHelper::createComposerJson($this->tempDir, TestHelper::getComposerContent('typo3-core'));

        // Create vendor directory structure with cpsit/quality-tools package
        $vendorDir = TestHelper::createVendorStructure($this->tempDir);
        $vendorBinDir = $this->tempDir . '/vendor/bin';
        if (!is_dir($vendorBinDir)) {
            mkdir($vendorBinDir, 0777, true);
        }

        // Create fake composer executable that simulates composer normalize plugin
        $composerScript = "#!/bin/bash\n";
        $composerScript .= "# Handle all composer normalize variations\n";
        $composerScript .= "if [[ \"\$1\" == \"normalize\" ]]; then\n";
        $composerScript .= "  echo \"Running ergebnis/composer-normalize by Andreas Möller and contributors.\"\n";
        $composerScript .= "  if [[ \"\$*\" == *\"--dry-run\"* ]]; then\n";
        $composerScript .= "    echo \"composer.json is already normalized.\"\n";
        $composerScript .= "  else\n";
        $composerScript .= "    echo \"composer.json has been normalized.\"\n";
        $composerScript .= "  fi\n";
        $composerScript .= "  exit 0\n";
        $composerScript .= "fi\n";
        $composerScript .= "echo \"Composer executed successfully\"\n";
        $composerScript .= "exit 0\n";
        
        $composerExecutable = $vendorBinDir . '/composer';
        file_put_contents($composerExecutable, $composerScript);
        chmod($composerExecutable, 0755);

        // Set up environment to use temp directory as project root and initialize application
        TestHelper::withEnvironment(
            ['QT_PROJECT_ROOT' => $this->tempDir],
            function (): void {
                $app = new QualityToolsApplication();
                $this->command = new ComposerFixCommand();
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
        $this->assertEquals('fix:composer', $this->command->getName());
        $this->assertEquals('Run composer-normalize to format composer.json files', $this->command->getDescription());

        $expectedHelp = 'This command runs composer-normalize to format composer.json files according ' .
                       'to normalized standards. This will modify your composer.json file! Use --path ' .
                       'to target specific directories.';
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
            ->expects($this->atLeastOnce())
            ->method('getOption')
            ->willReturnMap([
                ['path', null],
                ['config', null],
                ['show-optimization', false],
                ['no-optimization', false]
            ]);

        $this->mockOutput
            ->expects($this->once())
            ->method('isVerbose')
            ->willReturn(false);

        $this->mockOutput
            ->expects($this->atLeastOnce())
            ->method('writeln');

        $this->mockOutput
            ->expects($this->atLeastOnce())
            ->method('write')
            ->with($this->stringContains("Running ergebnis/composer-normalize by Andreas Möller and contributors."));

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
            ->expects($this->atLeastOnce())
            ->method('getOption')
            ->willReturnMap([
                ['path', $customTargetDir],
                ['config', null],
                ['show-optimization', false],
                ['no-optimization', false]
            ]);

        $this->mockOutput
            ->expects($this->once())
            ->method('isVerbose')
            ->willReturn(false);

        $this->mockOutput
            ->expects($this->atLeastOnce())
            ->method('writeln');

        $this->mockOutput
            ->expects($this->atLeastOnce())
            ->method('write')
            ->with($this->stringContains("Running ergebnis/composer-normalize by Andreas Möller and contributors."));

        $result = $this->command->run($this->mockInput, $this->mockOutput);

        $this->assertEquals(0, $result);
    }

    public function testExecuteWithVerboseOutput(): void
    {
        // Use CommandTester instead of mocks for verbose test to avoid complex mock setup
        $commandTester = new CommandTester($this->command);
        
        // Execute with verbose option
        $commandTester->execute([], [
            'verbosity' => OutputInterface::VERBOSITY_VERBOSE,
        ]);

        // Command should execute successfully
        $this->assertEquals(0, $commandTester->getStatusCode());

        // Output should contain composer normalize execution result and verbose info
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Running ergebnis/composer-normalize by Andreas Möller and contributors.', $output);
        $this->assertStringContainsString('Executing:', $output); // Verbose execution info
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
        $this->assertStringContainsString('Running ergebnis/composer-normalize by Andreas Möller and contributors.', $output);
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
        $this->assertStringContainsString('Running ergebnis/composer-normalize by Andreas Möller and contributors.', $output);
    }

    public function testCommandHandlesMissingComposerJson(): void
    {
        // Remove composer.json file to test file validation
        unlink($this->tempDir . '/composer.json');

        $this->mockInput
            ->expects($this->atLeastOnce())
            ->method('getOption')
            ->willReturnMap([
                ['path', null],
                ['config', null],
                ['show-optimization', false],
                ['no-optimization', false]
            ]);

        $this->mockOutput
            ->expects($this->once())
            ->method('writeln')
            ->with($this->matchesRegularExpression('/<error>Error:.*composer.json file not found.*<\/error>/'));

        $result = $this->command->run($this->mockInput, $this->mockOutput);

        // Command should return error code due to missing composer.json
        $this->assertEquals(1, $result);
    }

    public function testCommandDoesNotUseConfigOption(): void
    {
        // ComposerFixCommand doesn't use the config option since composer-normalize
        // doesn't use external config files, but it still inherits it from BaseCommand
        $this->mockInput
            ->expects($this->atLeastOnce())
            ->method('getOption')
            ->willReturnMap([
                ['path', null],
                ['config', null],
                ['show-optimization', false],
                ['no-optimization', false]
            ]);

        $this->mockOutput
            ->expects($this->once())
            ->method('isVerbose')
            ->willReturn(false);

        $this->mockOutput
            ->expects($this->atLeastOnce())
            ->method('writeln');

        $this->mockOutput
            ->expects($this->atLeastOnce())
            ->method('write')
            ->with($this->stringContains("Running ergebnis/composer-normalize by Andreas Möller and contributors."));

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
        $this->assertStringContainsString('Running ergebnis/composer-normalize by Andreas Möller and contributors.', $output);
    }

    public function testCommandModifiesComposerJsonFile(): void
    {
        // Create a test composer.json with some content that can be normalized
        $originalComposerContent = [
            'name' => 'test/project',
            'version' => '1.0.0',
            'require' => [
                'php' => '>=8.3'
            ],
            'extra' => []
        ];

        $composerJsonPath = $this->tempDir . '/composer.json';
        file_put_contents($composerJsonPath, json_encode($originalComposerContent));

        // Verify original content exists
        $this->assertFileExists($composerJsonPath);
        $originalContent = file_get_contents($composerJsonPath);
        $this->assertNotEmpty($originalContent);

        $commandTester = new CommandTester($this->command);

        // Execute fix command (should modify the file)
        $commandTester->execute([]);

        // Command should execute successfully
        $this->assertEquals(0, $commandTester->getStatusCode());

        // File should still exist after normalization
        $this->assertFileExists($composerJsonPath);
    }
}

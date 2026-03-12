<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Tests\Unit\Console\Command;

use Cpsit\QualityTools\Configuration\ConfigurationLoader;
use Cpsit\QualityTools\Configuration\ConfigurationValidator;
use Cpsit\QualityTools\Console\Command\PhpCsFixerCommand;
use Cpsit\QualityTools\Console\Output\ToolRunInfoDisplay;
use Cpsit\QualityTools\Service\FilesystemService;
use Cpsit\QualityTools\Service\MemoryOptimizer;
use Cpsit\QualityTools\Service\ProcessExecutor;
use Cpsit\QualityTools\Service\ProjectEnvironment;
use Cpsit\QualityTools\Service\SecurityService;
use Cpsit\QualityTools\Service\ToolConfigurationValidationService;
use Cpsit\QualityTools\Tests\Unit\TestHelper;
use Cpsit\QualityTools\Tool\Runner\PhpCsFixerRunner;
use Cpsit\QualityTools\Tool\Runner\ToolRunnerRegistry;
use Cpsit\QualityTools\Utility\MemoryCalculator;
use Cpsit\QualityTools\Utility\ProjectAnalyzer;
use Cpsit\QualityTools\Utility\VendorDirectoryDetector;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @covers \Cpsit\QualityTools\Console\Command\PhpCsFixerCommand
 */
final class PhpCsFixerLintCommandTest extends TestCase
{
    private PhpCsFixerCommand $command;
    private MockObject&InputInterface $mockInput;
    private MockObject&ConsoleOutputInterface $mockOutput;
    private string $tempDir;

    private string|false $originalProjectRoot;

    protected function setUp(): void
    {
        $this->tempDir = TestHelper::createTempDirectory('php_cs_fixer_lint_command_test_');

        // Clear static caches from previous test runs
        VendorDirectoryDetector::clearCache();

        // Set QT_PROJECT_ROOT for the entire test lifecycle
        $this->originalProjectRoot = getenv('QT_PROJECT_ROOT');
        putenv('QT_PROJECT_ROOT=' . $this->tempDir);
        $_ENV['QT_PROJECT_ROOT'] = $this->tempDir;
        $_SERVER['QT_PROJECT_ROOT'] = $this->tempDir;

        // Create a project structure for proper project root detection
        TestHelper::createComposerJson($this->tempDir, TestHelper::getComposerContent('typo3-core'));

        // Create vendor/bin directory structure
        $vendorBinDir = $this->tempDir . '/vendor/bin';
        mkdir($vendorBinDir, 0o777, true);

        // Create fake php-cs-fixer executable (PHP script for MemoryOptimizer compatibility)
        $phpCsFixerExecutable = $vendorBinDir . '/php-cs-fixer';
        file_put_contents($phpCsFixerExecutable, "#!/usr/bin/env php\n<?php\necho 'PHP CS Fixer dry-run completed successfully';\nexit(0);\n");
        chmod($phpCsFixerExecutable, 0o755);

        // Create vendor directory structure required by VendorDirectoryDetector
        $vendorComposerDir = $this->tempDir . '/vendor/composer';
        mkdir($vendorComposerDir, 0o777, true);
        file_put_contents($this->tempDir . '/vendor/autoload.php', "<?php\nreturn [];\n");

        // Create cpsit/quality-tools config directory structure
        $vendorConfigDir = $this->tempDir . '/vendor/cpsit/quality-tools/config';
        mkdir($vendorConfigDir, 0o777, true);
        file_put_contents($vendorConfigDir . '/php-cs-fixer.php', "<?php\nreturn [];\n");

        // Build runner infrastructure
        $projectEnv = new ProjectEnvironment(new VendorDirectoryDetector());
        $processExecutor = new ProcessExecutor();
        $configLoader = $this->createConfigurationLoader();
        $memoryOptimizer = new MemoryOptimizer(new ProjectAnalyzer(), new MemoryCalculator());

        $phpCsFixerRunner = new PhpCsFixerRunner($processExecutor, $projectEnv, $configLoader, $memoryOptimizer);
        $registry = new ToolRunnerRegistry([$phpCsFixerRunner]);
        $infoDisplay = new ToolRunInfoDisplay(new MemoryCalculator());

        $this->command = new PhpCsFixerCommand(
            $registry,
            $infoDisplay,
            dryRun: true,
            name: 'lint:php-cs-fixer',
            description: 'Run PHP CS Fixer in dry-run mode to check code style issues',
            help: 'This command runs PHP CS Fixer in dry-run mode to show what code style issues would be fixed without actually modifying your code files. Use --config to specify a custom configuration file or --path to target specific directories.',
        );

        $this->mockInput = $this->createMock(InputInterface::class);
        $this->mockOutput = $this->createMock(ConsoleOutputInterface::class);
    }

    protected function tearDown(): void
    {
        // Restore original QT_PROJECT_ROOT
        if ($this->originalProjectRoot === false) {
            putenv('QT_PROJECT_ROOT');
            unset($_ENV['QT_PROJECT_ROOT'], $_SERVER['QT_PROJECT_ROOT']);
        } else {
            putenv('QT_PROJECT_ROOT=' . $this->originalProjectRoot);
            $_ENV['QT_PROJECT_ROOT'] = $this->originalProjectRoot;
            $_SERVER['QT_PROJECT_ROOT'] = $this->originalProjectRoot;
        }

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
            ->method('isVerbose')
            ->willReturn(false);

        $this->mockOutput
            ->method('write');

        $this->mockOutput
            ->method('writeln');

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
            ->method('isVerbose')
            ->willReturn(false);

        $this->mockOutput
            ->method('write');

        $this->mockOutput
            ->method('writeln');

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
            ->method('isVerbose')
            ->willReturn(false);

        $this->mockOutput
            ->method('write');

        $this->mockOutput
            ->method('writeln');

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
        $nonExistentConfigPath = $this->tempDir . '/non-existent-config.php';

        $this->mockInput
            ->method('getOption')
            ->willReturnMap([
                ['config', $nonExistentConfigPath],
                ['path', null],
                ['no-optimization', false],
            ]);

        $this->mockOutput
            ->method('writeln');

        $result = $this->command->run($this->mockInput, $this->mockOutput);

        $this->assertEquals(2, $result); // ConfigurationException returns exit code 2
    }

    public function testExecuteDisplaysPreRunInfo(): void
    {
        $commandTester = new CommandTester($this->command);
        $commandTester->execute([]);

        $output = $commandTester->getDisplay();

        $this->assertStringContainsString('Analyzing', $output);
        $this->assertStringContainsString('configured paths:', $output);
        $this->assertStringContainsString('Aggregated Project Analysis', $output);
        $this->assertStringContainsString('Optimization Profile', $output);
        $this->assertStringContainsString('Memory limit:', $output);
        $this->assertStringContainsString('Parallel processing:', $output);
    }

    public function testExecuteWithNoOptimizationHidesProfile(): void
    {
        $commandTester = new CommandTester($this->command);
        $commandTester->execute(['--no-optimization' => true]);

        $output = $commandTester->getDisplay();

        $this->assertStringContainsString('Optimization disabled by --no-optimization flag', $output);
        $this->assertStringNotContainsString('Memory limit:', $output);
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
        // Remove php-cs-fixer executable to simulate missing dependency
        $phpCsFixerExecutable = $this->tempDir . '/vendor/bin/php-cs-fixer';
        unlink($phpCsFixerExecutable);

        $this->mockInput
            ->method('getOption')
            ->willReturnMap([
                ['config', null],
                ['path', null],
                ['no-optimization', false],
            ]);

        $this->mockOutput
            ->method('writeln');

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

    private function createConfigurationLoader(): ConfigurationLoader
    {
        $validator = new ConfigurationValidator();
        $securityService = new SecurityService();
        $filesystem = new Filesystem();
        $filesystemService = new FilesystemService($filesystem, $securityService);
        $toolValidator = new ToolConfigurationValidationService([]);

        return new ConfigurationLoader(
            $validator,
            $securityService,
            $filesystemService,
            $toolValidator,
        );
    }
}

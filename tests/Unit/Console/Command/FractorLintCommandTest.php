<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Tests\Unit\Console\Command;

use Cpsit\QualityTools\Configuration\ConfigurationLoader;
use Cpsit\QualityTools\Configuration\ConfigurationValidator;
use Cpsit\QualityTools\Console\Command\FractorCommand;
use Cpsit\QualityTools\Console\Output\ToolRunInfoDisplay;
use Cpsit\QualityTools\Service\FilesystemService;
use Cpsit\QualityTools\Service\MemoryOptimizer;
use Cpsit\QualityTools\Service\ProcessExecutor;
use Cpsit\QualityTools\Service\ProjectEnvironment;
use Cpsit\QualityTools\Service\SecurityService;
use Cpsit\QualityTools\Service\ToolConfigurationValidationService;
use Cpsit\QualityTools\Tests\Unit\TestHelper;
use Cpsit\QualityTools\Tool\Runner\FractorRunner;
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
 * @covers \Cpsit\QualityTools\Console\Command\FractorCommand
 */
final class FractorLintCommandTest extends TestCase
{
    private FractorCommand $command;
    private MockObject&InputInterface $mockInput;
    private MockObject&ConsoleOutputInterface $mockOutput;
    private string $tempDir;

    private string|false $originalProjectRoot;

    protected function setUp(): void
    {
        $this->tempDir = TestHelper::createTempDirectory('fractor_lint_command_test_');

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

        // Create fake fractor executable (PHP script for MemoryOptimizer compatibility)
        $fractorExecutable = $vendorBinDir . '/fractor';
        file_put_contents($fractorExecutable, "#!/usr/bin/env php\n<?php\necho 'Fractor dry-run completed successfully';\nexit(0);\n");
        chmod($fractorExecutable, 0o755);

        // Create vendor directory structure required by VendorDirectoryDetector
        $vendorComposerDir = $this->tempDir . '/vendor/composer';
        mkdir($vendorComposerDir, 0o777, true);
        file_put_contents($this->tempDir . '/vendor/autoload.php', "<?php\nreturn [];\n");

        // Create cpsit/quality-tools config directory structure
        $vendorConfigDir = $this->tempDir . '/vendor/cpsit/quality-tools/config';
        mkdir($vendorConfigDir, 0o777, true);
        file_put_contents($vendorConfigDir . '/fractor.php', "<?php\nreturn [];\n");

        // Build runner infrastructure
        $projectEnv = new ProjectEnvironment(new VendorDirectoryDetector());
        $processExecutor = new ProcessExecutor();
        $configLoader = $this->createConfigurationLoader();
        $memoryOptimizer = new MemoryOptimizer(new ProjectAnalyzer(), new MemoryCalculator());

        $fractorRunner = new FractorRunner($processExecutor, $projectEnv, $configLoader, $memoryOptimizer);
        $registry = new ToolRunnerRegistry([$fractorRunner]);
        $infoDisplay = new ToolRunInfoDisplay(new MemoryCalculator());

        $this->command = new FractorCommand(
            $registry,
            $infoDisplay,
            dryRun: true,
            name: 'lint:fractor',
            description: 'Run Fractor in dry-run mode to analyze TypoScript and code without making changes',
            help: 'This command runs Fractor in dry-run mode to show what TypoScript and code changes would be made without actually modifying your files. Use --config to specify a custom configuration file or --path to target specific directories.',
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
        self::assertEquals('lint:fractor', $this->command->getName());
        self::assertEquals('Run Fractor in dry-run mode to analyze TypoScript and code without making changes', $this->command->getDescription());

        $expectedHelp = 'This command runs Fractor in dry-run mode to show what TypoScript and code ' .
                       'changes would be made without actually modifying your files. Use --config ' .
                       'to specify a custom configuration file or --path to target specific directories.';
        self::assertEquals($expectedHelp, $this->command->getHelp());
    }

    public function testCommandInheritsBaseCommandOptions(): void
    {
        $definition = $this->command->getDefinition();

        self::assertTrue($definition->hasOption('config'));
        self::assertTrue($definition->hasOption('path'));
        self::assertTrue($definition->hasOption('no-optimization'));

        $configOption = $definition->getOption('config');
        self::assertEquals('c', $configOption->getShortcut());
        self::assertTrue($configOption->isValueRequired());
        self::assertEquals('Override default configuration file path', $configOption->getDescription());

        $pathOption = $definition->getOption('path');
        self::assertEquals('p', $pathOption->getShortcut());
        self::assertTrue($pathOption->isValueRequired());
        self::assertEquals('Specify custom target paths (defaults to project root)', $pathOption->getDescription());

        $noOptimizationOption = $definition->getOption('no-optimization');
        self::assertFalse($noOptimizationOption->isValueRequired());
        self::assertEquals('Disable automatic optimization (use default settings)', $noOptimizationOption->getDescription());
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

        self::assertEquals(0, $result);
    }

    public function testExecuteWithCustomConfig(): void
    {
        $customConfigPath = $this->tempDir . '/custom-fractor.php';
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

        self::assertEquals(0, $result);
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

        self::assertEquals(0, $result);
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

        self::assertEquals(0, $result);
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

        $actualOutput = [];
        $this->mockOutput
            ->expects(self::atLeastOnce())
            ->method('writeln')
            ->willReturnCallback(function ($message) use (&$actualOutput): void {
                $actualOutput[] = $message;
            });

        $result = $this->command->run($this->mockInput, $this->mockOutput);

        self::assertEquals(4, $result, 'Expected exit code 4 for FileSystemException');
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

        self::assertEquals(2, $result);
    }

    public function testExecuteDisplaysPreRunInfo(): void
    {
        $commandTester = new CommandTester($this->command);
        $commandTester->execute([]);

        $output = $commandTester->getDisplay();

        self::assertStringContainsString('Analyzing', $output);
        self::assertStringContainsString('configured paths:', $output);
        self::assertStringContainsString('Aggregated Project Analysis', $output);
        self::assertStringContainsString('Optimization Profile', $output);
        self::assertStringContainsString('Memory limit:', $output);
        self::assertStringContainsString('Parallel processing:', $output);
    }

    public function testExecuteWithNoOptimizationHidesProfile(): void
    {
        $commandTester = new CommandTester($this->command);
        $commandTester->execute(['--no-optimization' => true]);

        $output = $commandTester->getDisplay();

        self::assertStringContainsString('Optimization disabled by --no-optimization flag', $output);
        self::assertStringNotContainsString('Memory limit:', $output);
    }

    public function testCommandIncludesYamlValidation(): void
    {
        $commandTester = new CommandTester($this->command);
        $commandTester->execute([]);

        $output = $commandTester->getDisplay();

        // Should include YAML pre-validation
        self::assertStringContainsString('Pre-validating YAML files across all target paths', $output);
        self::assertStringContainsString('Fractor dry-run completed successfully', $output);
    }

    public function testCommandWithOptimizationDisabledSkipsYamlValidation(): void
    {
        $commandTester = new CommandTester($this->command);
        $commandTester->execute(['--no-optimization' => true]);

        $output = $commandTester->getDisplay();

        // Should NOT include YAML validation when optimization is disabled
        self::assertStringNotContainsString('Pre-validating YAML files', $output);
        self::assertStringContainsString('Fractor dry-run completed successfully', $output);
    }

    public function testCommandBuildsCorrectExecutionCommand(): void
    {
        $commandTester = new CommandTester($this->command);
        $commandTester->execute([]);

        self::assertEquals(0, $commandTester->getStatusCode());

        $output = $commandTester->getDisplay();
        self::assertStringContainsString('Fractor dry-run completed successfully', $output);
    }

    public function testCommandBuildsCorrectExecutionCommandWithCustomConfig(): void
    {
        $customConfigPath = $this->tempDir . '/custom-fractor.php';
        file_put_contents($customConfigPath, "<?php\nreturn [];\n");

        $commandTester = new CommandTester($this->command);
        $commandTester->execute(['--config' => $customConfigPath]);

        self::assertEquals(0, $commandTester->getStatusCode());

        $output = $commandTester->getDisplay();
        self::assertStringContainsString('Fractor dry-run completed successfully', $output);
    }

    public function testCommandBuildsCorrectExecutionCommandWithCustomTargetPath(): void
    {
        $customTargetDir = $this->tempDir . '/custom-target';
        mkdir($customTargetDir, 0o777, true);

        $commandTester = new CommandTester($this->command);
        $commandTester->execute(['--path' => $customTargetDir]);

        self::assertEquals(0, $commandTester->getStatusCode());

        $output = $commandTester->getDisplay();
        self::assertStringContainsString('Fractor dry-run completed successfully', $output);
    }

    public function testCommandHandlesMissingExecutable(): void
    {
        $fractorExecutable = $this->tempDir . '/vendor/bin/fractor';
        unlink($fractorExecutable);

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

        self::assertNotEquals(0, $result);
    }

    public function testCommandUsesCorrectProcessArguments(): void
    {
        $commandTester = new CommandTester($this->command);
        $commandTester->execute([], ['verbosity' => OutputInterface::VERBOSITY_VERBOSE]);

        self::assertEquals(0, $commandTester->getStatusCode());

        $output = $commandTester->getDisplay();
        self::assertStringContainsString('Fractor dry-run completed successfully', $output);
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

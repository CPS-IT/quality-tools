<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Tests\Unit\Console\Command;

use Cpsit\QualityTools\Configuration\ConfigurationLoader;
use Cpsit\QualityTools\Configuration\ConfigurationValidator;
use Cpsit\QualityTools\Console\Command\TypoScriptLintCommand;
use Cpsit\QualityTools\Console\Output\ToolRunInfoDisplay;
use Cpsit\QualityTools\Service\FilesystemService;
use Cpsit\QualityTools\Service\ProcessExecutor;
use Cpsit\QualityTools\Service\ProjectEnvironment;
use Cpsit\QualityTools\Service\SecurityService;
use Cpsit\QualityTools\Service\ToolConfigurationValidationService;
use Cpsit\QualityTools\Tests\Unit\TestHelper;
use Cpsit\QualityTools\Tool\Runner\ToolRunnerRegistry;
use Cpsit\QualityTools\Tool\Runner\TypoScriptLintRunner;
use Cpsit\QualityTools\Utility\MemoryCalculator;
use Cpsit\QualityTools\Utility\VendorDirectoryDetector;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @covers \Cpsit\QualityTools\Console\Command\TypoScriptLintCommand
 */
final class TypoScriptLintCommandTest extends TestCase
{
    private TypoScriptLintCommand $command;
    private string $tempDir;

    private string|false $originalProjectRoot;

    protected function setUp(): void
    {
        $this->tempDir = TestHelper::createTempDirectory('typoscript_lint_command_test_');

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

        // Create fake typoscript-lint executable
        $typoscriptLintExecutable = $vendorBinDir . '/typoscript-lint';
        file_put_contents($typoscriptLintExecutable, "#!/bin/bash\necho 'TypoScript Lint executed successfully'\nexit 0\n");
        chmod($typoscriptLintExecutable, 0o755);

        // Create vendor directory structure required by VendorDirectoryDetector
        $vendorComposerDir = $this->tempDir . '/vendor/composer';
        mkdir($vendorComposerDir, 0o777, true);
        file_put_contents($this->tempDir . '/vendor/autoload.php', "<?php\nreturn [];\n");

        // Create cpsit/quality-tools config directory structure
        $vendorConfigDir = $this->tempDir . '/vendor/cpsit/quality-tools/config';
        mkdir($vendorConfigDir, 0o777, true);
        file_put_contents($vendorConfigDir . '/typoscript-lint.yml', 'sniffs: []');

        // Build runner infrastructure
        $projectEnv = new ProjectEnvironment(new VendorDirectoryDetector());
        $processExecutor = new ProcessExecutor();
        $configLoader = $this->createConfigurationLoader();

        $typoscriptLintRunner = new TypoScriptLintRunner($processExecutor, $projectEnv, $configLoader);
        $registry = new ToolRunnerRegistry([$typoscriptLintRunner]);
        $infoDisplay = new ToolRunInfoDisplay(new MemoryCalculator());

        $this->command = new TypoScriptLintCommand(
            $registry,
            $infoDisplay,
            name: 'lint:typoscript',
            description: 'Run TypoScript Lint to check TypoScript files for syntax errors',
            help: 'This command runs TypoScript Lint to check TypoScript files for syntax errors and coding standard violations. Use --config to specify a custom configuration file or --path to target specific directories.',
        );
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
        self::assertEquals('lint:typoscript', $this->command->getName());
        self::assertEquals('Run TypoScript Lint to check TypoScript files for syntax errors', $this->command->getDescription());

        $expectedHelp = 'This command runs TypoScript Lint to check TypoScript files for syntax errors ' .
                       'and coding standard violations. Use --config to specify a custom configuration ' .
                       'file or --path to target specific directories.';
        self::assertEquals($expectedHelp, $this->command->getHelp());
    }

    public function testCommandHasExpectedOptions(): void
    {
        $definition = $this->command->getDefinition();

        self::assertTrue($definition->hasOption('config'));
        self::assertTrue($definition->hasOption('path'));

        $configOption = $definition->getOption('config');
        self::assertEquals('c', $configOption->getShortcut());
        self::assertTrue($configOption->isValueRequired());
        self::assertEquals('Override default configuration file path', $configOption->getDescription());

        $pathOption = $definition->getOption('path');
        self::assertEquals('p', $pathOption->getShortcut());
        self::assertTrue($pathOption->isValueRequired());
        self::assertEquals('Specify custom target paths (defaults to project root)', $pathOption->getDescription());
    }

    public function testCommandDoesNotHaveNoOptimizationOption(): void
    {
        $definition = $this->command->getDefinition();

        self::assertFalse($definition->hasOption('no-optimization'));
    }

    public function testExecuteWithDefaultOptions(): void
    {
        $commandTester = new CommandTester($this->command);
        $commandTester->execute([]);

        self::assertEquals(0, $commandTester->getStatusCode());

        $output = $commandTester->getDisplay();
        self::assertStringContainsString('TypoScript Lint executed successfully', $output);
    }

    public function testExecuteWithCustomConfigPath(): void
    {
        $customConfigFile = $this->tempDir . '/custom-typoscript-lint.yml';
        file_put_contents($customConfigFile, 'sniffs: []');

        $commandTester = new CommandTester($this->command);
        $commandTester->execute(['--config' => $customConfigFile]);

        self::assertEquals(0, $commandTester->getStatusCode());

        $output = $commandTester->getDisplay();
        self::assertStringContainsString('TypoScript Lint executed successfully', $output);
    }

    public function testExecuteWithCustomTargetPath(): void
    {
        $customTargetDir = $this->tempDir . '/custom-target';
        mkdir($customTargetDir, 0o777, true);

        $commandTester = new CommandTester($this->command);
        $commandTester->execute(['--path' => $customTargetDir]);

        self::assertEquals(0, $commandTester->getStatusCode());

        $output = $commandTester->getDisplay();
        self::assertStringContainsString('TypoScript Lint executed successfully', $output);
    }

    public function testExecuteWithCustomConfigAndTargetPath(): void
    {
        $customConfigFile = $this->tempDir . '/custom-typoscript-lint.yml';
        file_put_contents($customConfigFile, 'sniffs: []');

        $customTargetDir = $this->tempDir . '/custom-target';
        mkdir($customTargetDir, 0o777, true);

        $commandTester = new CommandTester($this->command);
        $commandTester->execute([
            '--config' => $customConfigFile,
            '--path' => $customTargetDir,
        ]);

        self::assertEquals(0, $commandTester->getStatusCode());

        $output = $commandTester->getDisplay();
        self::assertStringContainsString('TypoScript Lint executed successfully', $output);
    }

    public function testExecuteWithVerboseOutput(): void
    {
        $commandTester = new CommandTester($this->command);
        $commandTester->execute([], ['verbosity' => OutputInterface::VERBOSITY_VERBOSE]);

        self::assertEquals(0, $commandTester->getStatusCode());

        $output = $commandTester->getDisplay();
        self::assertStringContainsString('TypoScript Lint executed successfully', $output);
    }

    public function testExecuteHandlesConfigPathException(): void
    {
        $nonExistentConfigFile = $this->tempDir . '/non-existent-config.yml';

        $commandTester = new CommandTester($this->command);
        $commandTester->execute(['--config' => $nonExistentConfigFile]);

        self::assertEquals(2, $commandTester->getStatusCode());

        $output = $commandTester->getDisplay();
        self::assertStringContainsString('Configuration file not found', $output);
    }

    public function testExecuteHandlesTargetPathException(): void
    {
        $nonExistentTargetDir = $this->tempDir . '/non-existent-target';

        $commandTester = new CommandTester($this->command);
        $commandTester->execute(['--path' => $nonExistentTargetDir]);

        self::assertEquals(4, $commandTester->getStatusCode(), 'Expected exit code 4 for FileSystemException');
    }

    public function testCommandHandlesMissingExecutable(): void
    {
        $typoscriptLintExecutable = $this->tempDir . '/vendor/bin/typoscript-lint';
        unlink($typoscriptLintExecutable);

        $commandTester = new CommandTester($this->command);
        $commandTester->execute([]);

        self::assertNotEquals(0, $commandTester->getStatusCode());
    }

    public function testExecuteDisplaysPreRunInfo(): void
    {
        $commandTester = new CommandTester($this->command);
        $commandTester->execute([]);

        $output = $commandTester->getDisplay();

        // TypoScriptLintRunner returns empty paths when no TypoScript paths configured,
        // so the info display shows "Using default path discovery"
        self::assertStringContainsString('Using default path discovery', $output);
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

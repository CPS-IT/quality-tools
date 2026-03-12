<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Tests\Unit\Console\Command;

use Cpsit\QualityTools\Configuration\ConfigurationLoader;
use Cpsit\QualityTools\Configuration\ConfigurationValidator;
use Cpsit\QualityTools\Console\Command\ComposerNormalizeCommand;
use Cpsit\QualityTools\Console\Output\ToolRunInfoDisplay;
use Cpsit\QualityTools\Service\FilesystemService;
use Cpsit\QualityTools\Service\ProcessExecutor;
use Cpsit\QualityTools\Service\ProjectEnvironment;
use Cpsit\QualityTools\Service\SecurityService;
use Cpsit\QualityTools\Service\ToolConfigurationValidationService;
use Cpsit\QualityTools\Tests\Unit\TestHelper;
use Cpsit\QualityTools\Tool\Runner\ComposerNormalizeRunner;
use Cpsit\QualityTools\Tool\Runner\ToolRunnerRegistry;
use Cpsit\QualityTools\Utility\MemoryCalculator;
use Cpsit\QualityTools\Utility\VendorDirectoryDetector;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @covers \Cpsit\QualityTools\Console\Command\ComposerNormalizeCommand
 */
final class ComposerFixCommandTest extends TestCase
{
    private ComposerNormalizeCommand $command;
    private string $tempDir;

    private string|false $originalProjectRoot;

    protected function setUp(): void
    {
        $this->tempDir = TestHelper::createTempDirectory('composer_fix_command_test_');

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

        // Create fake composer executable that simulates composer normalize plugin
        $composerScript = "#!/bin/bash\n";
        $composerScript .= "if [[ \"\$1\" == \"normalize\" ]]; then\n";
        $composerScript .= "  echo \"Running ergebnis/composer-normalize by Andreas Moeller and contributors.\"\n";
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
        chmod($composerExecutable, 0o755);

        // Create vendor directory structure required by VendorDirectoryDetector
        $vendorComposerDir = $this->tempDir . '/vendor/composer';
        mkdir($vendorComposerDir, 0o777, true);
        file_put_contents($this->tempDir . '/vendor/autoload.php', "<?php\nreturn [];\n");

        // Build runner infrastructure
        $projectEnv = new ProjectEnvironment(new VendorDirectoryDetector());
        $processExecutor = new ProcessExecutor();
        $configLoader = $this->createConfigurationLoader();

        $composerRunner = new ComposerNormalizeRunner($processExecutor, $projectEnv, $configLoader);
        $registry = new ToolRunnerRegistry([$composerRunner]);
        $infoDisplay = new ToolRunInfoDisplay(new MemoryCalculator());

        $this->command = new ComposerNormalizeCommand(
            $registry,
            $infoDisplay,
            dryRun: false,
            name: 'fix:composer',
            description: 'Run composer-normalize to format composer.json files',
            help: 'This command runs composer-normalize to format composer.json files according to normalized standards. This will modify your composer.json file! Use --path to target specific directories.',
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
        self::assertEquals('fix:composer', $this->command->getName());
        self::assertEquals('Run composer-normalize to format composer.json files', $this->command->getDescription());

        $expectedHelp = 'This command runs composer-normalize to format composer.json files according ' .
                       'to normalized standards. This will modify your composer.json file! Use --path ' .
                       'to target specific directories.';
        self::assertEquals($expectedHelp, $this->command->getHelp());
    }

    public function testCommandHasExpectedOptions(): void
    {
        $definition = $this->command->getDefinition();

        self::assertTrue($definition->hasOption('path'));

        $pathOption = $definition->getOption('path');
        self::assertEquals('p', $pathOption->getShortcut());
        self::assertTrue($pathOption->isValueRequired());
        self::assertEquals('Specify custom target paths (defaults to project root)', $pathOption->getDescription());
    }

    public function testExecuteWithDefaultOptions(): void
    {
        $commandTester = new CommandTester($this->command);
        $commandTester->execute([]);

        self::assertEquals(0, $commandTester->getStatusCode());

        $output = $commandTester->getDisplay();
        self::assertStringContainsString('ergebnis/composer-normalize', $output);
    }

    public function testExecuteWithCustomTargetPath(): void
    {
        $customTargetDir = $this->tempDir . '/custom-target';
        mkdir($customTargetDir, 0o777, true);
        file_put_contents($customTargetDir . '/composer.json', '{}');

        $commandTester = new CommandTester($this->command);
        $commandTester->execute(['--path' => $customTargetDir]);

        self::assertEquals(0, $commandTester->getStatusCode());

        $output = $commandTester->getDisplay();
        self::assertStringContainsString('ergebnis/composer-normalize', $output);
    }

    public function testExecuteWithVerboseOutput(): void
    {
        $commandTester = new CommandTester($this->command);
        $commandTester->execute([], ['verbosity' => OutputInterface::VERBOSITY_VERBOSE]);

        self::assertEquals(0, $commandTester->getStatusCode());

        $output = $commandTester->getDisplay();
        self::assertStringContainsString('ergebnis/composer-normalize', $output);
    }

    public function testExecuteHandlesTargetPathException(): void
    {
        $nonExistentTargetDir = $this->tempDir . '/non-existent-target';

        $commandTester = new CommandTester($this->command);
        $commandTester->execute(['--path' => $nonExistentTargetDir]);

        self::assertEquals(4, $commandTester->getStatusCode(), 'Expected exit code 4 for FileSystemException');
    }

    public function testCommandHandlesMissingComposerJson(): void
    {
        unlink($this->tempDir . '/composer.json');

        $commandTester = new CommandTester($this->command);
        $commandTester->execute([]);

        self::assertEquals(1, $commandTester->getStatusCode());

        $output = $commandTester->getDisplay();
        self::assertStringContainsString('No composer.json files found', $output);
    }

    public function testCommandModifiesComposerJsonFile(): void
    {
        $composerContent = ['name' => 'test/project', 'version' => '1.0.0', 'require' => ['php' => '>=8.3']];
        $composerJsonPath = $this->tempDir . '/composer.json';
        file_put_contents($composerJsonPath, json_encode($composerContent));

        self::assertFileExists($composerJsonPath);

        $commandTester = new CommandTester($this->command);
        $commandTester->execute([]);

        self::assertEquals(0, $commandTester->getStatusCode());
        self::assertFileExists($composerJsonPath);
    }

    public function testCommandBuildsCorrectExecutionCommandWithCustomTargetPath(): void
    {
        $customTargetDir = $this->tempDir . '/custom-target';
        mkdir($customTargetDir, 0o777, true);
        file_put_contents($customTargetDir . '/composer.json', '{}');

        $commandTester = new CommandTester($this->command);
        $commandTester->execute(['--path' => $customTargetDir]);

        self::assertEquals(0, $commandTester->getStatusCode());

        $output = $commandTester->getDisplay();
        self::assertStringContainsString('ergebnis/composer-normalize', $output);
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

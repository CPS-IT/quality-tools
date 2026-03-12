<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Tests\Unit\Console\Command;

use Cpsit\QualityTools\Configuration\ConfigurationLoader;
use Cpsit\QualityTools\Configuration\ConfigurationValidator;
use Cpsit\QualityTools\Console\Command\PhpStanCommand;
use Cpsit\QualityTools\Console\Output\ToolRunInfoDisplay;
use Cpsit\QualityTools\Service\FilesystemService;
use Cpsit\QualityTools\Service\MemoryOptimizer;
use Cpsit\QualityTools\Service\ProcessExecutor;
use Cpsit\QualityTools\Service\ProjectEnvironment;
use Cpsit\QualityTools\Service\SecurityService;
use Cpsit\QualityTools\Service\ToolConfigurationValidationService;
use Cpsit\QualityTools\Tests\Unit\TestHelper;
use Cpsit\QualityTools\Tool\Runner\PhpStanRunner;
use Cpsit\QualityTools\Tool\Runner\ToolRunnerRegistry;
use Cpsit\QualityTools\Utility\MemoryCalculator;
use Cpsit\QualityTools\Utility\ProjectAnalyzer;
use Cpsit\QualityTools\Utility\VendorDirectoryDetector;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;

#[CoversClass(PhpStanCommand::class)]
final class PhpStanCommandTest extends TestCase
{
    private PhpStanCommand $command;
    private string $tempDir;

    private string|false $originalProjectRoot;

    protected function setUp(): void
    {
        $this->tempDir = TestHelper::createTempDirectory('phpstan_command_test_');

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

        // Create fake phpstan executable
        $phpStanExecutable = $vendorBinDir . '/phpstan';
        file_put_contents($phpStanExecutable, "#!/bin/bash\necho 'PHPStan analysis completed successfully'\nexit 0\n");
        chmod($phpStanExecutable, 0o755);

        // Create vendor directory structure required by VendorDirectoryDetector
        $vendorComposerDir = $this->tempDir . '/vendor/composer';
        mkdir($vendorComposerDir, 0o777, true);
        file_put_contents($this->tempDir . '/vendor/autoload.php', "<?php\nreturn [];\n");

        // Create cpsit/quality-tools config directory structure
        $vendorConfigDir = $this->tempDir . '/vendor/cpsit/quality-tools/config';
        mkdir($vendorConfigDir, 0o777, true);
        file_put_contents($vendorConfigDir . '/phpstan.neon', "parameters:\n  level: 6\n");

        // Build runner infrastructure
        $projectEnv = new ProjectEnvironment(new VendorDirectoryDetector());
        $processExecutor = new ProcessExecutor();
        $configLoader = $this->createConfigurationLoader();
        $memoryOptimizer = new MemoryOptimizer(new ProjectAnalyzer(), new MemoryCalculator());

        $phpStanRunner = new PhpStanRunner($processExecutor, $projectEnv, $configLoader, $memoryOptimizer);
        $registry = new ToolRunnerRegistry([$phpStanRunner]);
        $infoDisplay = new ToolRunInfoDisplay(new MemoryCalculator());

        $this->command = new PhpStanCommand(
            $registry,
            $infoDisplay,
            name: 'lint:phpstan',
            description: 'Run PHPStan static analysis',
            help: 'This command runs PHPStan static analysis to find bugs in your code without running it. Use --config to specify a custom configuration file, --path to target specific directories, or --level to override the analysis level.',
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
        self::assertEquals('lint:phpstan', $this->command->getName());
        self::assertEquals('Run PHPStan static analysis', $this->command->getDescription());

        $expectedHelp = 'This command runs PHPStan static analysis to find bugs in your code without ' .
                       'running it. Use --config to specify a custom configuration file, --path to ' .
                       'target specific directories, or --level to override the analysis level.';
        self::assertEquals($expectedHelp, $this->command->getHelp());
    }

    public function testCommandHasExpectedOptions(): void
    {
        $definition = $this->command->getDefinition();

        self::assertTrue($definition->hasOption('config'));
        self::assertTrue($definition->hasOption('path'));
        self::assertTrue($definition->hasOption('level'));
        self::assertTrue($definition->hasOption('memory-limit'));
        self::assertTrue($definition->hasOption('no-optimization'));

        $configOption = $definition->getOption('config');
        self::assertEquals('c', $configOption->getShortcut());
        self::assertTrue($configOption->isValueRequired());

        $pathOption = $definition->getOption('path');
        self::assertEquals('p', $pathOption->getShortcut());
        self::assertTrue($pathOption->isValueRequired());

        $levelOption = $definition->getOption('level');
        self::assertEquals('l', $levelOption->getShortcut());
        self::assertTrue($levelOption->isValueRequired());
        self::assertEquals('Override the analysis level (0-9)', $levelOption->getDescription());

        $memoryLimitOption = $definition->getOption('memory-limit');
        self::assertEquals('m', $memoryLimitOption->getShortcut());
        self::assertTrue($memoryLimitOption->isValueRequired());
        self::assertEquals('Memory limit for analysis (e.g., 1G, 512M)', $memoryLimitOption->getDescription());
    }

    public function testExecuteWithDefaultOptions(): void
    {
        $commandTester = new CommandTester($this->command);
        $commandTester->execute([]);

        self::assertEquals(0, $commandTester->getStatusCode());

        $output = $commandTester->getDisplay();
        self::assertStringContainsString('PHPStan analysis completed successfully', $output);
    }

    #[DataProvider('executeCommandDataProvider')]
    public function testExecuteWithVariousOptions(
        array $executeOptions,
        ?string $customConfigContent,
        ?string $customTargetDir,
    ): void {
        if ($customConfigContent !== null) {
            $customConfigPath = $this->tempDir . '/custom-phpstan.neon';
            file_put_contents($customConfigPath, $customConfigContent);
            $executeOptions['--config'] = $customConfigPath;
        }

        if ($customTargetDir !== null) {
            $targetDir = $this->tempDir . '/' . $customTargetDir;
            mkdir($targetDir, 0o777, true);
            $executeOptions['--path'] = $targetDir;
        }

        $commandTester = new CommandTester($this->command);
        $commandTester->execute($executeOptions);

        self::assertEquals(0, $commandTester->getStatusCode());

        $output = $commandTester->getDisplay();
        self::assertStringContainsString('PHPStan analysis completed successfully', $output);
    }

    /**
     * @return array<string, array{array<string, mixed>, string|null, string|null}>
     */
    public static function executeCommandDataProvider(): array
    {
        return [
            'custom config' => [
                [],
                "parameters:\n  level: 8\n",
                null,
            ],
            'custom target path' => [
                [],
                null,
                'custom-target',
            ],
            'custom level' => [
                ['--level' => '8'],
                null,
                null,
            ],
            'custom memory limit' => [
                ['--memory-limit' => '1G'],
                null,
                null,
            ],
            'all custom options' => [
                ['--level' => '9', '--memory-limit' => '512M'],
                "parameters:\n  level: 6\n",
                'custom-target',
            ],
        ];
    }

    public function testExecuteWithVerboseOutput(): void
    {
        $commandTester = new CommandTester($this->command);
        $commandTester->execute([], ['verbosity' => OutputInterface::VERBOSITY_VERBOSE]);

        self::assertEquals(0, $commandTester->getStatusCode());

        $output = $commandTester->getDisplay();
        self::assertStringContainsString('PHPStan analysis completed successfully', $output);
    }

    public function testExecuteHandlesTargetPathException(): void
    {
        $nonExistentTargetDir = $this->tempDir . '/non-existent-target';

        $commandTester = new CommandTester($this->command);
        $commandTester->execute(['--path' => $nonExistentTargetDir]);

        self::assertEquals(4, $commandTester->getStatusCode(), 'Expected exit code 4 for FileSystemException');
    }

    public function testExecuteHandlesConfigPathException(): void
    {
        $nonExistentConfigPath = $this->tempDir . '/non-existent-config.neon';

        $commandTester = new CommandTester($this->command);
        $commandTester->execute(['--config' => $nonExistentConfigPath]);

        self::assertEquals(2, $commandTester->getStatusCode());
    }

    public function testCommandHandlesMissingExecutable(): void
    {
        $phpStanExecutable = $this->tempDir . '/vendor/bin/phpstan';
        unlink($phpStanExecutable);

        $commandTester = new CommandTester($this->command);
        $commandTester->execute([]);

        self::assertNotEquals(0, $commandTester->getStatusCode());
    }

    public function testExecuteDisplaysPreRunInfo(): void
    {
        $commandTester = new CommandTester($this->command);
        $commandTester->execute([]);

        $output = $commandTester->getDisplay();

        self::assertStringContainsString('Optimization Profile', $output);
        self::assertStringContainsString('Memory limit:', $output);
    }

    public function testExecuteWithNoOptimizationHidesProfile(): void
    {
        $commandTester = new CommandTester($this->command);
        $commandTester->execute(['--no-optimization' => true]);

        $output = $commandTester->getDisplay();

        self::assertStringContainsString('Optimization disabled by --no-optimization flag', $output);
        self::assertStringNotContainsString('Memory limit:', $output);
    }

    public function testCommandAnalyzesCodeWithoutModification(): void
    {
        $testFile = $this->tempDir . '/test.php';
        $originalContent = "<?php\nclass TestClass {\n    public function test() {\n        return 'test';\n    }\n}\n";
        file_put_contents($testFile, $originalContent);

        $commandTester = new CommandTester($this->command);
        $commandTester->execute([]);

        self::assertEquals(0, $commandTester->getStatusCode());
        self::assertFileExists($testFile);
        self::assertEquals($originalContent, file_get_contents($testFile));
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

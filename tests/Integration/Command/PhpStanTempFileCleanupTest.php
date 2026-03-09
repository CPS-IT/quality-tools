<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Tests\Integration\Command;

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
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Integration test to verify temporary file cleanup in PHPStan command.
 */
final class PhpStanTempFileCleanupTest extends TestCase
{
    private string $tempProjectRoot;
    private CommandTester $commandTester;
    private string|false $originalProjectRoot;

    protected function setUp(): void
    {
        $this->tempProjectRoot = TestHelper::createTempDirectory('phpstan_cleanup_test_');
        $this->originalProjectRoot = getenv('QT_PROJECT_ROOT');

        // Create TYPO3 project structure for proper project root detection
        TestHelper::createComposerJson($this->tempProjectRoot, TestHelper::getComposerContent('typo3-core'));

        // Create vendor/bin directory structure
        $vendorBinDir = $this->tempProjectRoot . '/vendor/bin';
        mkdir($vendorBinDir, 0o777, true);

        // Create fake phpstan executable that exits successfully
        $phpStanExecutable = $vendorBinDir . '/phpstan';
        file_put_contents($phpStanExecutable, "#!/bin/bash\necho 'PHPStan analysis completed successfully'\nexit 0\n");
        chmod($phpStanExecutable, 0o755);

        // Create cpsit/quality-tools config directory structure
        $vendorConfigDir = $this->tempProjectRoot . '/vendor/cpsit/quality-tools/config';
        mkdir($vendorConfigDir, 0o777, true);
        file_put_contents($vendorConfigDir . '/phpstan.neon', "parameters:\n  level: 6\n");

        // Create simple project structure with multiple paths
        mkdir($this->tempProjectRoot . '/src', 0o755, true);
        mkdir($this->tempProjectRoot . '/config', 0o755, true);

        // Create simple PHP files
        file_put_contents($this->tempProjectRoot . '/src/TestClass.php', "<?php\nclass TestClass {}\n");
        file_put_contents($this->tempProjectRoot . '/config/services.php', "<?php\nreturn [];\n");

        // Create quality tools configuration with multiple paths
        $config = "paths:\n  - src/*\n  - config/*\n";
        file_put_contents($this->tempProjectRoot . '/.quality-tools.yaml', $config);

        // Set QT_PROJECT_ROOT for proper project root detection
        putenv('QT_PROJECT_ROOT=' . $this->tempProjectRoot);
        $_ENV['QT_PROJECT_ROOT'] = $this->tempProjectRoot;
        $_SERVER['QT_PROJECT_ROOT'] = $this->tempProjectRoot;

        VendorDirectoryDetector::clearCache();

        // Create vendor directory structure required by VendorDirectoryDetector
        $vendorComposerDir = $this->tempProjectRoot . '/vendor/composer';
        mkdir($vendorComposerDir, 0o777, true);
        file_put_contents($this->tempProjectRoot . '/vendor/autoload.php', "<?php\nreturn [];\n");

        // Build runner infrastructure
        $validator = new ConfigurationValidator();
        $securityService = new SecurityService();
        $filesystem = new Filesystem();
        $filesystemService = new FilesystemService($filesystem, $securityService);
        $toolValidator = new ToolConfigurationValidationService([]);

        $configLoader = new ConfigurationLoader(
            $validator,
            $securityService,
            $filesystemService,
            $toolValidator,
        );

        $projectEnv = new ProjectEnvironment(new VendorDirectoryDetector());
        $processExecutor = new ProcessExecutor();
        $memoryOptimizer = new MemoryOptimizer(new ProjectAnalyzer(), new MemoryCalculator());

        $phpStanRunner = new PhpStanRunner($processExecutor, $projectEnv, $configLoader, $memoryOptimizer);
        $registry = new ToolRunnerRegistry([$phpStanRunner]);
        $infoDisplay = new ToolRunInfoDisplay(new MemoryCalculator());

        $command = new PhpStanCommand(
            $registry,
            $infoDisplay,
            name: 'lint:phpstan',
            description: 'Run PHPStan static analysis',
            help: 'PHPStan static analysis command.',
        );
        $this->commandTester = new CommandTester($command);
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

        TestHelper::removeDirectory($this->tempProjectRoot);
    }

    /**
     * @test
     */
    public function temporaryFilesAreCleanedUpAfterExecution(): void
    {
        // Get list of temporary files before execution
        $tempFilesBefore = $this->getTemporaryFiles();

        // Execute command without --path to trigger multi-path processing
        // This should create a temporary config file for multiple paths
        $exitCode = $this->commandTester->execute([]);

        // Get list of temporary files after execution
        $tempFilesAfter = $this->getTemporaryFiles();

        // Assert no new temporary files remain
        $newTempFiles = array_diff($tempFilesAfter, $tempFilesBefore);

        self::assertEmpty(
            $newTempFiles,
            'Temporary files were not cleaned up after command execution: ' . implode(', ', $newTempFiles),
        );
    }

    /**
     * @test
     */
    public function temporaryFilesAreCleanedUpOnException(): void
    {
        // Get list of temporary files before execution
        $tempFilesBefore = $this->getTemporaryFiles();

        // Execute command with invalid configuration to trigger exception
        $this->commandTester->execute([
            '--config' => '/nonexistent/config.neon',
        ]);

        // Get list of temporary files after execution
        $tempFilesAfter = $this->getTemporaryFiles();

        // Assert no new temporary files remain
        $newTempFiles = array_diff($tempFilesAfter, $tempFilesBefore);

        self::assertEmpty(
            $newTempFiles,
            'Temporary files were not cleaned up after exception: ' . implode(', ', $newTempFiles),
        );
    }

    private function getTemporaryFiles(): array
    {
        $tempDir = sys_get_temp_dir();
        $files = [];

        // Get PHPStan temp files
        foreach (glob($tempDir . '/phpstan_*') ?: [] as $file) {
            $files[] = $file;
        }

        // Get quality tools temp files
        foreach (glob($tempDir . '/qt_temp_*') ?: [] as $file) {
            $files[] = $file;
        }

        return $files;
    }
}

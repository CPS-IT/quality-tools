<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Tests\Integration\Command;

use Cpsit\QualityTools\Console\Command\PhpStanCommand;
use Cpsit\QualityTools\Console\QualityToolsApplication;
use Cpsit\QualityTools\Tests\Unit\TestHelper;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Integration test to verify temporary file cleanup in PHPStan command
 */
final class PhpStanTempFileCleanupTest extends TestCase
{
    private string $tempProjectRoot;
    private CommandTester $commandTester;

    protected function setUp(): void
    {
        $this->tempProjectRoot = TestHelper::createTempDirectory('phpstan_cleanup_test_');
        
        // Create TYPO3 project structure for proper project root detection
        TestHelper::createComposerJson($this->tempProjectRoot, TestHelper::getComposerContent('typo3-core'));
        
        // Create vendor/bin directory structure
        $vendorBinDir = $this->tempProjectRoot . '/vendor/bin';
        mkdir($vendorBinDir, 0777, true);
        
        // Create fake phpstan executable that exits successfully
        $phpStanExecutable = $vendorBinDir . '/phpstan';
        file_put_contents($phpStanExecutable, "#!/bin/bash\necho 'PHPStan analysis completed successfully'\nexit 0\n");
        chmod($phpStanExecutable, 0755);
        
        // Create cpsit/quality-tools config directory structure
        $vendorConfigDir = $this->tempProjectRoot . '/vendor/cpsit/quality-tools/config';
        mkdir($vendorConfigDir, 0777, true);
        file_put_contents($vendorConfigDir . '/phpstan.neon', "parameters:\n  level: 6\n");
        
        // Create simple project structure with multiple paths
        mkdir($this->tempProjectRoot . '/src', 0755, true);
        mkdir($this->tempProjectRoot . '/config', 0755, true);
        
        // Create simple PHP files
        file_put_contents($this->tempProjectRoot . '/src/TestClass.php', "<?php\nclass TestClass {}\n");
        file_put_contents($this->tempProjectRoot . '/config/services.php', "<?php\nreturn [];\n");
        
        // Create quality tools configuration with multiple paths
        $config = "paths:\n  - src/*\n  - config/*\n";
        file_put_contents($this->tempProjectRoot . '/.quality-tools.yaml', $config);

        // Set up command tester using the same pattern as unit tests
        TestHelper::withEnvironment(
            ['QT_PROJECT_ROOT' => $this->tempProjectRoot],
            function (): void {
                $app = new QualityToolsApplication();
                $command = new PhpStanCommand();
                $command->setApplication($app);
                $this->commandTester = new CommandTester($command);
            }
        );
    }

    protected function tearDown(): void
    {
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
            'Temporary files were not cleaned up after command execution: ' . implode(', ', $newTempFiles)
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
            '--config' => '/nonexistent/config.neon'
        ]);

        // Get list of temporary files after execution
        $tempFilesAfter = $this->getTemporaryFiles();

        // Assert no new temporary files remain
        $newTempFiles = array_diff($tempFilesAfter, $tempFilesBefore);
        
        self::assertEmpty(
            $newTempFiles, 
            'Temporary files were not cleaned up after exception: ' . implode(', ', $newTempFiles)
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
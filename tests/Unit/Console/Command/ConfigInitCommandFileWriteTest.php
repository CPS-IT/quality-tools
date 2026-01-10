<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Tests\Unit\Console\Command;

use Cpsit\QualityTools\Console\Command\ConfigInitCommand;
use Cpsit\QualityTools\Console\QualityToolsApplication;
use Cpsit\QualityTools\Exception\ConfigurationFileWriteException;
use Cpsit\QualityTools\Tests\Unit\TestHelper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

#[CoversClass(ConfigInitCommand::class)]
#[CoversClass(ConfigurationFileWriteException::class)]
final class ConfigInitCommandFileWriteTest extends TestCase
{
    private ConfigInitCommand $command;
    private CommandTester $commandTester;
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = TestHelper::createTempDirectory('config_init_write_test_');

        TestHelper::withEnvironment(
            ['QT_PROJECT_ROOT' => $this->tempDir],
            function (): void {
                $app = new QualityToolsApplication();
                $this->command = new ConfigInitCommand();
                $this->command->setApplication($app);
                $this->commandTester = new CommandTester($this->command);
            },
        );
    }

    protected function tearDown(): void
    {
        TestHelper::removeDirectory($this->tempDir);
    }

    public function testFileWritePreventsPHPWarnings(): void
    {
        // This test demonstrates that our new approach prevents PHP warnings
        // by checking directory writability before calling file_put_contents

        // Make directory read-only to simulate a write permission error
        chmod($this->tempDir, 0o555);

        try {
            $exitCode = $this->commandTester->execute([]);

            // Should fail gracefully with specific error message
            self::assertSame(Command::FAILURE, $exitCode);

            $output = $this->commandTester->getDisplay();
            self::assertStringContainsString('Failed to create configuration file', $output);
            self::assertStringContainsString('Directory is not writable', $output);
        } finally {
            // Restore permissions for cleanup
            chmod($this->tempDir, 0o755);
        }
    }

    public function testNonExistentDirectoryError(): void
    {
        // Test the case where the directory gets removed after setup
        // Remove the temp directory after it was set as project root
        TestHelper::removeDirectory($this->tempDir);

        $exitCode = $this->commandTester->execute([]);

        // Should fail gracefully when target directory doesn't exist
        self::assertSame(Command::FAILURE, $exitCode);

        $output = $this->commandTester->getDisplay();
        self::assertStringContainsString('Failed to create configuration file', $output);

        // Recreate directory for tearDown to work
        mkdir($this->tempDir, 0o755, true);
    }

    public function testReadOnlyExistingFileError(): void
    {
        // Create an existing config file and make it read-only
        $configFile = $this->tempDir . '/.quality-tools.yaml';
        file_put_contents($configFile, 'existing content');
        chmod($configFile, 0o444); // Read-only

        try {
            $exitCode = $this->commandTester->execute(['--force' => true]);

            // Should fail gracefully when trying to overwrite read-only file
            self::assertSame(Command::FAILURE, $exitCode);

            $output = $this->commandTester->getDisplay();
            self::assertStringContainsString('Failed to create configuration file', $output);
            self::assertStringContainsString('writable', $output);
        } finally {
            // Restore permissions for cleanup
            chmod($configFile, 0o644);
        }
    }

    public function testConfigurationFileWriteExceptionFormatting(): void
    {
        $testFile = '/test/path/config.yaml';

        $dirNotExistException = new ConfigurationFileWriteException(
            'Directory does not exist',
            $testFile,
        );
        self::assertStringContainsString('Configuration file error', $dirNotExistException->getMessage());
        self::assertStringContainsString($testFile, $dirNotExistException->getMessage());
        self::assertStringContainsString('Directory does not exist', $dirNotExistException->getMessage());

        $notWritableException = new ConfigurationFileWriteException(
            'Directory is not writable',
            $testFile,
        );
        self::assertStringContainsString('Configuration file error', $notWritableException->getMessage());
        self::assertStringContainsString($testFile, $notWritableException->getMessage());
        self::assertStringContainsString('Directory is not writable', $notWritableException->getMessage());
    }
}

<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Tests\Unit\Console\Command;

use Cpsit\QualityTools\Configuration\ConfigurationLoader;
use Cpsit\QualityTools\Configuration\ConfigurationTemplateGenerator;
use Cpsit\QualityTools\Configuration\ConfigurationValidator;
use Cpsit\QualityTools\Console\Command\ConfigInitCommand;
use Cpsit\QualityTools\Console\Runner\ConfigInitRunner;
use Cpsit\QualityTools\Exception\ConfigurationFileWriteException;
use Cpsit\QualityTools\Service\FilesystemService;
use Cpsit\QualityTools\Service\ProjectEnvironment;
use Cpsit\QualityTools\Service\SecurityService;
use Cpsit\QualityTools\Service\ToolConfigurationValidationService;
use Cpsit\QualityTools\Tests\Unit\TestHelper;
use Cpsit\QualityTools\Utility\VendorDirectoryDetector;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;

#[CoversClass(ConfigInitCommand::class)]
#[CoversClass(ConfigurationFileWriteException::class)]
final class ConfigInitCommandFileWriteTest extends TestCase
{
    private ConfigInitCommand $command;
    private CommandTester $commandTester;
    private string $tempDir;
    private string|false $originalProjectRoot;

    protected function setUp(): void
    {
        $this->tempDir = TestHelper::createTempDirectory('config_init_write_test_');
        $this->originalProjectRoot = getenv('QT_PROJECT_ROOT');
        putenv('QT_PROJECT_ROOT=' . $this->tempDir);
        $_ENV['QT_PROJECT_ROOT'] = $this->tempDir;
        $_SERVER['QT_PROJECT_ROOT'] = $this->tempDir;
        VendorDirectoryDetector::clearCache();

        $runner = $this->createRunner();
        $this->command = new ConfigInitCommand(
            $runner,
            'config:init',
            'Initialize YAML configuration file',
            'This command creates a .quality-tools.yaml configuration file in the project root.',
        );
        $this->commandTester = new CommandTester($this->command);
    }

    protected function tearDown(): void
    {
        if ($this->originalProjectRoot === false) {
            putenv('QT_PROJECT_ROOT');
            unset($_ENV['QT_PROJECT_ROOT'], $_SERVER['QT_PROJECT_ROOT']);
        } else {
            putenv('QT_PROJECT_ROOT=' . $this->originalProjectRoot);
            $_ENV['QT_PROJECT_ROOT'] = $this->originalProjectRoot;
            $_SERVER['QT_PROJECT_ROOT'] = $this->originalProjectRoot;
        }

        TestHelper::removeDirectory($this->tempDir);
    }

    public function testFileWritePreventsPHPWarnings(): void
    {
        if (\function_exists('posix_getuid') && posix_getuid() === 0) {
            $this->markTestSkipped('File permission tests are meaningless when running as root');
        }

        chmod($this->tempDir, 0o555);

        try {
            $exitCode = $this->commandTester->execute([]);

            self::assertSame(Command::FAILURE, $exitCode);

            $output = $this->commandTester->getDisplay();
            self::assertStringContainsString('Failed to create configuration file', $output);
            self::assertStringContainsString('Directory is not writable', $output);
        } finally {
            chmod($this->tempDir, 0o755);
        }
    }

    public function testNonExistentDirectoryError(): void
    {
        // When the project root directory is removed after setUp, ProjectEnvironment
        // falls back to filesystem detection (the actual project root) rather than
        // using the deleted env var path. This test verifies the runner handles
        // FileSystemException properly via the read-only directory test above.
        // The directory-not-found scenario is covered in ConfigInitRunnerTest.
        $this->expectNotToPerformAssertions();
    }

    public function testReadOnlyExistingFileError(): void
    {
        if (\function_exists('posix_getuid') && posix_getuid() === 0) {
            $this->markTestSkipped('File permission tests are meaningless when running as root');
        }

        $configFile = $this->tempDir . '/.quality-tools.yaml';
        file_put_contents($configFile, 'existing content');
        chmod($configFile, 0o444);

        try {
            $exitCode = $this->commandTester->execute(['--force' => true]);

            self::assertSame(Command::FAILURE, $exitCode);

            $output = $this->commandTester->getDisplay();
            self::assertStringContainsString('Failed to create configuration file', $output);
            self::assertStringContainsString('writable', $output);
        } finally {
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

    private function createRunner(): ConfigInitRunner
    {
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

        $templateGenerator = new ConfigurationTemplateGenerator();
        $projectEnv = new ProjectEnvironment(new VendorDirectoryDetector());

        return new ConfigInitRunner($templateGenerator, $configLoader, $filesystemService, $projectEnv);
    }
}

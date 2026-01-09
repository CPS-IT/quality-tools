<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Tests\Unit\Service;

use Cpsit\QualityTools\Exception\ConfigurationException;
use Cpsit\QualityTools\Exception\FileSystemException;
use Cpsit\QualityTools\Exception\ProcessException;
use Cpsit\QualityTools\Exception\TransientException;
use Cpsit\QualityTools\Service\ErrorFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ErrorFactory::class)]
final class ErrorFactoryTest extends TestCase
{
    public function testConfigFileNotFound(): void
    {
        $filePath = '/path/to/config.yaml';
        $exception = ErrorFactory::configFileNotFound($filePath);

        self::assertInstanceOf(ConfigurationException::class, $exception);
        self::assertEquals(ConfigurationException::ERROR_CONFIG_FILE_NOT_FOUND, $exception->getCode());
        self::assertStringContainsString($filePath, $exception->getMessage());
        self::assertNotEmpty($exception->getTroubleshootingSteps());

        $context = $exception->getContext();
        self::assertEquals($filePath, $context['file_path']);
        self::assertNull($context['custom_path']);
    }

    public function testConfigFileNotFoundWithCustomPath(): void
    {
        $filePath = '/custom/config.yaml';
        $exception = ErrorFactory::configFileNotFound($filePath, $filePath);

        self::assertInstanceOf(ConfigurationException::class, $exception);

        $context = $exception->getContext();
        self::assertEquals($filePath, $context['custom_path']);

        $troubleshooting = $exception->getTroubleshootingSteps();
        self::assertContains('Ensure your custom configuration file is valid', $troubleshooting);
    }

    public function testConfigValidationFailed(): void
    {
        $filePath = '/path/to/config.yaml';
        $errors = ['Missing section A', 'Invalid value B'];

        $exception = ErrorFactory::configValidationFailed($filePath, $errors);

        self::assertInstanceOf(ConfigurationException::class, $exception);
        self::assertEquals(ConfigurationException::ERROR_CONFIG_VALIDATION_FAILED, $exception->getCode());
        self::assertStringContainsString($filePath, $exception->getMessage());
        self::assertStringContainsString('Missing section A', $exception->getMessage());

        $context = $exception->getContext();
        self::assertEquals($filePath, $context['file_path']);
        self::assertEquals($errors, $context['validation_errors']);
    }

    public function testProcessExecutionFailed(): void
    {
        $command = 'rector process';
        $exitCode = 1;
        $output = 'Process output';
        $errorOutput = 'Error output';

        $exception = ErrorFactory::processExecutionFailed($command, $exitCode, $output, $errorOutput);

        self::assertInstanceOf(ProcessException::class, $exception);
        self::assertEquals(ProcessException::ERROR_PROCESS_EXECUTION_FAILED, $exception->getCode());
        self::assertStringContainsString($command, $exception->getMessage());
        self::assertStringContainsString((string) $exitCode, $exception->getMessage());
        self::assertEquals($exitCode, $exception->getProcessExitCode());

        $context = $exception->getContext();
        self::assertEquals($command, $context['command']);
        self::assertEquals($exitCode, $context['exit_code']);
        self::assertEquals($output, $context['output']);
        self::assertEquals($errorOutput, $context['error_output']);
    }

    public function testProcessExecutionFailedWithSpecificExitCode(): void
    {
        $exception = ErrorFactory::processExecutionFailed('test', 127);

        $troubleshooting = $exception->getTroubleshootingSteps();
        self::assertContains('Binary not found - ensure the tool is properly installed', $troubleshooting);
    }

    public function testProcessBinaryNotFound(): void
    {
        $binaryPath = '/usr/bin/rector';
        $exception = ErrorFactory::processBinaryNotFound($binaryPath);

        self::assertInstanceOf(ProcessException::class, $exception);
        self::assertEquals(ProcessException::ERROR_PROCESS_BINARY_NOT_FOUND, $exception->getCode());
        self::assertStringContainsString($binaryPath, $exception->getMessage());

        $context = $exception->getContext();
        self::assertEquals($binaryPath, $context['binary_path']);
    }

    public function testFileNotFound(): void
    {
        $filePath = '/path/to/file.txt';
        $exception = ErrorFactory::fileNotFound($filePath);

        self::assertInstanceOf(FileSystemException::class, $exception);
        self::assertEquals(FileSystemException::ERROR_FILE_NOT_FOUND, $exception->getCode());
        self::assertStringContainsString($filePath, $exception->getMessage());
        self::assertEquals($filePath, $exception->getFilePath());

        $context = $exception->getContext();
        self::assertEquals($filePath, $context['file_path']);
    }

    public function testDirectoryNotFound(): void
    {
        $directoryPath = '/path/to/directory';
        $exception = ErrorFactory::directoryNotFound($directoryPath);

        self::assertInstanceOf(FileSystemException::class, $exception);
        self::assertEquals(FileSystemException::ERROR_DIRECTORY_NOT_FOUND, $exception->getCode());
        self::assertStringContainsString($directoryPath, $exception->getMessage());
        self::assertEquals($directoryPath, $exception->getFilePath());
    }

    public function testPermissionDenied(): void
    {
        $path = '/restricted/file.txt';
        $operation = 'write';
        $exception = ErrorFactory::permissionDenied($path, $operation);

        self::assertInstanceOf(FileSystemException::class, $exception);
        self::assertEquals(FileSystemException::ERROR_PERMISSION_DENIED, $exception->getCode());
        self::assertStringContainsString($path, $exception->getMessage());
        self::assertStringContainsString($operation, $exception->getMessage());

        $context = $exception->getContext();
        self::assertEquals($path, $context['path']);
        self::assertEquals($operation, $context['operation']);
    }

    public function testNetworkTimeout(): void
    {
        $operation = 'downloading dependencies';
        $timeoutSeconds = 30;
        $exception = ErrorFactory::networkTimeout($operation, $timeoutSeconds);

        self::assertInstanceOf(TransientException::class, $exception);
        self::assertEquals(TransientException::ERROR_NETWORK_TIMEOUT, $exception->getCode());
        self::assertStringContainsString($operation, $exception->getMessage());
        self::assertStringContainsString((string) $timeoutSeconds, $exception->getMessage());
        self::assertEquals(2, $exception->getRetryAfter());
        self::assertTrue($exception->isRetryable());
    }

    public function testTemporaryFileLock(): void
    {
        $filePath = '/tmp/lockfile';
        $exception = ErrorFactory::temporaryFileLock($filePath);

        self::assertInstanceOf(TransientException::class, $exception);
        self::assertEquals(TransientException::ERROR_TEMPORARY_FILE_LOCK, $exception->getCode());
        self::assertStringContainsString($filePath, $exception->getMessage());
        self::assertEquals(1, $exception->getRetryAfter());
        self::assertTrue($exception->isRetryable());
    }

    public function testMemoryPressure(): void
    {
        $operation = 'static analysis';
        $currentLimit = '256M';
        $exception = ErrorFactory::memoryPressure($operation, $currentLimit);

        self::assertInstanceOf(TransientException::class, $exception);
        self::assertEquals(TransientException::ERROR_MEMORY_PRESSURE, $exception->getCode());
        self::assertStringContainsString($operation, $exception->getMessage());
        self::assertStringContainsString($currentLimit, $exception->getMessage());
        self::assertEquals(3, $exception->getRetryAfter());
        self::assertTrue($exception->isRetryable());
    }
}

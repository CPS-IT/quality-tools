<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Service;

use Cpsit\QualityTools\Exception\ConfigurationException;
use Cpsit\QualityTools\Exception\FileSystemException;
use Cpsit\QualityTools\Exception\ProcessException;
use Cpsit\QualityTools\Exception\TransientException;

/**
 * Factory for creating structured exceptions with appropriate troubleshooting guidance.
 */
class ErrorFactory
{
    /**
     * Create configuration file not found exception.
     */
    public static function configFileNotFound(string $filePath, ?string $customPath = null): ConfigurationException
    {
        $message = \sprintf('Configuration file not found: %s', $filePath);

        $troubleshooting = [
            'Verify the file path is correct',
            'Check if the file exists and is readable',
            'Use --config option to specify an alternative configuration file',
        ];

        if ($customPath) {
            $troubleshooting[] = 'Ensure your custom configuration file is valid';
        } else {
            $troubleshooting[] = 'Ensure cpsit/quality-tools is properly installed';
        }

        return new ConfigurationException(
            $message,
            ConfigurationException::ERROR_CONFIG_FILE_NOT_FOUND,
            null,
            $troubleshooting,
            ['file_path' => $filePath, 'custom_path' => $customPath],
        );
    }

    /**
     * Create configuration validation failed exception.
     */
    public static function configValidationFailed(string $filePath, array $errors): ConfigurationException
    {
        $message = \sprintf('Configuration validation failed for %s: %s', $filePath, implode(', ', $errors));

        $troubleshooting = [
            'Check configuration file syntax',
            'Validate required configuration sections are present',
            'Compare with default configuration in vendor/cpsit/quality-tools/config/',
            'Run configuration validation command if available',
        ];

        return new ConfigurationException(
            $message,
            ConfigurationException::ERROR_CONFIG_VALIDATION_FAILED,
            null,
            $troubleshooting,
            ['file_path' => $filePath, 'validation_errors' => $errors],
        );
    }

    /**
     * Create process execution failed exception.
     */
    public static function processExecutionFailed(
        string $command,
        int $exitCode,
        string $output = '',
        string $errorOutput = '',
    ): ProcessException {
        $message = \sprintf('Process execution failed: %s (exit code: %d)', $command, $exitCode);

        $troubleshooting = [
            'Check if the tool binary is installed and in PATH',
            'Verify tool configuration is valid',
            'Try running the command manually to diagnose the issue',
            'Check system resources (memory, disk space)',
        ];

        // Add specific troubleshooting based on exit code
        if ($exitCode === 127) {
            $troubleshooting[] = 'Binary not found - ensure the tool is properly installed';
        } elseif ($exitCode === 137) {
            $troubleshooting[] = 'Process killed - likely due to memory limit or timeout';
        }

        return new ProcessException(
            $message,
            ProcessException::ERROR_PROCESS_EXECUTION_FAILED,
            null,
            $troubleshooting,
            [
                'command' => $command,
                'exit_code' => $exitCode,
                'output' => $output,
                'error_output' => $errorOutput,
            ],
            $exitCode,
        );
    }

    /**
     * Create process binary not found exception.
     */
    public static function processBinaryNotFound(string $binaryPath): ProcessException
    {
        $message = \sprintf('Required binary not found: %s', $binaryPath);

        $troubleshooting = [
            'Install the required tool using composer',
            'Check that vendor/bin directory is accessible',
            'Verify composer install completed successfully',
            'Check file permissions on vendor directory',
        ];

        return new ProcessException(
            $message,
            ProcessException::ERROR_PROCESS_BINARY_NOT_FOUND,
            null,
            $troubleshooting,
            ['binary_path' => $binaryPath],
        );
    }

    /**
     * Create file not found exception.
     */
    public static function fileNotFound(string $filePath): FileSystemException
    {
        $message = \sprintf('File not found: %s', $filePath);

        $troubleshooting = [
            'Verify the file path is correct',
            'Check if the file exists in the expected location',
            'Ensure you have read permissions for the file',
            'Check if the file was moved or deleted',
        ];

        return new FileSystemException(
            $message,
            FileSystemException::ERROR_FILE_NOT_FOUND,
            null,
            $troubleshooting,
            ['file_path' => $filePath],
            $filePath,
        );
    }

    /**
     * Create directory not found exception.
     */
    public static function directoryNotFound(string $directoryPath): FileSystemException
    {
        $message = \sprintf('Directory not found: %s', $directoryPath);

        $troubleshooting = [
            'Verify the directory path is correct',
            'Check if the directory exists',
            'Ensure you have read permissions for the directory',
            'Create the directory if it should exist',
        ];

        return new FileSystemException(
            $message,
            FileSystemException::ERROR_DIRECTORY_NOT_FOUND,
            null,
            $troubleshooting,
            ['directory_path' => $directoryPath],
            $directoryPath,
        );
    }

    /**
     * Create permission denied exception.
     */
    public static function permissionDenied(string $path, string $operation = 'access'): FileSystemException
    {
        $message = \sprintf('Permission denied: Cannot %s %s', $operation, $path);

        $troubleshooting = [
            'Check file/directory permissions using ls -la',
            'Ensure your user has appropriate permissions',
            'Try running with sudo if appropriate (be careful)',
            'Check if the file/directory is owned by another user',
        ];

        return new FileSystemException(
            $message,
            FileSystemException::ERROR_PERMISSION_DENIED,
            null,
            $troubleshooting,
            ['path' => $path, 'operation' => $operation],
            $path,
        );
    }

    /**
     * Create network timeout exception.
     */
    public static function networkTimeout(string $operation, int $timeoutSeconds = 30): TransientException
    {
        $message = \sprintf('Network timeout during %s after %d seconds', $operation, $timeoutSeconds);

        $troubleshooting = [
            'Check your internet connection',
            'Try again in a few moments',
            'Consider increasing timeout if available',
            'Check if any firewall is blocking the connection',
        ];

        return new TransientException(
            $message,
            TransientException::ERROR_NETWORK_TIMEOUT,
            null,
            $troubleshooting,
            ['operation' => $operation, 'timeout' => $timeoutSeconds],
            2, // retry after 2 seconds
        );
    }

    /**
     * Create temporary file lock exception.
     */
    public static function temporaryFileLock(string $filePath): TransientException
    {
        $message = \sprintf('Temporary file lock detected: %s', $filePath);

        $troubleshooting = [
            'Wait for other processes to complete',
            'Check if another quality tools process is running',
            'Remove stale lock files if confirmed they are orphaned',
            'Restart any stuck processes',
        ];

        return new TransientException(
            $message,
            TransientException::ERROR_TEMPORARY_FILE_LOCK,
            null,
            $troubleshooting,
            ['file_path' => $filePath],
            1, // retry after 1 second
        );
    }

    /**
     * Create memory pressure exception.
     */
    public static function memoryPressure(string $operation, string $currentLimit): TransientException
    {
        $message = \sprintf('Memory pressure detected during %s (limit: %s)', $operation, $currentLimit);

        $troubleshooting = [
            'Close other memory-intensive applications',
            'Consider increasing PHP memory limit',
            'Try processing smaller batches of files',
            'Check system memory usage with top or htop',
        ];

        return new TransientException(
            $message,
            TransientException::ERROR_MEMORY_PRESSURE,
            null,
            $troubleshooting,
            ['operation' => $operation, 'memory_limit' => $currentLimit],
            3, // retry after 3 seconds
        );
    }
}

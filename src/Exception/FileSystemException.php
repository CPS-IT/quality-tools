<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Exception;

/**
 * Exception thrown when file system operations fail.
 */
class FileSystemException extends QualityToolsException
{
    public const ERROR_FILE_NOT_FOUND = 3001;
    public const ERROR_DIRECTORY_NOT_FOUND = 3002;
    public const ERROR_FILE_NOT_READABLE = 3003;
    public const ERROR_FILE_NOT_WRITABLE = 3004;
    public const ERROR_PERMISSION_DENIED = 3005;
    public const ERROR_DISK_FULL = 3006;

    public function __construct(
        string $message = '',
        int $code = self::ERROR_FILE_NOT_FOUND,
        ?\Throwable $previous = null,
        array $troubleshootingSteps = [],
        array $context = [],
        private readonly string $filePath = '',
    ) {
        parent::__construct($message, $code, $previous, $troubleshootingSteps, $context);
    }

    public function getFilePath(): string
    {
        return $this->filePath;
    }
}

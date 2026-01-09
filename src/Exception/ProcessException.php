<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Exception;

/**
 * Exception thrown when process execution issues occur
 */
class ProcessException extends QualityToolsException
{
    public const ERROR_PROCESS_EXECUTION_FAILED = 2001;
    public const ERROR_PROCESS_TIMEOUT = 2002;
    public const ERROR_PROCESS_MEMORY_LIMIT = 2003;
    public const ERROR_PROCESS_BINARY_NOT_FOUND = 2004;
    public const ERROR_PROCESS_PERMISSION_DENIED = 2005;

    public function __construct(
        string $message = '',
        int $code = self::ERROR_PROCESS_EXECUTION_FAILED,
        ?\Throwable $previous = null,
        private readonly array $troubleshootingSteps = [],
        private readonly array $context = [],
        private readonly int $processExitCode = 0
    ) {
        parent::__construct($message, $code, $previous, $troubleshootingSteps, $context);
    }

    public function getProcessExitCode(): int
    {
        return $this->processExitCode;
    }
}
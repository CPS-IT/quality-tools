<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Exception;

/**
 * Exception thrown for transient failures that may succeed on retry.
 */
class TransientException extends QualityToolsException
{
    public const int ERROR_NETWORK_TIMEOUT = 4001;
    public const int ERROR_TEMPORARY_FILE_LOCK = 4002;
    public const int ERROR_MEMORY_PRESSURE = 4003;
    public const int ERROR_SERVICE_UNAVAILABLE = 4004;
    public const int ERROR_RATE_LIMIT_EXCEEDED = 4005;

    public function __construct(
        string $message = '',
        int $code = self::ERROR_SERVICE_UNAVAILABLE,
        ?\Throwable $previous = null,
        array $troubleshootingSteps = [],
        array $context = [],
        private readonly int $retryAfter = 1,
    ) {
        parent::__construct($message, $code, $previous, $troubleshootingSteps, $context);
    }

    public function getRetryAfter(): int
    {
        return $this->retryAfter;
    }

    #[\Override]
    public function isRetryable(): bool
    {
        return true;
    }
}

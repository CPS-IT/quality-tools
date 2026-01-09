<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Exception;

/**
 * Base exception for all Quality Tools exceptions with structured error information
 */
abstract class QualityToolsException extends \RuntimeException
{
    private readonly array $troubleshootingSteps;
    private readonly array $context;

    public function __construct(
        string $message = '',
        int $code = 0,
        ?\Throwable $previous = null,
        array $troubleshootingSteps = [],
        array $context = []
    ) {
        parent::__construct($message, $code, $previous);
        $this->troubleshootingSteps = $troubleshootingSteps;
        $this->context = $context;
    }

    /**
     * Get structured error information
     */
    public function getErrorInfo(): array
    {
        return [
            'type' => $this->getErrorType(),
            'code' => $this->getCode(),
            'message' => $this->getMessage(),
            'troubleshooting' => $this->troubleshootingSteps,
            'context' => $this->context,
            'timestamp' => date('Y-m-d H:i:s'),
        ];
    }

    /**
     * Get error type based on class name
     */
    public function getErrorType(): string
    {
        $className = static::class;
        $shortName = substr($className, strrpos($className, '\\') + 1);
        return strtolower(str_replace('Exception', '', $shortName));
    }

    /**
     * Get troubleshooting steps
     */
    public function getTroubleshootingSteps(): array
    {
        return $this->troubleshootingSteps;
    }

    /**
     * Get error context
     */
    public function getContext(): array
    {
        return $this->context;
    }

    /**
     * Check if this exception represents a retryable error
     */
    public function isRetryable(): bool
    {
        return false;
    }

    /**
     * Get suggested exit code for this exception type
     */
    public function getSuggestedExitCode(): int
    {
        return match ($this->getErrorType()) {
            'configuration' => 2,
            'process' => 3,
            'filesystem' => 4,
            'transient' => 5,
            'pathscanner' => 6,
            'vendordirectorynotfound' => 7,
            default => 1,
        };
    }
}
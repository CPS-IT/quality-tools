<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Exception;

/**
 * Exception thrown when configuration-related issues occur.
 */
class ConfigurationException extends QualityToolsException
{
    public const ERROR_CONFIG_FILE_NOT_FOUND = 1001;
    public const ERROR_CONFIG_FILE_INVALID = 1002;
    public const ERROR_CONFIG_PATH_NOT_ACCESSIBLE = 1003;
    public const ERROR_CONFIG_VALIDATION_FAILED = 1004;

    public function __construct(
        string $message = '',
        int $code = self::ERROR_CONFIG_VALIDATION_FAILED,
        ?\Throwable $previous = null,
        private readonly array $troubleshootingSteps = [],
        private readonly array $context = [],
    ) {
        parent::__construct($message, $code, $previous, $troubleshootingSteps, $context);
    }
}

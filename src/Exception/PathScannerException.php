<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Exception;

/**
 * Exception thrown when path scanning operations fail.
 */
class PathScannerException extends QualityToolsException
{
    public const ERROR_PATH_RESOLUTION_FAILED = 6001;
    public const ERROR_PATH_VALIDATION_FAILED = 6002;
    public const ERROR_PATTERN_INVALID = 6003;

    public function __construct(
        string $message = '',
        int $code = self::ERROR_PATH_RESOLUTION_FAILED,
        ?\Throwable $previous = null,
        array $troubleshootingSteps = [],
        array $context = [],
    ) {
        if (empty($troubleshootingSteps)) {
            $troubleshootingSteps = [
                'Check that target paths exist and are accessible',
                'Verify path patterns are correctly formatted',
                'Ensure sufficient permissions to read target directories',
                'Use --verbose flag to see detailed path resolution information',
            ];
        }

        parent::__construct($message, $code, $previous, $troubleshootingSteps, $context);
    }
}

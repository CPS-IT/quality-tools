<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Exception;

/**
 * Exception thrown when vendor directory cannot be detected or located.
 *
 * This exception is thrown by VendorDirectoryDetector when all detection
 * methods fail to locate a valid vendor directory for the project.
 */
final class VendorDirectoryNotFoundException extends QualityToolsException
{
    public const int ERROR_VENDOR_DIRECTORY_NOT_FOUND = 7001;
    public const int ERROR_VENDOR_DIRECTORY_INVALID = 7002;

    public function __construct(
        string $message = '',
        int $code = self::ERROR_VENDOR_DIRECTORY_NOT_FOUND,
        ?\Throwable $previous = null,
        array $troubleshootingSteps = [],
        array $context = [],
    ) {
        if (empty($troubleshootingSteps)) {
            $troubleshootingSteps = [
                'Ensure composer install has been run successfully',
                'Check that vendor directory exists in your project',
                'Verify cpsit/quality-tools is installed via composer',
                'Try running composer install --prefer-dist --no-dev',
            ];
        }

        parent::__construct($message, $code, $previous, $troubleshootingSteps, $context);
    }
}

<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Exception;

/**
 * Exception thrown when vendor directory cannot be detected or located
 * 
 * This exception is thrown by VendorDirectoryDetector when all detection
 * methods fail to locate a valid vendor directory for the project.
 */
final class VendorDirectoryNotFoundException extends \RuntimeException
{
    public function __construct(string $message = '', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
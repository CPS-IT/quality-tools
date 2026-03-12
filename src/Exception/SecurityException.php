<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Exception;

/**
 * Exception thrown when security-related issues occur.
 */
class SecurityException extends QualityToolsException
{
    public const int ERROR_DIRECTORY_TRAVERSAL = 2001;
    public const int ERROR_PATH_OUTSIDE_BOUNDARIES = 2002;
    public const int ERROR_INVALID_FILE_TYPE = 2003;
    public const int ERROR_UNSAFE_PATH_CONTENT = 2004;
    public const int ERROR_FILE_SIZE_EXCEEDED = 2005;
    public const int ERROR_INSECURE_PERMISSIONS = 2006;

    public function __construct(
        string $message = '',
        int $code = self::ERROR_UNSAFE_PATH_CONTENT,
        ?\Throwable $previous = null,
        array $troubleshootingSteps = [],
        array $context = [],
    ) {
        parent::__construct($message, $code, $previous, $troubleshootingSteps, $context);
    }

    #[\Override]
    public function getSuggestedExitCode(): int
    {
        return 8; // Security-specific exit code
    }
}

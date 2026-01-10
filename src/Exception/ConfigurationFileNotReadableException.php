<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Exception;

/**
 * Exception thrown when a configuration file exists but is not readable.
 */
final class ConfigurationFileNotReadableException extends ConfigurationFileException
{
    public function __construct(string $filePath, ?\Throwable $previous = null)
    {
        parent::__construct('File exists but is not readable', $filePath, $previous);
    }
}

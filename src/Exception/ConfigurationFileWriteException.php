<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Exception;

/**
 * Exception thrown when a configuration file cannot be written.
 */
final class ConfigurationFileWriteException extends ConfigurationFileException
{
    public function __construct(string $message, string $filePath, ?\Throwable $previous = null)
    {
        parent::__construct($message, $filePath, $previous);
    }
}

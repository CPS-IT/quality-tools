<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Exception;

/**
 * Exception thrown when a configuration file is not found.
 */
final class ConfigurationFileNotFoundException extends ConfigurationFileException
{
    public function __construct(string $filePath, ?\Throwable $previous = null)
    {
        parent::__construct('File not found', $filePath, $previous);
    }
}

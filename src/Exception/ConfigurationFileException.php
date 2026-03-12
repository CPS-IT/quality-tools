<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Exception;

/**
 * Base exception for configuration file related errors.
 */
class ConfigurationFileException extends \RuntimeException
{
    public function __construct(string $message, string $filePath, ?\Throwable $previous = null)
    {
        $fullMessage = \sprintf('Configuration file error [%s]: %s', $filePath, $message);
        parent::__construct($fullMessage, 0, $previous);
    }
}

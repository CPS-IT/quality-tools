<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Traits;

use Cpsit\QualityTools\Exception\ConfigurationFileNotFoundException;
use Cpsit\QualityTools\Exception\ConfigurationFileNotReadableException;
use Cpsit\QualityTools\Exception\ConfigurationLoadException;
use Cpsit\QualityTools\Exception\FileSystemException;

/**
 * Trait providing configuration file reading functionality.
 *
 * This trait provides methods to safely read configuration files from disk
 * with proper error handling and exception conversion.
 */
trait ConfigurationFileReaderTrait
{
    /**
     * Read the configuration file content with proper error handling.
     *
     * @param string $path The path to the configuration file
     *
     * @throws ConfigurationFileNotFoundException    When the file is not found
     * @throws ConfigurationFileNotReadableException When the file is not readable
     * @throws ConfigurationLoadException            For other file access errors
     *
     * @return string The file content
     */
    protected function readConfigurationFile(string $path): string
    {
        try {
            return $this->filesystemService->readFile($path);
        } catch (FileSystemException $e) {
            // Convert filesystem exceptions to configuration-specific exceptions
            if ($e->getCode() === FileSystemException::ERROR_FILE_NOT_FOUND) {
                throw new ConfigurationFileNotFoundException($path);
            }
            if ($e->getCode() === FileSystemException::ERROR_FILE_NOT_READABLE) {
                throw new ConfigurationFileNotReadableException($path);
            }
            throw new ConfigurationLoadException('Failed to read configuration file: ' . $e->getMessage(), $path, $e);
        } catch (\Exception $e) {
            // Handle cases where filesystemService is not available or other errors
            if (!file_exists($path)) {
                throw new ConfigurationFileNotFoundException($path);
            }
            if (!is_readable($path)) {
                throw new ConfigurationFileNotReadableException($path);
            }
            throw new ConfigurationLoadException('Failed to read configuration file: ' . $e->getMessage(), $path, $e);
        }
    }
}

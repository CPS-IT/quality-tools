<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Configuration\Validator;

use Cpsit\QualityTools\Exception\FileSystemException;
use Cpsit\QualityTools\Service\FilesystemService;

/**
 * Trait providing common validation functionality for tool validators.
 */
trait ToolValidationTrait
{
    protected ?string $lastError = null;
    private readonly FilesystemService $filesystemService;

    public function __construct(FilesystemService $filesystemService)
    {
        $this->filesystemService = $filesystemService;
    }

    public function getLastError(): ?string
    {
        return $this->lastError;
    }

    public function getToolName(): string
    {
        if (!\defined('static::TOOL_NAME')) {
            throw new \LogicException(\sprintf('Class %s must define TOOL_NAME constant', static::class));
        }

        return static::TOOL_NAME;
    }

    public function getSupportedExtensions(): array
    {
        if (!\defined('static::SUPPORTED_EXTENSIONS')) {
            throw new \LogicException(\sprintf('Class %s must define SUPPORTED_EXTENSIONS constant', static::class));
        }

        return static::SUPPORTED_EXTENSIONS;
    }

    /**
     * Validate PHP syntax without executing the file using a consistent method.
     */
    protected function validatePhpSyntaxWithTempFile(string $path, string $toolPrefix): bool
    {
        $tempFile = null;

        try {
            // Read file content using FilesystemService
            $content = $this->filesystemService->readFile($path);

            // Create temporary file using FilesystemService
            $tempFile = $this->filesystemService->createTempFile($toolPrefix . '_syntax_check_');

            // Write content to temp file using FilesystemService
            $this->filesystemService->writeFile($tempFile, $content);

            // Check PHP syntax
            $command = \sprintf('php -l %s 2>&1', escapeshellarg((string) $tempFile));
            $output = [];
            $returnCode = 0;

            exec($command, $output, $returnCode);

            if ($returnCode !== 0) {
                $this->lastError = 'PHP syntax error: ' . implode(' ', $output);

                return false;
            }

            return true;
        } catch (FileSystemException $e) {
            $this->lastError = "File operation failed: {$e->getMessage()}";

            return false;
        } catch (\Throwable $e) {
            $this->lastError = "Syntax validation failed: {$e->getMessage()}";

            return false;
        } finally {
            // Clean up temporary file
            if ($tempFile !== null) {
                try {
                    $this->filesystemService->removeFile($tempFile);
                } catch (FileSystemException) {
                    // Log but don't fail the validation for cleanup issues
                }
            }
        }
    }

    /**
     * Check if file content contains any of the specified patterns.
     */
    protected function containsAnyPattern(string $content, array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            if (str_contains($content, (string) $pattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Perform common file existence and readability checks using FilesystemService.
     */
    protected function performBasicFileChecks(string $path): bool
    {
        try {
            if (!$this->filesystemService->fileExists($path)) {
                $this->lastError = "Configuration file does not exist: {$path}";

                return false;
            }

            if (!$this->filesystemService->isReadable($path)) {
                $this->lastError = "Configuration file is not readable: {$path}";

                return false;
            }

            return true;
        } catch (FileSystemException $e) {
            $this->lastError = "File check failed: {$e->getMessage()}";

            return false;
        }
    }
}

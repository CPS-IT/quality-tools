<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Service;

final class TemporaryFile
{
    private string $filePath;
    private bool $isDeleted = false;
    private readonly SecurityService $securityService;

    public function __construct(string $prefix = 'qt_temp_', string $suffix = '', ?SecurityService $securityService = null)
    {
        $this->securityService = $securityService ?? new SecurityService();

        $tempDir = sys_get_temp_dir();
        $tempFile = tempnam($tempDir, $prefix);

        if ($tempFile === false) {
            throw new \RuntimeException('Failed to create temporary file');
        }

        // If suffix is provided, rename the file to include the suffix
        if ($suffix !== '') {
            $this->filePath = $tempFile . $suffix;
            if (!rename($tempFile, $this->filePath)) {
                unlink($tempFile);
                throw new \RuntimeException('Failed to add suffix to temporary file');
            }
        } else {
            $this->filePath = $tempFile;
        }

        // Set secure file permissions (readable/writable by owner only)
        try {
            $this->securityService->setSecureFilePermissions($this->filePath);
        } catch (\RuntimeException $e) {
            // If we can't set secure permissions, clean up and fail
            unlink($this->filePath);
            throw new \RuntimeException('Failed to set secure permissions on temporary file: ' . $e->getMessage());
        }

        // Log temporary file creation for debugging
        if (getenv('QT_DEBUG_TEMP_FILES') === '1') {
            error_log(\sprintf('[QT] Created temporary file with secure permissions: %s', $this->filePath));
        }

        // Register cleanup on process shutdown as fallback
        register_shutdown_function([$this, 'cleanup']);
    }

    public function __destruct()
    {
        $this->cleanup();
    }

    public function getPath(): string
    {
        return $this->filePath;
    }

    public function write(string $content): void
    {
        if ($this->isDeleted) {
            throw new \RuntimeException('Cannot write to deleted temporary file');
        }

        if (file_put_contents($this->filePath, $content) === false) {
            throw new \RuntimeException(\sprintf('Could not write to temporary file: %s', $this->filePath));
        }
    }

    public function cleanup(): void
    {
        if (!$this->isDeleted && file_exists($this->filePath)) {
            // Log temporary file cleanup for debugging
            if (getenv('QT_DEBUG_TEMP_FILES') === '1') {
                error_log(\sprintf('[QT] Cleaning up temporary file: %s', $this->filePath));
            }

            unlink($this->filePath);
            $this->isDeleted = true;
        }
    }

    public function isDeleted(): bool
    {
        return $this->isDeleted;
    }
}

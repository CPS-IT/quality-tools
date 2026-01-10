<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Service;

use Cpsit\QualityTools\Exception\FileSystemException;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;

final readonly class FilesystemService
{
    public function __construct(
        private Filesystem $filesystem = new Filesystem(),
    ) {
    }

    public function fileExists(string $path): bool
    {
        return $this->filesystem->exists($path) && is_file($path);
    }

    public function directoryExists(string $path): bool
    {
        return $this->filesystem->exists($path) && is_dir($path);
    }

    public function isReadable(string $path): bool
    {
        return is_readable($path);
    }

    public function isWritable(string $path): bool
    {
        return is_writable($path);
    }

    public function readFile(string $path): string
    {
        if (!$this->fileExists($path)) {
            throw new FileSystemException('File not found', FileSystemException::ERROR_FILE_NOT_FOUND, null, [], [], $path);
        }

        if (!$this->isReadable($path)) {
            throw new FileSystemException('File exists but is not readable', FileSystemException::ERROR_FILE_NOT_READABLE, null, [], [], $path);
        }

        $content = file_get_contents($path);
        if ($content === false) {
            throw new FileSystemException('Failed to read file contents', FileSystemException::ERROR_FILE_NOT_READABLE, null, [], [], $path);
        }

        return $content;
    }

    public function writeFile(string $path, string $content): void
    {
        $directory = \dirname($path);

        if (!$this->directoryExists($directory)) {
            throw new FileSystemException('Directory does not exist', FileSystemException::ERROR_DIRECTORY_NOT_FOUND, null, [], [], \dirname($path));
        }

        if (!$this->isWritable($directory)) {
            throw new FileSystemException('Directory is not writable', FileSystemException::ERROR_PERMISSION_DENIED, null, [], [], \dirname($path));
        }

        if ($this->fileExists($path) && !$this->isWritable($path)) {
            throw new FileSystemException('File exists but is not writable', FileSystemException::ERROR_FILE_NOT_WRITABLE, null, [], [], $path);
        }

        try {
            $this->filesystem->dumpFile($path, $content);
        } catch (IOException $e) {
            throw new FileSystemException('Failed to write file: ' . $e->getMessage(), FileSystemException::ERROR_FILE_NOT_WRITABLE, $e, [], [], $path);
        }
    }

    public function createDirectory(string $path, int $mode = 0o755): void
    {
        if ($this->directoryExists($path)) {
            return;
        }

        try {
            $this->filesystem->mkdir($path, $mode);
        } catch (IOException $e) {
            throw new FileSystemException('Failed to create directory: ' . $e->getMessage(), FileSystemException::ERROR_PERMISSION_DENIED, $e, [], [], $path);
        }
    }

    public function removeDirectory(string $path): void
    {
        if (!$this->directoryExists($path)) {
            return;
        }

        try {
            $this->filesystem->remove($path);
        } catch (IOException $e) {
            throw new FileSystemException('Failed to remove directory: ' . $e->getMessage(), FileSystemException::ERROR_PERMISSION_DENIED, $e, [], [], $path);
        }
    }

    public function createTempFile(string $prefix = 'qt_', string $suffix = ''): string
    {
        $tempDir = sys_get_temp_dir();
        $tempFile = tempnam($tempDir, $prefix);

        if ($tempFile === false) {
            throw new FileSystemException('Failed to create temporary file', FileSystemException::ERROR_PERMISSION_DENIED, null, [], [], $tempDir);
        }

        // If a suffix is provided, rename the file to include the suffix
        if ($suffix !== '') {
            $newTempFile = $tempFile . $suffix;
            if (!rename($tempFile, $newTempFile)) {
                unlink($tempFile);
                throw new FileSystemException('Failed to add suffix to temporary file', FileSystemException::ERROR_PERMISSION_DENIED, null, [], [], $tempFile);
            }

            return $newTempFile;
        }

        return $tempFile;
    }

    public function createTempDirectory(string $prefix = 'qt_'): string
    {
        $tempDir = sys_get_temp_dir();
        $tempDirectory = $tempDir . DIRECTORY_SEPARATOR . $prefix . uniqid('', true);

        $this->createDirectory($tempDirectory);

        return $tempDirectory;
    }

    public function realpath(string $path): string
    {
        $realPath = realpath($path);
        if ($realPath === false) {
            throw new FileSystemException('Failed to resolve real path', FileSystemException::ERROR_FILE_NOT_FOUND, null, [], [], $path);
        }

        return $realPath;
    }

    public function normalizePath(string $path): string
    {
        return str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $path);
    }

    public function makePathRelative(string $endPath, string $startPath): string
    {
        return $this->filesystem->makePathRelative($endPath, $startPath);
    }

    public function isAbsolutePath(string $path): bool
    {
        return $this->filesystem->isAbsolutePath($path);
    }
}

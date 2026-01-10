<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Tests\Unit;

use Cpsit\QualityTools\Service\FilesystemService;
use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamDirectory;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Virtual filesystem-aware FilesystemService for testing.
 */
final class TestFilesystemService
{
    public function __construct(private ?vfsStreamDirectory $vfsRoot = null, private readonly ?FilesystemService $filesystemService = new FilesystemService(new Filesystem()))
    {
    }

    /**
     * Initialize virtual filesystem with given structure.
     */
    public function initializeVfs(array $structure = []): vfsStreamDirectory
    {
        $this->vfsRoot = vfsStream::setup('root', null, $structure);

        return $this->vfsRoot;
    }

    /**
     * Get virtual filesystem root URL.
     */
    public function getVfsUrl(): string
    {
        if ($this->vfsRoot === null) {
            $this->initializeVfs();
        }

        return $this->vfsRoot->url();
    }

    /**
     * Create a virtual file with given content.
     */
    public function createVfsFile(string $path, string $content): string
    {
        if ($this->vfsRoot === null) {
            $this->initializeVfs();
        }

        $fullPath = $this->getVfsUrl() . '/' . ltrim($path, '/');
        $this->filesystemService->writeFile($fullPath, $content);

        return $fullPath;
    }

    /**
     * Create a virtual directory structure.
     */
    public function createVfsDirectory(string $path): string
    {
        if ($this->vfsRoot === null) {
            $this->initializeVfs();
        }

        $fullPath = $this->getVfsUrl() . '/' . ltrim($path, '/');
        $this->filesystemService->createDirectory($fullPath);

        return $fullPath;
    }

    /**
     * Reset the virtual filesystem.
     */
    public function resetVfs(): void
    {
        $this->vfsRoot = null;
    }

    /**
     * Check if a path is within the virtual filesystem.
     */
    public function isVfsPath(string $path): bool
    {
        return str_starts_with($path, 'vfs://');
    }

    /**
     * Forward filesystem operations to the composed service.
     */
    public function fileExists(string $path): bool
    {
        return $this->filesystemService->fileExists($path);
    }

    public function directoryExists(string $path): bool
    {
        return $this->filesystemService->directoryExists($path);
    }

    public function readFile(string $path): string
    {
        return $this->filesystemService->readFile($path);
    }

    public function writeFile(string $path, string $content): void
    {
        $this->filesystemService->writeFile($path, $content);
    }

    public function createDirectory(string $path): void
    {
        $this->filesystemService->createDirectory($path);
    }
}

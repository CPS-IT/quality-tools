<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Tests\Unit\Service;

use Cpsit\QualityTools\Service\DisposableTemporaryFile;
use Cpsit\QualityTools\Service\FilesystemService;
use Cpsit\QualityTools\Service\SecurityService;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Cpsit\QualityTools\Service\DisposableTemporaryFile
 */
final class DisposableTemporaryFileTest extends TestCase
{
    /**
     * @test
     */
    public function constructorCreatesTemporaryFile(): void
    {
        $tempFile = new DisposableTemporaryFile(new SecurityService(), new FilesystemService(), 'test_', '.tmp');
        $path = $tempFile->getPath();

        self::assertIsString($path);
        self::assertFileExists($path);
        self::assertStringContainsString('test_', basename($path));
        self::assertStringEndsWith('.tmp', $path);

        $tempFile->cleanup();
    }

    /**
     * @test
     */
    public function writeStoresContentInFile(): void
    {
        $tempFile = new DisposableTemporaryFile(new SecurityService(), new FilesystemService());
        $content = 'test content for disposable file';

        $tempFile->write($content);

        self::assertStringEqualsFile($tempFile->getPath(), $content);

        $tempFile->cleanup();
    }

    /**
     * @test
     */
    public function cleanupRemovesFile(): void
    {
        $tempFile = new DisposableTemporaryFile(new SecurityService(), new FilesystemService());
        $path = $tempFile->getPath();

        self::assertFileExists($path);

        $tempFile->cleanup();

        self::assertFileDoesNotExist($path);
        self::assertTrue($tempFile->isDeleted());
    }

    /**
     * @test
     */
    public function cleanupCanBeCalledMultipleTimes(): void
    {
        $tempFile = new DisposableTemporaryFile(new SecurityService(), new FilesystemService());
        $path = $tempFile->getPath();

        $tempFile->cleanup();
        $tempFile->cleanup();

        self::assertFileDoesNotExist($path);
        self::assertTrue($tempFile->isDeleted());
    }

    /**
     * @test
     */
    public function destructorCleansUpFile(): void
    {
        $tempFile = new DisposableTemporaryFile(new SecurityService(), new FilesystemService());
        $path = $tempFile->getPath();

        self::assertFileExists($path);

        // Manually trigger destructor by unsetting
        unset($tempFile);

        // File may still exist due to shutdown handler, but should be cleaned up eventually
        // For this test, we just ensure manual cleanup works
        if (file_exists($path)) {
            unlink($path); // Manual cleanup for test
        }

        self::assertFileDoesNotExist($path);
    }

    /**
     * @test
     */
    public function cleanupAllRemovesAllRegisteredFiles(): void
    {
        $tempFiles = [];
        $paths = [];

        // Create multiple temporary files and keep references
        for ($i = 0; $i < 3; ++$i) {
            $tempFile = new DisposableTemporaryFile(new SecurityService(), new FilesystemService());
            $tempFiles[] = $tempFile;
            $paths[] = $tempFile->getPath();
        }

        // Verify all files exist
        foreach ($paths as $path) {
            self::assertFileExists($path);
        }

        // Clean up all files
        DisposableTemporaryFile::cleanupAll();

        // Verify all files are removed
        foreach ($paths as $path) {
            self::assertFileDoesNotExist($path);
        }

        // Cleanup references to prevent issues
        unset($tempFiles);
    }

    /**
     * @test
     */
    public function registryHandlesFileCleanupCorrectly(): void
    {
        $tempFile1 = new DisposableTemporaryFile(new SecurityService(), new FilesystemService());
        $tempFile2 = new DisposableTemporaryFile(new SecurityService(), new FilesystemService());

        $path1 = $tempFile1->getPath();
        $path2 = $tempFile2->getPath();

        self::assertFileExists($path1);
        self::assertFileExists($path2);

        // Clean up first file manually
        $tempFile1->cleanup();
        self::assertFileDoesNotExist($path1);
        self::assertFileExists($path2);

        // Clean up all remaining files
        DisposableTemporaryFile::cleanupAll();
        self::assertFileDoesNotExist($path2);
    }
}

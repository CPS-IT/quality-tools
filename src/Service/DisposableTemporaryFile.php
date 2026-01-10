<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Service;

final class DisposableTemporaryFile
{
    private readonly TemporaryFile $temporaryFile;
    private static array $registry = [];

    public function __construct(
        SecurityService $securityService,
        FilesystemService $filesystemService,
        string $prefix = 'qt_temp_',
        string $suffix = '',
    ) {
        $this->temporaryFile = new TemporaryFile($securityService, $filesystemService, $prefix, $suffix);
        self::$registry[spl_object_id($this)] = $this->temporaryFile;
    }

    public function __destruct()
    {
        $this->cleanup();
    }

    public function getPath(): string
    {
        return $this->temporaryFile->getPath();
    }

    public function write(string $content): void
    {
        $this->temporaryFile->write($content);
    }

    public function cleanup(): void
    {
        $objectId = spl_object_id($this);
        if (isset(self::$registry[$objectId])) {
            self::$registry[$objectId]->cleanup();
            unset(self::$registry[$objectId]);
        }
    }

    public function isDeleted(): bool
    {
        return $this->temporaryFile->isDeleted();
    }

    /**
     * Emergency cleanup for all registered temporary files
     * Called during process shutdown.
     */
    public static function cleanupAll(): void
    {
        foreach (self::$registry as $temporaryFile) {
            $temporaryFile->cleanup();
        }
        self::$registry = [];
    }
}

// Register emergency cleanup on shutdown
register_shutdown_function([DisposableTemporaryFile::class, 'cleanupAll']);

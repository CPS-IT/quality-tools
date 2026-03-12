<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Service;

use Cpsit\QualityTools\Exception\ConfigurationException;
use Cpsit\QualityTools\Exception\FileSystemException;
use Cpsit\QualityTools\Exception\SecurityException;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;

readonly class FilesystemService
{
    /**
     * Allowed configuration file extensions for each tool.
     */
    private const array ALLOWED_CONFIG_EXTENSIONS = [
        'rector' => ['php'],
        'phpstan' => ['neon', 'neon.dist'],
        'fractor' => ['php'],
        'php-cs-fixer' => ['php'],
        'typoscript-lint' => ['yml', 'yaml'],
    ];

    /**
     * Maximum configuration file size in bytes (1MB).
     */
    private const int MAX_CONFIG_FILE_SIZE = 1024 * 1024;

    public function __construct(
        private Filesystem $filesystem,
        private SecurityService $securityService,
    ) {
    }

    public function fileExists(string $path, ?string $projectRoot = null, ?string $toolName = null): bool
    {
        if ($projectRoot !== null) {
            $path = $this->validateAndResolvePath($path, $projectRoot, $toolName);
        }

        return $this->filesystem->exists($path) && is_file($path);
    }

    public function directoryExists(string $path, ?string $projectRoot = null): bool
    {
        if ($projectRoot !== null) {
            $path = $this->validateAndResolvePath($path, $projectRoot);
        }

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

    public function removeFile(string $path): void
    {
        if (!$this->fileExists($path)) {
            return;
        }

        try {
            $this->filesystem->remove($path);
        } catch (IOException $e) {
            throw new FileSystemException('Failed to remove file: ' . $e->getMessage(), FileSystemException::ERROR_PERMISSION_DENIED, $e, [], [], $path);
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

    /**
     * Validates and resolves a configuration file path securely.
     *
     * @param string $path        Configuration file path to validate
     * @param string $projectRoot Project root directory
     * @param string $toolName    Tool name for validation
     *
     * @throws SecurityException      If path validation fails
     * @throws ConfigurationException If file validation fails
     *
     * @return string Validated and normalized path
     */
    public function validateConfigurationPath(string $path, string $projectRoot, string $toolName): string
    {
        $sanitizedPath = $this->securityService->sanitizePath($path);
        $resolvedPath = $this->resolvePath($sanitizedPath, $projectRoot);

        $this->validatePathBoundaries($resolvedPath, $projectRoot);
        $this->validateConfigurationFile($resolvedPath, $toolName);

        return $resolvedPath;
    }

    /**
     * Validates and resolves a path with security validation.
     *
     * @param string      $path        Path to resolve and validate
     * @param string      $projectRoot Project root directory
     * @param string|null $toolName    Optional tool name for additional validation
     *
     * @throws SecurityException If path validation fails
     *
     * @return string Validated and resolved path
     */
    private function validateAndResolvePath(string $path, string $projectRoot, ?string $toolName = null): string
    {
        $sanitizedPath = $this->securityService->sanitizePath($path);
        $resolvedPath = $this->resolvePath($sanitizedPath, $projectRoot);
        $this->validatePathBoundaries($resolvedPath, $projectRoot);

        if ($toolName !== null && $this->filesystem->exists($resolvedPath)) {
            $this->validateConfigurationFile($resolvedPath, $toolName);
        }

        return $resolvedPath;
    }

    /**
     * Resolves a path relative to the project root.
     *
     * @param string $path        Path to resolve
     * @param string $projectRoot Project root directory
     *
     * @return string Resolved absolute path
     */
    private function resolvePath(string $path, string $projectRoot): string
    {
        if ($this->isAbsolutePath($path)) {
            return $this->normalizePath($path);
        }

        return $this->normalizePath($projectRoot . DIRECTORY_SEPARATOR . $path);
    }

    /**
     * Validates that a path is within project boundaries.
     *
     * @param string $path        Path to validate
     * @param string $projectRoot Project root directory
     *
     * @throws SecurityException If a path is outside boundaries
     */
    private function validatePathBoundaries(string $path, string $projectRoot): void
    {
        if (!$this->filesystem->exists($path) && !$this->filesystem->exists(\dirname($path))) {
            throw new SecurityException(\sprintf('Path parent directory does not exist: %s', $path), SecurityException::ERROR_PATH_OUTSIDE_BOUNDARIES, null, ['Ensure the path exists within the project', 'Check directory permissions'], ['path' => $path, 'project_root' => $projectRoot]);
        }

        try {
            $realPath = $this->realpath(\dirname($path));
            $realProjectRoot = $this->realpath($projectRoot);

            if (!str_starts_with($realPath, $realProjectRoot)) {
                throw new SecurityException(\sprintf('Path is outside project boundaries: %s', $path), SecurityException::ERROR_PATH_OUTSIDE_BOUNDARIES, null, ['Use paths relative to project root', 'Avoid directory traversal patterns'], ['path' => $path, 'real_path' => $realPath, 'project_root' => $realProjectRoot]);
            }
        } catch (\RuntimeException $e) {
            throw new SecurityException(\sprintf('Cannot resolve path boundaries for: %s', $path), SecurityException::ERROR_PATH_OUTSIDE_BOUNDARIES, $e, ['Ensure path exists and is accessible', 'Check file permissions'], ['path' => $path, 'project_root' => $projectRoot]);
        }
    }

    /**
     * Validates a configuration file for a specific tool.
     *
     * @param string $path     Configuration file path
     * @param string $toolName Tool name
     *
     * @throws ConfigurationException If file validation fails
     * @throws SecurityException      If file has security issues
     */
    private function validateConfigurationFile(string $path, string $toolName): void
    {
        if (!$this->filesystem->exists($path) || !is_file($path)) {
            throw new ConfigurationException(\sprintf('Configuration file not found: %s', $path), ConfigurationException::ERROR_CONFIG_FILE_NOT_FOUND, null, ['Check if the file path is correct', 'Ensure the file exists in the project'], ['path' => $path, 'tool' => $toolName]);
        }

        if (!is_readable($path)) {
            throw new ConfigurationException(\sprintf('Configuration file is not readable: %s', $path), ConfigurationException::ERROR_CONFIG_PATH_NOT_ACCESSIBLE, null, ['Check file permissions', 'Ensure read access to the file'], ['path' => $path, 'tool' => $toolName]);
        }

        $this->validateFileSize($path);
        $this->validateFileType($path, $toolName);
    }

    /**
     * Validates file size limits.
     *
     * @param string $path File path to check
     *
     * @throws SecurityException If file is too large
     */
    private function validateFileSize(string $path): void
    {
        $fileSize = filesize($path);

        if ($fileSize === false || $fileSize > self::MAX_CONFIG_FILE_SIZE) {
            throw new SecurityException(\sprintf('Configuration file exceeds maximum size limit: %s', $path), SecurityException::ERROR_FILE_SIZE_EXCEEDED, null, ['Reduce configuration file size', 'Split large configurations'], ['path' => $path, 'size' => $fileSize, 'limit' => self::MAX_CONFIG_FILE_SIZE]);
        }
    }

    /**
     * Validates file type for specific tool.
     *
     * @param string $path     File path to check
     * @param string $toolName Tool name
     *
     * @throws SecurityException If file type is invalid
     */
    private function validateFileType(string $path, string $toolName): void
    {
        $fileName = basename($path);
        $allowedExtensions = self::ALLOWED_CONFIG_EXTENSIONS[$toolName] ?? [];

        $isValidExtension = false;
        foreach ($allowedExtensions as $allowedExtension) {
            if (str_ends_with($fileName, '.' . $allowedExtension)) {
                $isValidExtension = true;
                break;
            }
        }

        if (!$isValidExtension) {
            $extension = pathinfo($path, PATHINFO_EXTENSION);
            throw new SecurityException(\sprintf('Invalid configuration file type for %s: %s', $toolName, $extension), SecurityException::ERROR_INVALID_FILE_TYPE, null, ['Use correct file extension for the tool', 'Check tool documentation'], ['path' => $path, 'tool' => $toolName, 'extension' => $extension, 'allowed' => $allowedExtensions]);
        }
    }

    /**
     * Validates file permissions are secure.
     *
     * @param string $filePath Path to the file to check
     *
     * @return bool True if the file has secure permissions
     */
    public function hasSecureFilePermissions(string $filePath): bool
    {
        if (!$this->filesystem->exists($filePath) || !is_file($filePath)) {
            return false;
        }

        $permissions = fileperms($filePath);
        $mode = $permissions & 0o777;

        // File should be readable/writable by owner only (0600 or stricter)
        // Allow read for a group in some cases (0640) but not world-readable (0604, 0644, etc.)
        $securePermissions = [0o600, 0o640];

        return \in_array($mode, $securePermissions, true);
    }

    /**
     * Sets secure permissions on a file.
     *
     * @param string $filePath Path to the file
     *
     * @throws FileSystemException If permissions cannot be set
     */
    public function setSecureFilePermissions(string $filePath): void
    {
        if (!$this->filesystem->exists($filePath) || !is_file($filePath)) {
            throw new FileSystemException('File does not exist', FileSystemException::ERROR_FILE_NOT_FOUND, null, ['Check if file path is correct'], ['path' => $filePath], $filePath);
        }

        if (!chmod($filePath, 0o600)) {
            throw new FileSystemException('Failed to set secure permissions on file', FileSystemException::ERROR_PERMISSION_DENIED, null, ['Check file ownership and parent directory permissions'], ['path' => $filePath], $filePath);
        }
    }
}

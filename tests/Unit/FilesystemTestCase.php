<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Tests\Unit;

use Cpsit\QualityTools\Configuration\ConfigurationValidator;
use Cpsit\QualityTools\Configuration\SimpleConfigurationLoader;
use Cpsit\QualityTools\Service\FilesystemService;
use Cpsit\QualityTools\Service\SecurityService;

/**
 * Base test case for filesystem-related tests with virtual filesystem support.
 */
abstract class FilesystemTestCase extends BaseTestCase
{
    /**
     * Create a SimpleConfigurationLoader with dependencies for testing.
     */
    protected function createConfigurationLoader(?FilesystemService $filesystemService = null): SimpleConfigurationLoader
    {
        return new SimpleConfigurationLoader(
            new ConfigurationValidator(),
            new SecurityService(),
            $filesystemService ?? new FilesystemService(),
        );
    }

    /**
     * Create a standard configuration file structure in virtual filesystem.
     */
    protected function createConfigurationStructure(array $configs = []): string
    {
        $projectRoot = $this->getVirtualRoot() . '/project';
        $this->createVirtualDirectory('project');

        // Create default configurations
        $defaultConfigs = [
            '.quality-tools.yaml' => 'quality-tools: { project: { name: "test-project" } }',
            'composer.json' => '{"name": "test/project", "require": {"php": "^8.3"}}',
            'packages' => null, // Directory
        ];

        $allConfigs = array_merge($defaultConfigs, $configs);

        foreach ($allConfigs as $path => $content) {
            $fullPath = 'project/' . $path;

            if ($content === null) {
                // Create directory
                $this->createVirtualDirectory($fullPath);
            } else {
                // Create file
                $this->createVirtualFile($fullPath, $content);
            }
        }

        return $projectRoot;
    }

    /**
     * Create a temporary file structure for testing.
     */
    protected function createTemporaryStructure(array $files = []): string
    {
        $tempRoot = $this->getVirtualRoot() . '/temp';
        $this->createVirtualDirectory('temp');

        foreach ($files as $path => $content) {
            $this->createVirtualFile('temp/' . $path, $content);
        }

        return $tempRoot;
    }

    /**
     * Assert that a virtual file exists with expected content.
     */
    protected function assertVirtualFileExists(string $path, ?string $expectedContent = null): void
    {
        $this->assertTrue(
            $this->testFilesystem->fileExists($path),
            "Virtual file does not exist: {$path}",
        );

        if ($expectedContent !== null) {
            $actualContent = $this->testFilesystem->readFile($path);
            $this->assertSame(
                $expectedContent,
                $actualContent,
                "Virtual file content mismatch for: {$path}",
            );
        }
    }

    /**
     * Assert that a virtual directory exists.
     */
    protected function assertVirtualDirectoryExists(string $path): void
    {
        $this->assertTrue(
            $this->testFilesystem->directoryExists($path),
            "Virtual directory does not exist: {$path}",
        );
    }

    /**
     * Create configuration files with different priorities for testing.
     */
    protected function createConfigurationHierarchy(): array
    {
        $projectRoot = $this->createConfigurationStructure();

        // Global configuration (home directory)
        $homeDir = $this->getVirtualRoot() . '/home';
        $this->createVirtualDirectory('home');
        $this->createVirtualFile(
            'home/.quality-tools.yaml',
            'quality-tools: { project: { php_version: "8.4" }, output: { colors: false } }',
        );

        // Project configuration (overrides global)
        $this->createVirtualFile(
            'project/.quality-tools.yaml',
            'quality-tools: { project: { name: "project-override" }, tools: { rector: { enabled: false } } }',
        );

        return [
            'projectRoot' => $projectRoot,
            'homeDir' => $homeDir,
        ];
    }
}

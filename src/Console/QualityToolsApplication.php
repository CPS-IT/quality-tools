<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Console;

use Cpsit\QualityTools\DependencyInjection\ServiceContainer;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class QualityToolsApplication extends Application
{
    private const string APP_NAME = 'CPSIT Quality Tools';
    private const string APP_VERSION = '1.0.0-dev';
    private const string COMMAND_TAG = 'console.command';

    private ?string $projectRoot = null;
    private ContainerBuilder $container;

    public function __construct()
    {
        parent::__construct(self::APP_NAME, self::APP_VERSION);

        // Initialize service container (gracefully handle test scenarios)
        try {
            $this->container = ServiceContainer::getContainer();
        } catch (\Throwable) {
            // Fallback for test scenarios where container initialization might fail
            $this->container = new ContainerBuilder();
        }

        try {
            $this->projectRoot = $this->findProjectRoot();
        } catch (RuntimeException) {
            // Project root detection will be handled per-command if needed
        }

        $this->registerCommands();
    }

    #[\Override]
    public function getHelp(): string
    {
        return Tagline::random();
    }

    public function getProjectRoot(): string
    {
        if ($this->projectRoot === null) {
            $this->projectRoot = $this->findProjectRoot();
        }

        return $this->projectRoot;
    }

    /**
     * Clear cached project root to force re-detection.
     * Useful for tests that change environment variables.
     */
    public function clearCachedProjectRoot(): void
    {
        $this->projectRoot = null;
    }

    private function findProjectRoot(): string
    {
        $currentDir = getcwd();
        if ($currentDir === false) {
            throw new RuntimeException('Unable to determine current working directory');
        }

        // Check for environment variable override
        $envProjectRoot = getenv('QT_PROJECT_ROOT');
        if ($envProjectRoot !== false && is_dir($envProjectRoot)) {
            return realpath($envProjectRoot);
        }

        // Start from current directory and traverse upward
        $searchDir = $currentDir;
        $maxLevels = 10; // Prevent infinite traversal

        for ($i = 0; $i < $maxLevels; ++$i) {
            $composerFile = $searchDir . '/composer.json';

            if (file_exists($composerFile) && $this->isTypo3Project($composerFile)) {
                return $searchDir;
            }

            $parentDir = \dirname($searchDir);
            if ($parentDir === $searchDir) {
                // Reached filesystem root
                break;
            }

            $searchDir = $parentDir;
        }

        throw new RuntimeException('TYPO3 project root not found. Please run this command from within a TYPO3 project directory, or set the QT_PROJECT_ROOT environment variable.');
    }

    private function isTypo3Project(string $composerFile): bool
    {
        // Check if file is readable before attempting to read it
        if (!is_readable($composerFile)) {
            return false;
        }

        try {
            $content = file_get_contents($composerFile);
            if ($content === false) {
                return false;
            }

            $composer = json_decode($content, true);
            if (!\is_array($composer)) {
                return false;
            }
        } catch (\Throwable) {
            // File access failed (permissions, corruption, etc.)
            return false;
        }

        // Check for TYPO3 dependencies
        $dependencies = array_merge(
            $composer['require'] ?? [],
            $composer['require-dev'] ?? [],
        );

        $typo3Packages = [
            'typo3/cms-core',
            'typo3/cms',
            'typo3/minimal',
        ];

        foreach ($typo3Packages as $package) {
            if (isset($dependencies[$package])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Discover and register all commands tagged with 'console.command' in the DI container.
     */
    private function registerCommands(): void
    {
        $taggedServiceIds = $this->container->findTaggedServiceIds(self::COMMAND_TAG);

        foreach (array_keys($taggedServiceIds) as $serviceId) {
            try {
                $command = $this->container->get($serviceId);
                if ($command instanceof Command) {
                    $this->add($command);
                }
            } catch (\Throwable) {
                continue;
            }
        }
    }
}

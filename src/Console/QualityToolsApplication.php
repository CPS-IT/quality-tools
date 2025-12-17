<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Console;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\RuntimeException;

final class QualityToolsApplication extends Application
{
    private const APP_NAME = 'CPSIT Quality Tools';
    private const APP_VERSION = '1.0.0-dev';
    
    private ?string $projectRoot = null;

    public function __construct()
    {
        parent::__construct(self::APP_NAME, self::APP_VERSION);
        
        try {
            $this->projectRoot = $this->findProjectRoot();
        } catch (RuntimeException $e) {
            // Project root detection will be handled per-command if needed
        }
        
        $this->registerCommands();
    }

    public function getHelp(): string
    {
        return 'Simple command-line interface for TYPO3 quality assurance tools';
    }

    public function getProjectRoot(): string
    {
        if ($this->projectRoot === null) {
            $this->projectRoot = $this->findProjectRoot();
        }
        
        return $this->projectRoot;
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
        
        for ($i = 0; $i < $maxLevels; $i++) {
            $composerFile = $searchDir . '/composer.json';
            
            if (file_exists($composerFile)) {
                if ($this->isTypo3Project($composerFile)) {
                    return $searchDir;
                }
            }
            
            $parentDir = dirname($searchDir);
            if ($parentDir === $searchDir) {
                // Reached filesystem root
                break;
            }
            
            $searchDir = $parentDir;
        }

        throw new RuntimeException(
            'TYPO3 project root not found. Please run this command from within a TYPO3 project directory, ' .
            'or set the QT_PROJECT_ROOT environment variable.'
        );
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
            if (!is_array($composer)) {
                return false;
            }
        } catch (\Throwable $e) {
            // File access failed (permissions, corruption, etc.)
            return false;
        }

        // Check for TYPO3 dependencies
        $dependencies = array_merge(
            $composer['require'] ?? [],
            $composer['require-dev'] ?? []
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

    private function registerCommands(): void
    {
        $commandDir = __DIR__ . '/Command';
        
        if (!is_dir($commandDir)) {
            return; // No commands directory yet
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($commandDir, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $relativePath = str_replace($commandDir . '/', '', $file->getPathname());
            $className = $this->getClassNameFromFile($relativePath);

            if ($className && $this->isValidCommandClass($className)) {
                try {
                    $command = new $className();
                    $this->add($command);
                } catch (\Throwable $e) {
                    // Skip commands that fail to instantiate
                    continue;
                }
            }
        }
    }

    private function getClassNameFromFile(string $relativePath): ?string
    {
        $pathWithoutExtension = str_replace('.php', '', $relativePath);
        $classPath = str_replace('/', '\\', $pathWithoutExtension);
        
        return 'Cpsit\\QualityTools\\Console\\Command\\' . $classPath;
    }

    private function isValidCommandClass(string $className): bool
    {
        if (!class_exists($className)) {
            return false;
        }

        try {
            $reflection = new ReflectionClass($className);
            
            return $reflection->isSubclassOf(Command::class) && 
                   !$reflection->isAbstract() && 
                   $reflection->isInstantiable();
        } catch (\Throwable $e) {
            return false;
        }
    }
}

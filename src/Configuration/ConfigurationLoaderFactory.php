<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Configuration;

/**
 * Factory that creates the appropriate configuration loader based on context.
 *
 * This factory implements the ConfigurationLoaderInterface and intelligently
 * selects between SimpleConfigurationLoader and HierarchicalConfigurationLoader
 * based on the command context and performance requirements.
 *
 * Strategy:
 * - Use SimpleConfigurationLoader for tool commands (performance-critical)
 * - Use HierarchicalConfigurationLoader for config commands (feature-rich)
 * - Allow explicit override via environment variables or configuration
 *
 * Part of Step 3.1 in Issue 019: Configuration Class Hierarchy Simplification
 */
final readonly class ConfigurationLoaderFactory implements ConfigurationLoaderInterface
{
    private const string ENV_LOADER_MODE = 'QT_LOADER_MODE';

    private const string MODE_SIMPLE = 'simple';
    private const string MODE_HIERARCHICAL = 'hierarchical';
    private const string MODE_AUTO = 'auto';

    // Commands that benefit from hierarchical features
    private const array HIERARCHICAL_COMMANDS = [
        'config:show',
        'config:validate',
        'config:init',
    ];

    // Commands that prioritize performance
    private const array SIMPLE_COMMANDS = [
        'lint:rector',
        'lint:phpstan',
        'lint:php-cs-fixer',
        'lint:fractor',
        'lint:typoscript',
        'lint:composer',
        'fix:rector',
        'fix:php-cs-fixer',
        'fix:fractor',
        'fix:composer',
    ];

    public function __construct(
        private SimpleConfigurationLoader $simpleLoader,
        private HierarchicalConfigurationLoader $hierarchicalLoader,
        private string $defaultMode = self::MODE_AUTO,
    ) {
    }

    public function load(string $projectRoot, array $commandLineOverrides = []): ConfigurationInterface
    {
        $loader = $this->selectLoader();

        // Load with the selected loader and wrap the result
        $configuration = $loader->load($projectRoot, $commandLineOverrides);

        // Return wrapped configuration to maintain interface consistency
        $variant = $loader instanceof SimpleConfigurationLoader ? 'simple' : 'enhanced';

        return new ConfigurationWrapper(
            $configuration,
            $variant,
        );
    }

    public function findConfigurationFile(string $projectRoot): ?string
    {
        // Try both loaders and return the first successful result
        $simpleResult = $this->simpleLoader->findConfigurationFile($projectRoot);
        if ($simpleResult !== null) {
            return $simpleResult;
        }

        return $this->hierarchicalLoader->findConfigurationFile($projectRoot);
    }

    public function supportsConfiguration(string $projectRoot): bool
    {
        // Factory supports configuration if either loader supports it
        return $this->simpleLoader->supportsConfiguration($projectRoot)
            || $this->hierarchicalLoader->supportsConfiguration($projectRoot);
    }

    public function loadForTool(string $projectRoot, string $tool, array $commandLineOverrides = []): ConfigurationInterface
    {
        // Tool-specific loading always uses simple loader for performance
        $configuration = $this->simpleLoader->loadForTool($projectRoot, $tool, $commandLineOverrides);

        return new ConfigurationWrapper(
            $configuration,
            'simple',
        );
    }

    public function hasHierarchicalConfiguration(string $projectRoot): bool
    {
        // Delegate to hierarchical loader
        return $this->hierarchicalLoader->hasHierarchicalConfiguration($projectRoot);
    }

    public function getConfigurationErrors(string $projectRoot): array
    {
        // Use hierarchical loader for detailed error reporting
        return $this->hierarchicalLoader->getConfigurationErrors($projectRoot);
    }

    public function getConfigurationDebugInfo(string $projectRoot): array
    {
        $selectedLoader = $this->selectLoader();
        $loaderType = $selectedLoader instanceof SimpleConfigurationLoader ? 'simple' : 'hierarchical';

        $debugInfo = $selectedLoader->getConfigurationDebugInfo($projectRoot);

        // Add factory-specific debug information
        $debugInfo['factory_info'] = $this->getFactoryInfo();
        $debugInfo['selected_loader'] = $loaderType;

        return $debugInfo;
    }

    public function getConfigurationSources(string $projectRoot): array
    {
        // Use hierarchical loader for source tracking
        return $this->hierarchicalLoader->getConfigurationSources($projectRoot);
    }

    public function previewMergedConfiguration(string $projectRoot, array $commandLineOverrides = []): array
    {
        // Use hierarchical loader for preview functionality
        return $this->hierarchicalLoader->previewMergedConfiguration($projectRoot, $commandLineOverrides);
    }

    public function createSimpleConfiguration(string $projectRoot): ConfigurationInterface
    {
        // Always delegate to simple loader for this method
        $configuration = $this->simpleLoader->createSimpleConfiguration($projectRoot);

        return new ConfigurationWrapper(
            $configuration,
            'simple',
        );
    }

    /**
     * Select the appropriate loader based on current context.
     */
    private function selectLoader(): ConfigurationLoaderInterface
    {
        $mode = $this->determineMode();

        return match ($mode) {
            self::MODE_SIMPLE => $this->simpleLoader,
            self::MODE_HIERARCHICAL => $this->hierarchicalLoader,
            self::MODE_AUTO => $this->selectLoaderByContext(),
            default => $this->simpleLoader,
        };
    }

    /**
     * Determine the loader mode from various sources.
     */
    private function determineMode(): string
    {
        // 1. Check environment variable override
        $envMode = getenv(self::ENV_LOADER_MODE);
        if ($envMode !== false && \in_array($envMode, [self::MODE_SIMPLE, self::MODE_HIERARCHICAL], true)) {
            return $envMode;
        }

        // 2. Use configured default mode
        return $this->defaultMode;
    }

    /**
     * Select loader based on command context (auto mode).
     */
    private function selectLoaderByContext(): ConfigurationLoaderInterface
    {
        $currentCommand = $this->getCurrentCommand();

        // If we can identify the command, use appropriate loader
        if ($currentCommand !== null) {
            if (\in_array($currentCommand, self::HIERARCHICAL_COMMANDS, true)) {
                return $this->hierarchicalLoader;
            }

            if (\in_array($currentCommand, self::SIMPLE_COMMANDS, true)) {
                return $this->simpleLoader;
            }
        }

        // Default to simple loader for performance (most commands are tools)
        return $this->simpleLoader;
    }

    /**
     * Attempt to determine the current command being executed.
     */
    private function getCurrentCommand(): ?string
    {
        // Method 1: Check global arguments
        global $argv;
        if (isset($argv[1]) && str_contains($argv[1], ':')) {
            return $argv[1];
        }

        // Method 2: Check server variables (for web context)
        if (isset($_SERVER['argv'][1]) && str_contains((string) $_SERVER['argv'][1], ':')) {
            return $_SERVER['argv'][1];
        }

        // Method 3: Analyze stack trace for command class
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
        foreach ($trace as $frame) {
            if (isset($frame['class']) && str_contains($frame['class'], 'Command')) {
                $className = $frame['class'];

                // Extract command name from class name
                // e.g., ConfigShowCommand -> config:show
                if (preg_match('/\\\\([A-Z][a-z]+)Command$/', $className, $matches)) {
                    $commandName = strtolower((string) preg_replace('/([A-Z])/', '-$1', $matches[1]));
                    $commandName = ltrim($commandName, '-');

                    // Convert CamelCase to kebab-case with namespace
                    if (str_contains($commandName, '-')) {
                        $parts = explode('-', $commandName);
                        if (\count($parts) >= 2) {
                            return $parts[0] . ':' . implode('-', \array_slice($parts, 1));
                        }
                    }

                    return $commandName;
                }
            }
        }

        return null;
    }

    /**
     * Get information about the factory's current configuration.
     */
    public function getFactoryInfo(): array
    {
        $currentCommand = $this->getCurrentCommand();
        $mode = $this->determineMode();
        $selectedLoader = $this->selectLoader();

        return [
            'factory_version' => '1.0.0',
            'default_mode' => $this->defaultMode,
            'current_mode' => $mode,
            'current_command' => $currentCommand,
            'selected_loader' => $selectedLoader instanceof SimpleConfigurationLoader ? 'simple' : 'hierarchical',
            'env_override' => getenv(self::ENV_LOADER_MODE) ?: null,
            'supported_modes' => [self::MODE_SIMPLE, self::MODE_HIERARCHICAL, self::MODE_AUTO],
            'hierarchical_commands' => self::HIERARCHICAL_COMMANDS,
            'simple_commands' => self::SIMPLE_COMMANDS,
        ];
    }

    /**
     * Explicitly set the loader mode (for testing purposes).
     */
    public function withMode(string $mode): self
    {
        if (!\in_array($mode, [self::MODE_SIMPLE, self::MODE_HIERARCHICAL, self::MODE_AUTO], true)) {
            throw new \InvalidArgumentException("Invalid mode: {$mode}");
        }

        return new self($this->simpleLoader, $this->hierarchicalLoader, $mode);
    }
}

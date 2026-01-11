<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Configuration;

/**
 * Defines configuration hierarchy and precedence rules.
 *
 * Implements the configuration precedence order as defined in Feature 015.
 */
final readonly class ConfigurationHierarchy
{
    /**
     * Configuration precedence levels (from highest to lowest priority).
     */
    public const array PRECEDENCE_LEVELS = [
        'command_line',      // 1. Command line arguments (highest priority)
        'project_root',      // 2. quality-tools.yaml in project root
        'config_dir',        // 3. quality-tools.yaml in config/ directory
        'tool_specific',     // 4. Tool-specific config in project root
        'tool_config_dir',   // 5. Tool-specific config in config/ directory
        'package_config',    // 6. quality-tools.yaml in package root
        'global',            // 7. Global user configuration (~/.quality-tools.yaml)
        'package_defaults',  // 8. Package defaults (lowest priority)
    ];

    /**
     * Configuration file patterns for each precedence level.
     */
    public const array FILE_PATTERNS = [
        'project_root' => [
            'quality-tools.yaml',
            '.quality-tools.yaml',
            'quality-tools.yml',
        ],
        'config_dir' => [
            'config/quality-tools.yaml',
            'config/.quality-tools.yaml',
            'config/quality-tools.yml',
        ],
        'tool_specific' => [
            'rector.php',
            'phpstan.neon',
            'phpstan.neon.dist',
            '.php-cs-fixer.dist.php',
            '.php-cs-fixer.php',
            'typoscript-lint.yml',
        ],
        'tool_config_dir' => [
            'config/rector.php',
            'config/phpstan.neon',
            'config/.php-cs-fixer.dist.php',
            'config/.php-cs-fixer.php',
            'config/typoscript-lint.yml',
        ],
        'package_config' => [
            'packages/*/quality-tools.yaml',
            'packages/*/.quality-tools.yaml',
        ],
    ];

    /**
     * Tool-specific configuration file mappings.
     */
    public const array TOOL_CONFIG_FILES = [
        'rector' => ['rector.php'],
        'phpstan' => ['phpstan.neon', 'phpstan.neon.dist'],
        'php-cs-fixer' => ['.php-cs-fixer.dist.php', '.php-cs-fixer.php'],
        'typoscript-lint' => ['typoscript-lint.yml'],
        'fractor' => ['fractor.php'],
    ];

    /**
     * Configuration merging strategies for different data types.
     */
    public const array MERGE_STRATEGIES = [
        'arrays' => 'merge_unique',      // Arrays: merge and deduplicate
        'objects' => 'deep_merge',       // Objects: deep merge with override
        'scalars' => 'override',         // Scalars: override completely
        'paths' => 'resolve_relative',   // Special handling for path arrays
    ];

    /**
     * Special configuration keys that require custom handling.
     */
    public const array SPECIAL_KEYS = [
        'paths' => 'path_resolution',
        'exclude' => 'path_resolution',
        'scan' => 'path_resolution',
        'config_file' => 'tool_config_override',
    ];

    public function __construct(
        private string $projectRoot,
        private ?string $packageRoot = null,
    ) {
    }

    /**
     * Get all potential configuration file paths in precedence order.
     */
    public function getConfigurationFilePaths(): array
    {
        $paths = [];

        // Project root configurations
        foreach (self::FILE_PATTERNS['project_root'] as $pattern) {
            $paths['project_root'][] = $this->projectRoot . '/' . $pattern;
        }

        // Config directory configurations
        foreach (self::FILE_PATTERNS['config_dir'] as $pattern) {
            $paths['config_dir'][] = $this->projectRoot . '/' . $pattern;
        }

        // Tool-specific configurations
        foreach (self::FILE_PATTERNS['tool_specific'] as $pattern) {
            $paths['tool_specific'][] = $this->projectRoot . '/' . $pattern;
        }

        // Tool configs in config directory
        foreach (self::FILE_PATTERNS['tool_config_dir'] as $pattern) {
            $paths['tool_config_dir'][] = $this->projectRoot . '/' . $pattern;
        }

        // Package configurations (if package root is different)
        if ($this->packageRoot !== null && $this->packageRoot !== $this->projectRoot) {
            foreach (self::FILE_PATTERNS['package_config'] as $pattern) {
                $expandedPaths = glob($this->packageRoot . '/' . $pattern);
                if ($expandedPaths !== false) {
                    $paths['package_config'] = array_merge($paths['package_config'] ?? [], $expandedPaths);
                }
            }
        }

        return $paths;
    }

    /**
     * Get existing configuration files in precedence order.
     */
    public function getExistingConfigurationFiles(): array
    {
        $allPaths = $this->getConfigurationFilePaths();
        $existingFiles = [];

        foreach (self::PRECEDENCE_LEVELS as $level) {
            if ($level === 'command_line' || $level === 'package_defaults') {
                continue; // These are handled separately
            }

            if (!isset($allPaths[$level])) {
                continue;
            }

            foreach ($allPaths[$level] as $filePath) {
                if (file_exists($filePath)) {
                    $existingFiles[$level][] = [
                        'path' => $filePath,
                        'type' => $this->getFileType($filePath),
                        'tool' => $this->getToolForConfigFile($filePath),
                    ];
                }
            }
        }

        return $existingFiles;
    }

    /**
     * Determine the file type (yaml, php, neon, etc.).
     */
    private function getFileType(string $filePath): string
    {
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        return match ($extension) {
            'yaml', 'yml' => 'yaml',
            'php' => 'php',
            'neon' => 'neon',
            default => 'unknown'
        };
    }

    /**
     * Determine which tool a configuration file belongs to.
     */
    private function getToolForConfigFile(string $filePath): ?string
    {
        $fileName = basename($filePath);

        foreach (self::TOOL_CONFIG_FILES as $tool => $patterns) {
            foreach ($patterns as $pattern) {
                if ($fileName === $pattern || fnmatch($pattern, $fileName)) {
                    return $tool;
                }
            }
        }

        // Check if it's a general quality-tools config
        if (str_contains($fileName, 'quality-tools')) {
            return null; // General config, not tool-specific
        }

        return null;
    }

    /**
     * Check if a tool-specific config file overrides unified configuration.
     */
    public function hasToolConfigOverride(string $tool): bool
    {
        $existingFiles = $this->getExistingConfigurationFiles();

        foreach (['tool_specific', 'tool_config_dir'] as $level) {
            if (isset($existingFiles[$level])) {
                foreach ($existingFiles[$level] as $fileInfo) {
                    if ($fileInfo['tool'] === $tool) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * Get the precedence level for a configuration source.
     */
    public function getPrecedenceLevel(string $source): int
    {
        $index = array_search($source, self::PRECEDENCE_LEVELS, true);

        return $index !== false ? $index : \count(self::PRECEDENCE_LEVELS);
    }

    /**
     * Check if one configuration source has higher precedence than another.
     */
    public function hasHigherPrecedence(string $source1, string $source2): bool
    {
        return $this->getPrecedenceLevel($source1) < $this->getPrecedenceLevel($source2);
    }

    /**
     * Get debug information about the configuration hierarchy.
     */
    public function getDebugInfo(): array
    {
        return [
            'project_root' => $this->projectRoot,
            'package_root' => $this->packageRoot,
            'precedence_levels' => self::PRECEDENCE_LEVELS,
            'all_potential_files' => $this->getConfigurationFilePaths(),
            'existing_files' => $this->getExistingConfigurationFiles(),
            'tool_overrides' => $this->getToolOverrideStatus(),
        ];
    }

    /**
     * Get tool override status for debugging.
     */
    private function getToolOverrideStatus(): array
    {
        $status = [];

        foreach (array_keys(self::TOOL_CONFIG_FILES) as $tool) {
            $status[$tool] = $this->hasToolConfigOverride($tool);
        }

        return $status;
    }
}

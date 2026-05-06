<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Service;

/**
 * Service for extracting tool-specific configuration data.
 *
 * Avoids duplication between SimpleConfiguration and EnhancedConfiguration
 * by centralizing tool configuration logic (Step 4.1 of Issue 019).
 */
final readonly class ToolConfigService
{
    public function __construct()
    {
    }

    /**
     * Get tool configuration from configuration data.
     */
    public function getToolConfig(array $data, string $tool): array
    {
        $qualityTools = $data['quality-tools'] ?? [];
        $toolsConfig = $qualityTools['tools'] ?? [];

        return $toolsConfig[$tool] ?? [];
    }

    /**
     * Check if the tool is enabled from configuration data.
     */
    public function isToolEnabled(array $data, string $tool): bool
    {
        $toolConfig = $this->getToolConfig($data, $tool);

        return $toolConfig['enabled'] ?? true;
    }

    /**
     * Get tool-specific paths from configuration data.
     */
    public function getToolPaths(array $data, string $tool): array
    {
        $toolConfig = $this->getToolConfig($data, $tool);

        return $toolConfig['paths'] ?? [];
    }

    /**
     * Get Rector configuration with defaults.
     */
    public function getRectorConfig(array $data, string $phpVersion): array
    {
        $config = $this->getToolConfig($data, 'rector');

        return array_merge([
            'enabled' => true,
            'level' => 'typo3-14',
            'php_version' => $phpVersion,
        ], $config);
    }

    /**
     * Get Fractor configuration with defaults.
     */
    public function getFractorConfig(array $data): array
    {
        $config = $this->getToolConfig($data, 'fractor');

        return array_merge([
            'enabled' => true,
            'indentation' => 2,
        ], $config);
    }

    /**
     * Get PHPStan configuration with defaults.
     */
    public function getPhpStanConfig(array $data): array
    {
        $config = $this->getToolConfig($data, 'phpstan');

        return array_merge([
            'enabled' => true,
            'level' => 6,
            'memory_limit' => '1G',
        ], $config);
    }

    /**
     * Get PHP CS Fixer configuration with defaults.
     */
    public function getPhpCsFixerConfig(array $data): array
    {
        $config = $this->getToolConfig($data, 'php-cs-fixer');

        return array_merge([
            'enabled' => true,
            'preset' => 'typo3',
        ], $config);
    }

    /**
     * Get TypoScript Lint configuration with defaults.
     */
    public function getTypoScriptLintConfig(array $data): array
    {
        $config = $this->getToolConfig($data, 'typoscript-lint');

        return array_merge([
            'enabled' => true,
            'indentation' => 2,
        ], $config);
    }

    /**
     * Get Composer configuration with defaults.
     */
    public function getComposerConfig(array $data): array
    {
        $config = $this->getToolConfig($data, 'composer');

        return array_merge([
            'enabled' => true,
            'normalize' => true,
        ], $config);
    }

    /**
     * Extract all tools configuration as a structured array.
     */
    public function getToolsConfig(array $data): array
    {
        $qualityTools = $data['quality-tools'] ?? [];

        return $qualityTools['tools'] ?? [];
    }

    /**
     * Get a list of enabled tools.
     */
    public function getEnabledTools(array $data): array
    {
        $toolsConfig = $this->getToolsConfig($data);
        $enabledTools = [];

        foreach ($toolsConfig as $tool => $config) {
            if ($config['enabled'] ?? true) {
                $enabledTools[] = $tool;
            }
        }

        return $enabledTools;
    }

    /**
     * Get tool configuration merged with defaults.
     */
    public function getToolConfigWithDefaults(array $data, string $tool, array $defaults = []): array
    {
        $config = $this->getToolConfig($data, $tool);

        return array_merge($defaults, $config);
    }
}

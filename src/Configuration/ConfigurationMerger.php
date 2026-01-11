<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Configuration;

/**
 * Advanced configuration merging with source tracking and conflict resolution.
 *
 * Implements the sophisticated merging algorithm for Feature 015.
 */
final class ConfigurationMerger
{
    private array $sourceMap = [];
    private array $conflicts = [];

    public function __construct()
    {
    }

    /**
     * Merge multiple configuration sources with full metadata tracking.
     */
    public function mergeConfigurations(array $configurations): array
    {
        $this->sourceMap = [];
        $this->conflicts = [];

        if (empty($configurations)) {
            return [
                'data' => [],
                'source_map' => [],
                'conflicts' => [],
                'merge_summary' => $this->getMergeSummary([]),
            ];
        }

        // Sort configurations by precedence (highest to lowest priority)
        // Lower precedence numbers = higher priority (override later configurations)
        usort($configurations, fn (array $a, array $b): int => $b['precedence'] <=> $a['precedence']);

        // Start with the lowest priority configuration
        $mergedData = [];

        foreach ($configurations as $config) {
            $this->mergeConfiguration($mergedData, $this->sourceMap, $config);
        }

        return [
            'data' => $mergedData,
            'source_map' => $this->sourceMap,
            'conflicts' => $this->conflicts,
            'merge_summary' => $this->getMergeSummary($configurations),
        ];
    }

    /**
     * Merge a single configuration into the merged data.
     */
    private function mergeConfiguration(array &$mergedData, array &$sourceMap, array $config): void
    {
        $configData = $config['data'] ?? [];
        $source = $config['source'] ?? 'unknown';

        $this->deepMerge($mergedData, $sourceMap, $configData, $source, []);
    }

    /**
     * Deep merge algorithm with source tracking and conflict detection.
     */
    private function deepMerge(
        array &$target,
        array &$targetSourceMap,
        array $source,
        string $sourceName,
        array $keyPath,
    ): void {
        foreach ($source as $key => $value) {
            $currentKeyPath = array_merge($keyPath, [$key]);
            $keyPathStr = implode('.', $currentKeyPath);

            if (!\array_key_exists($key, $target)) {
                // New key, just add it
                $target[$key] = $value;
                $targetSourceMap[$keyPathStr] = $sourceName;

                // If this is an array, recursively populate source map for all nested keys
                if (\is_array($value)) {
                    $this->populateSourceMapForNewArray($targetSourceMap, $value, $sourceName, $currentKeyPath);
                }
            } elseif (\is_array($value) && \is_array($target[$key])) {
                // Both are arrays, determine merge strategy
                $mergeStrategy = $this->getMergeStrategy($currentKeyPath);

                switch ($mergeStrategy) {
                    case 'replace':
                        $this->recordConflict($keyPathStr, $target[$key], $value, $targetSourceMap[$keyPathStr] ?? 'unknown', $sourceName);
                        $target[$key] = $value;
                        $targetSourceMap[$keyPathStr] = $sourceName;
                        break;

                    case 'merge_unique':
                        if ($this->isIndexedArray($target[$key]) && $this->isIndexedArray($value)) {
                            // Merge indexed arrays and remove duplicates
                            $merged = array_merge($target[$key], $value);
                            $target[$key] = array_values(array_unique($merged));
                            $targetSourceMap[$keyPathStr] = $sourceName;
                        } else {
                            // Deep merge associative arrays
                            $this->deepMerge($target[$key], $targetSourceMap, $value, $sourceName, $currentKeyPath);
                        }
                        break;

                    case 'deep_merge':
                        $this->deepMerge($target[$key], $targetSourceMap, $value, $sourceName, $currentKeyPath);
                        break;

                    case 'path_resolution':
                        // For path resolution, handle indexed arrays specially
                        if ($this->isIndexedArray($target[$key]) && $this->isIndexedArray($value)) {
                            // Merge path arrays and remove duplicates
                            $merged = array_merge($target[$key], $value);
                            $target[$key] = array_values(array_unique($merged));
                            $targetSourceMap[$keyPathStr] = $sourceName;
                        } else {
                            // Deep merge associative arrays
                            $this->deepMerge($target[$key], $targetSourceMap, $value, $sourceName, $currentKeyPath);
                        }
                        break;

                    case 'tool_config_override':
                        // Tool config files completely override unified configuration
                        if (isset($value['custom_config']) && $value['custom_config'] === true) {
                            $this->recordConflict($keyPathStr, $target[$key], $value, $targetSourceMap[$keyPathStr] ?? 'unknown', $sourceName);
                            $target[$key] = $value;
                            $targetSourceMap[$keyPathStr] = $sourceName;
                        } else {
                            $this->deepMerge($target[$key], $targetSourceMap, $value, $sourceName, $currentKeyPath);
                        }
                        break;
                }
            } else {
                // Different types or scalar values, override
                $this->recordConflict($keyPathStr, $target[$key], $value, $targetSourceMap[$keyPathStr] ?? 'unknown', $sourceName);
                $target[$key] = $value;
                $targetSourceMap[$keyPathStr] = $sourceName;
            }
        }
    }

    /**
     * Determine the merge strategy for a specific key path.
     */
    private function getMergeStrategy(array $keyPath): string
    {
        $fullPath = implode('.', $keyPath);
        $lastKey = end($keyPath);

        // Check for special keys
        foreach (ConfigurationHierarchy::SPECIAL_KEYS as $specialKey => $strategy) {
            if ($lastKey === $specialKey || str_contains($fullPath, $specialKey)) {
                return $strategy;
            }
        }

        // Default strategies based on context
        if (\in_array($lastKey, ['scan', 'exclude'], true)) {
            return 'merge_unique';
        }

        if ($lastKey === 'paths') {
            return 'deep_merge';
        }

        if (\in_array($lastKey, ['tools', 'project', 'output', 'performance'], true)) {
            return 'deep_merge';
        }

        // For simple lists, replace rather than merge
        if ($lastKey === 'list') {
            return 'replace';
        }

        // Default to deep merge for objects
        return 'deep_merge';
    }

    /**
     * Check if array is indexed (not associative).
     */
    private function isIndexedArray(array $array): bool
    {
        return array_is_list($array);
    }

    /**
     * Recursively populate source map for all nested keys in a new array.
     */
    private function populateSourceMapForNewArray(array &$sourceMap, array $array, string $sourceName, array $basePath): void
    {
        foreach ($array as $key => $value) {
            $currentPath = array_merge($basePath, [$key]);
            $keyPathStr = implode('.', $currentPath);

            $sourceMap[$keyPathStr] = $sourceName;

            if (\is_array($value) && !$this->isIndexedArray($value)) {
                // Recursively process associative arrays, but not indexed arrays
                $this->populateSourceMapForNewArray($sourceMap, $value, $sourceName, $currentPath);
            }
        }
    }

    /**
     * Record a configuration conflict for debugging.
     */
    private function recordConflict(
        string $keyPath,
        mixed $existingValue,
        mixed $newValue,
        string $existingSource,
        string $newSource,
    ): void {
        $this->conflicts[] = [
            'key_path' => $keyPath,
            'existing_value' => $existingValue,
            'new_value' => $newValue,
            'existing_source' => $existingSource,
            'new_source' => $newSource,
            'resolution' => 'override',
            'winner' => $newSource,
        ];
    }

    /**
     * Get merge summary with statistics.
     */
    private function getMergeSummary(array $configurations): array
    {
        $summary = [
            'total_configurations' => \count($configurations),
            'configurations_by_source' => [],
            'total_conflicts' => \count($this->conflicts),
            'conflicts_by_key' => [],
        ];

        foreach ($configurations as $config) {
            $source = $config['source'] ?? 'unknown';
            $summary['configurations_by_source'][$source] = [
                'file_path' => $config['file_path'] ?? null,
                'file_type' => $config['file_type'] ?? 'unknown',
                'tool' => $config['tool'] ?? null,
                'precedence' => $config['precedence'] ?? 999,
            ];
        }

        foreach ($this->conflicts as $conflict) {
            $keyPath = $conflict['key_path'];
            if (!isset($summary['conflicts_by_key'][$keyPath])) {
                $summary['conflicts_by_key'][$keyPath] = 0;
            }
            ++$summary['conflicts_by_key'][$keyPath];
        }

        return $summary;
    }

    /**
     * Get all recorded conflicts.
     */
    public function getConflicts(): array
    {
        return $this->conflicts;
    }

    /**
     * Check if there were any conflicts during merging.
     */
    public function hasConflicts(): bool
    {
        return !empty($this->conflicts);
    }

    /**
     * Get conflicts for a specific key path.
     */
    public function getConflictsForKey(string $keyPath): array
    {
        return array_values(array_filter($this->conflicts, fn (array $conflict): bool => $conflict['key_path'] === $keyPath));
    }

    /**
     * Merge two individual configuration arrays (utility method).
     */
    public static function mergeTwo(array $base, array $override): array
    {
        $merger = new self();

        $result = $merger->mergeConfigurations([
            [
                'source' => 'base',
                'precedence' => 1,
                'data' => $base,
            ],
            [
                'source' => 'override',
                'precedence' => 0,
                'data' => $override,
            ],
        ]);

        return $result['data'];
    }

    /**
     * Create a merger with debug output for testing.
     */
    public static function createDebugMerger(ConfigurationHierarchy $hierarchy): self
    {
        return new self();
    }

    /**
     * Get the source map for debugging which source provided which values.
     */
    public function getSourceMap(): array
    {
        return $this->sourceMap;
    }
}

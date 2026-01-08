<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Utility;

/**
 * Strategy class for filtering paths based on exclusion patterns
 *
 * This class handles the complex logic for applying exclusion patterns to resolved paths,
 * with special handling for explicit vendor paths that should be exempt from vendor/ exclusions.
 */
final class PathExclusionFilter
{
    private string $projectRoot;
    private ?string $vendorPath;
    private array $excludePatterns;
    private array $explicitVendorPaths;

    public function __construct(
        string $projectRoot,
        ?string $vendorPath,
        array $excludePatterns,
        array $explicitVendorPaths = []
    ) {
        $this->projectRoot = rtrim($projectRoot, '/');
        $this->vendorPath = $vendorPath;
        $this->excludePatterns = $excludePatterns;
        $this->explicitVendorPaths = $explicitVendorPaths;
    }

    /**
     * Filter paths by applying exclusion patterns
     */
    public function filter(array $paths): array
    {
        return array_filter($paths, fn(string $path) => $this->shouldIncludePath($path));
    }

    /**
     * Determine if a path should be included based on exclusion rules
     */
    private function shouldIncludePath(string $path): bool
    {
        if ($this->isExplicitVendorPath($path)) {
            return $this->applyNonVendorExclusions($path);
        }

        return $this->applyAllExclusions($path);
    }

    /**
     * Check if a path is an explicit vendor path (exempt from vendor/ exclusions)
     */
    private function isExplicitVendorPath(string $path): bool
    {
        return in_array($path, $this->explicitVendorPaths, true);
    }

    /**
     * Apply non-vendor exclusions to explicit vendor paths
     */
    private function applyNonVendorExclusions(string $path): bool
    {
        foreach ($this->excludePatterns as $excludePattern) {
            if ($this->isVendorExclusion($excludePattern)) {
                continue; // Skip vendor exclusions for explicit vendor paths
            }

            if ($this->matchesExclusionPattern($path, $excludePattern)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Apply all exclusion patterns to regular paths
     */
    private function applyAllExclusions(string $path): bool
    {
        foreach ($this->excludePatterns as $excludePattern) {
            if ($this->matchesExclusionPattern($path, $excludePattern)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if an exclusion pattern targets vendor paths
     */
    private function isVendorExclusion(string $excludePattern): bool
    {
        return $excludePattern === 'vendor/' || $excludePattern === 'vendor';
    }

    /**
     * Check if a path matches an exclusion pattern
     */
    private function matchesExclusionPattern(string $path, string $excludePattern): bool
    {
        $absoluteExcludePattern = $this->buildAbsolutePattern($excludePattern);

        // Handle wildcard patterns
        if (str_ends_with($excludePattern, '*')) {
            return $this->matchesWildcardPattern($path, $absoluteExcludePattern);
        }

        // Handle directory exclusions
        if (str_ends_with($excludePattern, '/')) {
            return $this->matchesDirectoryPattern($path, $absoluteExcludePattern);
        }

        // Handle an exact match or directory prefix match for patterns without trailing slash
        if ($this->isExactMatch($path, $absoluteExcludePattern)) {
            return true;
        }

        // Handle directory prefix match for patterns without a trailing slash (e.g., "vendor" should match "vendor/...")
        if ($this->isDirectoryPrefixMatch($path, $absoluteExcludePattern)) {
            return true;
        }

        // Handle glob pattern match
        if ($this->containsGlobCharacters($excludePattern)) {
            return fnmatch($absoluteExcludePattern, $path);
        }

        return false;
    }

    /**
     * Match wildcard patterns (ending with *)
     */
    private function matchesWildcardPattern(string $path, string $absoluteExcludePattern): bool
    {
        $prefix = rtrim($absoluteExcludePattern, '*');

        // Try to normalize the prefix if its directory exists
        $prefixDir = dirname($prefix);
        if (is_dir($prefixDir)) {
            $normalizedPrefixDir = realpath($prefixDir);
            if ($normalizedPrefixDir !== false) {
                $normalizedPrefix = $normalizedPrefixDir . '/' . basename($prefix);
                return str_starts_with($path, $normalizedPrefix);
            }
        }

        return str_starts_with($path, $prefix);
    }

    /**
     * Match directory patterns (ending with /)
     */
    private function matchesDirectoryPattern(string $path, string $absoluteExcludePattern): bool
    {
        $prefix = rtrim($absoluteExcludePattern, '/');

        // Try to normalize the prefix if it exists as a directory
        if (is_dir($prefix)) {
            $normalizedPrefix = realpath($prefix);
            if ($normalizedPrefix !== false) {
                return str_starts_with($path, $normalizedPrefix);
            }
        }

        return str_starts_with($path, $prefix);
    }

    /**
     * Check for the exact pattern match
     */
    private function isExactMatch(string $path, string $absoluteExcludePattern): bool
    {
        // Try to normalize the exclude pattern if it exists
        if (is_dir($absoluteExcludePattern) || is_file($absoluteExcludePattern)) {
            $normalizedExcludePattern = realpath($absoluteExcludePattern);
            if ($normalizedExcludePattern !== false) {
                return $path === $normalizedExcludePattern;
            }
        }

        return $path === $absoluteExcludePattern;
    }

    /**
     * Check if a path matches as directory prefix (for patterns without a trailing slash)
     */
    private function isDirectoryPrefixMatch(string $path, string $absoluteExcludePattern): bool
    {
        // Only apply directory prefix matching if the pattern exists as a directory
        if (is_dir($absoluteExcludePattern)) {
            $normalizedExcludePattern = realpath($absoluteExcludePattern);
            if ($normalizedExcludePattern !== false) {
                return str_starts_with($path, $normalizedExcludePattern . '/') || $path === $normalizedExcludePattern;
            }
        }

        // Fallback for non-existing directories
        return str_starts_with($path, $absoluteExcludePattern . '/');
    }

    /**
     * Build absolute pattern for matching operations
     */
    private function buildAbsolutePattern(string $pattern): string
    {
        // Already absolute
        if (str_starts_with($pattern, '/') || preg_match('/^[a-zA-Z]:/', $pattern)) {
            return $pattern;
        }

        // Handle vendor/ prefix for vendor namespace resolution
        if ($this->vendorPath !== null && str_starts_with($pattern, 'vendor/')) {
            return $this->vendorPath . '/' . substr($pattern, 7);
        }

        return $this->projectRoot . '/' . ltrim($pattern, '/');
    }

    /**
     * Check if a pattern contains glob characters
     */
    private function containsGlobCharacters(string $pattern): bool
    {
        return str_contains($pattern, '*') ||
            str_contains($pattern, '?') ||
            str_contains($pattern, '[');
    }
}

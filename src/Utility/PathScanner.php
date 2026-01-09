<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Utility;

/**
 * Scans and resolves additional package paths based on various patterns.
 *
 * This utility supports multiple path specification formats:
 * - Glob patterns: packages/*\/Classes/**\/*.php
 * - Vendor namespaces: cpsit/*, fr/*
 * - Direct paths: src/, app/Classes/
 * - Exclusion patterns: !packages/legacy/*
 */
final class PathScanner
{
    private readonly string $projectRoot;
    private ?string $vendorPath = null;
    private array $resolvedPathsCache = [];

    public function __construct(string $projectRoot)
    {
        $this->projectRoot = rtrim($projectRoot, '/');
    }

    public function setVendorPath(?string $vendorPath): void
    {
        $this->vendorPath = $vendorPath;
        $this->resolvedPathsCache = []; // Clear cache when a vendor path changes
    }

    /**
     * Generate optimized cache key for path patterns and vendor path.
     */
    private function generateCacheKey(array $pathPatterns, string $vendorPath): string
    {
        $data = implode('|', $pathPatterns) . '|' . $vendorPath;

        return md5($data);
    }

    /**
     * Resolve an array of path patterns to actual filesystem paths.
     */
    public function resolvePaths(array $pathPatterns): array
    {
        $cacheKey = $this->generateCacheKey($pathPatterns, $this->vendorPath ?? '');

        if (isset($this->resolvedPathsCache[$cacheKey])) {
            return $this->resolvedPathsCache[$cacheKey];
        }

        $includePatterns = [];
        $excludePatterns = [];

        // Separate inclusion and exclusion patterns
        foreach ($pathPatterns as $pattern) {
            if (str_starts_with((string) $pattern, '!')) {
                $excludePatterns[] = substr((string) $pattern, 1);
            } else {
                $includePatterns[] = $pattern;
            }
        }

        // Resolve all inclusion patterns and track explicit vendor paths
        $resolvedPaths = [];
        $explicitVendorPaths = [];

        foreach ($includePatterns as $pattern) {
            $paths = $this->resolvePattern($pattern);
            $resolvedPaths = array_merge($resolvedPaths, $paths);

            // Track paths that came from vendor namespace patterns or explicit vendor patterns
            if ($this->isVendorNamespacePattern($pattern) || $this->isExplicitVendorPattern($pattern)) {
                $explicitVendorPaths = array_merge($explicitVendorPaths, $paths);
            }
        }

        // Remove duplicates
        $resolvedPaths = array_unique($resolvedPaths);
        $explicitVendorPaths = array_unique($explicitVendorPaths);

        // Apply exclusion patterns
        if (!empty($excludePatterns)) {
            $resolvedPaths = $this->applyExclusions($resolvedPaths, $excludePatterns, $explicitVendorPaths);
        }

        // Sort paths for consistent ordering
        sort($resolvedPaths);

        $this->resolvedPathsCache[$cacheKey] = $resolvedPaths;

        return $resolvedPaths;
    }

    /**
     * Resolve a single pattern to filesystem paths.
     */
    private function resolvePattern(string $pattern): array
    {
        // Handle vendor namespace patterns (e.g., "cpsit/*", "fr/*")
        if ($this->isVendorNamespacePattern($pattern)) {
            return $this->resolveVendorNamespacePattern($pattern);
        }

        // Handle direct glob patterns
        if ($this->containsGlobCharacters($pattern)) {
            return $this->resolveGlobPattern($pattern);
        }

        // Handle simple directory paths
        return $this->resolveDirectoryPath($pattern);
    }

    /**
     * Check if a pattern is a vendor namespace pattern (vendor/namespace/*).
     */
    private function isVendorNamespacePattern(string $pattern): bool
    {
        // Must have a vendor path set, and the pattern must not start with vendor/
        if ($this->vendorPath === null) {
            return false;
        }

        // Pattern like "cpsit/*" or "cpsit/*/Classes" but NOT "vendor/cpsit/*"
        if (str_starts_with($pattern, 'vendor/')) {
            return false;
        }

        // Must match the vendor namespace pattern
        if (preg_match('/^([a-zA-Z0-9_-]+)\/\*(?:\/.*)?$/', $pattern, $matches) !== 1) {
            return false;
        }

        $namespace = $matches[1];

        // Check if this namespace actually exists in the vendor directory
        $vendorNamespaceDir = $this->vendorPath . '/' . $namespace;

        return is_dir($vendorNamespaceDir);
    }

    /**
     * Check if a pattern is an explicit vendor path pattern (vendor/namespace/*).
     */
    private function isExplicitVendorPattern(string $pattern): bool
    {
        // Pattern must start with vendor/
        if (!str_starts_with($pattern, 'vendor/')) {
            return false;
        }

        // Must be a glob pattern with vendor paths
        return $this->containsGlobCharacters($pattern);
    }

    /**
     * Resolve vendor namespace patterns to actual vendor directories.
     */
    private function resolveVendorNamespacePattern(string $pattern): array
    {
        if ($this->vendorPath === null) {
            return [];
        }

        // Extract namespace and subpath from a pattern like "cpsit/*" or "cpsit/*/Classes"
        $parts = explode('/', $pattern);
        $namespace = $parts[0];

        // Find matching vendor packages
        $vendorNamespaceDir = $this->vendorPath . '/' . $namespace;

        if (!is_dir($vendorNamespaceDir)) {
            return [];
        }

        $packages = glob($vendorNamespaceDir . '/*', GLOB_ONLYDIR);
        if ($packages === false) {
            return [];
        }

        $resolvedPaths = [];
        foreach ($packages as $package) {
            // Build the complete path by replacing * with the actual package name
            $packageName = basename($package);
            $resolvedPattern = str_replace('*', $packageName, $pattern);

            // For vendor namespace patterns, we need to prepend the vendor path directly
            $absolutePath = $this->vendorPath . '/' . $resolvedPattern;

            if ($this->pathExists($absolutePath)) {
                $normalizedPath = realpath($absolutePath);
                if ($normalizedPath !== false) {
                    $resolvedPaths[] = $normalizedPath;
                }
            }
        }

        return $resolvedPaths;
    }

    /**
     * Check if a pattern contains glob characters.
     */
    private function containsGlobCharacters(string $pattern): bool
    {
        return str_contains($pattern, '*')
            || str_contains($pattern, '?')
            || str_contains($pattern, '[');
    }

    /**
     * Resolve glob patterns to matching paths.
     */
    private function resolveGlobPattern(string $pattern): array
    {
        $absolutePattern = $this->buildAbsolutePattern($pattern);
        $matches = glob($absolutePattern, GLOB_BRACE);

        if ($matches === false) {
            return [];
        }

        // Filter to existing directories/files and normalize paths
        $normalizedPaths = [];
        foreach ($matches as $path) {
            if ($this->pathExists($path)) {
                $normalizedPath = realpath($path);
                if ($normalizedPath !== false) {
                    $normalizedPaths[] = $normalizedPath;
                }
            }
        }

        return $normalizedPaths;
    }

    /**
     * Resolve a simple directory path.
     */
    private function resolveDirectoryPath(string $pattern): array
    {
        $absolutePath = $this->toAbsolutePath($pattern);

        if ($this->pathExists($absolutePath)) {
            $normalizedPath = realpath($absolutePath);
            if ($normalizedPath !== false) {
                return [$normalizedPath];
            }
        }

        return [];
    }

    /**
     * Build absolute pattern for glob operations (without realpath normalization).
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
     * Convert a relative path to absolute based on the project root.
     */
    private function toAbsolutePath(string $path): string
    {
        // Already absolute
        if (str_starts_with($path, '/') || preg_match('/^[a-zA-Z]:/', $path)) {
            return realpath($path) ?: $path;
        }

        // Handle vendor/ prefix for vendor namespace resolution
        if ($this->vendorPath !== null && str_starts_with($path, 'vendor/')) {
            $fullPath = $this->vendorPath . '/' . substr($path, 7);

            return realpath($fullPath) ?: $fullPath;
        }

        $fullPath = $this->projectRoot . '/' . ltrim($path, '/');

        return realpath($fullPath) ?: $fullPath;
    }

    /**
     * Check if a path exists (directory or file).
     */
    private function pathExists(string $path): bool
    {
        return is_dir($path) || is_file($path);
    }

    /**
     * Apply exclusion patterns to resolved paths.
     */
    private function applyExclusions(array $paths, array $excludePatterns, array $explicitVendorPaths = []): array
    {
        $pathFilter = new PathExclusionFilter(
            $this->projectRoot,
            $this->vendorPath,
            $excludePatterns,
            $explicitVendorPaths,
        );

        return $pathFilter->filter($paths);
    }

    /**
     * Validate that paths exist and are accessible.
     */
    public function validatePaths(array $paths): array
    {
        $validation = [
            'valid' => [],
            'invalid' => [],
            'inaccessible' => [],
        ];

        foreach ($paths as $path) {
            if (!$this->pathExists($path)) {
                $validation['invalid'][] = $path;
                continue;
            }

            if (!is_readable($path)) {
                $validation['inaccessible'][] = $path;
                continue;
            }

            // Normalize valid paths
            $normalizedPath = realpath($path);
            $validation['valid'][] = $normalizedPath !== false ? $normalizedPath : $path;
        }

        return $validation;
    }

    /**
     * Get debug information about path resolution.
     */
    public function getPathResolutionDebugInfo(array $pathPatterns): array
    {
        return [
            'project_root' => $this->projectRoot,
            'vendor_path' => $this->vendorPath,
            'input_patterns' => $pathPatterns,
            'resolved_paths' => $this->resolvePaths($pathPatterns),
            'pattern_analysis' => array_map(fn ($pattern): array => [
                'pattern' => $pattern,
                'type' => $this->getPatternType($pattern),
                'is_exclusion' => str_starts_with((string) $pattern, '!'),
                'absolute_pattern' => str_starts_with((string) $pattern, '!')
                    ? $this->getAbsolutePatternForDebug(substr((string) $pattern, 1))
                    : $this->getAbsolutePatternForDebug($pattern),
            ], $pathPatterns),
        ];
    }

    /**
     * Determine the type of pattern.
     */
    private function getPatternType(string $pattern): string
    {
        $cleanPattern = str_starts_with($pattern, '!') ? substr($pattern, 1) : $pattern;

        if ($this->isVendorNamespacePattern($cleanPattern)) {
            return 'vendor_namespace';
        }

        if ($this->containsGlobCharacters($cleanPattern)) {
            return 'glob';
        }

        return 'direct_path';
    }

    /**
     * Get absolute pattern for debug purposes (handles vendor namespace patterns correctly).
     */
    private function getAbsolutePatternForDebug(string $pattern): string
    {
        if ($this->isVendorNamespacePattern($pattern)) {
            return $this->vendorPath . '/' . $pattern;
        }

        return $this->buildAbsolutePattern($pattern);
    }

    /**
     * Clear internal caches.
     */
    public function clearCache(): void
    {
        $this->resolvedPathsCache = [];
    }
}

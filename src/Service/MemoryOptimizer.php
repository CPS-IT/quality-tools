<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Service;

use Cpsit\QualityTools\Utility\MemoryCalculator;
use Cpsit\QualityTools\Utility\ProjectAnalyzer;
use Cpsit\QualityTools\Utility\ProjectMetrics;

/**
 * Calculates optimal memory limits for tool execution based on project analysis.
 *
 * Composes ProjectAnalyzer and MemoryCalculator to provide a single entry point
 * for tool runners that need memory optimization.
 */
final readonly class MemoryOptimizer
{
    public function __construct(
        private ProjectAnalyzer $projectAnalyzer,
        private MemoryCalculator $memoryCalculator,
    ) {
    }

    /**
     * Calculate the optimal memory limit for a tool based on the target paths.
     *
     * Analyzes each target path, aggregates metrics across all paths,
     * then applies the tool-specific memory multiplier.
     *
     * @param string       $toolName    Tool identifier (e.g. 'rector', 'phpstan')
     * @param list<string> $targetPaths Paths to analyze
     */
    public function calculateMemoryLimit(string $toolName, array $targetPaths): string
    {
        $metrics = $this->analyzeAndAggregate($targetPaths);

        return $this->memoryCalculator->calculateOptimalMemoryForTool($metrics, $toolName);
    }

    /**
     * Analyze target paths and aggregate metrics across all of them.
     *
     * @param list<string> $targetPaths
     */
    private function analyzeAndAggregate(array $targetPaths): ProjectMetrics
    {
        $aggregated = null;

        foreach ($targetPaths as $path) {
            if (!is_dir($path)) {
                continue;
            }

            $pathMetrics = $this->projectAnalyzer->analyzeProject($path);

            $aggregated = $aggregated === null ? $pathMetrics : $this->mergeMetrics($aggregated, $pathMetrics);
        }

        return $aggregated ?? new ProjectMetrics([]);
    }

    /**
     * Merge two ProjectMetrics instances with weighted complexity averaging.
     */
    private function mergeMetrics(ProjectMetrics $base, ProjectMetrics $additional): ProjectMetrics
    {
        return new ProjectMetrics([
            'php' => $this->mergeCategoryMetrics($base->php, $additional->php),
            'yaml' => $this->mergeCategoryMetrics($base->yaml, $additional->yaml),
            'json' => $this->mergeCategoryMetrics($base->json, $additional->json),
            'xml' => $this->mergeCategoryMetrics($base->xml, $additional->xml),
            'typoscript' => $this->mergeCategoryMetrics($base->typoscript, $additional->typoscript),
            'other' => $this->mergeCategoryMetrics($base->other, $additional->other),
        ]);
    }

    /**
     * Merge metrics for a single file category.
     *
     * @param array<string, int> $base
     * @param array<string, int> $additional
     *
     * @return array<string, int>
     */
    private function mergeCategoryMetrics(array $base, array $additional): array
    {
        $baseCount = $base['fileCount'] ?? 0;
        $additionalCount = $additional['fileCount'] ?? 0;
        $totalCount = $baseCount + $additionalCount;

        $baseAvgComplexity = $base['avgComplexity'] ?? 0;
        $additionalAvgComplexity = $additional['avgComplexity'] ?? 0;

        $avgComplexity = $totalCount > 0
            ? (int) round(($baseAvgComplexity * $baseCount + $additionalAvgComplexity * $additionalCount) / $totalCount)
            : 0;

        return [
            'fileCount' => $totalCount,
            'totalLines' => ($base['totalLines'] ?? 0) + ($additional['totalLines'] ?? 0),
            'totalSize' => ($base['totalSize'] ?? 0) + ($additional['totalSize'] ?? 0),
            'avgComplexity' => $avgComplexity,
            'maxComplexity' => max($base['maxComplexity'] ?? 0, $additional['maxComplexity'] ?? 0),
        ];
    }
}

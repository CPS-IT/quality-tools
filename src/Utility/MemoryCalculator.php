<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Utility;

final class MemoryCalculator
{
    private const int BASE_MEMORY_MB = 128;
    private const int MIN_MEMORY_MB = 256;
    private const int MAX_MEMORY_MB = 2048;

    private const float PHP_FILE_MULTIPLIER = 0.5;
    private const float PHP_COMPLEXITY_MULTIPLIER = 0.1;
    private const float OTHER_FILE_MULTIPLIER = 0.1;

    public function calculateOptimalMemory(ProjectMetrics $metrics): string
    {
        $memoryMb = self::BASE_MEMORY_MB;

        $memoryMb += $metrics->getPhpFileCount() * self::PHP_FILE_MULTIPLIER;
        $memoryMb += $metrics->getPhpComplexityScore() * self::PHP_COMPLEXITY_MULTIPLIER;

        $otherFileCount = ($metrics->yaml['fileCount'] ?? 0) + ($metrics->json['fileCount'] ?? 0)
                        + ($metrics->xml['fileCount'] ?? 0) + ($metrics->typoscript['fileCount'] ?? 0);
        $memoryMb += $otherFileCount * self::OTHER_FILE_MULTIPLIER;

        $adjustedMemory = max(self::MIN_MEMORY_MB, min($memoryMb, self::MAX_MEMORY_MB));

        return (int) round($adjustedMemory) . 'M';
    }

    public function calculateOptimalMemoryForTool(ProjectMetrics $metrics, string $tool): string
    {
        $baseMemory = $this->calculateOptimalMemory($metrics);
        $memoryMb = (int) str_replace('M', '', $baseMemory);

        $toolMultiplier = match ($tool) {
            'phpstan' => 1.2,
            'php-cs-fixer' => 1.0,
            'rector' => 1.5,
            'fractor' => 0.8,
            default => 1.0,
        };

        $adjustedMemory = max(self::MIN_MEMORY_MB, min($memoryMb * $toolMultiplier, self::MAX_MEMORY_MB));

        return (int) round($adjustedMemory) . 'M';
    }

    public function shouldEnableParallelProcessing(ProjectMetrics $metrics): bool
    {
        return $metrics->getTotalFileCount() >= 100;
    }

    public function supportsParallelProcessing(string $tool): bool
    {
        return match ($tool) {
            'phpstan', 'php-cs-fixer' => true,
            'rector', 'fractor' => false,
            default => false
        };
    }

    public function shouldShowProgressIndicator(ProjectMetrics $metrics): bool
    {
        return $metrics->getTotalFileCount() >= 500;
    }

    public function getOptimizationProfile(ProjectMetrics $metrics): array
    {
        $projectSize = $metrics->getProjectSize();
        $memoryLimit = $this->calculateOptimalMemory($metrics);
        $parallelProcessing = $this->shouldEnableParallelProcessing($metrics);
        $progressIndicator = $this->shouldShowProgressIndicator($metrics);

        return [
            'projectSize' => $projectSize,
            'memoryLimit' => $memoryLimit,
            'parallelProcessing' => $parallelProcessing,
            'progressIndicator' => $progressIndicator,
            'optimization' => $this->getOptimizationDescription($projectSize),
            'recommendations' => $this->getOptimizationRecommendations($metrics),
        ];
    }

    private function getOptimizationDescription(string $projectSize): string
    {
        return match ($projectSize) {
            'small' => 'Basic configuration suitable for small projects',
            'medium' => 'Enhanced configuration with parallel processing enabled',
            'large' => 'High-performance configuration for large projects',
            'enterprise' => 'Maximum optimization for enterprise-scale projects',
            default => 'Standard configuration'
        };
    }

    private function getOptimizationRecommendations(ProjectMetrics $metrics): array
    {
        $recommendations = [];

        if ($metrics->getPhpFileCount() > 1000) {
            $recommendations[] = 'Consider using PHPStan result cache for faster subsequent runs';
        }

        if ($metrics->getTotalFileCount() > 3000) {
            $recommendations[] = 'Enable parallel processing for all tools to improve performance';
        }

        if ($metrics->getPhpComplexityScore() > 5000) {
            $recommendations[] = 'High complexity detected - consider running analysis in smaller scopes';
        }

        if (($metrics->yaml['fileCount'] ?? 0) > 500) {
            $recommendations[] = 'Large number of YAML files detected - Fractor analysis may take longer';
        }

        return $recommendations;
    }
}

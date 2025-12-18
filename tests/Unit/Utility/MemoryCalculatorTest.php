<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Tests\Unit\Utility;

use Cpsit\QualityTools\Utility\MemoryCalculator;
use Cpsit\QualityTools\Utility\ProjectMetrics;
use PHPUnit\Framework\TestCase;

final class MemoryCalculatorTest extends TestCase
{
    private MemoryCalculator $memoryCalculator;

    protected function setUp(): void
    {
        $this->memoryCalculator = new MemoryCalculator();
    }

    public function testCalculateOptimalMemoryForSmallProject(): void
    {
        $metrics = new ProjectMetrics([
            'php' => ['fileCount' => 10, 'totalLines' => 500, 'avgComplexity' => 5, 'maxComplexity' => 10],
            'yaml' => ['fileCount' => 2, 'totalLines' => 50],
            'json' => ['fileCount' => 1, 'totalLines' => 20],
            'xml' => ['fileCount' => 0, 'totalLines' => 0],
            'typoscript' => ['fileCount' => 1, 'totalLines' => 30],
            'other' => ['fileCount' => 5, 'totalLines' => 100]
        ]);

        $memory = $this->memoryCalculator->calculateOptimalMemory($metrics);

        $this->assertEquals('256M', $memory);
    }

    public function testCalculateOptimalMemoryForMediumProject(): void
    {
        $metrics = new ProjectMetrics([
            'php' => ['fileCount' => 200, 'totalLines' => 15000, 'avgComplexity' => 8, 'maxComplexity' => 25],
            'yaml' => ['fileCount' => 50, 'totalLines' => 2000],
            'json' => ['fileCount' => 10, 'totalLines' => 500],
            'xml' => ['fileCount' => 5, 'totalLines' => 200],
            'typoscript' => ['fileCount' => 20, 'totalLines' => 1000],
            'other' => ['fileCount' => 100, 'totalLines' => 5000]
        ]);

        $memory = $this->memoryCalculator->calculateOptimalMemory($metrics);

        $memoryValue = (int) str_replace('M', '', $memory);
        $this->assertGreaterThan(256, $memoryValue);
        $this->assertLessThan(1024, $memoryValue);
    }

    public function testCalculateOptimalMemoryForLargeProject(): void
    {
        $metrics = new ProjectMetrics([
            'php' => ['fileCount' => 2000, 'totalLines' => 150000, 'avgComplexity' => 15, 'maxComplexity' => 50],
            'yaml' => ['fileCount' => 500, 'totalLines' => 20000],
            'json' => ['fileCount' => 100, 'totalLines' => 5000],
            'xml' => ['fileCount' => 50, 'totalLines' => 2000],
            'typoscript' => ['fileCount' => 200, 'totalLines' => 10000],
            'other' => ['fileCount' => 1000, 'totalLines' => 50000]
        ]);

        $memory = $this->memoryCalculator->calculateOptimalMemory($metrics);

        $this->assertEquals('2048M', $memory); // Should hit the maximum limit
    }

    public function testCalculateOptimalMemoryForToolSpecific(): void
    {
        $metrics = new ProjectMetrics([
            'php' => ['fileCount' => 100, 'totalLines' => 5000, 'avgComplexity' => 10, 'maxComplexity' => 20],
            'yaml' => ['fileCount' => 20, 'totalLines' => 1000],
            'json' => ['fileCount' => 5, 'totalLines' => 200],
            'xml' => ['fileCount' => 2, 'totalLines' => 100],
            'typoscript' => ['fileCount' => 10, 'totalLines' => 500],
            'other' => ['fileCount' => 50, 'totalLines' => 2000]
        ]);

        $phpstanMemory = $this->memoryCalculator->calculateOptimalMemoryForTool($metrics, 'phpstan');
        $phpCsFixerMemory = $this->memoryCalculator->calculateOptimalMemoryForTool($metrics, 'php-cs-fixer');
        $rectorMemory = $this->memoryCalculator->calculateOptimalMemoryForTool($metrics, 'rector');
        $fractorMemory = $this->memoryCalculator->calculateOptimalMemoryForTool($metrics, 'fractor');

        // PHPStan should get more memory than PHP CS Fixer
        $phpstanValue = (int) str_replace('M', '', $phpstanMemory);
        $phpCsFixerValue = (int) str_replace('M', '', $phpCsFixerMemory);
        $rectorValue = (int) str_replace('M', '', $rectorMemory);
        $fractorValue = (int) str_replace('M', '', $fractorMemory);

        $this->assertGreaterThan($phpCsFixerValue, $phpstanValue);
        $this->assertGreaterThan($phpstanValue, $rectorValue);
        $this->assertLessThan($phpCsFixerValue, $fractorValue);
    }

    public function testShouldEnableParallelProcessing(): void
    {
        $smallProject = new ProjectMetrics([
            'php' => ['fileCount' => 50],
            'yaml' => ['fileCount' => 10],
            'json' => ['fileCount' => 5],
            'xml' => ['fileCount' => 2],
            'typoscript' => ['fileCount' => 3],
            'other' => ['fileCount' => 20]
        ]);

        $largeProject = new ProjectMetrics([
            'php' => ['fileCount' => 200],
            'yaml' => ['fileCount' => 100],
            'json' => ['fileCount' => 50],
            'xml' => ['fileCount' => 25],
            'typoscript' => ['fileCount' => 75],
            'other' => ['fileCount' => 100]
        ]);

        $this->assertFalse($this->memoryCalculator->shouldEnableParallelProcessing($smallProject));
        $this->assertTrue($this->memoryCalculator->shouldEnableParallelProcessing($largeProject));
    }

    public function testShouldShowProgressIndicator(): void
    {
        $smallProject = new ProjectMetrics([
            'php' => ['fileCount' => 100],
            'yaml' => ['fileCount' => 50],
            'json' => ['fileCount' => 25],
            'xml' => ['fileCount' => 10],
            'typoscript' => ['fileCount' => 15],
            'other' => ['fileCount' => 100]
        ]);

        $largeProject = new ProjectMetrics([
            'php' => ['fileCount' => 1000],
            'yaml' => ['fileCount' => 500],
            'json' => ['fileCount' => 250],
            'xml' => ['fileCount' => 100],
            'typoscript' => ['fileCount' => 150],
            'other' => ['fileCount' => 1000]
        ]);

        $this->assertFalse($this->memoryCalculator->shouldShowProgressIndicator($smallProject));
        $this->assertTrue($this->memoryCalculator->shouldShowProgressIndicator($largeProject));
    }

    public function testGetOptimizationProfile(): void
    {
        $metrics = new ProjectMetrics([
            'php' => ['fileCount' => 500, 'totalLines' => 25000, 'avgComplexity' => 12, 'maxComplexity' => 30],
            'yaml' => ['fileCount' => 100, 'totalLines' => 5000],
            'json' => ['fileCount' => 25, 'totalLines' => 1000],
            'xml' => ['fileCount' => 10, 'totalLines' => 500],
            'typoscript' => ['fileCount' => 50, 'totalLines' => 2500],
            'other' => ['fileCount' => 200, 'totalLines' => 10000]
        ]);

        $profile = $this->memoryCalculator->getOptimizationProfile($metrics);

        $this->assertArrayHasKey('projectSize', $profile);
        $this->assertArrayHasKey('memoryLimit', $profile);
        $this->assertArrayHasKey('parallelProcessing', $profile);
        $this->assertArrayHasKey('progressIndicator', $profile);
        $this->assertArrayHasKey('optimization', $profile);
        $this->assertArrayHasKey('recommendations', $profile);

        $this->assertEquals('medium', $profile['projectSize']);
        $this->assertTrue($profile['parallelProcessing']);
        $this->assertTrue($profile['progressIndicator']);
        $this->assertIsArray($profile['recommendations']);
    }

    public function testOptimizationRecommendationsForComplexProject(): void
    {
        $metrics = new ProjectMetrics([
            'php' => ['fileCount' => 1500, 'totalLines' => 75000, 'avgComplexity' => 20, 'maxComplexity' => 80],
            'yaml' => ['fileCount' => 600, 'totalLines' => 30000],
            'json' => ['fileCount' => 100, 'totalLines' => 5000],
            'xml' => ['fileCount' => 50, 'totalLines' => 2500],
            'typoscript' => ['fileCount' => 300, 'totalLines' => 15000],
            'other' => ['fileCount' => 2000, 'totalLines' => 100000]
        ]);

        $profile = $this->memoryCalculator->getOptimizationProfile($metrics);

        $this->assertCount(4, $profile['recommendations']);
        $this->assertStringContainsString('PHPStan result cache', $profile['recommendations'][0]);
        $this->assertStringContainsString('parallel processing', $profile['recommendations'][1]);
        $this->assertStringContainsString('High complexity detected', $profile['recommendations'][2]);
        $this->assertStringContainsString('YAML files detected', $profile['recommendations'][3]);
    }

    public function testMinimumMemoryLimit(): void
    {
        $metrics = new ProjectMetrics([
            'php' => ['fileCount' => 1, 'totalLines' => 10, 'avgComplexity' => 1],
            'yaml' => ['fileCount' => 0, 'totalLines' => 0],
            'json' => ['fileCount' => 0, 'totalLines' => 0],
            'xml' => ['fileCount' => 0, 'totalLines' => 0],
            'typoscript' => ['fileCount' => 0, 'totalLines' => 0],
            'other' => ['fileCount' => 0, 'totalLines' => 0]
        ]);

        $memory = $this->memoryCalculator->calculateOptimalMemory($metrics);

        $this->assertEquals('256M', $memory);
    }
}

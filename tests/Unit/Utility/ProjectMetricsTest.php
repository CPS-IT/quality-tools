<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Tests\Unit\Utility;

use Cpsit\QualityTools\Utility\ProjectMetrics;
use PHPUnit\Framework\TestCase;

final class ProjectMetricsTest extends TestCase
{
    public function testProjectMetricsConstruction(): void
    {
        $data = [
            'php' => ['fileCount' => 100, 'totalLines' => 5000, 'avgComplexity' => 10],
            'yaml' => ['fileCount' => 20, 'totalLines' => 1000],
            'json' => ['fileCount' => 5, 'totalLines' => 200],
            'xml' => ['fileCount' => 3, 'totalLines' => 150],
            'typoscript' => ['fileCount' => 8, 'totalLines' => 400],
            'other' => ['fileCount' => 50, 'totalLines' => 2500],
        ];

        $metrics = new ProjectMetrics($data);

        $this->assertEquals(100, $metrics->php['fileCount']);
        $this->assertEquals(20, $metrics->yaml['fileCount']);
        $this->assertEquals(5, $metrics->json['fileCount']);
        $this->assertEquals(3, $metrics->xml['fileCount']);
        $this->assertEquals(8, $metrics->typoscript['fileCount']);
        $this->assertEquals(50, $metrics->other['fileCount']);
    }

    public function testGetTotalFileCount(): void
    {
        $metrics = new ProjectMetrics([
            'php' => ['fileCount' => 100],
            'yaml' => ['fileCount' => 20],
            'json' => ['fileCount' => 5],
            'xml' => ['fileCount' => 3],
            'typoscript' => ['fileCount' => 8],
            'other' => ['fileCount' => 50],
        ]);

        $this->assertEquals(186, $metrics->getTotalFileCount());
    }

    public function testGetTotalLines(): void
    {
        $metrics = new ProjectMetrics([
            'php' => ['totalLines' => 5000],
            'yaml' => ['totalLines' => 1000],
            'json' => ['totalLines' => 200],
            'xml' => ['totalLines' => 150],
            'typoscript' => ['totalLines' => 400],
            'other' => ['totalLines' => 2500],
        ]);

        $this->assertEquals(9250, $metrics->getTotalLines());
    }

    public function testGetPhpFileCount(): void
    {
        $metrics = new ProjectMetrics([
            'php' => ['fileCount' => 125],
        ]);

        $this->assertEquals(125, $metrics->getPhpFileCount());
    }

    public function testGetPhpLines(): void
    {
        $metrics = new ProjectMetrics([
            'php' => ['totalLines' => 7500],
        ]);

        $this->assertEquals(7500, $metrics->getPhpLines());
    }

    public function testGetPhpComplexityScore(): void
    {
        $metrics = new ProjectMetrics([
            'php' => ['fileCount' => 100, 'avgComplexity' => 15],
        ]);

        $this->assertEquals(1500, $metrics->getPhpComplexityScore());
    }

    public function testGetProjectSizeSmall(): void
    {
        $metrics = new ProjectMetrics([
            'php' => ['fileCount' => 50],
            'yaml' => ['fileCount' => 10],
            'json' => ['fileCount' => 5],
            'xml' => ['fileCount' => 2],
            'typoscript' => ['fileCount' => 3],
            'other' => ['fileCount' => 20],
        ]);

        $this->assertEquals('small', $metrics->getProjectSize());
    }

    public function testGetProjectSizeMedium(): void
    {
        $metrics = new ProjectMetrics([
            'php' => ['fileCount' => 200],
            'yaml' => ['fileCount' => 100],
            'json' => ['fileCount' => 50],
            'xml' => ['fileCount' => 25],
            'typoscript' => ['fileCount' => 75],
            'other' => ['fileCount' => 150],
        ]);

        $this->assertEquals('medium', $metrics->getProjectSize());
    }

    public function testGetProjectSizeLarge(): void
    {
        $metrics = new ProjectMetrics([
            'php' => ['fileCount' => 1500],
            'yaml' => ['fileCount' => 500],
            'json' => ['fileCount' => 250],
            'xml' => ['fileCount' => 100],
            'typoscript' => ['fileCount' => 200],
            'other' => ['fileCount' => 1000],
        ]);

        $this->assertEquals('large', $metrics->getProjectSize());
    }

    public function testGetProjectSizeEnterprise(): void
    {
        $metrics = new ProjectMetrics([
            'php' => ['fileCount' => 8000],
            'yaml' => ['fileCount' => 2000],
            'json' => ['fileCount' => 1000],
            'xml' => ['fileCount' => 500],
            'typoscript' => ['fileCount' => 1000],
            'other' => ['fileCount' => 5000],
        ]);

        $this->assertEquals('enterprise', $metrics->getProjectSize());
    }

    public function testToArray(): void
    {
        $data = [
            'php' => ['fileCount' => 100, 'totalLines' => 5000, 'avgComplexity' => 10],
            'yaml' => ['fileCount' => 20, 'totalLines' => 1000],
            'json' => ['fileCount' => 5, 'totalLines' => 200],
            'xml' => ['fileCount' => 3, 'totalLines' => 150],
            'typoscript' => ['fileCount' => 8, 'totalLines' => 400],
            'other' => ['fileCount' => 50, 'totalLines' => 2500],
        ];

        $metrics = new ProjectMetrics($data);
        $array = $metrics->toArray();

        $this->assertArrayHasKey('summary', $array);
        $this->assertEquals(186, $array['summary']['totalFiles']);
        $this->assertEquals(9250, $array['summary']['totalLines']);
        $this->assertEquals('medium', $array['summary']['projectSize']);
        $this->assertEquals(100, $array['summary']['phpFiles']);
        $this->assertEquals(5000, $array['summary']['phpLines']);
        $this->assertEquals(1000, $array['summary']['complexityScore']);
    }

    public function testConstructionWithMissingData(): void
    {
        $metrics = new ProjectMetrics([
            'php' => ['fileCount' => 50],
        ]);

        $this->assertEquals(0, $metrics->yaml['fileCount'] ?? 0);
        $this->assertEquals(0, $metrics->json['fileCount'] ?? 0);
    }

    public function testConstructionWithEmptyData(): void
    {
        $metrics = new ProjectMetrics([]);

        $this->assertEquals(0, $metrics->getTotalFileCount());
        $this->assertEquals(0, $metrics->getPhpFileCount());
        $this->assertEquals('small', $metrics->getProjectSize());
    }
}

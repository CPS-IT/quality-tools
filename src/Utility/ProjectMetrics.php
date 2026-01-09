<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Utility;

final readonly class ProjectMetrics
{
    public array $php;
    public array $yaml;
    public array $json;
    public array $xml;
    public array $typoscript;
    public array $other;

    public function __construct(array $metrics)
    {
        $this->php = $metrics['php'] ?? [];
        $this->yaml = $metrics['yaml'] ?? [];
        $this->json = $metrics['json'] ?? [];
        $this->xml = $metrics['xml'] ?? [];
        $this->typoscript = $metrics['typoscript'] ?? [];
        $this->other = $metrics['other'] ?? [];
    }

    public function getTotalFileCount(): int
    {
        return ($this->php['fileCount'] ?? 0) + ($this->yaml['fileCount'] ?? 0) + ($this->json['fileCount'] ?? 0)
            + ($this->xml['fileCount'] ?? 0) + ($this->typoscript['fileCount'] ?? 0) + ($this->other['fileCount'] ?? 0);
    }

    public function getTotalLines(): int
    {
        return ($this->php['totalLines'] ?? 0) + ($this->yaml['totalLines'] ?? 0) + ($this->json['totalLines'] ?? 0)
            + ($this->xml['totalLines'] ?? 0) + ($this->typoscript['totalLines'] ?? 0) + ($this->other['totalLines'] ?? 0);
    }

    public function getPhpFileCount(): int
    {
        return $this->php['fileCount'] ?? 0;
    }

    public function getPhpLines(): int
    {
        return $this->php['totalLines'] ?? 0;
    }

    public function getPhpComplexityScore(): int
    {
        return ($this->php['avgComplexity'] ?? 0) * ($this->php['fileCount'] ?? 0);
    }

    public function getProjectSize(): string
    {
        $totalFiles = $this->getTotalFileCount();

        if ($totalFiles < 100) {
            return 'small';
        }
        if ($totalFiles < 1000) {
            return 'medium';
        }
        if ($totalFiles < 5000) {
            return 'large';
        }

        return 'enterprise';
    }

    public function toArray(): array
    {
        return [
            'php' => $this->php,
            'yaml' => $this->yaml,
            'json' => $this->json,
            'xml' => $this->xml,
            'typoscript' => $this->typoscript,
            'other' => $this->other,
            'summary' => [
                'totalFiles' => $this->getTotalFileCount(),
                'totalLines' => $this->getTotalLines(),
                'projectSize' => $this->getProjectSize(),
                'phpFiles' => $this->getPhpFileCount(),
                'phpLines' => $this->getPhpLines(),
                'complexityScore' => $this->getPhpComplexityScore(),
            ],
        ];
    }
}

<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Utility;

final class ProjectAnalyzer
{
    private const array DEFAULT_EXCLUDE_PATTERNS = [
        'vendor/',
        'node_modules/',
        '.git/',
        '.ddev/',
        'var/',
        'public/',
        'web/',
        'cache/',
        'temp/',
        'tmp/',
        'logs/',
        'log/',
        '.cache/',
        '.tmp/',
    ];

    public function analyzeProject(string $projectPath): ProjectMetrics
    {
        if (!is_dir($projectPath)) {
            throw new \InvalidArgumentException(\sprintf('Project path "%s" is not a directory', $projectPath));
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($projectPath, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::LEAVES_ONLY,
        );

        $phpFiles = [];
        $yamlFiles = [];
        $jsonFiles = [];
        $xmlFiles = [];
        $typoscriptFiles = [];
        $otherFiles = [];

        foreach ($iterator as $file) {
            if (!$file instanceof \SplFileInfo || !$file->isFile()) {
                continue;
            }

            $relativePath = $this->getRelativePath($file->getPathname(), $projectPath);

            if ($this->shouldExcludeFile($relativePath)) {
                continue;
            }

            $extension = strtolower($file->getExtension());
            $fileInfo = [
                'path' => $relativePath,
                'size' => $file->getSize(),
                'lines' => $this->countLines($file->getPathname()),
            ];

            switch ($extension) {
                case 'php':
                    $fileInfo['complexity'] = $this->estimatePhpComplexity($file->getPathname());
                    $phpFiles[] = $fileInfo;
                    break;
                case 'yaml':
                case 'yml':
                    $yamlFiles[] = $fileInfo;
                    break;
                case 'json':
                    $jsonFiles[] = $fileInfo;
                    break;
                case 'xml':
                    $xmlFiles[] = $fileInfo;
                    break;
                case 'ts':
                case 'typoscript':
                case 'txt':
                    if ($this->isTypoScriptFile($file->getPathname())) {
                        $typoscriptFiles[] = $fileInfo;
                    } else {
                        $otherFiles[] = $fileInfo;
                    }
                    break;
                default:
                    $otherFiles[] = $fileInfo;
                    break;
            }
        }

        return new ProjectMetrics([
            'php' => $this->aggregateFileMetrics($phpFiles),
            'yaml' => $this->aggregateFileMetrics($yamlFiles),
            'json' => $this->aggregateFileMetrics($jsonFiles),
            'xml' => $this->aggregateFileMetrics($xmlFiles),
            'typoscript' => $this->aggregateFileMetrics($typoscriptFiles),
            'other' => $this->aggregateFileMetrics($otherFiles),
        ]);
    }

    private function getRelativePath(string $filePath, string $basePath): string
    {
        $realFilePath = realpath($filePath);
        $realBasePath = realpath($basePath);

        if ($realFilePath === false || $realBasePath === false) {
            return $filePath;
        }

        return ltrim(str_replace($realBasePath, '', $realFilePath), DIRECTORY_SEPARATOR);
    }

    private function shouldExcludeFile(string $relativePath): bool
    {
        foreach (self::DEFAULT_EXCLUDE_PATTERNS as $pattern) {
            if (str_starts_with($relativePath, $pattern)) {
                return true;
            }
        }

        return false;
    }

    private function countLines(string $filePath): int
    {
        $content = file_get_contents($filePath);
        if ($content === false) {
            return 0;
        }

        return substr_count($content, "\n") + 1;
    }

    private function estimatePhpComplexity(string $filePath): int
    {
        $content = file_get_contents($filePath);
        if ($content === false) {
            return 1;
        }

        $complexity = 1;

        $complexityKeywords = [
            'if', 'else', 'elseif', 'while', 'for', 'foreach',
            'switch', 'case', 'catch', 'throw', '?', '&&', '||',
        ];

        foreach ($complexityKeywords as $keyword) {
            $complexity += substr_count($content, $keyword);
        }

        $complexity += substr_count($content, 'function ');
        $complexity += substr_count($content, 'class ');
        $complexity += substr_count($content, 'interface ');

        return $complexity + substr_count($content, 'trait ');
    }

    private function isTypoScriptFile(string $filePath): bool
    {
        $content = file_get_contents($filePath);
        if ($content === false) {
            return false;
        }

        $typoscriptPatterns = [
            'plugin.',
            'lib.',
            'config.',
            'page.',
            'temp.',
            'styles.',
            'tt_content.',
            'TYPO3\CMS\\',
            'includeLibs',
            'includeCSS',
            'includeJS',
        ];

        foreach ($typoscriptPatterns as $pattern) {
            if (str_contains($content, $pattern)) {
                return true;
            }
        }

        return false;
    }

    private function aggregateFileMetrics(array $files): array
    {
        if (empty($files)) {
            return [
                'fileCount' => 0,
                'totalLines' => 0,
                'totalSize' => 0,
                'avgComplexity' => 0,
                'maxComplexity' => 0,
            ];
        }

        $fileCount = \count($files);
        $totalLines = array_sum(array_column($files, 'lines'));
        $totalSize = array_sum(array_column($files, 'size'));

        $complexities = array_filter(array_column($files, 'complexity'));
        $avgComplexity = empty($complexities) ? 0 : (int) round(array_sum($complexities) / \count($complexities));
        $maxComplexity = empty($complexities) ? 0 : max($complexities);

        return [
            'fileCount' => $fileCount,
            'totalLines' => $totalLines,
            'totalSize' => $totalSize,
            'avgComplexity' => $avgComplexity,
            'maxComplexity' => $maxComplexity,
        ];
    }
}

<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Utility;

use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/**
 * YAML validation utility for pre-validating YAML files before Fractor processing.
 */
final class YamlValidator
{
    private array $validationResults = [];

    /**
     * Validate YAML files in a directory and return validation results.
     *
     * @param string $projectPath Directory to scan for YAML files
     * @return array{valid: array, invalid: array, summary: array}
     */
    public function validateYamlFiles(string $projectPath): array
    {
        $this->validationResults = ['valid' => [], 'invalid' => [], 'summary' => []];

        $yamlFiles = $this->findYamlFiles($projectPath);

        foreach ($yamlFiles as $yamlFile) {
            $result = $this->validateSingleFile($yamlFile);
            if ($result['valid']) {
                $this->validationResults['valid'][] = $result;
            } else {
                $this->validationResults['invalid'][] = $result;
            }
        }

        $this->validationResults['summary'] = [
            'total' => count($yamlFiles),
            'valid' => count($this->validationResults['valid']),
            'invalid' => count($this->validationResults['invalid']),
        ];

        return $this->validationResults;
    }

    /**
     * Find all YAML files in a directory.
     *
     * @param string $projectPath Directory to scan
     * @return string[] Array of YAML file paths
     */
    private function findYamlFiles(string $projectPath): array
    {
        $yamlFiles = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($projectPath, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $extension = strtolower($file->getExtension());
                if (in_array($extension, ['yaml', 'yml'], true)) {
                    $yamlFiles[] = $file->getRealPath();
                }
            }
        }

        return $yamlFiles;
    }

    /**
     * Validate a single YAML file.
     *
     * @param string $filePath Path to YAML file
     * @return array{file: string, valid: bool, error: string|null, type: string}
     */
    private function validateSingleFile(string $filePath): array
    {
        $result = [
            'file' => $filePath,
            'valid' => false,
            'error' => null,
            'type' => 'unknown'
        ];

        if (!file_exists($filePath) || !is_readable($filePath)) {
            $result['error'] = 'File not readable';
            return $result;
        }

        $content = file_get_contents($filePath);
        if ($content === false) {
            $result['error'] = 'Could not read file content';
            return $result;
        }

        // Check for empty files
        if (trim($content) === '') {
            $result['error'] = 'Empty file';
            $result['type'] = 'empty';
            return $result;
        }

        try {
            $parsed = Yaml::parse($content);

            // Check if parsed result is array (expected by Fractor)
            if (!is_array($parsed)) {
                $result['error'] = sprintf(
                    'YAML parses to %s instead of array (Fractor requirement)',
                    gettype($parsed)
                );
                $result['type'] = 'wrong_type';
                return $result;
            }

            $result['valid'] = true;
            $result['type'] = 'valid_array';

        } catch (ParseException $e) {
            $result['error'] = sprintf('Parse error: %s', $e->getMessage());
            $result['type'] = 'parse_error';
        } catch (\Exception $e) {
            $result['error'] = sprintf('Validation error: %s', $e->getMessage());
            $result['type'] = 'validation_error';
        }

        return $result;
    }

    /**
     * Get a summary of problematic YAML files for user reporting.
     *
     * @param array $validationResults Results from validateYamlFiles()
     * @return string[] Array of user-friendly error descriptions
     */
    public function getProblematicFilesSummary(array $validationResults): array
    {
        $summary = [];

        foreach ($validationResults['invalid'] as $invalid) {
            $relativePath = $this->getRelativePath($invalid['file']);
            $summary[] = sprintf('%s: %s', $relativePath, $invalid['error']);
        }

        return $summary;
    }

    /**
     * Create exclude patterns for Fractor to skip problematic YAML files.
     *
     * @param array $validationResults Results from validateYamlFiles()
     * @return string[] Array of file paths to exclude
     */
    public function getProblematicFilePaths(array $validationResults): array
    {
        return array_column($validationResults['invalid'], 'file');
    }

    /**
     * Get relative path for display purposes.
     *
     * @param string $filePath Absolute file path
     * @return string Relative path for display
     */
    private function getRelativePath(string $filePath): string
    {
        $cwd = getcwd();
        if ($cwd !== false && str_starts_with($filePath, $cwd)) {
            return substr($filePath, strlen($cwd) + 1);
        }
        return $filePath;
    }
}

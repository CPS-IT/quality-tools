<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Configuration;

final class Configuration
{
    private array $data;
    private array $projectConfig;
    private array $pathsConfig;
    private array $toolsConfig;
    private array $outputConfig;
    private array $performanceConfig;

    public function __construct(array $data = [])
    {
        $this->data = $data;
        $this->parseConfiguration();
    }

    private function parseConfiguration(): void
    {
        $qualityTools = $this->data['quality-tools'] ?? [];

        $this->projectConfig = $qualityTools['project'] ?? [];
        $this->pathsConfig = $qualityTools['paths'] ?? [];
        $this->toolsConfig = $qualityTools['tools'] ?? [];
        $this->outputConfig = $qualityTools['output'] ?? [];
        $this->performanceConfig = $qualityTools['performance'] ?? [];
    }

    public function getProjectPhpVersion(): string
    {
        return $this->projectConfig['php_version'] ?? '8.3';
    }

    public function getProjectTypo3Version(): string
    {
        return $this->projectConfig['typo3_version'] ?? '13.4';
    }

    public function getProjectName(): ?string
    {
        return $this->projectConfig['name'] ?? null;
    }

    public function getScanPaths(): array
    {
        return $this->pathsConfig['scan'] ?? ['packages/', 'config/system/'];
    }

    public function getExcludePaths(): array
    {
        return $this->pathsConfig['exclude'] ?? ['var/', 'vendor/', 'node_modules/'];
    }

    public function isToolEnabled(string $tool): bool
    {
        return $this->toolsConfig[$tool]['enabled'] ?? true;
    }

    public function getToolConfig(string $tool): array
    {
        return $this->toolsConfig[$tool] ?? [];
    }

    public function getRectorConfig(): array
    {
        $config = $this->getToolConfig('rector');
        return array_merge([
            'enabled' => true,
            'level' => 'typo3-13',
            'php_version' => $this->getProjectPhpVersion(),
        ], $config);
    }

    public function getFractorConfig(): array
    {
        $config = $this->getToolConfig('fractor');
        return array_merge([
            'enabled' => true,
            'indentation' => 2,
        ], $config);
    }

    public function getPhpStanConfig(): array
    {
        $config = $this->getToolConfig('phpstan');
        return array_merge([
            'enabled' => true,
            'level' => 6,
            'memory_limit' => '1G',
        ], $config);
    }

    public function getPhpCsFixerConfig(): array
    {
        $config = $this->getToolConfig('php-cs-fixer');
        return array_merge([
            'enabled' => true,
            'preset' => 'typo3',
        ], $config);
    }

    public function getTypoScriptLintConfig(): array
    {
        $config = $this->getToolConfig('typoscript-lint');
        return array_merge([
            'enabled' => true,
            'indentation' => 2,
        ], $config);
    }

    public function getVerbosity(): string
    {
        return $this->outputConfig['verbosity'] ?? 'normal';
    }

    public function isColorsEnabled(): bool
    {
        return $this->outputConfig['colors'] ?? true;
    }

    public function isProgressEnabled(): bool
    {
        return $this->outputConfig['progress'] ?? true;
    }

    public function isParallelEnabled(): bool
    {
        return $this->performanceConfig['parallel'] ?? true;
    }

    public function getMaxProcesses(): int
    {
        return $this->performanceConfig['max_processes'] ?? 4;
    }

    public function isCacheEnabled(): bool
    {
        return $this->performanceConfig['cache_enabled'] ?? true;
    }

    public function toArray(): array
    {
        return $this->data;
    }

    public function merge(Configuration $other): self
    {
        $mergedData = array_merge_recursive($this->data, $other->toArray());
        return new self($mergedData);
    }

    public static function createDefault(): self
    {
        return new self([
            'quality-tools' => [
                'project' => [
                    'php_version' => '8.3',
                    'typo3_version' => '13.4',
                ],
                'paths' => [
                    'scan' => ['packages/', 'config/system/'],
                    'exclude' => ['var/', 'vendor/', 'node_modules/'],
                ],
                'tools' => [
                    'rector' => [
                        'enabled' => true,
                        'level' => 'typo3-13',
                    ],
                    'fractor' => [
                        'enabled' => true,
                        'indentation' => 2,
                    ],
                    'phpstan' => [
                        'enabled' => true,
                        'level' => 6,
                        'memory_limit' => '1G',
                    ],
                    'php-cs-fixer' => [
                        'enabled' => true,
                        'preset' => 'typo3',
                    ],
                    'typoscript-lint' => [
                        'enabled' => true,
                        'indentation' => 2,
                    ],
                ],
                'output' => [
                    'verbosity' => 'normal',
                    'colors' => true,
                    'progress' => true,
                ],
                'performance' => [
                    'parallel' => true,
                    'max_processes' => 4,
                    'cache_enabled' => true,
                ],
            ],
        ]);
    }
}

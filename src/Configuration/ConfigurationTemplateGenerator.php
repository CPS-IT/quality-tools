<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Configuration;

/**
 * Generates .quality-tools.yaml configuration templates for different project types.
 *
 * Extracted from ConfigInitCommand to be independently testable
 * and reusable by the ConfigInitRunner.
 */
final readonly class ConfigurationTemplateGenerator
{
    private const string TEMPLATE_DEFAULT = 'default';
    private const string TEMPLATE_TYPO3_EXTENSION = 'typo3-extension';
    private const string TEMPLATE_TYPO3_SITE_PACKAGE = 'typo3-site-package';
    private const string TEMPLATE_TYPO3_DISTRIBUTION = 'typo3-distribution';

    private const array TEMPLATES = [
        self::TEMPLATE_DEFAULT => 'Default Configuration',
        self::TEMPLATE_TYPO3_EXTENSION => 'TYPO3 Extension',
        self::TEMPLATE_TYPO3_SITE_PACKAGE => 'TYPO3 Site Package',
        self::TEMPLATE_TYPO3_DISTRIBUTION => 'TYPO3 Distribution',
    ];

    public function generate(string $template, string $projectRoot): string
    {
        $projectName = $this->detectProjectName($projectRoot);

        return match ($template) {
            self::TEMPLATE_TYPO3_EXTENSION => $this->extensionTemplate($projectName),
            self::TEMPLATE_TYPO3_SITE_PACKAGE => $this->sitePackageTemplate($projectName),
            self::TEMPLATE_TYPO3_DISTRIBUTION => $this->distributionTemplate($projectName),
            default => $this->baseTemplate($projectName),
        };
    }

    public function isValidTemplate(string $template): bool
    {
        return \array_key_exists($template, self::TEMPLATES);
    }

    /**
     * @return array<string, string>
     */
    public function getAvailableTemplates(): array
    {
        return self::TEMPLATES;
    }

    private function detectProjectName(string $projectRoot): string
    {
        $composerFile = $projectRoot . '/composer.json';
        if (file_exists($composerFile)) {
            try {
                $composerData = json_decode(
                    file_get_contents($composerFile),
                    true,
                    512,
                    JSON_THROW_ON_ERROR,
                );
                if (isset($composerData['name'])) {
                    return $composerData['name'];
                }
            } catch (\JsonException) {
                // Fall back to directory name
            }
        }

        return basename($projectRoot);
    }

    private function baseTemplate(string $projectName): string
    {
        return <<<YAML
            # Quality Tools Configuration for $projectName
            # This file configures all quality analysis tools for your TYPO3 project
            quality-tools:
              project:
                name: "$projectName"
                php_version: "8.3"
                typo3_version: "14.0"

              paths:
                scan:
                  - "packages/"
                  - "config/system/"
                exclude:
                  - "var/"
                  - "vendor/"
                  - "node_modules/"

              tools:
                rector:
                  enabled: true
                  level: "typo3-14"

                fractor:
                  enabled: true
                  indentation: 2

                phpstan:
                  enabled: true
                  level: 6
                  memory_limit: "1G"

                php-cs-fixer:
                  enabled: true
                  preset: "typo3"

                typoscript-lint:
                  enabled: true
                  indentation: 2

              output:
                verbosity: "normal"
                colors: true
                progress: true

              performance:
                parallel: true
                max_processes: 4
                cache_enabled: true
            YAML;
    }

    private function extensionTemplate(string $projectName): string
    {
        return <<<YAML
            # Quality Tools Configuration for $projectName Extension
            quality-tools:
              project:
                name: "$projectName"
                php_version: "8.3"
                typo3_version: "14.0"

              paths:
                scan:
                  - "Classes/"
                  - "Configuration/"
                  - "Tests/"
                exclude:
                  - "var/"
                  - "vendor/"
                  - ".build/"

              tools:
                rector:
                  enabled: true
                  level: "typo3-14"

                fractor:
                  enabled: true
                  indentation: 2

                phpstan:
                  enabled: true
                  level: 8
                  memory_limit: "512M"

                php-cs-fixer:
                  enabled: true
                  preset: "typo3"

                typoscript-lint:
                  enabled: true
                  indentation: 2

              output:
                verbosity: "normal"
                colors: true

              performance:
                parallel: false
                cache_enabled: true
            YAML;
    }

    private function sitePackageTemplate(string $projectName): string
    {
        return <<<YAML
            # Quality Tools Configuration for $projectName Site Package
            quality-tools:
              project:
                name: "$projectName"
                php_version: "8.3"
                typo3_version: "14.0"

              paths:
                scan:
                  - "packages/"
                  - "config/"
                exclude:
                  - "var/"
                  - "vendor/"
                  - "public/"
                  - "node_modules/"

              tools:
                rector:
                  enabled: true
                  level: "typo3-14"

                fractor:
                  enabled: true
                  indentation: 2

                phpstan:
                  enabled: true
                  level: 6
                  memory_limit: "1G"

                php-cs-fixer:
                  enabled: true
                  preset: "typo3"

                typoscript-lint:
                  enabled: true
                  indentation: 2

              output:
                verbosity: "normal"
                colors: true
                progress: true

              performance:
                parallel: true
                max_processes: 4
                cache_enabled: true
            YAML;
    }

    private function distributionTemplate(string $projectName): string
    {
        return <<<YAML
            # Quality Tools Configuration for $projectName Distribution
            quality-tools:
              project:
                name: "$projectName"
                php_version: "8.3"
                typo3_version: "14.0"

              paths:
                scan:
                  - "packages/"
                  - "config/system/"
                  - "config/sites/"
                exclude:
                  - "var/"
                  - "vendor/"
                  - "public/"
                  - "node_modules/"
                  - ".build/"

              tools:
                rector:
                  enabled: true
                  level: "typo3-14"

                fractor:
                  enabled: true
                  indentation: 2

                phpstan:
                  enabled: true
                  level: 5
                  memory_limit: "2G"

                php-cs-fixer:
                  enabled: true
                  preset: "typo3"

                typoscript-lint:
                  enabled: true
                  indentation: 2

              output:
                verbosity: "normal"
                colors: true
                progress: true

              performance:
                parallel: true
                max_processes: 8
                cache_enabled: true
            YAML;
    }
}

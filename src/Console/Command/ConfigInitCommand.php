<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Console\Command;

use Cpsit\QualityTools\Configuration\ConfigurationLoaderInterface;
use Cpsit\QualityTools\Exception\ConfigurationFileWriteException;
use Cpsit\QualityTools\Exception\FileSystemException;
use Cpsit\QualityTools\Service\FilesystemService;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

final class ConfigInitCommand extends BaseCommand
{
    public function __construct(
        private readonly FilesystemService $filesystemService,
        ?ConfigurationLoaderInterface $configurationLoader = null,
    ) {
        parent::__construct('config:init', $configurationLoader);
    }

    private const string TEMPLATE_TYPO3_EXTENSION = 'typo3-extension';
    private const string TEMPLATE_TYPO3_SITE_PACKAGE = 'typo3-site-package';
    private const string TEMPLATE_TYPO3_DISTRIBUTION = 'typo3-distribution';
    private const string TEMPLATE_DEFAULT = 'default';

    private const array TEMPLATES = [
        self::TEMPLATE_TYPO3_EXTENSION => 'TYPO3 Extension',
        self::TEMPLATE_TYPO3_SITE_PACKAGE => 'TYPO3 Site Package',
        self::TEMPLATE_TYPO3_DISTRIBUTION => 'TYPO3 Distribution',
        self::TEMPLATE_DEFAULT => 'Default Configuration',
    ];

    #[\Override]
    protected function configure(): void
    {
        parent::configure();

        $this
            ->setName('config:init')
            ->setDescription('Initialize YAML configuration file')
            ->setHelp('This command creates a .quality-tools.yaml configuration file in the project root.')
            ->addOption(
                'template',
                't',
                InputOption::VALUE_REQUIRED,
                'Configuration template: ' . implode(', ', array_keys(self::TEMPLATES)),
                'default',
            )
            ->addOption(
                'force',
                'f',
                InputOption::VALUE_NONE,
                'Overwrite existing configuration file',
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $projectRoot = $this->getProjectRoot();
        $template = $input->getOption('template');
        $force = $input->getOption('force');

        if (!\array_key_exists($template, self::TEMPLATES)) {
            $io->error(\sprintf(
                'Invalid template "%s". Available templates: %s',
                $template,
                implode(', ', array_keys(self::TEMPLATES)),
            ));

            return self::FAILURE;
        }

        $configFile = $projectRoot . '/.quality-tools.yaml';

        // Check if a configuration file already exists
        $loader = $this->getYamlConfigurationLoader();
        $existingConfig = $loader->findConfigurationFile($projectRoot);

        if ($existingConfig !== null && !$force) {
            $io->warning(\sprintf('Configuration file already exists: %s', $existingConfig));
            $io->note('Use --force to overwrite the existing configuration.');

            return self::SUCCESS;
        }

        try {
            $configContent = $this->generateTemplate($template, $projectRoot);
            $this->writeConfigurationFile($configFile, $configContent);

            $io->success([
                \sprintf('Created configuration file: %s', $configFile),
                \sprintf('Template used: %s', self::TEMPLATES[$template]),
            ]);

            $io->note([
                'Next steps:',
                '1. Review and customize the configuration',
                '2. Run "qt config:validate" to check syntax',
                '3. Use "qt config:show" to see resolved settings',
            ]);

            return self::SUCCESS;
        } catch (\Exception $e) {
            $io->error([
                'Failed to create configuration file:',
                $e->getMessage(),
            ]);

            return self::FAILURE;
        }
    }

    private function generateTemplate(string $template, string $projectRoot): string
    {
        $projectName = $this->detectProjectName($projectRoot);

        $baseTemplate = $this->getBaseTemplate($projectName);

        return match ($template) {
            self::TEMPLATE_TYPO3_EXTENSION => $this->getExtensionTemplate($projectName),
            self::TEMPLATE_TYPO3_SITE_PACKAGE => $this->getSitePackageTemplate($projectName),
            self::TEMPLATE_TYPO3_DISTRIBUTION => $this->getDistributionTemplate($projectName),
            default => $baseTemplate
        };
    }

    private function detectProjectName(string $projectRoot): string
    {
        // Try to detect project name from composer.json
        $composerFile = $projectRoot . '/composer.json';
        if (file_exists($composerFile)) {
            try {
                $composerData = json_decode(file_get_contents($composerFile), true, 512, JSON_THROW_ON_ERROR);
                if (isset($composerData['name'])) {
                    return $composerData['name'];
                }
            } catch (\JsonException) {
                // Ignore invalid JSON and fall back to directory name
            }
        }

        // Use directory name as fallback
        return basename($projectRoot);
    }

    private function getBaseTemplate(string $projectName): string
    {
        return <<<YAML
            # Quality Tools Configuration for $projectName
            # This file configures all quality analysis tools for your TYPO3 project
            quality-tools:
              project:
                name: "$projectName"
                php_version: "8.3"
                typo3_version: "13.4"

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
                  level: "typo3-13"

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

    private function getExtensionTemplate(string $projectName): string
    {
        return <<<YAML
            # Quality Tools Configuration for $projectName Extension
            quality-tools:
              project:
                name: "$projectName"
                php_version: "8.3"
                typo3_version: "13.4"

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
                  level: "typo3-13"

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

    private function getSitePackageTemplate(string $projectName): string
    {
        return <<<YAML
            # Quality Tools Configuration for $projectName Site Package
            quality-tools:
              project:
                name: "$projectName"
                php_version: "8.3"
                typo3_version: "13.4"

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
                  level: "typo3-13"

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

    private function getDistributionTemplate(string $projectName): string
    {
        return <<<YAML
            # Quality Tools Configuration for $projectName Distribution
            quality-tools:
              project:
                name: "$projectName"
                php_version: "8.3"
                typo3_version: "13.4"

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
                  level: "typo3-13"

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

    /**
     * Safely write configuration content to a file with proper validation.
     */
    private function writeConfigurationFile(string $configFile, string $content): void
    {
        try {
            // Use the FilesystemService for secure file writing
            $this->filesystemService->writeFile($configFile, $content);
        } catch (FileSystemException $e) {
            // Convert filesystem exceptions to configuration-specific exceptions
            throw new ConfigurationFileWriteException($e->getMessage(), $configFile, $e);
        }
    }
}

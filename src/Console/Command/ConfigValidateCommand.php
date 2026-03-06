<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Console\Command;

use Cpsit\QualityTools\Configuration\ConfigurationLoaderInterface;
use Cpsit\QualityTools\Service\ErrorHandler;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'config:validate',
    description: 'Validate YAML configuration file',
    help: 'This command validates the quality-tools.yaml configuration file against the schema.',
)]
final class ConfigValidateCommand extends BaseCommand
{
    private ?ErrorHandler $errorHandler = null;

    public function __construct(ConfigurationLoaderInterface $configurationLoader)
    {
        parent::__construct($configurationLoader);
    }

    #[\Override]
    protected function configure(): void
    {
        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $projectRoot = $this->getProjectRoot();

        // Check if YAML configuration exists
        $configFile = $this->configurationLoader->findConfigurationFile($projectRoot);
        if ($configFile === null) {
            $io->warning('No YAML configuration file found in project root.');
            $io->note([
                'Looked for:',
                '  - .quality-tools.yaml',
                '  - quality-tools.yaml',
                '  - quality-tools.yml',
                '',
                'Use "qt config:init" to create a configuration file.',
            ]);

            return self::SUCCESS;
        }

        $io->info(\sprintf('Validating configuration file: %s', $configFile));

        try {
            // Load and validate configuration
            $configuration = $this->configurationLoader->load($projectRoot);

            // Check config_file paths for each tool
            $warnings = $this->validateConfigFilePaths($configuration->toArray(), $projectRoot);

            $io->success('Configuration is valid.');

            if (!empty($warnings)) {
                $io->warning('Config file path issues (fallback to package defaults will be used):');
                foreach ($warnings as $warning) {
                    $io->writeln(\sprintf('  - %s', $warning));
                }
            }

            if ($output->isVerbose()) {
                $this->showConfigurationSummary($io, $configuration->toArray());
            }

            return self::SUCCESS;
        } catch (\Throwable $e) {
            return $this->getErrorHandler()->handleException($e, $output, $output->isVerbose());
        }
    }

    private function getErrorHandler(): ErrorHandler
    {
        if ($this->errorHandler === null) {
            $this->errorHandler = new ErrorHandler();
        }

        return $this->errorHandler;
    }

    /**
     * Validate that config_file paths reference existing files.
     *
     * @return string[] Warning messages for invalid paths
     */
    private function validateConfigFilePaths(array $config, string $projectRoot): array
    {
        $warnings = [];
        $tools = $config['quality-tools']['tools'] ?? [];

        foreach ($tools as $tool => $toolConfig) {
            if (!isset($toolConfig['config_file'])) {
                continue;
            }

            $configFile = $toolConfig['config_file'];

            // Skip absolute paths - these come from auto-discovery and are always valid
            if (str_starts_with($configFile, '/')) {
                continue;
            }

            $resolvedPath = $projectRoot . '/' . $configFile;

            if (!file_exists($resolvedPath)) {
                $warnings[] = \sprintf(
                    'Tool "%s": config_file "%s" does not exist',
                    $tool,
                    $configFile,
                );
            }
        }

        return $warnings;
    }

    private function showConfigurationSummary(SymfonyStyle $io, array $config): void
    {
        $io->section('Configuration Summary');

        $qualityTools = $config['quality-tools'] ?? [];

        // Project information
        $project = $qualityTools['project'] ?? [];
        $io->definitionList(
            ['Project Name' => $project['name'] ?? 'Not specified'],
            ['PHP Version' => $project['php_version'] ?? '8.3'],
            ['TYPO3 Version' => $project['typo3_version'] ?? '13.4'],
        );

        // Enabled tools
        $tools = $qualityTools['tools'] ?? [];
        $enabledTools = [];
        foreach ($tools as $tool => $config) {
            if ($config['enabled'] ?? true) {
                $enabledTools[] = $tool;
            }
        }

        if (!empty($enabledTools)) {
            $io->listing($enabledTools);
        } else {
            $io->note('All tools enabled by default');
        }

        // Scan paths
        $paths = $qualityTools['paths'] ?? [];
        if (!empty($paths['scan'])) {
            $io->writeln('<info>Scan Paths:</info>');
            foreach ($paths['scan'] as $path) {
                $io->writeln(\sprintf('  - %s', $path));
            }
        }
    }
}

<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Console\Command;

use Cpsit\QualityTools\Configuration\YamlConfigurationLoader;
use Cpsit\QualityTools\Service\ErrorHandler;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Yaml\Yaml;

final class ConfigValidateCommand extends BaseCommand
{
    private ?ErrorHandler $errorHandler = null;

    #[\Override]
    protected function configure(): void
    {
        $this
            ->setName('config:validate')
            ->setDescription('Validate YAML configuration file')
            ->setHelp('This command validates the quality-tools.yaml configuration file against the schema.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $projectRoot = $this->getProjectRoot();
        $loader = new YamlConfigurationLoader();

        // Check if YAML configuration exists
        $configFile = $loader->findConfigurationFile($projectRoot);
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
            $configuration = $loader->load($projectRoot);

            $io->success('Configuration is valid.');

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

<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Console\Command;

use Cpsit\QualityTools\Configuration\YamlConfigurationLoader;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Yaml\Yaml;

final class ConfigShowCommand extends BaseCommand
{
    protected function configure(): void
    {
        $this
            ->setName('config:show')
            ->setDescription('Show resolved configuration')
            ->setHelp('This command shows the resolved configuration after merging all sources.')
            ->addOption(
                'format',
                'f',
                InputOption::VALUE_REQUIRED,
                'Output format: yaml, json',
                'yaml'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $projectRoot = $this->getProjectRoot();
        $format = $input->getOption('format');

        if (!in_array($format, ['yaml', 'json'], true)) {
            $io->error('Format must be either "yaml" or "json".');
            return self::FAILURE;
        }

        try {
            $loader = new YamlConfigurationLoader();
            $configuration = $loader->load($projectRoot);
            $configData = $configuration->toArray();

            $io->title('Resolved Configuration');

            // Show configuration file sources if verbose
            if ($output->isVerbose()) {
                $this->showConfigurationSources($io, $loader, $projectRoot);
            }

            // Output configuration in requested format
            switch ($format) {
                case 'json':
                    $output->writeln(json_encode($configData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
                    break;

                case 'yaml':
                default:
                    $yamlOutput = Yaml::dump($configData, 4, 2, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK);
                    $output->writeln($yamlOutput);
                    break;
            }

            return self::SUCCESS;

        } catch (\Exception $e) {
            $io->error([
                'Failed to load configuration:',
                $e->getMessage(),
            ]);
            return self::FAILURE;
        }
    }

    private function showConfigurationSources(SymfonyStyle $io, YamlConfigurationLoader $loader, string $projectRoot): void
    {
        $io->section('Configuration Sources');

        $sources = [];

        // Check for global configuration
        $homeDir = getenv('HOME') ?: ($_SERVER['HOME'] ?? $_SERVER['USERPROFILE'] ?? '');
        if (!empty($homeDir)) {
            $globalConfig = $homeDir . '/.quality-tools.yaml';
            if (file_exists($globalConfig)) {
                $sources[] = sprintf('Global: %s', $globalConfig);
            }
        }

        // Check for project configuration
        $projectConfig = $loader->findConfigurationFile($projectRoot);
        if ($projectConfig !== null) {
            $sources[] = sprintf('Project: %s', $projectConfig);
        }

        // Show package defaults
        $sources[] = 'Package defaults (built-in)';

        if (!empty($sources)) {
            $io->listing($sources);
        } else {
            $io->note('Using package defaults only');
        }

        $io->newLine();
    }
}

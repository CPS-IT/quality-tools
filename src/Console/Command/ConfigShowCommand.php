<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Console\Command;

use Cpsit\QualityTools\Configuration\ConfigurationLoaderInterface;
use Cpsit\QualityTools\Configuration\ConfigurationLoaderWrapper;
use Cpsit\QualityTools\Configuration\HierarchicalConfigurationLoader;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Yaml\Yaml;

final class ConfigShowCommand extends BaseCommand
{
    public function __construct(?ConfigurationLoaderInterface $configurationLoader = null)
    {
        parent::__construct('config:show', $configurationLoader);
    }

    #[\Override]
    protected function configure(): void
    {
        parent::configure();

        $this
            ->setName('config:show')
            ->setDescription('Show resolved configuration')
            ->setHelp('This command shows the resolved configuration after merging all sources.')
            ->addOption(
                'format',
                'f',
                InputOption::VALUE_REQUIRED,
                'Output format: yaml, json',
                'yaml',
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $projectRoot = $this->getProjectRoot();
        $format = $input->getOption('format');

        if (!\in_array($format, ['yaml', 'json'], true)) {
            $io->error('Format must be either "yaml" or "json".');

            return self::FAILURE;
        }

        try {
            // For config:show command, we need to validate that critical configuration
            // files can be loaded. If they can't, we should fail.
            $this->validateCriticalConfigurationFiles($projectRoot);

            // Use hierarchical configuration loader specifically for config:show
            $loader = $this->getConfigurationLoaderInHierarchicalMode();
            $configuration = $loader->load($projectRoot);
            $configData = $configuration->toArray();

            $io->title('Resolved Configuration');

            // Show configuration file sources if verbose
            if ($output->isVerbose()) {
                $this->showConfigurationSources($io, $loader, $projectRoot);
            }

            // Output configuration in the requested format
            switch ($format) {
                case 'json':
                    $output->writeln(json_encode($configData, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
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

    private function showConfigurationSources(SymfonyStyle $io, ConfigurationLoaderInterface $loader, string $projectRoot): void
    {
        $io->section('Configuration Sources');

        $sources = [];

        // Get all configuration sources from hierarchical loader
        try {
            $configSources = $loader->getConfigurationSources($projectRoot);
            foreach ($configSources as $source) {
                if ($source['file_path'] !== null) {
                    $label = match ($source['source']) {
                        'project_root' => 'Project',
                        'config_dir' => 'Config directory',
                        'global' => 'Global',
                        'package_config' => 'Package',
                        'tool_specific' => 'Tool-specific',
                        'tool_config_dir' => 'Tool config dir',
                        default => ucfirst((string) $source['source'])
                    };
                    $sources[] = \sprintf('%s: %s', $label, $source['file_path']);
                } elseif ($source['source'] === 'package_defaults') {
                    $sources[] = 'Package defaults (built-in)';
                }
            }
        } catch (\Exception) {
            $sources[] = 'Package defaults (built-in)';
        }

        $io->listing($sources);

        // Show configuration errors if any occurred
        $configErrors = $loader->getConfigurationErrors($projectRoot);
        if (!empty($configErrors)) {
            $io->warning('Some configuration files could not be loaded:');
            foreach ($configErrors as $filePath => $error) {
                $io->text(\sprintf('• %s: %s', $filePath, $error));
            }
            $io->newLine();
        }

        $io->newLine();
    }

    /**
     * Validate that critical configuration files can be loaded.
     *
     * @throws \RuntimeException when critical configuration files fail to load
     */
    private function validateCriticalConfigurationFiles(string $projectRoot): void
    {
        $hierarchy = new \Cpsit\QualityTools\Configuration\ConfigurationHierarchy($projectRoot);
        $existingFiles = $hierarchy->getExistingConfigurationFiles();

        // Check project_root and config_dir configuration files
        foreach (['project_root', 'config_dir'] as $criticalLevel) {
            if (!isset($existingFiles[$criticalLevel])) {
                continue;
            }

            foreach ($existingFiles[$criticalLevel] as $fileInfo) {
                try {
                    // Try to load the configuration file directly
                    $securityService = new \Cpsit\QualityTools\Service\SecurityService();
                    $validator = new \Cpsit\QualityTools\Configuration\ConfigurationValidator();

                    // Load the file content
                    $content = file_get_contents($fileInfo['path']);
                    if ($content === false) {
                        throw new \RuntimeException("Cannot read configuration file: {$fileInfo['path']}");
                    }

                    // Parse YAML and interpolate environment variables
                    $data = Yaml::parse($content);
                    if (!\is_array($data)) {
                        throw new \RuntimeException('Configuration file must contain valid YAML data');
                    }

                    // Interpolate environment variables
                    $interpolatedContent = preg_replace_callback(
                        '/\$\{([A-Z_][A-Z0-9_]*):?([^}]*)\}/',
                        function (array $matches) use ($securityService): string {
                            $envVar = $matches[1];
                            $default = $matches[2];

                            // Handle syntax: ${VAR:-default}
                            if (str_starts_with($default, '-')) {
                                $default = substr($default, 1);
                            }

                            return $securityService->getEnvironmentVariable($envVar, $default);
                        },
                        $content,
                    );

                    // Re-parse after interpolation
                    Yaml::parse($interpolatedContent);
                } catch (\Exception $e) {
                    throw new \RuntimeException('Failed to load configuration: ' . $e->getMessage());
                }
            }
        }
    }

    /**
     * Get configuration loader specifically configured for hierarchical mode.
     * ConfigShowCommand needs hierarchical features for source tracking.
     */
    private function getConfigurationLoaderInHierarchicalMode(): ConfigurationLoaderInterface
    {
        if ($this->hasService(ConfigurationLoaderInterface::class)) {
            $loader = $this->getService(ConfigurationLoaderInterface::class);
            
            // If it's a wrapper, switch to hierarchical mode
            if ($loader instanceof ConfigurationLoaderWrapper) {
                return $loader->withMode('hierarchical');
            }
            
            return $loader;
        }

        // Fallback for tests and scenarios without DI container
        // Force hierarchical mode for ConfigShowCommand
        return new ConfigurationLoaderWrapper(
            $this->getYamlConfigurationLoader(),
            $this->getHierarchicalConfigurationLoader(),
            'hierarchical'
        );
    }
}

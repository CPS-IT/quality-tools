<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Console\Command;

use Cpsit\QualityTools\Configuration\Configuration;
use Cpsit\QualityTools\Configuration\ConfigurationValidator;
use Cpsit\QualityTools\Configuration\YamlConfigurationLoader;
use Cpsit\QualityTools\Console\QualityToolsApplication;
use Cpsit\QualityTools\DependencyInjection\ContainerAwareInterface;
use Cpsit\QualityTools\DependencyInjection\ContainerAwareTrait;
use Cpsit\QualityTools\Exception\VendorDirectoryNotFoundException;
use Cpsit\QualityTools\Service\CommandBuilder;
use Cpsit\QualityTools\Service\ErrorFactory;
use Cpsit\QualityTools\Service\ProcessEnvironmentPreparer;
use Cpsit\QualityTools\Service\ProcessExecutor;
use Cpsit\QualityTools\Service\SecurityService;
use Cpsit\QualityTools\Utility\MemoryCalculator;
use Cpsit\QualityTools\Utility\ProjectAnalyzer;
use Cpsit\QualityTools\Utility\ProjectMetrics;
use Cpsit\QualityTools\Utility\VendorDirectoryDetector;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

abstract class BaseCommand extends Command implements ContainerAwareInterface
{
    use ContainerAwareTrait;

    protected ?ProjectMetrics $projectMetrics = null;
    protected ?MemoryCalculator $memoryCalculator = null;
    protected ?string $cachedTargetPath = null;
    protected ?bool $cachedNoOptimization = null;
    protected ?Configuration $configuration = null;

    protected function configure(): void
    {
        $this
            ->addOption(
                'config',
                'c',
                InputOption::VALUE_REQUIRED,
                'Override default configuration file path',
            )
            ->addOption(
                'path',
                'p',
                InputOption::VALUE_REQUIRED,
                'Specify custom target paths (defaults to project root)',
            )
            ->addOption(
                'no-optimization',
                null,
                InputOption::VALUE_NONE,
                'Disable automatic optimization (use default settings)',
            );
        // Note: Optimization details are shown by default unless --no-optimization is used
    }

    protected function getProjectRoot(): string
    {
        $application = $this->getApplication();

        if (!$application instanceof QualityToolsApplication) {
            throw new \RuntimeException('Command must be run within QualityToolsApplication');
        }

        return $application->getProjectRoot();
    }

    protected function resolveConfigPath(string $configFile, ?string $customConfigPath = null): string
    {
        if ($customConfigPath !== null) {
            if (!file_exists($customConfigPath)) {
                throw ErrorFactory::configFileNotFound($customConfigPath, $customConfigPath);
            }

            return realpath($customConfigPath);
        }

        $vendorPath = $this->findVendorPath();
        $defaultConfigPath = $vendorPath . '/cpsit/quality-tools/config/' . $configFile;

        if (!file_exists($defaultConfigPath)) {
            throw ErrorFactory::configFileNotFound($defaultConfigPath);
        }

        return $defaultConfigPath;
    }

    protected function getVendorBinPath(): string
    {
        return $this->findVendorPath() . '/bin';
    }

    private function findVendorPath(): string
    {
        $projectRoot = $this->getProjectRoot();
        $detector = $this->getVendorDirectoryDetector();

        try {
            $vendorPath = $detector->detectVendorPath($projectRoot);

            // Validate that cpsit/quality-tools is installed in detected vendor directory
            if (!is_dir($vendorPath . '/cpsit/quality-tools')) {
                throw new \RuntimeException(\sprintf('cpsit/quality-tools package not found in detected vendor directory: %s. Please ensure the package is properly installed.', $vendorPath));
            }

            return $vendorPath;
        } catch (VendorDirectoryNotFoundException $e) {
            // Fallback to old hardcoded detection for backward compatibility
            $vendorPaths = [
                $projectRoot . '/app/vendor',  // TYPO3 with app/vendor structure
                $projectRoot . '/vendor',      // Standard composer structure
            ];

            foreach ($vendorPaths as $vendorPath) {
                if (is_dir($vendorPath) && is_dir($vendorPath . '/cpsit/quality-tools')) {
                    return $vendorPath;
                }
            }

            throw new \RuntimeException(\sprintf('Could not detect vendor directory. Automatic detection failed: %s. Also checked fallback paths: %s', $e->getMessage(), implode(', ', $vendorPaths)));
        }
    }

    /**
     * @throws \JsonException
     */
    protected function executeProcess(
        array $command,
        InputInterface $input,
        OutputInterface $output,
        ?string $memoryLimit = null,
        ?string $tool = null,
    ): int {
        $resolvedPaths = $tool !== null ? $this->getResolvedPathsForTool($input, $tool) : null;

        $environmentPreparer = $this->getProcessEnvironmentPreparer();
        $commandBuilder = $this->getCommandBuilder();
        $processExecutor = $this->getProcessExecutor();

        $environment = $environmentPreparer->prepareEnvironment($input, $memoryLimit, $tool, $resolvedPaths);
        $preparedCommand = $commandBuilder->prepareCommandWithMemoryLimit($command, $memoryLimit);

        return $processExecutor->executeProcess(
            $preparedCommand,
            $this->getProjectRoot(),
            $environment,
            $output,
        );
    }

    protected function getTargetPath(InputInterface $input): string
    {
        if ($this->cachedTargetPath === null) {
            $customPath = $input->getOption('path');

            if ($customPath !== null) {
                if (!is_dir($customPath)) {
                    throw ErrorFactory::directoryNotFound($customPath);
                }
                $this->cachedTargetPath = realpath($customPath);
            } else {
                $this->cachedTargetPath = $this->getProjectRoot();
            }
        }

        return $this->cachedTargetPath;
    }

    protected function isOptimizationDisabled(InputInterface $input): bool
    {
        if ($this->cachedNoOptimization === null) {
            $this->cachedNoOptimization = $input->getOption('no-optimization');
        }

        return $this->cachedNoOptimization;
    }

    protected function getProjectMetrics(InputInterface $input): ProjectMetrics
    {
        if ($this->projectMetrics === null) {
            $analyzer = $this->getProjectAnalyzer();
            $this->projectMetrics = $analyzer->analyzeProject($this->getTargetPath($input));
        }

        return $this->projectMetrics;
    }

    /**
     * Get aggregated project metrics across all resolved paths for a tool.
     */
    protected function getAggregatedProjectMetrics(InputInterface $input, string $tool): ProjectMetrics
    {
        $resolvedPaths = $this->getResolvedPathsForTool($input, $tool);

        if (empty($resolvedPaths)) {
            // Fall back to single path metrics
            return $this->getProjectMetrics($input);
        }

        $analyzer = $this->getProjectAnalyzer();
        $aggregatedMetrics = null;

        foreach ($resolvedPaths as $path) {
            if (!is_dir($path)) {
                continue;
            }

            $pathMetrics = $analyzer->analyzeProject($path);

            if ($aggregatedMetrics === null) {
                // The first path becomes the base
                $aggregatedMetrics = $pathMetrics;
            } else {
                // Aggregate metrics from subsequent paths
                $aggregatedMetrics = $this->mergeProjectMetrics($aggregatedMetrics, $pathMetrics);
            }
        }

        return $aggregatedMetrics ?? $this->getProjectMetrics($input);
    }

    /**
     * Merge two ProjectMetrics instances by adding their values.
     */
    private function mergeProjectMetrics(ProjectMetrics $base, ProjectMetrics $additional): ProjectMetrics
    {
        // Aggregate the metrics from both instances
        $mergedMetrics = [
            'php' => [
                'fileCount' => $base->getPhpFileCount() + $additional->getPhpFileCount(),
                'totalLines' => $base->getPhpLines() + $additional->getPhpLines(),
                'totalSize' => ($base->php['totalSize'] ?? 0) + ($additional->php['totalSize'] ?? 0),
                'avgComplexity' => $this->calculateAverageComplexity(
                    $base->php['avgComplexity'] ?? 0,
                    $base->getPhpFileCount(),
                    $additional->php['avgComplexity'] ?? 0,
                    $additional->getPhpFileCount(),
                ),
                'maxComplexity' => max($base->php['maxComplexity'] ?? 0, $additional->php['maxComplexity'] ?? 0),
            ],
            'yaml' => [
                'fileCount' => ($base->yaml['fileCount'] ?? 0) + ($additional->yaml['fileCount'] ?? 0),
                'totalLines' => ($base->yaml['totalLines'] ?? 0) + ($additional->yaml['totalLines'] ?? 0),
                'totalSize' => ($base->yaml['totalSize'] ?? 0) + ($additional->yaml['totalSize'] ?? 0),
                'avgComplexity' => 0,
                'maxComplexity' => 0,
            ],
            'json' => [
                'fileCount' => ($base->json['fileCount'] ?? 0) + ($additional->json['fileCount'] ?? 0),
                'totalLines' => ($base->json['totalLines'] ?? 0) + ($additional->json['totalLines'] ?? 0),
                'totalSize' => ($base->json['totalSize'] ?? 0) + ($additional->json['totalSize'] ?? 0),
                'avgComplexity' => 0,
                'maxComplexity' => 0,
            ],
            'xml' => [
                'fileCount' => ($base->xml['fileCount'] ?? 0) + ($additional->xml['fileCount'] ?? 0),
                'totalLines' => ($base->xml['totalLines'] ?? 0) + ($additional->xml['totalLines'] ?? 0),
                'totalSize' => ($base->xml['totalSize'] ?? 0) + ($additional->xml['totalSize'] ?? 0),
                'avgComplexity' => 0,
                'maxComplexity' => 0,
            ],
            'typoscript' => [
                'fileCount' => ($base->typoscript['fileCount'] ?? 0) + ($additional->typoscript['fileCount'] ?? 0),
                'totalLines' => ($base->typoscript['totalLines'] ?? 0) + ($additional->typoscript['totalLines'] ?? 0),
                'totalSize' => ($base->typoscript['totalSize'] ?? 0) + ($additional->typoscript['totalSize'] ?? 0),
                'avgComplexity' => 0,
                'maxComplexity' => 0,
            ],
            'other' => [
                'fileCount' => ($base->other['fileCount'] ?? 0) + ($additional->other['fileCount'] ?? 0),
                'totalLines' => ($base->other['totalLines'] ?? 0) + ($additional->other['totalLines'] ?? 0),
                'totalSize' => ($base->other['totalSize'] ?? 0) + ($additional->other['totalSize'] ?? 0),
                'avgComplexity' => 0,
                'maxComplexity' => 0,
            ],
        ];

        return new ProjectMetrics($mergedMetrics);
    }

    /**
     * Calculate weighted average complexity across two metrics sets.
     */
    private function calculateAverageComplexity(int $avg1, int $count1, int $avg2, int $count2): int
    {
        $totalCount = $count1 + $count2;
        if ($totalCount === 0) {
            return 0;
        }

        return (int) round(($avg1 * $count1 + $avg2 * $count2) / $totalCount);
    }

    protected function getMemoryCalculator(): MemoryCalculator
    {
        if ($this->memoryCalculator === null) {
            $this->memoryCalculator = new MemoryCalculator();
        }

        return $this->memoryCalculator;
    }

    protected function getOptimalMemoryLimit(InputInterface $input, string $tool = 'default'): string
    {
        if ($this->isOptimizationDisabled($input)) {
            return '128M';
        }

        // Use aggregated metrics for accurate memory calculation across all paths
        $metrics = $this->getAggregatedProjectMetrics($input, $tool);

        return $this->getMemoryCalculator()->calculateOptimalMemoryForTool($metrics, $tool);
    }

    protected function shouldEnableParallelProcessing(InputInterface $input, string $tool = 'default'): bool
    {
        if ($this->isOptimizationDisabled($input)) {
            return false;
        }

        // Use aggregated metrics for parallel processing decision across all paths
        $metrics = $this->getAggregatedProjectMetrics($input, $tool);

        return $this->getMemoryCalculator()->shouldEnableParallelProcessing($metrics);
    }

    protected function showOptimizationDetails(InputInterface $input, OutputInterface $output, string $tool = 'default'): void
    {
        // Get all resolved paths for this tool
        $resolvedPaths = $this->getResolvedPathsForTool($input, $tool);

        if (!empty($resolvedPaths)) {
            $output->writeln(\sprintf('<comment>Analyzing %d configured paths:</comment>', \count($resolvedPaths)));
            foreach ($resolvedPaths as $i => $path) {
                $output->writeln(\sprintf('  [%d] %s', $i + 1, $path));
            }
        } else {
            $output->writeln('<comment>Using default path discovery</comment>');
        }

        // Use aggregated metrics across all paths for accurate optimization
        $metrics = $this->getAggregatedProjectMetrics($input, $tool);
        $calculator = $this->getMemoryCalculator();
        $profile = $calculator->getOptimizationProfile($metrics);

        $output->writeln('<comment>Aggregated Project Analysis (across all paths):</comment>');
        $output->writeln(\sprintf(
            '  Total project size: %s (%d files, %d lines)',
            $profile['projectSize'],
            $metrics->getTotalFileCount(),
            $metrics->getTotalLines(),
        ));
        $output->writeln(\sprintf(
            '  Total PHP files: %d (combined complexity score: %d)',
            $metrics->getPhpFileCount(),
            $metrics->getPhpComplexityScore(),
        ));

        if (!$this->isOptimizationDisabled($input)) {
            $output->writeln('<comment>Optimization Profile (based on total workload):</comment>');
            $output->writeln(\sprintf('  Memory limit: %s', $profile['memoryLimit']));

            if ($calculator->supportsParallelProcessing($tool)) {
                $output->writeln(\sprintf('  Parallel processing: %s', $profile['parallelProcessing'] ? 'enabled' : 'disabled'));
            } else {
                $output->writeln('  Parallel processing: not supported by this tool');
            }

            $output->writeln(\sprintf('  Progress indicator: %s', $profile['progressIndicator'] ? 'enabled' : 'disabled'));
            $output->writeln(\sprintf('  Tool-specific memory: %s', $calculator->calculateOptimalMemoryForTool($metrics, $tool)));

            if (!empty($profile['recommendations'])) {
                $output->writeln('<comment>Recommendations:</comment>');
                foreach ($profile['recommendations'] as $recommendation) {
                    $output->writeln(\sprintf('  - %s', $recommendation));
                }
            }
        } else {
            $output->writeln('<comment>Optimization disabled by --no-optimization flag</comment>');
        }

        $output->writeln('');
    }

    protected function getTargetPathForTool(InputInterface $input, string $tool): string
    {
        if ($this->cachedTargetPath === null) {
            $customPath = $input->getOption('path');
            if ($customPath !== null) {
                if (!is_dir($customPath)) {
                    throw ErrorFactory::directoryNotFound($customPath);
                }
                $this->cachedTargetPath = realpath($customPath);
            } else {
                // Use configuration-based path resolution
                $configuration = $this->getConfiguration($input);
                $resolvedPaths = $configuration->getResolvedPathsForTool($tool);

                if (!empty($resolvedPaths)) {
                    // Use the first resolved path as the target for compatibility
                    $this->cachedTargetPath = $resolvedPaths[0];
                } else {
                    // Fall back to the project root
                    $this->cachedTargetPath = $this->getProjectRoot();
                }
            }
        }

        return $this->cachedTargetPath;
    }

    /**
     * Get all resolved paths for a tool (for tools that can handle multiple paths).
     */
    protected function getResolvedPathsForTool(InputInterface $input, string $tool): array
    {
        $customPath = $input->getOption('path');
        if ($customPath !== null) {
            if (!is_dir($customPath)) {
                throw new \InvalidArgumentException(\sprintf('Target path does not exist or is not a directory: %s', $customPath));
            }

            return [realpath($customPath)];
        }

        // Use configuration-based path resolution
        $configuration = $this->getConfiguration($input);
        $resolvedPaths = $configuration->getResolvedPathsForTool($tool);

        if (!empty($resolvedPaths)) {
            return $resolvedPaths;
        }

        // Fall back to the project root
        return [$this->getProjectRoot()];
    }

    protected function getConfiguration(InputInterface $input): Configuration
    {
        if ($this->configuration === null) {
            $projectRoot = $this->getProjectRoot();
            $loader = $this->getYamlConfigurationLoader();
            $this->configuration = $loader->load($projectRoot);

            // Override with a custom config path if provided
            $customConfigPath = $input->getOption('config');
            if ($customConfigPath && file_exists($customConfigPath)) {
                // For now, we'll use the loaded configuration
                // TODO: Implement config override logic if needed
            }
        }

        return $this->configuration;
    }

    /**
     * Service getters for dependency injection with fallback for testing.
     */
    private function getVendorDirectoryDetector(): VendorDirectoryDetector
    {
        if ($this->hasService(VendorDirectoryDetector::class)) {
            return $this->getService(VendorDirectoryDetector::class);
        }

        return new VendorDirectoryDetector();
    }

    private function getProcessEnvironmentPreparer(): ProcessEnvironmentPreparer
    {
        if ($this->hasService(ProcessEnvironmentPreparer::class)) {
            return $this->getService(ProcessEnvironmentPreparer::class);
        }

        return new ProcessEnvironmentPreparer();
    }

    private function getCommandBuilder(): CommandBuilder
    {
        if ($this->hasService(CommandBuilder::class)) {
            return $this->getService(CommandBuilder::class);
        }

        return new CommandBuilder();
    }

    private function getProcessExecutor(): ProcessExecutor
    {
        if ($this->hasService(ProcessExecutor::class)) {
            return $this->getService(ProcessExecutor::class);
        }

        return new ProcessExecutor();
    }

    private function getProjectAnalyzer(): ProjectAnalyzer
    {
        if ($this->hasService(ProjectAnalyzer::class)) {
            return $this->getService(ProjectAnalyzer::class);
        }

        return new ProjectAnalyzer();
    }

    protected function getYamlConfigurationLoader(): YamlConfigurationLoader
    {
        if ($this->hasService(YamlConfigurationLoader::class)) {
            return $this->getService(YamlConfigurationLoader::class);
        }

        // Fallback for tests and scenarios without DI container
        return new YamlConfigurationLoader(
            new ConfigurationValidator(),
            new SecurityService(),
        );
    }
}

<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Console\Command;

use Cpsit\QualityTools\Console\QualityToolsApplication;
use Cpsit\QualityTools\Configuration\Configuration;
use Cpsit\QualityTools\Configuration\YamlConfigurationLoader;
use Cpsit\QualityTools\Exception\VendorDirectoryNotFoundException;
use Cpsit\QualityTools\Utility\MemoryCalculator;
use Cpsit\QualityTools\Utility\ProjectAnalyzer;
use Cpsit\QualityTools\Utility\ProjectMetrics;
use Cpsit\QualityTools\Utility\VendorDirectoryDetector;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

abstract class BaseCommand extends Command
{
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
                'Override default configuration file path'
            )
            ->addOption(
                'path',
                'p',
                InputOption::VALUE_REQUIRED,
                'Specify custom target paths (defaults to project root)'
            )
            ->addOption(
                'no-optimization',
                null,
                InputOption::VALUE_NONE,
                'Disable automatic optimization (use default settings)'
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
                throw new \InvalidArgumentException(
                    sprintf('Custom configuration file not found: %s', $customConfigPath)
                );
            }
            return realpath($customConfigPath);
        }

        $vendorPath = $this->findVendorPath();
        $defaultConfigPath = $vendorPath . '/cpsit/quality-tools/config/' . $configFile;

        if (!file_exists($defaultConfigPath)) {
            throw new \RuntimeException(
                sprintf(
                    'Default configuration file not found: %s. Please ensure cpsit/quality-tools is properly installed.',
                    $defaultConfigPath
                )
            );
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
        $detector = new VendorDirectoryDetector();

        try {
            $vendorPath = $detector->detectVendorPath($projectRoot);

            // Validate that cpsit/quality-tools is installed in detected vendor directory
            if (!is_dir($vendorPath . '/cpsit/quality-tools')) {
                throw new \RuntimeException(
                    sprintf(
                        'cpsit/quality-tools package not found in detected vendor directory: %s. Please ensure the package is properly installed.',
                        $vendorPath
                    )
                );
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

            throw new \RuntimeException(
                sprintf(
                    'Could not detect vendor directory. Automatic detection failed: %s. Also checked fallback paths: %s',
                    $e->getMessage(),
                    implode(', ', $vendorPaths)
                )
            );
        }
    }

    protected function executeProcess(
        array $command,
        InputInterface $input,
        OutputInterface $output,
        ?string $memoryLimit = null,
        ?string $tool = null
    ): int {
        $process = new Process($command, $this->getProjectRoot());

        // Prepare environment variables
        $env = $_SERVER;

        // Set memory limit if specified
        if ($memoryLimit !== null) {
            $env['PHP_MEMORY_LIMIT'] = $memoryLimit;

            // Prepend php with memory limit to the command if it's a php script
            $executable = basename($command[0]);
            if (str_contains($executable, 'php') || str_ends_with($command[0], '.php') || str_ends_with($command[0], '.phar')) {
                $originalCommand = $command;
                $command = array_merge(['php', '-d', 'memory_limit=' . $memoryLimit], $originalCommand);
            }
        }

        // Set dynamic paths environment variable for configuration-based tools like Fractor
        // Other tools (Rector, PHP-CS-Fixer) receive paths as direct command arguments
        if ($tool === 'fractor' && !$input->hasParameterOption('--path')) {
            $resolvedPaths = $this->getResolvedPathsForTool($input, $tool);
            $env['QT_DYNAMIC_PATHS'] = json_encode($resolvedPaths);
        }

        $process = new Process($command, $this->getProjectRoot(), $env);

        // Handle verbose mode using Symfony's built-in verbose levels
        if ($output->isVerbose()) {
            $output->writeln(sprintf('<info>Executing: %s</info>', $process->getCommandLine()));
        }

        $process->run(function (string $type, string $buffer) use ($output): void {
            // Forward output from the process (Symfony handles quiet mode automatically)
            if ($type === Process::ERR) {
                // Check if output supports getErrorOutput() method (ConsoleOutputInterface)
                if (method_exists($output, 'getErrorOutput')) {
                    $output->getErrorOutput()->write($buffer);
                } else {
                    // For outputs that don't support error output (like StreamOutput), write to main output
                    $output->write($buffer);
                }
            } else {
                $output->write($buffer);
            }
        });

        return $process->getExitCode() ?? 1;
    }

    protected function getTargetPath(InputInterface $input): string
    {
        if ($this->cachedTargetPath === null) {
            $customPath = $input->getOption('path');

            if ($customPath !== null) {
                if (!is_dir($customPath)) {
                    throw new \InvalidArgumentException(
                        sprintf('Target path does not exist or is not a directory: %s', $customPath)
                    );
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

    protected function getProjectMetrics(InputInterface $input, OutputInterface $output): ProjectMetrics
    {
        if ($this->projectMetrics === null) {
            $analyzer = new ProjectAnalyzer();
            $this->projectMetrics = $analyzer->analyzeProject($this->getTargetPath($input));
        }

        return $this->projectMetrics;
    }

    /**
     * Get aggregated project metrics across all resolved paths for a tool
     */
    protected function getAggregatedProjectMetrics(InputInterface $input, OutputInterface $output, string $tool): ProjectMetrics
    {
        $resolvedPaths = $this->getResolvedPathsForTool($input, $tool);
        
        if (empty($resolvedPaths)) {
            // Fall back to single path metrics
            return $this->getProjectMetrics($input, $output);
        }

        $analyzer = new ProjectAnalyzer();
        $aggregatedMetrics = null;
        
        foreach ($resolvedPaths as $path) {
            if (!is_dir($path)) {
                continue;
            }
            
            $pathMetrics = $analyzer->analyzeProject($path);
            
            if ($aggregatedMetrics === null) {
                // First path becomes the base
                $aggregatedMetrics = $pathMetrics;
            } else {
                // Aggregate metrics from subsequent paths
                $aggregatedMetrics = $this->mergeProjectMetrics($aggregatedMetrics, $pathMetrics);
            }
        }
        
        return $aggregatedMetrics ?? $this->getProjectMetrics($input, $output);
    }

    /**
     * Merge two ProjectMetrics instances by adding their values
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
                    $base->php['avgComplexity'] ?? 0, $base->getPhpFileCount(),
                    $additional->php['avgComplexity'] ?? 0, $additional->getPhpFileCount()
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
     * Calculate weighted average complexity across two metrics sets
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

    protected function getOptimalMemoryLimit(InputInterface $input, OutputInterface $output, string $tool = 'default'): string
    {
        if ($this->isOptimizationDisabled($input)) {
            return '128M';
        }

        // Use aggregated metrics for accurate memory calculation across all paths
        $metrics = $this->getAggregatedProjectMetrics($input, $output, $tool);
        $calculator = $this->getMemoryCalculator();

        return $calculator->calculateOptimalMemoryForTool($metrics, $tool);
    }

    protected function shouldEnableParallelProcessing(InputInterface $input, OutputInterface $output, string $tool = 'default'): bool
    {
        if ($this->isOptimizationDisabled($input)) {
            return false;
        }

        // Use aggregated metrics for parallel processing decision across all paths
        $metrics = $this->getAggregatedProjectMetrics($input, $output, $tool);
        $calculator = $this->getMemoryCalculator();

        return $calculator->shouldEnableParallelProcessing($metrics);
    }

    protected function showOptimizationDetails(InputInterface $input, OutputInterface $output, string $tool = 'default'): void
    {
        // Get all resolved paths for this tool
        $resolvedPaths = $this->getResolvedPathsForTool($input, $tool);

        if (!empty($resolvedPaths)) {
            $output->writeln(sprintf('<comment>Analyzing %d configured paths:</comment>', count($resolvedPaths)));
            foreach ($resolvedPaths as $i => $path) {
                $output->writeln(sprintf('  [%d] %s', $i + 1, $path));
            }
        } else {
            $output->writeln('<comment>Using default path discovery</comment>');
        }

        // Use aggregated metrics across all paths for accurate optimization
        $metrics = $this->getAggregatedProjectMetrics($input, $output, $tool);
        $calculator = $this->getMemoryCalculator();
        $profile = $calculator->getOptimizationProfile($metrics);

        $output->writeln('<comment>Aggregated Project Analysis (across all paths):</comment>');
        $output->writeln(sprintf(
            '  Total project size: %s (%d files, %d lines)',
            $profile['projectSize'],
            $metrics->getTotalFileCount(),
            $metrics->getTotalLines()
        ));
        $output->writeln(sprintf(
            '  Total PHP files: %d (combined complexity score: %d)',
            $metrics->getPhpFileCount(),
            $metrics->getPhpComplexityScore()
        ));

        if (!$this->isOptimizationDisabled($input)) {
            $output->writeln('<comment>Optimization Profile (based on total workload):</comment>');
            $output->writeln(sprintf('  Memory limit: %s', $profile['memoryLimit']));

            if ($calculator->supportsParallelProcessing($tool)) {
                $output->writeln(sprintf('  Parallel processing: %s', $profile['parallelProcessing'] ? 'enabled' : 'disabled'));
            } else {
                $output->writeln('  Parallel processing: not supported by this tool');
            }

            $output->writeln(sprintf('  Progress indicator: %s', $profile['progressIndicator'] ? 'enabled' : 'disabled'));
            $output->writeln(sprintf('  Tool-specific memory: %s', $calculator->calculateOptimalMemoryForTool($metrics, $tool)));

            if (!empty($profile['recommendations'])) {
                $output->writeln('<comment>Recommendations:</comment>');
                foreach ($profile['recommendations'] as $recommendation) {
                    $output->writeln(sprintf('  - %s', $recommendation));
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
                    throw new \InvalidArgumentException(
                        sprintf('Target path does not exist or is not a directory: %s', $customPath)
                    );
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
                    // Fall back to project root
                    $this->cachedTargetPath = $this->getProjectRoot();
                }
            }
        }
        return $this->cachedTargetPath;
    }

    /**
     * Get all resolved paths for a tool (for tools that can handle multiple paths)
     */
    protected function getResolvedPathsForTool(InputInterface $input, string $tool): array
    {
        $customPath = $input->getOption('path');
        if ($customPath !== null) {
            if (!is_dir($customPath)) {
                throw new \InvalidArgumentException(
                    sprintf('Target path does not exist or is not a directory: %s', $customPath)
                );
            }
            return [realpath($customPath)];
        }

        // Use configuration-based path resolution
        $configuration = $this->getConfiguration($input);
        $resolvedPaths = $configuration->getResolvedPathsForTool($tool);

        if (!empty($resolvedPaths)) {
            return $resolvedPaths;
        }

        // Fall back to project root
        return [$this->getProjectRoot()];
    }

    protected function getConfiguration(InputInterface $input): Configuration
    {
        if ($this->configuration === null) {
            $projectRoot = $this->getProjectRoot();
            $loader = new YamlConfigurationLoader();
            $this->configuration = $loader->load($projectRoot);

            // Override with custom config path if provided
            $customConfigPath = $input->getOption('config');
            if ($customConfigPath && file_exists($customConfigPath)) {
                // For now, we'll use the loaded configuration
                // TODO: Implement config override logic if needed
            }
        }
        return $this->configuration;
    }
}

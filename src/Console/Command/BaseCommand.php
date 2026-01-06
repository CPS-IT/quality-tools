<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Console\Command;

use Cpsit\QualityTools\Console\QualityToolsApplication;
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
            )
            ->addOption(
                'show-optimization',
                null,
                InputOption::VALUE_NONE,
                'Show optimization details and project analysis'
            );
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
        ?string $memoryLimit = null
    ): int {
        $process = new Process($command, $this->getProjectRoot());

        // Set memory limit if specified
        if ($memoryLimit !== null) {
            $env = $_SERVER;
            $env['PHP_MEMORY_LIMIT'] = $memoryLimit;

            // Prepend php with memory limit to the command if it's a php script
            $executable = basename($command[0]);
            if (str_contains($executable, 'php') || str_ends_with($command[0], '.php') || str_ends_with($command[0], '.phar')) {
                $originalCommand = $command;
                $command = array_merge(['php', '-d', 'memory_limit=' . $memoryLimit], $originalCommand);
                $process = new Process($command, $this->getProjectRoot(), $env);
            } else {
                $process->setEnv($env);
            }
        }

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

        $metrics = $this->getProjectMetrics($input, $output);
        $calculator = $this->getMemoryCalculator();

        return $calculator->calculateOptimalMemoryForTool($metrics, $tool);
    }

    protected function shouldEnableParallelProcessing(InputInterface $input, OutputInterface $output): bool
    {
        if ($this->isOptimizationDisabled($input)) {
            return false;
        }

        $metrics = $this->getProjectMetrics($input, $output);
        $calculator = $this->getMemoryCalculator();

        return $calculator->shouldEnableParallelProcessing($metrics);
    }

    protected function showOptimizationDetails(InputInterface $input, OutputInterface $output, string $tool = 'default'): void
    {
        $targetPath = $this->getTargetPath($input);
        $output->writeln(sprintf('<comment>Analyzing target directory: %s</comment>', $targetPath));

        $metrics = $this->getProjectMetrics($input, $output);
        $calculator = $this->getMemoryCalculator();
        $profile = $calculator->getOptimizationProfile($metrics);

        $output->writeln('<comment>Project Analysis:</comment>');
        $output->writeln(sprintf(
            '  Project size: %s (%d files, %d lines)',
            $profile['projectSize'],
            $metrics->getTotalFileCount(),
            $metrics->getTotalLines()
        ));
        $output->writeln(sprintf(
            '  PHP files: %d (complexity score: %d)',
            $metrics->getPhpFileCount(),
            $metrics->getPhpComplexityScore()
        ));

        if (!$this->isOptimizationDisabled($input)) {
            $output->writeln('<comment>Optimization Profile:</comment>');
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
}

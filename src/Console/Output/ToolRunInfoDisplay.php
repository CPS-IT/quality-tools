<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Console\Output;

use Cpsit\QualityTools\Tool\Runner\DTO\ToolRunDescription;
use Cpsit\QualityTools\Utility\MemoryCalculator;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Renders pre-run information for tool commands.
 *
 * Displays config path, target paths, project analysis metrics,
 * and optimization profile before the tool actually executes.
 */
final readonly class ToolRunInfoDisplay
{
    public function __construct(
        private MemoryCalculator $memoryCalculator,
    ) {
    }

    public function display(
        ToolRunDescription $description,
        OutputInterface $output,
        bool $optimizationDisabled = false,
    ): void {
        $this->displayTargetPaths($description, $output);
        $this->displayProjectAnalysis($description, $output);
        $this->displayOptimizationProfile($description, $output, $optimizationDisabled);
        $output->writeln('');
    }

    private function displayTargetPaths(ToolRunDescription $description, OutputInterface $output): void
    {
        $paths = $description->targetPaths;
        if ($paths !== []) {
            $output->writeln(\sprintf('<comment>Analyzing %d configured paths:</comment>', \count($paths)));
            foreach ($paths as $i => $path) {
                $output->writeln(\sprintf('  [%d] %s', $i + 1, $path));
            }
        } else {
            $output->writeln('<comment>Using default path discovery</comment>');
        }
    }

    private function displayProjectAnalysis(ToolRunDescription $description, OutputInterface $output): void
    {
        $metrics = $description->metrics;
        if ($metrics === null) {
            return;
        }

        $profile = $this->memoryCalculator->getOptimizationProfile($metrics);

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
    }

    private function displayOptimizationProfile(
        ToolRunDescription $description,
        OutputInterface $output,
        bool $optimizationDisabled,
    ): void {
        if ($optimizationDisabled) {
            $output->writeln('<comment>Optimization disabled by --no-optimization flag</comment>');

            return;
        }

        $metrics = $description->metrics;
        if ($metrics === null) {
            return;
        }

        $profile = $this->memoryCalculator->getOptimizationProfile($metrics);
        $toolName = $description->toolName;

        $output->writeln('<comment>Optimization Profile (based on total workload):</comment>');
        $output->writeln(\sprintf('  Memory limit: %s', $profile['memoryLimit']));

        if ($this->memoryCalculator->supportsParallelProcessing($toolName)) {
            $output->writeln(\sprintf(
                '  Parallel processing: %s',
                $profile['parallelProcessing'] ? 'enabled' : 'disabled',
            ));
        } else {
            $output->writeln('  Parallel processing: not supported by this tool');
        }

        $output->writeln(\sprintf('  Progress indicator: %s', $profile['progressIndicator'] ? 'enabled' : 'disabled'));
        $output->writeln(\sprintf(
            '  Tool-specific memory: %s',
            $this->memoryCalculator->calculateOptimalMemoryForTool($metrics, $toolName),
        ));

        if (!empty($profile['recommendations'])) {
            $output->writeln('<comment>Recommendations:</comment>');
            foreach ($profile['recommendations'] as $recommendation) {
                $output->writeln(\sprintf('  - %s', $recommendation));
            }
        }
    }
}

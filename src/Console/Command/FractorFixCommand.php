<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Console\Command;

use Cpsit\QualityTools\Utility\YamlValidator;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class FractorFixCommand extends BaseCommand
{
    protected function getTargetPath(InputInterface $input): string
    {
        return $this->getTargetPathForTool($input, 'fractor');
    }

    protected function configure(): void
    {
        parent::configure();

        $this
            ->setName('fix:fractor')
            ->setDescription('Run Fractor to apply TypoScript and code changes')
            ->setHelp(
                'This command runs Fractor to apply TypoScript and code changes to your files. ' .
                'This will modify your files! Use --config to specify a custom configuration ' .
                'file or --path to target specific directories.'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            // Show optimization details by default unless disabled
            if (!$this->isOptimizationDisabled($input)) {
                $this->showOptimizationDetails($input, $output, 'fractor');
            }

            $configPath = $this->resolveConfigPath('fractor.php', $input->getOption('config'));

            // Get all target paths for YAML validation
            $customPath = $input->getOption('path');
            if ($customPath !== null) {
                if (!is_dir($customPath)) {
                    throw new \InvalidArgumentException(
                        sprintf('Target path does not exist or is not a directory: %s', $customPath)
                    );
                }
                $targetPaths = [realpath($customPath)];
            } else {
                // Use all resolved paths for YAML validation
                $targetPaths = $this->getResolvedPathsForTool($input, 'fractor');
                if (empty($targetPaths)) {
                    $targetPaths = [$this->getProjectRoot()];
                }
            }

            // Perform YAML validation on all target paths before running Fractor
            $yamlValidation = $this->validateYamlFiles($input, $output, $targetPaths);

            $command = [
                $this->getVendorBinPath() . '/fractor',
                'process',
                '--config=' . $configPath,
            ];

            // Only add target path if user provided a custom path via --path option
            // Otherwise, let Fractor use paths from configuration file (which will use resolved paths via environment variable)
            if ($customPath !== null) {
                $command[] = $targetPaths[0]; // Use the resolved custom path
                if ($output->isVerbose()) {
                    $output->writeln(sprintf('<comment>Analyzing custom path: %s</comment>', $customPath));
                }
            } else {
                if ($output->isVerbose()) {
                    $output->writeln(sprintf('<comment>Analyzing resolved paths via configuration: %s</comment>', implode(', ', $targetPaths)));
                }
            }

            // Get optimal memory limit for automatic optimization
            if (!$this->isOptimizationDisabled($input)) {
                $optimalMemory = $this->getOptimalMemoryLimit($input, $output, 'fractor');
                $exitCode = $this->executeProcess($command, $input, $output, $optimalMemory, 'fractor');
            } else {
                $exitCode = $this->executeProcess($command, $input, $output, null, 'fractor');
            }

            // Show YAML validation summary if there were issues
            if (!empty($yamlValidation['invalid'])) {
                $this->showYamlValidationSummary($output, $yamlValidation);
            }

            return $exitCode;

        } catch (\Exception $e) {
            $output->writeln(sprintf('<error>Error: %s</error>', $e->getMessage()));
            return 1;
        }
    }

    /**
     * Validate YAML files before Fractor processing.
     */
    private function validateYamlFiles(InputInterface $input, OutputInterface $output, array $targetPaths): array
    {
        if ($this->isOptimizationDisabled($input)) {
            return ['valid' => [], 'invalid' => [], 'summary' => []];
        }

        $output->writeln('<comment>Pre-validating YAML files across all target paths...</comment>');

        $validator = new YamlValidator();
        $combinedResults = ['valid' => [], 'invalid' => [], 'summary' => ['total' => 0, 'valid' => 0, 'invalid' => 0]];

        foreach ($targetPaths as $targetPath) {
            if (!is_dir($targetPath)) {
                continue;
            }
            
            $output->writeln(sprintf('<comment>  Validating YAML files in: %s</comment>', $targetPath));
            $results = $validator->validateYamlFiles($targetPath);
            
            // Merge results
            $combinedResults['valid'] = array_merge($combinedResults['valid'], $results['valid']);
            $combinedResults['invalid'] = array_merge($combinedResults['invalid'], $results['invalid']);
            $combinedResults['summary']['total'] += $results['summary']['total'];
            $combinedResults['summary']['valid'] += $results['summary']['valid'];
            $combinedResults['summary']['invalid'] += $results['summary']['invalid'];
        }

        if ($combinedResults['summary']['invalid'] > 0) {
            $output->writeln(sprintf(
                '<comment>Found %d problematic YAML files across all paths (will be processed with error recovery)</comment>',
                $combinedResults['summary']['invalid']
            ));
        } else {
            $output->writeln('<info>All YAML files validated successfully across all paths</info>');
        }

        $output->writeln('');

        return $combinedResults;
    }

    /**
     * Show summary of YAML validation issues.
     */
    private function showYamlValidationSummary(OutputInterface $output, array $validationResults): void
    {
        $output->writeln('');
        $output->writeln('<comment>YAML Validation Summary:</comment>');
        $output->writeln(sprintf('  Total files: %d', $validationResults['summary']['total']));
        $output->writeln(sprintf('  Valid files: %d', $validationResults['summary']['valid']));
        $output->writeln(sprintf('  Problematic files: %d', $validationResults['summary']['invalid']));

        if (!empty($validationResults['invalid'])) {
            $output->writeln('');
            $output->writeln('<comment>Problematic YAML files:</comment>');

            $validator = new YamlValidator();
            $summary = $validator->getProblematicFilesSummary($validationResults);

            foreach ($summary as $issue) {
                $output->writeln(sprintf('  - %s', $issue));
            }

            $output->writeln('');
            $output->writeln('<info>These files may need manual review and correction.</info>');
            $output->writeln('<info>Fractor will attempt to process other files despite these issues.</info>');
        }
    }
}

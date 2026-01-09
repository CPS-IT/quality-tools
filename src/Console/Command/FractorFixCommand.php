<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Console\Command;

use Cpsit\QualityTools\Utility\YamlValidator;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class FractorFixCommand extends AbstractToolCommand
{
    private array $yamlValidationResults = [];

    #[\Override]
    protected function configure(): void
    {
        parent::configure();

        $this
            ->setName('fix:fractor')
            ->setDescription('Run Fractor to apply TypoScript and code changes')
            ->setHelp(
                'This command runs Fractor to apply TypoScript and code changes to your files. ' .
                'This will modify your files! Use --config to specify a custom configuration ' .
                'file or --path to target specific directories.',
            );
    }

    protected function getToolName(): string
    {
        return 'fractor';
    }

    protected function getDefaultConfigFileName(): string
    {
        return 'fractor.php';
    }

    #[\Override]
    protected function resolveTargetPaths(InputInterface $input, OutputInterface $output): array
    {
        $targetPaths = parent::resolveTargetPaths($input, $output);

        // If no paths resolved, use project root for Fractor
        if (empty($targetPaths)) {
            $targetPaths = [$this->getProjectRoot()];
        }

        return $targetPaths;
    }

    #[\Override]
    protected function executePreProcessingHooks(InputInterface $input, OutputInterface $output, array $targetPaths): void
    {
        // Perform YAML validation before running Fractor
        $this->yamlValidationResults = $this->validateYamlFiles($input, $output, $targetPaths);
    }

    protected function buildToolCommand(
        InputInterface $input,
        OutputInterface $output,
        string $configPath,
        array $targetPaths,
    ): array {
        $command = [
            $this->getVendorBinPath() . '/fractor',
            'process',
            '--config=' . $configPath,
        ];

        // Only add target path if user provided a custom path via --path option
        // Otherwise, let Fractor use paths from configuration file
        $customPath = $input->getOption('path');
        if ($customPath !== null && !empty($targetPaths)) {
            $command[] = $targetPaths[0]; // Use the resolved custom path
        }

        return $command;
    }

    #[\Override]
    protected function executePostProcessingHooks(InputInterface $input, OutputInterface $output, int $exitCode): void
    {
        // Show YAML validation summary if there were issues
        if (!empty($this->yamlValidationResults['invalid'])) {
            $this->showYamlValidationSummary($output, $this->yamlValidationResults);
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

            $output->writeln(\sprintf('<comment>  Validating YAML files in: %s</comment>', $targetPath));
            $results = $validator->validateYamlFiles($targetPath);

            // Merge results
            $combinedResults['valid'] = array_merge($combinedResults['valid'], $results['valid']);
            $combinedResults['invalid'] = array_merge($combinedResults['invalid'], $results['invalid']);
            $combinedResults['summary']['total'] += $results['summary']['total'];
            $combinedResults['summary']['valid'] += $results['summary']['valid'];
            $combinedResults['summary']['invalid'] += $results['summary']['invalid'];
        }

        if ($combinedResults['summary']['invalid'] > 0) {
            $output->writeln(\sprintf(
                '<comment>Found %d problematic YAML files across all paths (will be processed with error recovery)</comment>',
                $combinedResults['summary']['invalid'],
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
        $output->writeln(\sprintf('  Total files: %d', $validationResults['summary']['total']));
        $output->writeln(\sprintf('  Valid files: %d', $validationResults['summary']['valid']));
        $output->writeln(\sprintf('  Problematic files: %d', $validationResults['summary']['invalid']));

        if (!empty($validationResults['invalid'])) {
            $output->writeln('');
            $output->writeln('<comment>Problematic YAML files:</comment>');

            $validator = new YamlValidator();
            $summary = $validator->getProblematicFilesSummary($validationResults);

            foreach ($summary as $issue) {
                $output->writeln(\sprintf('  - %s', $issue));
            }

            $output->writeln('');
            $output->writeln('<info>These files may need manual review and correction.</info>');
            $output->writeln('<info>Fractor processed other files successfully despite these issues.</info>');
        }
    }
}

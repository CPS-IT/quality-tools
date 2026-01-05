<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Console\Command;

use Cpsit\QualityTools\Utility\YamlValidator;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class FractorLintCommand extends BaseCommand
{
    protected function getTargetPath(InputInterface $input): string
    {
        // If user specified a custom path, use it
        $customPath = $input->getOption('path');
        if ($customPath !== null) {
            if (!is_dir($customPath)) {
                throw new \InvalidArgumentException(
                    sprintf('Target path does not exist or is not a directory: %s', $customPath)
                );
            }
            return realpath($customPath);
        }

        // For Fractor, scan both packages and site configuration directories
        $projectRoot = $this->getProjectRoot();
        $packagesPath = $projectRoot . '/packages';

        if (is_dir($packagesPath)) {
            return $packagesPath;
        }

        // Fall back to project root
        return $projectRoot;
    }

    protected function configure(): void
    {
        parent::configure();

        $this
            ->setName('lint:fractor')
            ->setDescription('Run Fractor in dry-run mode to analyze TypoScript and code without making changes')
            ->setHelp(
                'This command runs Fractor in dry-run mode to show what TypoScript and code ' .
                'changes would be made without actually modifying your files. Use --config ' .
                'to specify a custom configuration file or --path to target specific directories.'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $this->showOptimizationDetails($input, $output, 'fractor');

            $configPath = $this->resolveConfigPath('fractor.php', $input->getOption('config'));
            $targetPath = $this->getTargetPath($input);

            // Perform YAML validation before running Fractor
            $yamlValidation = $this->validateYamlFiles($input, $output, $targetPath);

            $command = [
                $this->getVendorBinPath() . '/fractor',
                'process',
                '--dry-run',
                '--config=' . $configPath,
                $targetPath,
            ];

            // Get optimal memory limit for automatic optimization
            if (!$this->isOptimizationDisabled($input)) {
                $optimalMemory = $this->getOptimalMemoryLimit($input, $output, 'fractor');
                $exitCode = $this->executeProcess($command, $input, $output, $optimalMemory);
            } else {
                $exitCode = $this->executeProcess($command, $input, $output);
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
    private function validateYamlFiles(InputInterface $input, OutputInterface $output, string $targetPath): array
    {
        if ($this->isOptimizationDisabled($input)) {
            return ['valid' => [], 'invalid' => [], 'summary' => []];
        }

        $output->writeln('<comment>Pre-validating YAML files...</comment>');

        $validator = new YamlValidator();
        $results = $validator->validateYamlFiles($targetPath);

        if ($results['summary']['invalid'] > 0) {
            $output->writeln(sprintf(
                '<comment>Found %d problematic YAML files (will be processed with error recovery)</comment>',
                $results['summary']['invalid']
            ));
        } else {
            $output->writeln('<info>All YAML files validated successfully</info>');
        }

        $output->writeln('');

        return $results;
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
            $output->writeln('<info>Fractor processed other files successfully despite these issues.</info>');
        }
    }
}

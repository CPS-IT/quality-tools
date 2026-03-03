<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Console\Command;

use Cpsit\QualityTools\Utility\YamlValidator;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Shared functionality for Fractor commands (FractorLintCommand and FractorFixCommand).
 */
trait FractorCommandTrait
{
    private array $yamlValidationResults = [];

    public function getToolName(): string
    {
        return 'fractor';
    }

    protected function getDefaultConfigFileName(): string
    {
        return 'fractor.php';
    }

    /**
     * Resolve target paths for Fractor, using project root as fallback.
     */
    protected function resolveFractorTargetPaths(InputInterface $input, OutputInterface $output): array
    {
        $targetPaths = parent::resolveTargetPaths($input, $output);

        // If no paths resolved, use project root for Fractor
        if (empty($targetPaths)) {
            $targetPaths = [$this->getProjectRoot()];
        }

        return $targetPaths;
    }

    /**
     * Execute pre-processing hooks for Fractor commands.
     */
    protected function executeFractorPreProcessingHooks(InputInterface $input, OutputInterface $output, array $targetPaths): void
    {
        // Perform YAML validation before running Fractor
        $this->yamlValidationResults = $this->validateYamlFiles($input, $output, $targetPaths);
    }

    /**
     * Build the command array for Fractor execution.
     */
    protected function buildFractorCommand(
        InputInterface $input,
        string $configPath,
        array $targetPaths,
        bool $dryRun = false
    ): array {
        $command = [
            $this->getVendorBinPath() . '/fractor',
            'process',
        ];

        if ($dryRun) {
            $command[] = '--dry-run';
        }

        $command[] = '--config=' . $configPath;

        // Always add target paths if available
        // This ensures Fractor processes the correct directories
        if (!empty($targetPaths)) {
            // Fractor accepts multiple paths as arguments
            foreach ($targetPaths as $path) {
                $command[] = $path;
            }
        }

        return $command;
    }

    /**
     * Execute post-processing hooks for Fractor commands.
     */
    protected function executeFractorPostProcessingHooks(OutputInterface $output, int $exitCode): void
    {
        // If Fractor failed with no output, provide a helpful error message
        if ($exitCode !== 0 && !$output->isVerbose()) {
            $output->writeln('');
            $output->writeln('<error>Fractor exited with an error but provided no output.</error>');
            $output->writeln('<comment>This may indicate an environment issue. Try running with --verbose for more details.</comment>');
        }
        
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

    /**
     * Abstract method that must be implemented by the parent class.
     */
    abstract protected function getProjectRoot(): string;
    
    /**
     * Abstract method that must be implemented by the parent class.
     */
    abstract protected function getVendorBinPath(): string;
    
    /**
     * Abstract method that must be implemented by the parent class.
     */
    abstract protected function isOptimizationDisabled(InputInterface $input): bool;
}
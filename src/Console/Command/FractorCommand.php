<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Console\Command;

use Cpsit\QualityTools\Console\Output\ToolRunInfoDisplay;
use Cpsit\QualityTools\Exception\FileSystemException;
use Cpsit\QualityTools\Messaging\StreamingOutputCollector;
use Cpsit\QualityTools\Service\ErrorFactory;
use Cpsit\QualityTools\Service\ErrorHandler;
use Cpsit\QualityTools\Tool\Runner\DTO\ToolRunRequest;
use Cpsit\QualityTools\Tool\Runner\ToolRunnerRegistry;
use Cpsit\QualityTools\Tool\ToolName;
use Cpsit\QualityTools\Utility\YamlValidator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Unified Fractor command supporting both lint (dry-run) and fix modes.
 *
 * Registered twice in services.yaml with different names and dryRun values.
 * Includes YAML pre-validation specific to Fractor processing.
 */
final class FractorCommand extends Command
{
    public function __construct(
        private readonly ToolRunnerRegistry $registry,
        private readonly ToolRunInfoDisplay $infoDisplay,
        private readonly bool $dryRun,
        string $name,
        string $description,
        string $help,
    ) {
        parent::__construct($name);
        $this->setDescription($description);
        $this->setHelp($help);
    }

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
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $pathOverride = $input->getOption('path');
            if ($pathOverride !== null && !is_dir($pathOverride)) {
                throw new FileSystemException(\sprintf('Target path does not exist or is not a directory: %s', $pathOverride));
            }

            $configOverride = $input->getOption('config');
            if ($configOverride !== null && !file_exists($configOverride)) {
                throw ErrorFactory::configFileNotFound($configOverride, $configOverride);
            }

            $request = new ToolRunRequest(
                toolName: ToolName::Fractor->value,
                dryRun: $this->dryRun,
                configOverride: $configOverride,
                pathOverride: $pathOverride,
            );

            $runner = $this->registry->get(ToolName::Fractor->value);
            $description = $runner->describe($request);
            $optimizationDisabled = (bool) $input->getOption('no-optimization');
            $this->infoDisplay->display($description, $output, $optimizationDisabled);

            // Fractor-specific: pre-validate YAML files in target paths
            if (!$optimizationDisabled) {
                $this->validateYamlFiles($description->targetPaths, $output);
            }

            $collector = new StreamingOutputCollector($output);

            return $runner->run($request, $collector)->exitCode;
        } catch (\Throwable $e) {
            return (new ErrorHandler())->handleException($e, $output, $output->isVerbose());
        }
    }

    /**
     * @param list<string> $targetPaths
     */
    private function validateYamlFiles(array $targetPaths, OutputInterface $output): void
    {
        $output->writeln('<comment>Pre-validating YAML files across all target paths...</comment>');

        $validator = new YamlValidator();
        $totalInvalid = 0;

        foreach ($targetPaths as $targetPath) {
            if (!is_dir($targetPath)) {
                continue;
            }

            $output->writeln(\sprintf('<comment>  Validating YAML files in: %s</comment>', $targetPath));
            $results = $validator->validateYamlFiles($targetPath);
            $totalInvalid += $results['summary']['invalid'];
        }

        if ($totalInvalid > 0) {
            $output->writeln(\sprintf(
                '<comment>Found %d problematic YAML files across all paths (will be processed with error recovery)</comment>',
                $totalInvalid,
            ));
        } else {
            $output->writeln('<info>All YAML files validated successfully across all paths</info>');
        }

        $output->writeln('');
    }
}

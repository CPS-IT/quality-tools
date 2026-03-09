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
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Unified PHP CS Fixer command supporting both lint (dry-run) and fix modes.
 *
 * Registered twice in services.yaml with different names and dryRun values.
 */
final class PhpCsFixerCommand extends Command
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
                toolName: ToolName::PhpCsFixer->value,
                dryRun: $this->dryRun,
                configOverride: $configOverride,
                pathOverride: $pathOverride,
            );

            $runner = $this->registry->get(ToolName::PhpCsFixer->value);
            $this->infoDisplay->display($runner->describe($request), $output, (bool) $input->getOption('no-optimization'));

            $collector = new StreamingOutputCollector($output);

            return $runner->run($request, $collector)->exitCode;
        } catch (\Throwable $e) {
            return (new ErrorHandler())->handleException($e, $output, $output->isVerbose());
        }
    }
}

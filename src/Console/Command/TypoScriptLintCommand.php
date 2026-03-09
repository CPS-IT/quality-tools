<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Console\Command;

use Cpsit\QualityTools\Console\Output\ToolRunInfoDisplay;
use Cpsit\QualityTools\Exception\FileSystemException;
use Cpsit\QualityTools\Messaging\StreamingOutputCollector;
use Cpsit\QualityTools\Service\ErrorFactory;
use Cpsit\QualityTools\Service\ErrorHandler;
use Cpsit\QualityTools\Tool\Runner\ToolRunnerRegistry;
use Cpsit\QualityTools\Tool\Runner\ToolRunRequest;
use Cpsit\QualityTools\Tool\ToolName;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * TypoScript Lint command for checking TypoScript files.
 *
 * Lint-only tool with no fix mode. Uses runner infrastructure for execution.
 */
final class TypoScriptLintCommand extends Command
{
    public function __construct(
        private readonly ToolRunnerRegistry $registry,
        private readonly ToolRunInfoDisplay $infoDisplay,
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
                toolName: ToolName::TypoScriptLint->value,
                dryRun: false,
                configOverride: $configOverride,
                pathOverride: $pathOverride,
            );

            $runner = $this->registry->get(ToolName::TypoScriptLint->value);
            $this->infoDisplay->display($runner->describe($request), $output, false);

            $collector = new StreamingOutputCollector($output);

            return $runner->run($request, $collector)->exitCode;
        } catch (\Throwable $e) {
            return (new ErrorHandler())->handleException($e, $output, $output->isVerbose());
        }
    }
}

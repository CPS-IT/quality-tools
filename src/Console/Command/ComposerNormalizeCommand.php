<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Console\Command;

use Cpsit\QualityTools\Console\Output\ToolRunInfoDisplay;
use Cpsit\QualityTools\Exception\FileSystemException;
use Cpsit\QualityTools\Messaging\MessageSeverity;
use Cpsit\QualityTools\Messaging\StreamingOutputCollector;
use Cpsit\QualityTools\Service\ErrorHandler;
use Cpsit\QualityTools\Tool\Runner\ToolRunnerRegistry;
use Cpsit\QualityTools\Tool\Runner\ToolRunRequest;
use Cpsit\QualityTools\Tool\ToolName;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Unified composer-normalize command supporting both lint (dry-run) and fix modes.
 *
 * Registered twice in services.yaml with different names and dryRun values.
 * Composer-normalize does not use a config file, only --path is relevant.
 */
final class ComposerNormalizeCommand extends Command
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

            $request = new ToolRunRequest(
                toolName: ToolName::ComposerNormalize->value,
                dryRun: $this->dryRun,
                configOverride: null,
                pathOverride: $pathOverride,
            );

            $runner = $this->registry->get(ToolName::ComposerNormalize->value);
            $this->infoDisplay->display($runner->describe($request), $output, false);

            $collector = new StreamingOutputCollector($output);
            $result = $runner->run($request, $collector);

            // Render runner messages (e.g. "no composer.json files found")
            foreach ($result->messages as $message) {
                match ($message->severity) {
                    MessageSeverity::Error => $output->writeln('<error>' . $message->text . '</error>'),
                    MessageSeverity::Warning => $output->writeln('<comment>' . $message->text . '</comment>'),
                    MessageSeverity::Info => $output->writeln('<info>' . $message->text . '</info>'),
                };
            }

            return $result->exitCode;
        } catch (\Throwable $e) {
            return (new ErrorHandler())->handleException($e, $output, $output->isVerbose());
        }
    }
}

<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Console\Runner;

use Cpsit\QualityTools\Configuration\ConfigurationLoaderInterface;
use Cpsit\QualityTools\Configuration\ConfigurationTemplateGenerator;
use Cpsit\QualityTools\Console\Runner\DTO\CommandRunDescription;
use Cpsit\QualityTools\Console\Runner\DTO\ConfigInitRequest;
use Cpsit\QualityTools\Exception\FileSystemException;
use Cpsit\QualityTools\Messaging\Message;
use Cpsit\QualityTools\Messaging\OutputCollectorInterface;
use Cpsit\QualityTools\Service\FilesystemService;
use Cpsit\QualityTools\Service\ProjectEnvironment;
use Cpsit\QualityTools\Tool\Runner\DTO\ToolRunResult;

/**
 * Orchestrates configuration template generation and file writing.
 */
final readonly class ConfigInitRunner
{
    public function __construct(
        private ConfigurationTemplateGenerator $templateGenerator,
        private ConfigurationLoaderInterface $configLoader,
        private FilesystemService $filesystemService,
        private ProjectEnvironment $projectEnv,
    ) {
    }

    public function describe(ConfigInitRequest $request): CommandRunDescription
    {
        $projectRoot = $this->projectEnv->getProjectRoot();

        return new CommandRunDescription(
            operationName: 'config-init',
            configPath: $projectRoot . '/.quality-tools.yaml',
            info: ['template' => $request->template],
        );
    }

    public function run(
        ConfigInitRequest $request,
        OutputCollectorInterface $collector,
    ): ToolRunResult {
        $projectRoot = $this->projectEnv->getProjectRoot();
        $configFile = $projectRoot . '/.quality-tools.yaml';

        if (!$this->templateGenerator->isValidTemplate($request->template)) {
            $available = implode(', ', array_keys($this->templateGenerator->getAvailableTemplates()));

            return new ToolRunResult(1, [
                Message::error(\sprintf(
                    'Invalid template "%s". Available templates: %s',
                    $request->template,
                    $available,
                )),
            ]);
        }

        // Check existing config
        $existing = $this->configLoader->findConfigurationFile($projectRoot);
        if ($existing !== null && !$request->force) {
            return new ToolRunResult(0, [
                Message::warning(\sprintf('Configuration file already exists: %s', $existing)),
                Message::info('Use --force to overwrite the existing configuration.'),
            ]);
        }

        try {
            $content = $this->templateGenerator->generate($request->template, $projectRoot);
            $this->filesystemService->writeFile($configFile, $content);
        } catch (FileSystemException $e) {
            return new ToolRunResult(1, [
                Message::error('Failed to create configuration file: ' . $e->getMessage()),
            ]);
        }

        $templates = $this->templateGenerator->getAvailableTemplates();

        return new ToolRunResult(0, [
            Message::info(\sprintf('Created configuration file: %s', $configFile)),
            Message::info(\sprintf('Template used: %s', $templates[$request->template] ?? $request->template)),
        ]);
    }
}

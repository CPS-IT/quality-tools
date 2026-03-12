<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Console\Runner;

use Cpsit\QualityTools\Configuration\ConfigurationLoaderInterface;
use Cpsit\QualityTools\Configuration\ConfigurationValidator;
use Cpsit\QualityTools\Console\Runner\DTO\CommandRunDescription;
use Cpsit\QualityTools\Console\Runner\DTO\ConfigValidateRequest;
use Cpsit\QualityTools\Messaging\Message;
use Cpsit\QualityTools\Messaging\OutputCollectorInterface;
use Cpsit\QualityTools\Service\ProjectEnvironment;
use Cpsit\QualityTools\Tool\Runner\DTO\ToolRunResult;

/**
 * Orchestrates configuration loading, schema validation,
 * and config_file path checking.
 */
final readonly class ConfigValidateRunner
{
    public function __construct(
        private ConfigurationLoaderInterface $configLoader,
        private ConfigurationValidator $configValidator,
        private ProjectEnvironment $projectEnv,
    ) {
    }

    public function describe(ConfigValidateRequest $request): CommandRunDescription
    {
        $projectRoot = $this->projectEnv->getProjectRoot();
        $configFile = $this->configLoader->findConfigurationFile($projectRoot) ?? '';

        return new CommandRunDescription(
            operationName: 'config-validate',
            configPath: $configFile,
        );
    }

    public function run(
        ConfigValidateRequest $request,
        OutputCollectorInterface $collector,
    ): ToolRunResult {
        $projectRoot = $this->projectEnv->getProjectRoot();

        // Check if config exists
        $configFile = $this->configLoader->findConfigurationFile($projectRoot);
        if ($configFile === null) {
            return new ToolRunResult(0, [
                Message::warning('No YAML configuration file found in project root.'),
                Message::warning('Looked for: .quality-tools.yaml, quality-tools.yaml, quality-tools.yml'),
                Message::warning('Use "qt config:init" to create a configuration file.'),
            ]);
        }

        $collector->write(\sprintf("Validating configuration file: %s\n", $configFile));

        // Load and validate (ConfigurationLoader performs schema validation)
        $configuration = $this->configLoader->load($projectRoot);

        // Check config_file paths for each tool
        $warnings = $this->configValidator->validateToolConfigFilePaths($configuration->toArray(), $projectRoot);

        $collector->write("Configuration is valid.\n");

        $messages = [];
        if ($warnings !== []) {
            $messages[] = Message::warning('Config file path issues (fallback to package defaults will be used):');
            foreach ($warnings as $warning) {
                $messages[] = Message::warning($warning);
            }
        }

        // Add summary info for verbose display
        $summaryMessages = $this->buildSummaryMessages($configuration->toArray());
        $messages = [...$messages, ...$summaryMessages];

        return new ToolRunResult(0, $messages);
    }

    /**
     * Build summary messages for verbose display.
     *
     * @return list<Message>
     */
    private function buildSummaryMessages(array $config): array
    {
        $messages = [];
        $qualityTools = $config['quality-tools'] ?? [];

        // Project information
        $project = $qualityTools['project'] ?? [];
        if ($project !== []) {
            $messages[] = Message::info(\sprintf(
                'Project: %s (PHP %s, TYPO3 %s)',
                $project['name'] ?? 'Not specified',
                $project['php_version'] ?? '8.3',
                $project['typo3_version'] ?? '13.4',
            ));
        }

        // Enabled tools
        $tools = $qualityTools['tools'] ?? [];
        $enabledTools = [];
        foreach ($tools as $tool => $toolConfig) {
            if ($toolConfig['enabled'] ?? true) {
                $enabledTools[] = $tool;
            }
        }

        if ($enabledTools !== []) {
            $messages[] = Message::info('Enabled tools: ' . implode(', ', $enabledTools));
        }

        // Scan paths
        $paths = $qualityTools['paths'] ?? [];
        if (!empty($paths['scan'])) {
            $messages[] = Message::info('Scan paths: ' . implode(', ', $paths['scan']));
        }

        return $messages;
    }
}

<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Console\Runner;

use Cpsit\QualityTools\Configuration\ConfigurationLoaderInterface;
use Cpsit\QualityTools\Console\Runner\DTO\CommandRunDescription;
use Cpsit\QualityTools\Console\Runner\DTO\ConfigShowRequest;
use Cpsit\QualityTools\Messaging\Message;
use Cpsit\QualityTools\Messaging\OutputCollectorInterface;
use Cpsit\QualityTools\Service\ProjectEnvironment;
use Cpsit\QualityTools\Tool\Runner\DTO\ToolRunResult;
use Symfony\Component\Yaml\Yaml;

/**
 * Orchestrates configuration loading, source display, and formatting.
 */
final readonly class ConfigShowRunner
{
    public function __construct(
        private ConfigurationLoaderInterface $configLoader,
        private ProjectEnvironment $projectEnv,
    ) {
    }

    public function describe(ConfigShowRequest $request): CommandRunDescription
    {
        $projectRoot = $this->projectEnv->getProjectRoot();
        $configFile = $this->configLoader->findConfigurationFile($projectRoot) ?? '';

        return new CommandRunDescription(
            operationName: 'config-show',
            configPath: $configFile,
            info: ['format' => $request->format],
        );
    }

    public function run(
        ConfigShowRequest $request,
        OutputCollectorInterface $collector,
    ): ToolRunResult {
        $projectRoot = $this->projectEnv->getProjectRoot();

        if (!\in_array($request->format, ['yaml', 'json'], true)) {
            return new ToolRunResult(1, [
                Message::error('Format must be either "yaml" or "json".'),
            ]);
        }

        // Load configuration (ConfigurationLoader handles validation internally)
        $configuration = $this->configLoader->load($projectRoot);
        $configData = $configuration->toArray();

        // Format and write output
        $formatted = match ($request->format) {
            'json' => json_encode(
                $configData,
                JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES,
            ),
            default => Yaml::dump($configData, 4, 2, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK),
        };
        $collector->write($formatted);

        // Build source info as messages (commands can show these in verbose mode)
        $messages = $this->buildSourceMessages($projectRoot);

        return new ToolRunResult(0, $messages);
    }

    /**
     * @return list<Message>
     */
    private function buildSourceMessages(string $projectRoot): array
    {
        $messages = [];

        try {
            $configSources = $this->configLoader->getConfigurationSources($projectRoot);
            foreach ($configSources as $source) {
                if ($source['file_path'] !== null) {
                    $label = match ($source['source']) {
                        'project_root' => 'Project',
                        'config_dir' => 'Config directory',
                        'global' => 'Global',
                        'package_config' => 'Package',
                        'tool_specific' => 'Tool-specific',
                        'tool_config_dir' => 'Tool config dir',
                        default => ucfirst((string) $source['source']),
                    };
                    $messages[] = Message::info(\sprintf('Source: %s: %s', $label, $source['file_path']));
                } elseif ($source['source'] === 'package_defaults') {
                    $messages[] = Message::info('Source: Package defaults (built-in)');
                }
            }
        } catch (\Exception) {
            $messages[] = Message::info('Source: Package defaults (built-in)');
        }

        // Report configuration errors
        $configErrors = $this->configLoader->getConfigurationErrors($projectRoot);
        if ($configErrors !== []) {
            $messages[] = Message::warning('Some configuration files could not be loaded:');
            foreach ($configErrors as $filePath => $error) {
                $messages[] = Message::warning(\sprintf('%s: %s', $filePath, $error));
            }
        }

        return $messages;
    }
}

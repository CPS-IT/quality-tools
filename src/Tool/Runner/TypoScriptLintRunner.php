<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Tool\Runner;

use Cpsit\QualityTools\Configuration\ConfigurationLoaderInterface;
use Cpsit\QualityTools\Messaging\OutputCollectorInterface;
use Cpsit\QualityTools\Service\ProcessExecutor;
use Cpsit\QualityTools\Service\ProjectEnvironment;
use Cpsit\QualityTools\Tool\ToolName;

final readonly class TypoScriptLintRunner implements ToolRunnerInterface
{
    private const string DEFAULT_CONFIG_FILE = 'typoscript-lint.yml';

    public function __construct(
        private ProcessExecutor $processExecutor,
        private ProjectEnvironment $projectEnv,
        private ConfigurationLoaderInterface $configLoader,
    ) {
    }

    public function supportedTools(): array
    {
        return [ToolName::TypoScriptLint->value];
    }

    public function describe(ToolRunRequest $request): ToolRunDescription
    {
        $projectRoot = $this->projectEnv->getProjectRoot();
        $configPath = $this->resolveConfigPath($request);
        $targetPaths = $this->resolveTargetPaths($request, $projectRoot);

        return new ToolRunDescription(
            toolName: ToolName::TypoScriptLint->value,
            configPath: $configPath,
            targetPaths: $targetPaths,
            metrics: null,
            memoryLimit: null,
        );
    }

    public function run(ToolRunRequest $request, OutputCollectorInterface $collector): ToolRunResult
    {
        $projectRoot = $this->projectEnv->getProjectRoot();
        $configPath = $this->resolveConfigPath($request);
        $targetPaths = $this->resolveTargetPaths($request, $projectRoot);

        $command = [
            $this->projectEnv->getVendorBinPath() . '/typoscript-lint',
            '-c',
            $configPath,
        ];

        foreach ($targetPaths as $path) {
            $command[] = $path;
        }

        $exitCode = $this->processExecutor->executeWithCollector(
            $command,
            $projectRoot,
            $_SERVER,
            $collector,
        );

        return new ToolRunResult($exitCode);
    }

    private function resolveConfigPath(ToolRunRequest $request): string
    {
        if ($request->configOverride !== null) {
            return $request->configOverride;
        }

        $projectRoot = $this->projectEnv->getProjectRoot();
        $discovered = $this->configLoader->resolveToolConfigPath($projectRoot, ToolName::TypoScriptLint->value);
        if ($discovered !== null) {
            return $discovered;
        }

        return $this->projectEnv->getVendorPath()
            . '/cpsit/quality-tools/config/' . self::DEFAULT_CONFIG_FILE;
    }

    /**
     * @return list<string>
     */
    private function resolveTargetPaths(ToolRunRequest $request, string $projectRoot): array
    {
        if ($request->pathOverride !== null) {
            return [$request->pathOverride];
        }

        $configuration = $this->configLoader->load($projectRoot);

        return $configuration->getResolvedPathsForTool(ToolName::TypoScriptLint->value);
    }
}

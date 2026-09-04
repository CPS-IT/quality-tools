<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Tool\Runner;

use Cpsit\QualityTools\Configuration\ConfigurationInterface;
use Cpsit\QualityTools\Configuration\ConfigurationLoaderInterface;
use Cpsit\QualityTools\Messaging\OutputCollectorInterface;
use Cpsit\QualityTools\Service\MemoryOptimizer;
use Cpsit\QualityTools\Service\ProcessExecutor;
use Cpsit\QualityTools\Service\ProjectEnvironment;
use Cpsit\QualityTools\Tool\Runner\DTO\ToolRunDescription;
use Cpsit\QualityTools\Tool\Runner\DTO\ToolRunRequest;
use Cpsit\QualityTools\Tool\Runner\DTO\ToolRunResult;
use Cpsit\QualityTools\Tool\ToolName;

final readonly class RectorRunner implements ToolRunnerInterface
{
    private const string DEFAULT_CONFIG_FILE = 'rector.php';

    public function __construct(
        private ProcessExecutor $processExecutor,
        private ProjectEnvironment $projectEnv,
        private ConfigurationLoaderInterface $configLoader,
        private ?MemoryOptimizer $memoryOptimizer = null,
    ) {
    }

    public function supportedTools(): array
    {
        return [ToolName::Rector->value];
    }

    public function describe(ToolRunRequest $request): ToolRunDescription
    {
        $projectRoot = $this->projectEnv->getProjectRoot();
        $configPath = $this->resolveConfigPath($request, $projectRoot);
        $targetPaths = $this->resolveTargetPaths($request, $projectRoot);

        $metrics = null;
        $memoryLimit = null;
        if ($this->memoryOptimizer !== null) {
            $metrics = $this->memoryOptimizer->analyzeAndAggregate($targetPaths);
            $memoryLimit = $this->memoryOptimizer->calculateMemoryLimit(
                ToolName::Rector->value,
                $targetPaths,
            );
        }

        return new ToolRunDescription(
            toolName: ToolName::Rector->value,
            configPath: $configPath,
            targetPaths: $targetPaths,
            metrics: $metrics,
            memoryLimit: $memoryLimit,
        );
    }

    public function run(ToolRunRequest $request, OutputCollectorInterface $collector): ToolRunResult
    {
        $projectRoot = $this->projectEnv->getProjectRoot();
        $configPath = $this->resolveConfigPath($request, $projectRoot);
        $targetPaths = $this->resolveTargetPaths($request, $projectRoot);

        $command = [
            $this->projectEnv->getVendorBinPath() . '/rector',
            'process',
        ];

        if ($request->dryRun) {
            $command[] = '--dry-run';
        }

        $command[] = '--config=' . $configPath;

        foreach ($targetPaths as $path) {
            $command[] = $path;
        }

        $command = $this->applyMemoryLimit($command, $targetPaths);

        $exitCode = $this->processExecutor->executeWithCollector(
            $command,
            $projectRoot,
            $_SERVER,
            $collector,
        );

        return new ToolRunResult($exitCode);
    }

    /**
     * @param list<string> $command
     * @param list<string> $targetPaths
     *
     * @return list<string>
     */
    private function applyMemoryLimit(array $command, array $targetPaths): array
    {
        if ($this->memoryOptimizer === null) {
            return $command;
        }

        $memoryLimit = $this->memoryOptimizer->calculateMemoryLimit(
            ToolName::Rector->value,
            $targetPaths,
        );

        return ['php', '-d', 'memory_limit=' . $memoryLimit, ...$command];
    }

    private function resolveConfigPath(ToolRunRequest $request, string $projectRoot): string
    {
        if ($request->configOverride !== null) {
            return $request->configOverride;
        }

        $discovered = $this->configLoader->resolveToolConfigPath($projectRoot, ToolName::Rector->value);
        if ($discovered !== null) {
            return $discovered;
        }

        $rectorConfig = $this->configLoader->load($projectRoot)->getToolConfig('rector');
        $level = $rectorConfig['level'] ?? ConfigurationInterface::DEFAULT_RECTOR_LEVEL;
        if (!\in_array($level, ConfigurationInterface::ALLOWED_RECTOR_LEVELS, true)) {
            $level = ConfigurationInterface::DEFAULT_RECTOR_LEVEL;
        }

        $packageConfigDir = $this->projectEnv->getPackageConfigDir();
        $versionedConfig = $packageConfigDir . '/rector-' . $level . '.php';

        if (file_exists($versionedConfig)) {
            return $versionedConfig;
        }

        return $packageConfigDir . '/' . self::DEFAULT_CONFIG_FILE;
    }

    /**
     * @return list<string>
     */
    private function resolveTargetPaths(ToolRunRequest $request, string $projectRoot): array
    {
        if ($request->pathOverride !== null) {
            return [$request->pathOverride];
        }

        $paths = $this->configLoader->load($projectRoot)
            ->getResolvedPathsForTool(ToolName::Rector->value);

        return $paths !== [] ? $paths : [$projectRoot];
    }
}

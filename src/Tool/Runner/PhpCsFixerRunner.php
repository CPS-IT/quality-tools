<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Tool\Runner;

use Cpsit\QualityTools\Configuration\ConfigurationLoaderInterface;
use Cpsit\QualityTools\Messaging\OutputCollectorInterface;
use Cpsit\QualityTools\Service\MemoryOptimizer;
use Cpsit\QualityTools\Service\ProcessExecutor;
use Cpsit\QualityTools\Service\ProjectEnvironment;
use Cpsit\QualityTools\Tool\ToolName;

final readonly class PhpCsFixerRunner implements ToolRunnerInterface
{
    private const string DEFAULT_CONFIG_FILE = 'php-cs-fixer.php';

    public function __construct(
        private ProcessExecutor $processExecutor,
        private ProjectEnvironment $projectEnv,
        private ConfigurationLoaderInterface $configLoader,
        private ?MemoryOptimizer $memoryOptimizer = null,
    ) {
    }

    public function supportedTools(): array
    {
        return [ToolName::PhpCsFixer->value];
    }

    public function run(ToolRunRequest $request, OutputCollectorInterface $collector): ToolRunResult
    {
        $projectRoot = $this->projectEnv->getProjectRoot();
        $configPath = $this->resolveConfigPath($request);
        $targetPaths = $this->resolveTargetPaths($request, $projectRoot);

        $command = [
            $this->projectEnv->getVendorBinPath() . '/php-cs-fixer',
            'fix',
        ];

        if ($request->dryRun) {
            $command[] = '--dry-run';
            $command[] = '--diff';
        }

        $command[] = '--config=' . $configPath;

        if ($this->shouldEnableCache($request)) {
            $command[] = '--using-cache=yes';
        }

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
            ToolName::PhpCsFixer->value,
            $targetPaths,
        );

        return ['php', '-d', 'memory_limit=' . $memoryLimit, ...$command];
    }

    private function resolveConfigPath(ToolRunRequest $request): string
    {
        if ($request->configOverride !== null) {
            return $request->configOverride;
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
        $paths = $configuration->getResolvedPathsForTool(ToolName::PhpCsFixer->value);

        return $paths !== [] ? $paths : [$projectRoot];
    }

    private function shouldEnableCache(ToolRunRequest $request): bool
    {
        return (bool) ($request->toolOptions['cache'] ?? false);
    }
}

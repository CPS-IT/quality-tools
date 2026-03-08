<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Tool\Runner;

use Cpsit\QualityTools\Configuration\ConfigurationLoaderInterface;
use Cpsit\QualityTools\Messaging\OutputCollectorInterface;
use Cpsit\QualityTools\Service\ProcessExecutor;
use Cpsit\QualityTools\Service\ProjectEnvironment;
use Cpsit\QualityTools\Tool\ToolName;

final readonly class RectorRunner implements ToolRunnerInterface
{
    private const string DEFAULT_CONFIG_FILE = 'rector.php';

    public function __construct(
        private ProcessExecutor $processExecutor,
        private ProjectEnvironment $projectEnv,
        private ConfigurationLoaderInterface $configLoader,
    ) {
    }

    public function supportedTools(): array
    {
        return [ToolName::Rector->value];
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

        $exitCode = $this->processExecutor->executeWithCollector(
            $command,
            $projectRoot,
            $_SERVER,
            $collector,
        );

        return new ToolRunResult($exitCode);
    }

    private function resolveConfigPath(ToolRunRequest $request, string $projectRoot): string
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

        $paths = $this->configLoader->load($projectRoot)
            ->getResolvedPathsForTool(ToolName::Rector->value);

        return $paths !== [] ? $paths : [$projectRoot];
    }
}

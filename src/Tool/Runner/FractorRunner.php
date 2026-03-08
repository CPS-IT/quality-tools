<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Tool\Runner;

use Cpsit\QualityTools\Configuration\ConfigurationLoaderInterface;
use Cpsit\QualityTools\Messaging\OutputCollectorInterface;
use Cpsit\QualityTools\Service\ProcessExecutor;
use Cpsit\QualityTools\Service\ProjectEnvironment;
use Cpsit\QualityTools\Tool\ToolName;

final readonly class FractorRunner implements ToolRunnerInterface
{
    private const string DEFAULT_CONFIG_FILE = 'fractor.php';

    public function __construct(
        private ProcessExecutor $processExecutor,
        private ProjectEnvironment $projectEnv,
        private ConfigurationLoaderInterface $configLoader,
    ) {
    }

    public function supportedTools(): array
    {
        return [ToolName::Fractor->value];
    }

    public function run(ToolRunRequest $request, OutputCollectorInterface $collector): ToolRunResult
    {
        $projectRoot = $this->projectEnv->getProjectRoot();
        $configPath = $this->resolveConfigPath($request);
        $targetPaths = $this->resolveTargetPaths($request, $projectRoot);

        $command = [
            $this->projectEnv->getVendorBinPath() . '/fractor',
            'process',
        ];

        if ($request->dryRun) {
            $command[] = '--dry-run';
        }

        $command[] = '--config=' . $configPath;

        foreach ($targetPaths as $path) {
            $command[] = $path;
        }

        $environment = $_SERVER;

        // Pass resolved paths as environment variable for dynamic path support
        if ($request->pathOverride === null && $targetPaths !== []) {
            $environment['QT_DYNAMIC_PATHS'] = json_encode($targetPaths, JSON_THROW_ON_ERROR);
        }

        $exitCode = $this->processExecutor->executeWithCollector(
            $command,
            $projectRoot,
            $environment,
            $collector,
        );

        return new ToolRunResult($exitCode);
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
        $paths = $configuration->getResolvedPathsForTool(ToolName::Fractor->value);

        return $paths !== [] ? $paths : [$projectRoot];
    }
}

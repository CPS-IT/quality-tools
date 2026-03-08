<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Tool\Runner;

use Cpsit\QualityTools\Configuration\ConfigurationLoaderInterface;
use Cpsit\QualityTools\Messaging\OutputCollectorInterface;
use Cpsit\QualityTools\Service\ProcessExecutor;
use Cpsit\QualityTools\Service\ProjectEnvironment;
use Cpsit\QualityTools\Tool\ToolName;

final readonly class PhpStanRunner implements ToolRunnerInterface
{
    private const string DEFAULT_CONFIG_FILE = 'phpstan.neon';

    public function __construct(
        private ProcessExecutor $processExecutor,
        private ProjectEnvironment $projectEnv,
        private ConfigurationLoaderInterface $configLoader,
    ) {
    }

    public function supportedTools(): array
    {
        return [ToolName::PhpStan->value];
    }

    public function run(ToolRunRequest $request, OutputCollectorInterface $collector): ToolRunResult
    {
        $projectRoot = $this->projectEnv->getProjectRoot();
        $targetPaths = $this->resolveTargetPaths($request, $projectRoot);

        $configPath = $this->resolveConfigPath($request);
        $temporaryConfigPath = null;

        // If multiple paths, create a temporary neon config that includes
        // the base config and overrides paths
        if (\count($targetPaths) > 1) {
            $temporaryConfigPath = $this->createTemporaryConfig($configPath, $targetPaths);
        }

        $actualConfigPath = $temporaryConfigPath ?? $configPath;

        $command = [
            $this->projectEnv->getVendorBinPath() . '/phpstan',
            'analyse',
            '--configuration=' . $actualConfigPath,
        ];

        $level = $request->toolOptions['level'] ?? null;
        if ($level !== null) {
            $command[] = '--level=' . $level;
        }

        $memoryLimit = $request->toolOptions['memory-limit'] ?? null;
        if ($memoryLimit !== null) {
            $command[] = '--memory-limit=' . $memoryLimit;
        }

        // For single path, pass it directly on the command line
        if (\count($targetPaths) === 1 && $request->pathOverride !== null) {
            $command[] = $targetPaths[0];
        }

        try {
            $exitCode = $this->processExecutor->executeWithCollector(
                $command,
                $projectRoot,
                $_SERVER,
                $collector,
            );
        } finally {
            if ($temporaryConfigPath !== null && file_exists($temporaryConfigPath)) {
                unlink($temporaryConfigPath);
            }
        }

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

        return $this->configLoader->load($projectRoot)->getResolvedPathsForTool(ToolName::PhpStan->value);
    }

    /**
     * Create a temporary neon config that includes the base config and overrides paths.
     *
     * @param list<string> $paths
     */
    private function createTemporaryConfig(string $baseConfigPath, array $paths): string
    {
        $pathEntries = array_map(
            static fn (string $path): string => '        - ' . $path,
            $paths,
        );

        $content = \sprintf(
            "includes:\n    - %s\n\nparameters:\n    paths:\n%s\n",
            $baseConfigPath,
            implode("\n", $pathEntries),
        );

        $tempFile = tempnam(sys_get_temp_dir(), 'phpstan_') . '.neon';
        file_put_contents($tempFile, $content);

        return $tempFile;
    }
}

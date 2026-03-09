<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Tool\Runner;

use Cpsit\QualityTools\Configuration\ConfigurationLoaderInterface;
use Cpsit\QualityTools\Messaging\Message;
use Cpsit\QualityTools\Messaging\OutputCollectorInterface;
use Cpsit\QualityTools\Service\ProcessExecutor;
use Cpsit\QualityTools\Service\ProjectEnvironment;
use Cpsit\QualityTools\Tool\Runner\DTO\ToolRunDescription;
use Cpsit\QualityTools\Tool\Runner\DTO\ToolRunRequest;
use Cpsit\QualityTools\Tool\Runner\DTO\ToolRunResult;
use Cpsit\QualityTools\Tool\ToolName;

final readonly class ComposerNormalizeRunner implements ToolRunnerInterface
{
    public function __construct(
        private ProcessExecutor $processExecutor,
        private ProjectEnvironment $projectEnv,
        private ConfigurationLoaderInterface $configLoader,
    ) {
    }

    public function supportedTools(): array
    {
        return [ToolName::ComposerNormalize->value];
    }

    public function describe(ToolRunRequest $request): ToolRunDescription
    {
        $projectRoot = $this->projectEnv->getProjectRoot();
        $targetPaths = $this->resolveTargetPaths($request, $projectRoot);

        return new ToolRunDescription(
            toolName: ToolName::ComposerNormalize->value,
            configPath: '',
            targetPaths: $targetPaths,
            metrics: null,
            memoryLimit: null,
        );
    }

    public function run(ToolRunRequest $request, OutputCollectorInterface $collector): ToolRunResult
    {
        $projectRoot = $this->projectEnv->getProjectRoot();
        $targetPaths = $this->resolveTargetPaths($request, $projectRoot);
        $composerExecutable = $this->resolveComposerExecutable();

        $totalExitCode = 0;
        $foundFiles = 0;

        /** @var list<Message> $messages */
        $messages = [];

        foreach ($targetPaths as $targetPath) {
            $composerJsonPath = $targetPath . '/composer.json';

            if (!file_exists($composerJsonPath)) {
                continue;
            }

            ++$foundFiles;

            $command = [$composerExecutable, 'normalize'];

            if ($request->dryRun) {
                $command[] = '--dry-run';
                $command[] = '--diff';
            }

            $command[] = $composerJsonPath;

            $collector->write(\sprintf('Checking composer.json: %s', $composerJsonPath));

            $exitCode = $this->processExecutor->executeWithCollector(
                $command,
                $projectRoot,
                $_SERVER,
                $collector,
            );

            if ($exitCode !== 0) {
                $totalExitCode = $exitCode;
            }
        }

        if ($foundFiles === 0) {
            $messages[] = Message::warning('No composer.json files found in configured paths.');

            return new ToolRunResult(1, $messages);
        }

        return new ToolRunResult($totalExitCode, $messages);
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
        $paths = $configuration->getResolvedPathsForTool(ToolName::ComposerNormalize->value);

        return $paths !== [] ? $paths : [$projectRoot];
    }

    /**
     * Resolve the composer executable path.
     *
     * Prefers vendor/bin/composer if it exists and is functional,
     * falls back to system composer.
     */
    private function resolveComposerExecutable(): string
    {
        try {
            $vendorComposer = $this->projectEnv->getVendorBinPath() . '/composer';
            if (is_file($vendorComposer) && is_executable($vendorComposer) && $this->isComposerWrapperFunctional($vendorComposer)) {
                return $vendorComposer;
            }
        } catch (\Throwable) {
            // Fall through to system composer
        }

        return 'composer';
    }

    /**
     * Check whether the vendor/bin/composer wrapper can actually run.
     *
     * Composer generates a wrapper that hardcodes the path to composer.phar.
     * In CI where build and test run in separate containers, this path may not exist.
     */
    private function isComposerWrapperFunctional(string $wrapperPath): bool
    {
        $content = @file_get_contents($wrapperPath);
        if ($content === false) {
            return false;
        }

        if (preg_match('#([\'"]?)(/[^\'"\s]+composer\.phar)\1#', $content, $matches)) {
            return file_exists($matches[2]);
        }

        return true;
    }
}

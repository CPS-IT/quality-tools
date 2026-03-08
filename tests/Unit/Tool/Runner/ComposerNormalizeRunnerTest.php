<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Tests\Unit\Tool\Runner;

use Cpsit\QualityTools\Configuration\ConfigurationInterface;
use Cpsit\QualityTools\Configuration\ConfigurationLoaderInterface;
use Cpsit\QualityTools\Messaging\BufferingOutputCollector;
use Cpsit\QualityTools\Messaging\MessageSeverity;
use Cpsit\QualityTools\Service\ProcessExecutor;
use Cpsit\QualityTools\Service\ProjectEnvironment;
use Cpsit\QualityTools\Tool\Runner\ComposerNormalizeRunner;
use Cpsit\QualityTools\Tool\Runner\ToolRunRequest;
use Cpsit\QualityTools\Utility\VendorDirectoryDetector;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

#[CoversClass(ComposerNormalizeRunner::class)]
final class ComposerNormalizeRunnerTest extends TestCase
{
    private string $packageRoot;

    private ProjectEnvironment $projectEnv;

    /** @var ConfigurationLoaderInterface&\PHPUnit\Framework\MockObject\MockObject */
    private \PHPUnit\Framework\MockObject\MockObject $configLoader;

    /** @var ConfigurationInterface&\PHPUnit\Framework\MockObject\MockObject */
    private \PHPUnit\Framework\MockObject\MockObject $configuration;

    protected function setUp(): void
    {
        parent::setUp();

        $this->packageRoot = \dirname(__DIR__, 4);
        putenv('QT_PROJECT_ROOT=' . $this->packageRoot);
        VendorDirectoryDetector::clearCache();

        $this->projectEnv = new ProjectEnvironment(new VendorDirectoryDetector());

        $this->configuration = $this->createMock(ConfigurationInterface::class);
        $this->configLoader = $this->createMock(ConfigurationLoaderInterface::class);
        $this->configLoader->method('load')->willReturn($this->configuration);
    }

    protected function tearDown(): void
    {
        putenv('QT_PROJECT_ROOT');
        VendorDirectoryDetector::clearCache();

        parent::tearDown();
    }

    #[Test]
    public function supportedToolsReturnsExpectedNames(): void
    {
        $runner = $this->createRunner();

        self::assertSame(['composer-normalize'], $runner->supportedTools());
    }

    #[Test]
    public function runBuildsCorrectCommandForProjectRoot(): void
    {
        // The package root has a composer.json, so it should be found
        $this->configuration->method('getResolvedPathsForTool')
            ->with('composer-normalize')
            ->willReturn([]);

        $capturedCommand = null;
        $runner = $this->createRunnerCapturingCommand($capturedCommand);
        $request = new ToolRunRequest('composer-normalize', dryRun: false);

        $runner->run($request, new BufferingOutputCollector());

        self::assertIsArray($capturedCommand);
        // Command should end with composer normalize <path>/composer.json
        self::assertSame('normalize', $capturedCommand[1]);
        self::assertStringEndsWith('/composer.json', $capturedCommand[2]);
    }

    #[Test]
    public function runPassesDryRunAndDiffFlags(): void
    {
        $this->configuration->method('getResolvedPathsForTool')
            ->with('composer-normalize')
            ->willReturn([]);

        $capturedCommand = null;
        $runner = $this->createRunnerCapturingCommand($capturedCommand);
        $request = new ToolRunRequest('composer-normalize', dryRun: true);

        $runner->run($request, new BufferingOutputCollector());

        self::assertIsArray($capturedCommand);
        self::assertContains('--dry-run', $capturedCommand);
        self::assertContains('--diff', $capturedCommand);
    }

    #[Test]
    public function runOmitsDryRunFlag(): void
    {
        $this->configuration->method('getResolvedPathsForTool')
            ->with('composer-normalize')
            ->willReturn([]);

        $capturedCommand = null;
        $runner = $this->createRunnerCapturingCommand($capturedCommand);
        $request = new ToolRunRequest('composer-normalize', dryRun: false);

        $runner->run($request, new BufferingOutputCollector());

        self::assertIsArray($capturedCommand);
        self::assertNotContains('--dry-run', $capturedCommand);
        self::assertNotContains('--diff', $capturedCommand);
    }

    #[Test]
    public function runUsesPathOverride(): void
    {
        // Use the actual package root which has a composer.json
        $capturedCommand = null;
        $runner = $this->createRunnerCapturingCommand($capturedCommand);
        $request = new ToolRunRequest(
            'composer-normalize',
            dryRun: false,
            pathOverride: $this->packageRoot,
        );

        $runner->run($request, new BufferingOutputCollector());

        self::assertIsArray($capturedCommand);
        self::assertStringEndsWith('/composer.json', $capturedCommand[2]);
        self::assertStringContains($this->packageRoot, $capturedCommand[2]);
    }

    #[Test]
    public function runReturnsErrorWhenNoComposerJsonFound(): void
    {
        $this->configuration->method('getResolvedPathsForTool')
            ->with('composer-normalize')
            ->willReturn(['/nonexistent/path']);

        $runner = $this->createRunnerWithExitCode(0);
        $request = new ToolRunRequest('composer-normalize', dryRun: false);

        $result = $runner->run($request, new BufferingOutputCollector());

        self::assertSame(1, $result->exitCode);
        self::assertTrue($result->hasWarnings());
        $warnings = $result->getMessagesBySeverity(MessageSeverity::Warning);
        self::assertCount(1, $warnings);
        self::assertStringContainsString('No composer.json files found', $warnings[0]->text);
    }

    #[Test]
    public function runIteratesOverMultiplePaths(): void
    {
        // Use the package root twice to ensure both iterations find composer.json
        $this->configuration->method('getResolvedPathsForTool')
            ->with('composer-normalize')
            ->willReturn([$this->packageRoot, $this->packageRoot]);

        $executionCount = 0;
        $executor = new ProcessExecutor(function () use (&$executionCount): Process {
            ++$executionCount;

            return $this->createMockProcess(0);
        });

        $runner = new ComposerNormalizeRunner($executor, $this->projectEnv, $this->configLoader);
        $request = new ToolRunRequest('composer-normalize', dryRun: false);

        $runner->run($request, new BufferingOutputCollector());

        self::assertSame(2, $executionCount);
    }

    #[Test]
    public function runAggregatesExitCodes(): void
    {
        // Use the package root twice
        $this->configuration->method('getResolvedPathsForTool')
            ->with('composer-normalize')
            ->willReturn([$this->packageRoot, $this->packageRoot]);

        $callCount = 0;
        $executor = new ProcessExecutor(function () use (&$callCount): Process {
            ++$callCount;
            // First call succeeds, second fails
            $exitCode = $callCount === 1 ? 0 : 1;

            return $this->createMockProcess($exitCode);
        });

        $runner = new ComposerNormalizeRunner($executor, $this->projectEnv, $this->configLoader);
        $request = new ToolRunRequest('composer-normalize', dryRun: false);

        $result = $runner->run($request, new BufferingOutputCollector());

        // Should return the non-zero exit code
        self::assertSame(1, $result->exitCode);
    }

    #[Test]
    public function runReturnsSuccessWhenAllPathsSucceed(): void
    {
        $this->configuration->method('getResolvedPathsForTool')
            ->with('composer-normalize')
            ->willReturn([$this->packageRoot]);

        $runner = $this->createRunnerWithExitCode(0);
        $request = new ToolRunRequest('composer-normalize', dryRun: false);

        $result = $runner->run($request, new BufferingOutputCollector());

        self::assertSame(0, $result->exitCode);
        self::assertTrue($result->isSuccessful());
    }

    #[Test]
    public function runSkipsPathsWithoutComposerJson(): void
    {
        // Mix of paths: one with composer.json, one without
        $this->configuration->method('getResolvedPathsForTool')
            ->with('composer-normalize')
            ->willReturn(['/nonexistent/path', $this->packageRoot]);

        $executionCount = 0;
        $executor = new ProcessExecutor(function () use (&$executionCount): Process {
            ++$executionCount;

            return $this->createMockProcess(0);
        });

        $runner = new ComposerNormalizeRunner($executor, $this->projectEnv, $this->configLoader);
        $request = new ToolRunRequest('composer-normalize', dryRun: false);

        $runner->run($request, new BufferingOutputCollector());

        // Only the package root should have been processed
        self::assertSame(1, $executionCount);
    }

    #[Test]
    public function runWritesCheckingMessageToCollector(): void
    {
        $this->configuration->method('getResolvedPathsForTool')
            ->with('composer-normalize')
            ->willReturn([$this->packageRoot]);

        $runner = $this->createRunnerWithExitCode(0);
        $collector = new BufferingOutputCollector();
        $request = new ToolRunRequest('composer-normalize', dryRun: false);

        $runner->run($request, $collector);

        $output = $collector->getOutput();
        self::assertStringContainsString('Checking composer.json:', $output);
    }

    /**
     * Custom assertion for string containment within another string.
     */
    private static function assertStringContains(string $needle, string $haystack): void
    {
        self::assertStringContainsString($needle, $haystack);
    }

    private function createRunner(?ProcessExecutor $executor = null): ComposerNormalizeRunner
    {
        $executor ??= $this->createMockExecutor();

        return new ComposerNormalizeRunner($executor, $this->projectEnv, $this->configLoader);
    }

    /**
     * @param list<string>|null $capturedCommand
     */
    private function createRunnerCapturingCommand(?array &$capturedCommand): ComposerNormalizeRunner
    {
        $executor = $this->createCapturingExecutor($capturedCommand);

        return new ComposerNormalizeRunner($executor, $this->projectEnv, $this->configLoader);
    }

    private function createRunnerWithExitCode(int $exitCode): ComposerNormalizeRunner
    {
        $executor = $this->createMockExecutor($exitCode);

        return new ComposerNormalizeRunner($executor, $this->projectEnv, $this->configLoader);
    }

    private function createMockExecutor(int $exitCode = 0): ProcessExecutor
    {
        $process = $this->createMockProcess($exitCode);

        return new ProcessExecutor(static fn (): Process => $process);
    }

    /**
     * @param list<string>|null $capturedCommand
     */
    private function createCapturingExecutor(?array &$capturedCommand): ProcessExecutor
    {
        return new ProcessExecutor(function (array $command) use (&$capturedCommand): Process {
            $capturedCommand = $command;

            return $this->createMockProcess(0);
        });
    }

    private function createMockProcess(int $exitCode): Process
    {
        $process = $this->createMock(Process::class);
        $process->method('getExitCode')->willReturn($exitCode);
        $process->method('getCommandLine')->willReturn('mocked-command');
        $process->method('run')->willReturn(0);

        return $process;
    }
}

<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Tests\Unit\Tool\Runner;

use Cpsit\QualityTools\Configuration\ConfigurationInterface;
use Cpsit\QualityTools\Configuration\ConfigurationLoaderInterface;
use Cpsit\QualityTools\Messaging\BufferingOutputCollector;
use Cpsit\QualityTools\Service\MemoryOptimizer;
use Cpsit\QualityTools\Service\ProcessExecutor;
use Cpsit\QualityTools\Service\ProjectEnvironment;
use Cpsit\QualityTools\Tool\Runner\DTO\ToolRunRequest;
use Cpsit\QualityTools\Tool\Runner\RectorRunner;
use Cpsit\QualityTools\Utility\MemoryCalculator;
use Cpsit\QualityTools\Utility\ProjectAnalyzer;
use Cpsit\QualityTools\Utility\VendorDirectoryDetector;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

#[CoversClass(RectorRunner::class)]
final class RectorRunnerTest extends TestCase
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
        $this->configuration->method('getToolConfig')
            ->with('rector')
            ->willReturn([]);
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

        self::assertSame(['rector'], $runner->supportedTools());
    }

    #[Test]
    public function runBuildsCorrectCommand(): void
    {
        $capturedCommand = null;
        $runner = $this->createRunnerCapturingCommand($capturedCommand);
        $request = new ToolRunRequest('rector', dryRun: false);

        $runner->run($request, new BufferingOutputCollector());

        self::assertIsArray($capturedCommand);
        self::assertStringEndsWith('/rector', $capturedCommand[0]);
        self::assertSame('process', $capturedCommand[1]);
        self::assertStringStartsWith('--config=', $capturedCommand[2]);
        self::assertStringEndsWith('/config/rector-' . ConfigurationInterface::DEFAULT_RECTOR_LEVEL . '.php', $capturedCommand[2]);
    }

    #[Test]
    public function runPassesDryRunFlag(): void
    {
        $capturedCommand = null;
        $runner = $this->createRunnerCapturingCommand($capturedCommand);
        $request = new ToolRunRequest('rector', dryRun: true);

        $runner->run($request, new BufferingOutputCollector());

        self::assertIsArray($capturedCommand);
        self::assertContains('--dry-run', $capturedCommand);
    }

    #[Test]
    public function runOmitsDryRunFlag(): void
    {
        $capturedCommand = null;
        $runner = $this->createRunnerCapturingCommand($capturedCommand);
        $request = new ToolRunRequest('rector', dryRun: false);

        $runner->run($request, new BufferingOutputCollector());

        self::assertIsArray($capturedCommand);
        self::assertNotContains('--dry-run', $capturedCommand);
    }

    #[Test]
    public function runUsesConfigOverride(): void
    {
        $capturedCommand = null;
        $runner = $this->createRunnerCapturingCommand($capturedCommand);
        $request = new ToolRunRequest('rector', dryRun: false, configOverride: '/custom/rector.php');

        $runner->run($request, new BufferingOutputCollector());

        self::assertIsArray($capturedCommand);
        self::assertContains('--config=/custom/rector.php', $capturedCommand);
    }

    #[Test]
    public function runUsesPathOverride(): void
    {
        $capturedCommand = null;
        $runner = $this->createRunnerCapturingCommand($capturedCommand);
        $request = new ToolRunRequest('rector', dryRun: false, pathOverride: '/custom/path');

        $runner->run($request, new BufferingOutputCollector());

        self::assertIsArray($capturedCommand);
        self::assertContains('/custom/path', $capturedCommand);
    }

    #[Test]
    public function runUsesConfigurationPaths(): void
    {
        $this->configuration->method('getResolvedPathsForTool')
            ->with('rector')
            ->willReturn(['/project/packages', '/project/config']);

        $capturedCommand = null;
        $runner = $this->createRunnerCapturingCommand($capturedCommand);
        $request = new ToolRunRequest('rector', dryRun: false);

        $runner->run($request, new BufferingOutputCollector());

        self::assertIsArray($capturedCommand);
        self::assertContains('/project/packages', $capturedCommand);
        self::assertContains('/project/config', $capturedCommand);
    }

    #[Test]
    public function runFallsBackToProjectRootWhenNoPathsConfigured(): void
    {
        $this->configuration->method('getResolvedPathsForTool')
            ->with('rector')
            ->willReturn([]);

        $capturedCommand = null;
        $runner = $this->createRunnerCapturingCommand($capturedCommand);
        $request = new ToolRunRequest('rector', dryRun: false);

        $runner->run($request, new BufferingOutputCollector());

        self::assertIsArray($capturedCommand);
        self::assertContains($this->packageRoot, $capturedCommand);
    }

    #[Test]
    public function runPrependsPhpMemoryLimitWhenMemoryOptimizerIsPresent(): void
    {
        $memoryOptimizer = new MemoryOptimizer(new ProjectAnalyzer(), new MemoryCalculator());

        $capturedCommand = null;
        $executor = $this->createCapturingExecutor($capturedCommand);
        $runner = new RectorRunner($executor, $this->projectEnv, $this->configLoader, $memoryOptimizer);
        $request = new ToolRunRequest('rector', dryRun: false);

        $runner->run($request, new BufferingOutputCollector());

        self::assertIsArray($capturedCommand);
        self::assertSame('php', $capturedCommand[0]);
        self::assertSame('-d', $capturedCommand[1]);
        self::assertStringStartsWith('memory_limit=', $capturedCommand[2]);
        self::assertMatchesRegularExpression('/^memory_limit=\d+M$/', $capturedCommand[2]);
        self::assertStringEndsWith('/rector', $capturedCommand[3]);
    }

    #[Test]
    public function runSelectsTypo3V13ConfigWhenLevelIsTypo3V13(): void
    {
        $capturedCommand = null;
        $runner = $this->createRunnerCapturingCommandWithLevel('typo3-13', $capturedCommand);
        $request = new ToolRunRequest('rector', dryRun: false);

        $runner->run($request, new BufferingOutputCollector());

        self::assertIsArray($capturedCommand);
        self::assertStringEndsWith('/config/rector-typo3-13.php', $capturedCommand[2]);
    }

    #[Test]
    public function runSelectsTypo3V14ConfigWhenLevelIsTypo3V14(): void
    {
        $capturedCommand = null;
        $runner = $this->createRunnerCapturingCommandWithLevel('typo3-14', $capturedCommand);
        $request = new ToolRunRequest('rector', dryRun: false);

        $runner->run($request, new BufferingOutputCollector());

        self::assertIsArray($capturedCommand);
        self::assertStringEndsWith('/config/rector-typo3-14.php', $capturedCommand[2]);
    }

    #[Test]
    public function runFallsBackToDefaultConfigWhenLevelHasNoVersionedFile(): void
    {
        $capturedCommand = null;
        $runner = $this->createRunnerCapturingCommandWithLevel('unknown-level', $capturedCommand);
        $request = new ToolRunRequest('rector', dryRun: false);

        $runner->run($request, new BufferingOutputCollector());

        self::assertIsArray($capturedCommand);
        self::assertStringEndsWith('/config/rector.php', $capturedCommand[2]);
    }

    #[Test]
    public function runReturnsExitCode(): void
    {
        $runner = $this->createRunnerWithExitCode(42);
        $request = new ToolRunRequest('rector', dryRun: false);

        $result = $runner->run($request, new BufferingOutputCollector());

        self::assertSame(42, $result->exitCode);
    }

    #[Test]
    public function runReturnsSuccessExitCode(): void
    {
        $runner = $this->createRunnerWithExitCode(0);
        $request = new ToolRunRequest('rector', dryRun: false);

        $result = $runner->run($request, new BufferingOutputCollector());

        self::assertSame(0, $result->exitCode);
        self::assertTrue($result->isSuccessful());
    }

    private function createRunner(?ProcessExecutor $executor = null): RectorRunner
    {
        $executor ??= $this->createMockExecutor();

        return new RectorRunner($executor, $this->projectEnv, $this->configLoader);
    }

    /**
     * @param list<string>|null $capturedCommand
     */
    private function createRunnerCapturingCommand(?array &$capturedCommand): RectorRunner
    {
        $executor = $this->createCapturingExecutor($capturedCommand);

        return new RectorRunner($executor, $this->projectEnv, $this->configLoader);
    }

    /**
     * @param list<string>|null $capturedCommand
     */
    private function createRunnerCapturingCommandWithLevel(string $level, ?array &$capturedCommand): RectorRunner
    {
        $configuration = $this->createMock(ConfigurationInterface::class);
        $configuration->method('getToolConfig')->with('rector')->willReturn(['level' => $level]);
        $configuration->method('getResolvedPathsForTool')->willReturn([]);

        $configLoader = $this->createMock(ConfigurationLoaderInterface::class);
        $configLoader->method('load')->willReturn($configuration);

        $executor = $this->createCapturingExecutor($capturedCommand);

        return new RectorRunner($executor, $this->projectEnv, $configLoader);
    }

    private function createRunnerWithExitCode(int $exitCode): RectorRunner
    {
        $executor = $this->createMockExecutor($exitCode);

        return new RectorRunner($executor, $this->projectEnv, $this->configLoader);
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

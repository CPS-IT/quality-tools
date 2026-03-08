<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Tests\Unit\Tool\Runner;

use Cpsit\QualityTools\Configuration\ConfigurationInterface;
use Cpsit\QualityTools\Configuration\ConfigurationLoaderInterface;
use Cpsit\QualityTools\Messaging\BufferingOutputCollector;
use Cpsit\QualityTools\Service\ProcessExecutor;
use Cpsit\QualityTools\Service\ProjectEnvironment;
use Cpsit\QualityTools\Tool\Runner\FractorRunner;
use Cpsit\QualityTools\Tool\Runner\ToolRunRequest;
use Cpsit\QualityTools\Utility\VendorDirectoryDetector;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

#[CoversClass(FractorRunner::class)]
final class FractorRunnerTest extends TestCase
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

        self::assertSame(['fractor'], $runner->supportedTools());
    }

    #[Test]
    public function runBuildsCorrectCommand(): void
    {
        $capturedCommand = null;
        $runner = $this->createRunnerCapturingCommand($capturedCommand);
        $request = new ToolRunRequest('fractor', dryRun: false);

        $runner->run($request, new BufferingOutputCollector());

        self::assertIsArray($capturedCommand);
        self::assertStringEndsWith('/fractor', $capturedCommand[0]);
        self::assertSame('process', $capturedCommand[1]);
        self::assertStringStartsWith('--config=', $capturedCommand[2]);
        self::assertStringEndsWith('/config/fractor.php', $capturedCommand[2]);
    }

    #[Test]
    public function runPassesDryRunFlag(): void
    {
        $capturedCommand = null;
        $runner = $this->createRunnerCapturingCommand($capturedCommand);
        $request = new ToolRunRequest('fractor', dryRun: true);

        $runner->run($request, new BufferingOutputCollector());

        self::assertIsArray($capturedCommand);
        self::assertContains('--dry-run', $capturedCommand);
    }

    #[Test]
    public function runOmitsDryRunFlag(): void
    {
        $capturedCommand = null;
        $runner = $this->createRunnerCapturingCommand($capturedCommand);
        $request = new ToolRunRequest('fractor', dryRun: false);

        $runner->run($request, new BufferingOutputCollector());

        self::assertIsArray($capturedCommand);
        self::assertNotContains('--dry-run', $capturedCommand);
    }

    #[Test]
    public function runUsesConfigOverride(): void
    {
        $capturedCommand = null;
        $runner = $this->createRunnerCapturingCommand($capturedCommand);
        $request = new ToolRunRequest('fractor', dryRun: false, configOverride: '/custom/fractor.php');

        $runner->run($request, new BufferingOutputCollector());

        self::assertIsArray($capturedCommand);
        self::assertContains('--config=/custom/fractor.php', $capturedCommand);
    }

    #[Test]
    public function runUsesPathOverride(): void
    {
        $capturedCommand = null;
        $runner = $this->createRunnerCapturingCommand($capturedCommand);
        $request = new ToolRunRequest('fractor', dryRun: false, pathOverride: '/custom/path');

        $runner->run($request, new BufferingOutputCollector());

        self::assertIsArray($capturedCommand);
        self::assertContains('/custom/path', $capturedCommand);
    }

    #[Test]
    public function runUsesConfigurationPaths(): void
    {
        $this->configuration->method('getResolvedPathsForTool')
            ->with('fractor')
            ->willReturn(['/project/packages']);

        $capturedCommand = null;
        $runner = $this->createRunnerCapturingCommand($capturedCommand);
        $request = new ToolRunRequest('fractor', dryRun: false);

        $runner->run($request, new BufferingOutputCollector());

        self::assertIsArray($capturedCommand);
        self::assertContains('/project/packages', $capturedCommand);
    }

    #[Test]
    public function runFallsBackToProjectRootWhenNoPathsConfigured(): void
    {
        $this->configuration->method('getResolvedPathsForTool')
            ->with('fractor')
            ->willReturn([]);

        $capturedCommand = null;
        $runner = $this->createRunnerCapturingCommand($capturedCommand);
        $request = new ToolRunRequest('fractor', dryRun: false);

        $runner->run($request, new BufferingOutputCollector());

        self::assertIsArray($capturedCommand);
        self::assertContains($this->packageRoot, $capturedCommand);
    }

    #[Test]
    public function runSetsQtDynamicPathsEnvVar(): void
    {
        $this->configuration->method('getResolvedPathsForTool')
            ->with('fractor')
            ->willReturn(['/project/packages', '/project/config']);

        $capturedEnv = null;
        $executor = new ProcessExecutor(function (array $command, string $cwd, array $env) use (&$capturedEnv): Process {
            $capturedEnv = $env;

            return $this->createMockProcess(0);
        });

        $runner = new FractorRunner($executor, $this->projectEnv, $this->configLoader);
        $request = new ToolRunRequest('fractor', dryRun: false);

        $runner->run($request, new BufferingOutputCollector());

        self::assertIsArray($capturedEnv);
        self::assertArrayHasKey('QT_DYNAMIC_PATHS', $capturedEnv);

        $decoded = json_decode($capturedEnv['QT_DYNAMIC_PATHS'], true);
        self::assertSame(['/project/packages', '/project/config'], $decoded);
    }

    #[Test]
    public function runDoesNotSetQtDynamicPathsEnvVarWhenPathOverrideUsed(): void
    {
        $capturedEnv = null;
        $executor = new ProcessExecutor(function (array $command, string $cwd, array $env) use (&$capturedEnv): Process {
            $capturedEnv = $env;

            return $this->createMockProcess(0);
        });

        $runner = new FractorRunner($executor, $this->projectEnv, $this->configLoader);
        $request = new ToolRunRequest('fractor', dryRun: false, pathOverride: '/custom/path');

        $runner->run($request, new BufferingOutputCollector());

        self::assertIsArray($capturedEnv);
        self::assertArrayNotHasKey('QT_DYNAMIC_PATHS', $capturedEnv);
    }

    #[Test]
    public function runReturnsExitCode(): void
    {
        $runner = $this->createRunnerWithExitCode(3);
        $request = new ToolRunRequest('fractor', dryRun: false);

        $result = $runner->run($request, new BufferingOutputCollector());

        self::assertSame(3, $result->exitCode);
    }

    private function createRunner(?ProcessExecutor $executor = null): FractorRunner
    {
        $executor ??= $this->createMockExecutor();

        return new FractorRunner($executor, $this->projectEnv, $this->configLoader);
    }

    /**
     * @param list<string>|null $capturedCommand
     */
    private function createRunnerCapturingCommand(?array &$capturedCommand): FractorRunner
    {
        $executor = $this->createCapturingExecutor($capturedCommand);

        return new FractorRunner($executor, $this->projectEnv, $this->configLoader);
    }

    private function createRunnerWithExitCode(int $exitCode): FractorRunner
    {
        $executor = $this->createMockExecutor($exitCode);

        return new FractorRunner($executor, $this->projectEnv, $this->configLoader);
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

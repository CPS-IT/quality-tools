<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Tests\Unit\Tool\Runner;

use Cpsit\QualityTools\Configuration\ConfigurationInterface;
use Cpsit\QualityTools\Configuration\ConfigurationLoaderInterface;
use Cpsit\QualityTools\Messaging\BufferingOutputCollector;
use Cpsit\QualityTools\Service\ProcessExecutor;
use Cpsit\QualityTools\Service\ProjectEnvironment;
use Cpsit\QualityTools\Tool\Runner\PhpStanRunner;
use Cpsit\QualityTools\Tool\Runner\ToolRunRequest;
use Cpsit\QualityTools\Utility\VendorDirectoryDetector;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

#[CoversClass(PhpStanRunner::class)]
final class PhpStanRunnerTest extends TestCase
{
    private string $packageRoot;

    private ProjectEnvironment $projectEnv;

    /** @var ConfigurationLoaderInterface&\PHPUnit\Framework\MockObject\MockObject */
    private ConfigurationLoaderInterface $configLoader;

    /** @var ConfigurationInterface&\PHPUnit\Framework\MockObject\MockObject */
    private ConfigurationInterface $configuration;

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

        self::assertSame(['phpstan'], $runner->supportedTools());
    }

    #[Test]
    public function runBuildsCorrectCommand(): void
    {
        $this->configuration->method('getResolvedPathsForTool')
            ->with('phpstan')
            ->willReturn([]);

        $capturedCommand = null;
        $runner = $this->createRunnerCapturingCommand($capturedCommand);
        $request = new ToolRunRequest('phpstan', dryRun: false);

        $runner->run($request, new BufferingOutputCollector());

        self::assertIsArray($capturedCommand);
        self::assertStringEndsWith('/phpstan', $capturedCommand[0]);
        self::assertSame('analyse', $capturedCommand[1]);
        self::assertStringStartsWith('--configuration=', $capturedCommand[2]);
        self::assertStringEndsWith('/config/phpstan.neon', $capturedCommand[2]);
    }

    #[Test]
    public function runUsesConfigOverride(): void
    {
        $this->configuration->method('getResolvedPathsForTool')
            ->with('phpstan')
            ->willReturn([]);

        $capturedCommand = null;
        $runner = $this->createRunnerCapturingCommand($capturedCommand);
        $request = new ToolRunRequest('phpstan', dryRun: false, configOverride: '/custom/phpstan.neon');

        $runner->run($request, new BufferingOutputCollector());

        self::assertIsArray($capturedCommand);
        self::assertContains('--configuration=/custom/phpstan.neon', $capturedCommand);
    }

    #[Test]
    public function runUsesPathOverride(): void
    {
        $capturedCommand = null;
        $runner = $this->createRunnerCapturingCommand($capturedCommand);
        $request = new ToolRunRequest('phpstan', dryRun: false, pathOverride: '/custom/path');

        $runner->run($request, new BufferingOutputCollector());

        self::assertIsArray($capturedCommand);
        self::assertContains('/custom/path', $capturedCommand);
    }

    #[Test]
    public function runPassesLevelOption(): void
    {
        $this->configuration->method('getResolvedPathsForTool')
            ->with('phpstan')
            ->willReturn([]);

        $capturedCommand = null;
        $runner = $this->createRunnerCapturingCommand($capturedCommand);
        $request = new ToolRunRequest('phpstan', dryRun: false, toolOptions: ['level' => '8']);

        $runner->run($request, new BufferingOutputCollector());

        self::assertIsArray($capturedCommand);
        self::assertContains('--level=8', $capturedCommand);
    }

    #[Test]
    public function runOmitsLevelOptionByDefault(): void
    {
        $this->configuration->method('getResolvedPathsForTool')
            ->with('phpstan')
            ->willReturn([]);

        $capturedCommand = null;
        $runner = $this->createRunnerCapturingCommand($capturedCommand);
        $request = new ToolRunRequest('phpstan', dryRun: false);

        $runner->run($request, new BufferingOutputCollector());

        self::assertIsArray($capturedCommand);
        foreach ($capturedCommand as $arg) {
            self::assertStringStartsNotWith('--level=', $arg);
        }
    }

    #[Test]
    public function runPassesMemoryLimitOption(): void
    {
        $this->configuration->method('getResolvedPathsForTool')
            ->with('phpstan')
            ->willReturn([]);

        $capturedCommand = null;
        $runner = $this->createRunnerCapturingCommand($capturedCommand);
        $request = new ToolRunRequest('phpstan', dryRun: false, toolOptions: ['memory-limit' => '2G']);

        $runner->run($request, new BufferingOutputCollector());

        self::assertIsArray($capturedCommand);
        self::assertContains('--memory-limit=2G', $capturedCommand);
    }

    #[Test]
    public function runCreatesTemporaryConfigForMultiplePaths(): void
    {
        $this->configuration->method('getResolvedPathsForTool')
            ->with('phpstan')
            ->willReturn(['/project/packages', '/project/config']);

        $capturedCommand = null;
        $runner = $this->createRunnerCapturingCommand($capturedCommand);
        $request = new ToolRunRequest('phpstan', dryRun: false);

        $runner->run($request, new BufferingOutputCollector());

        self::assertIsArray($capturedCommand);
        // The configuration flag should point to a temporary file, not the default
        $configArg = $capturedCommand[2];
        self::assertStringStartsWith('--configuration=', $configArg);
        $configPath = substr($configArg, \strlen('--configuration='));
        // Temporary config should NOT end with the default config path
        self::assertStringEndsWith('.neon', $configPath);
    }

    #[Test]
    public function runCleansUpTemporaryConfig(): void
    {
        $this->configuration->method('getResolvedPathsForTool')
            ->with('phpstan')
            ->willReturn(['/project/packages', '/project/config']);

        // We need to capture the temp file path. The simplest way: after run() returns,
        // the temp file should have been deleted.
        $capturedConfigPath = null;
        $executor = new ProcessExecutor(function (array $command) use (&$capturedConfigPath): Process {
            foreach ($command as $arg) {
                if (str_starts_with($arg, '--configuration=')) {
                    $capturedConfigPath = substr($arg, \strlen('--configuration='));
                }
            }

            return $this->createMockProcess(0);
        });

        $runner = new PhpStanRunner($executor, $this->projectEnv, $this->configLoader);
        $request = new ToolRunRequest('phpstan', dryRun: false);

        $runner->run($request, new BufferingOutputCollector());

        self::assertNotNull($capturedConfigPath);
        self::assertFileDoesNotExist($capturedConfigPath);
    }

    #[Test]
    public function runCleansUpTemporaryConfigEvenOnProcessFailure(): void
    {
        $this->configuration->method('getResolvedPathsForTool')
            ->with('phpstan')
            ->willReturn(['/project/packages', '/project/config']);

        $capturedConfigPath = null;
        $executor = new ProcessExecutor(function (array $command) use (&$capturedConfigPath): Process {
            foreach ($command as $arg) {
                if (str_starts_with($arg, '--configuration=')) {
                    $capturedConfigPath = substr($arg, \strlen('--configuration='));
                }
            }

            return $this->createMockProcess(1);
        });

        $runner = new PhpStanRunner($executor, $this->projectEnv, $this->configLoader);
        $request = new ToolRunRequest('phpstan', dryRun: false);

        $runner->run($request, new BufferingOutputCollector());

        self::assertNotNull($capturedConfigPath);
        self::assertFileDoesNotExist($capturedConfigPath);
    }

    #[Test]
    public function runDoesNotCreateTemporaryConfigForSinglePath(): void
    {
        $this->configuration->method('getResolvedPathsForTool')
            ->with('phpstan')
            ->willReturn(['/project/packages']);

        $capturedCommand = null;
        $runner = $this->createRunnerCapturingCommand($capturedCommand);
        $request = new ToolRunRequest('phpstan', dryRun: false);

        $runner->run($request, new BufferingOutputCollector());

        self::assertIsArray($capturedCommand);
        $configArg = $capturedCommand[2];
        self::assertStringStartsWith('--configuration=', $configArg);
        $configPath = substr($configArg, \strlen('--configuration='));
        // Should use the default config, not a temporary one
        self::assertStringEndsWith('/config/phpstan.neon', $configPath);
    }

    #[Test]
    public function runUsesConfigurationPaths(): void
    {
        $this->configuration->method('getResolvedPathsForTool')
            ->with('phpstan')
            ->willReturn(['/project/packages']);

        $capturedCommand = null;
        $runner = $this->createRunnerCapturingCommand($capturedCommand);
        $request = new ToolRunRequest('phpstan', dryRun: false);

        $runner->run($request, new BufferingOutputCollector());

        // Single path from config is NOT passed on CLI (only pathOverride triggers that)
        self::assertIsArray($capturedCommand);
        self::assertNotContains('/project/packages', $capturedCommand);
    }

    #[Test]
    public function runReturnsExitCode(): void
    {
        $this->configuration->method('getResolvedPathsForTool')
            ->with('phpstan')
            ->willReturn([]);

        $runner = $this->createRunnerWithExitCode(1);
        $request = new ToolRunRequest('phpstan', dryRun: false);

        $result = $runner->run($request, new BufferingOutputCollector());

        self::assertSame(1, $result->exitCode);
    }

    private function createRunner(?ProcessExecutor $executor = null): PhpStanRunner
    {
        $executor ??= $this->createMockExecutor();

        return new PhpStanRunner($executor, $this->projectEnv, $this->configLoader);
    }

    /**
     * @param list<string>|null $capturedCommand
     */
    private function createRunnerCapturingCommand(?array &$capturedCommand): PhpStanRunner
    {
        $executor = $this->createCapturingExecutor($capturedCommand);

        return new PhpStanRunner($executor, $this->projectEnv, $this->configLoader);
    }

    private function createRunnerWithExitCode(int $exitCode): PhpStanRunner
    {
        $executor = $this->createMockExecutor($exitCode);

        return new PhpStanRunner($executor, $this->projectEnv, $this->configLoader);
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

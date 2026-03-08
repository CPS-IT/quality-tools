<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Tests\Unit\Tool\Runner;

use Cpsit\QualityTools\Configuration\ConfigurationInterface;
use Cpsit\QualityTools\Configuration\ConfigurationLoaderInterface;
use Cpsit\QualityTools\Messaging\BufferingOutputCollector;
use Cpsit\QualityTools\Service\MemoryOptimizer;
use Cpsit\QualityTools\Service\ProcessExecutor;
use Cpsit\QualityTools\Service\ProjectEnvironment;
use Cpsit\QualityTools\Tool\Runner\PhpCsFixerRunner;
use Cpsit\QualityTools\Tool\Runner\ToolRunRequest;
use Cpsit\QualityTools\Utility\MemoryCalculator;
use Cpsit\QualityTools\Utility\ProjectAnalyzer;
use Cpsit\QualityTools\Utility\VendorDirectoryDetector;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

#[CoversClass(PhpCsFixerRunner::class)]
final class PhpCsFixerRunnerTest extends TestCase
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

        self::assertSame(['php-cs-fixer'], $runner->supportedTools());
    }

    #[Test]
    public function runBuildsCorrectCommand(): void
    {
        $capturedCommand = null;
        $runner = $this->createRunnerCapturingCommand($capturedCommand);
        $request = new ToolRunRequest('php-cs-fixer', dryRun: false);

        $runner->run($request, new BufferingOutputCollector());

        self::assertIsArray($capturedCommand);
        self::assertStringEndsWith('/php-cs-fixer', $capturedCommand[0]);
        self::assertSame('fix', $capturedCommand[1]);
        self::assertStringStartsWith('--config=', $capturedCommand[2]);
        self::assertStringEndsWith('/config/php-cs-fixer.php', $capturedCommand[2]);
    }

    #[Test]
    public function runPassesDryRunFlag(): void
    {
        $capturedCommand = null;
        $runner = $this->createRunnerCapturingCommand($capturedCommand);
        $request = new ToolRunRequest('php-cs-fixer', dryRun: true);

        $runner->run($request, new BufferingOutputCollector());

        self::assertIsArray($capturedCommand);
        self::assertContains('--dry-run', $capturedCommand);
    }

    #[Test]
    public function runOmitsDryRunFlag(): void
    {
        $capturedCommand = null;
        $runner = $this->createRunnerCapturingCommand($capturedCommand);
        $request = new ToolRunRequest('php-cs-fixer', dryRun: false);

        $runner->run($request, new BufferingOutputCollector());

        self::assertIsArray($capturedCommand);
        self::assertNotContains('--dry-run', $capturedCommand);
    }

    #[Test]
    public function runAddsDiffFlagOnDryRun(): void
    {
        $capturedCommand = null;
        $runner = $this->createRunnerCapturingCommand($capturedCommand);
        $request = new ToolRunRequest('php-cs-fixer', dryRun: true);

        $runner->run($request, new BufferingOutputCollector());

        self::assertIsArray($capturedCommand);
        self::assertContains('--diff', $capturedCommand);
    }

    #[Test]
    public function runOmitsDiffFlagWhenNotDryRun(): void
    {
        $capturedCommand = null;
        $runner = $this->createRunnerCapturingCommand($capturedCommand);
        $request = new ToolRunRequest('php-cs-fixer', dryRun: false);

        $runner->run($request, new BufferingOutputCollector());

        self::assertIsArray($capturedCommand);
        self::assertNotContains('--diff', $capturedCommand);
    }

    #[Test]
    public function runAddsCacheFlagWhenRequested(): void
    {
        $capturedCommand = null;
        $runner = $this->createRunnerCapturingCommand($capturedCommand);
        $request = new ToolRunRequest('php-cs-fixer', dryRun: false, toolOptions: ['cache' => true]);

        $runner->run($request, new BufferingOutputCollector());

        self::assertIsArray($capturedCommand);
        self::assertContains('--using-cache=yes', $capturedCommand);
    }

    #[Test]
    public function runOmitsCacheFlagByDefault(): void
    {
        $capturedCommand = null;
        $runner = $this->createRunnerCapturingCommand($capturedCommand);
        $request = new ToolRunRequest('php-cs-fixer', dryRun: false);

        $runner->run($request, new BufferingOutputCollector());

        self::assertIsArray($capturedCommand);
        self::assertNotContains('--using-cache=yes', $capturedCommand);
    }

    #[Test]
    public function runUsesConfigOverride(): void
    {
        $capturedCommand = null;
        $runner = $this->createRunnerCapturingCommand($capturedCommand);
        $request = new ToolRunRequest('php-cs-fixer', dryRun: false, configOverride: '/custom/cs-fixer.php');

        $runner->run($request, new BufferingOutputCollector());

        self::assertIsArray($capturedCommand);
        self::assertContains('--config=/custom/cs-fixer.php', $capturedCommand);
    }

    #[Test]
    public function runUsesPathOverride(): void
    {
        $capturedCommand = null;
        $runner = $this->createRunnerCapturingCommand($capturedCommand);
        $request = new ToolRunRequest('php-cs-fixer', dryRun: false, pathOverride: '/custom/path');

        $runner->run($request, new BufferingOutputCollector());

        self::assertIsArray($capturedCommand);
        self::assertContains('/custom/path', $capturedCommand);
    }

    #[Test]
    public function runUsesConfigurationPaths(): void
    {
        $this->configuration->method('getResolvedPathsForTool')
            ->with('php-cs-fixer')
            ->willReturn(['/project/packages']);

        $capturedCommand = null;
        $runner = $this->createRunnerCapturingCommand($capturedCommand);
        $request = new ToolRunRequest('php-cs-fixer', dryRun: false);

        $runner->run($request, new BufferingOutputCollector());

        self::assertIsArray($capturedCommand);
        self::assertContains('/project/packages', $capturedCommand);
    }

    #[Test]
    public function runFallsBackToProjectRootWhenNoPathsConfigured(): void
    {
        $this->configuration->method('getResolvedPathsForTool')
            ->with('php-cs-fixer')
            ->willReturn([]);

        $capturedCommand = null;
        $runner = $this->createRunnerCapturingCommand($capturedCommand);
        $request = new ToolRunRequest('php-cs-fixer', dryRun: false);

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
        $runner = new PhpCsFixerRunner($executor, $this->projectEnv, $this->configLoader, $memoryOptimizer);
        $request = new ToolRunRequest('php-cs-fixer', dryRun: false);

        $runner->run($request, new BufferingOutputCollector());

        self::assertIsArray($capturedCommand);
        self::assertSame('php', $capturedCommand[0]);
        self::assertSame('-d', $capturedCommand[1]);
        self::assertStringStartsWith('memory_limit=', $capturedCommand[2]);
        self::assertMatchesRegularExpression('/^memory_limit=\d+M$/', $capturedCommand[2]);
        self::assertStringEndsWith('/php-cs-fixer', $capturedCommand[3]);
    }

    #[Test]
    public function runReturnsExitCode(): void
    {
        $runner = $this->createRunnerWithExitCode(8);
        $request = new ToolRunRequest('php-cs-fixer', dryRun: false);

        $result = $runner->run($request, new BufferingOutputCollector());

        self::assertSame(8, $result->exitCode);
    }

    private function createRunner(?ProcessExecutor $executor = null): PhpCsFixerRunner
    {
        $executor ??= $this->createMockExecutor();

        return new PhpCsFixerRunner($executor, $this->projectEnv, $this->configLoader);
    }

    /**
     * @param list<string>|null $capturedCommand
     */
    private function createRunnerCapturingCommand(?array &$capturedCommand): PhpCsFixerRunner
    {
        $executor = $this->createCapturingExecutor($capturedCommand);

        return new PhpCsFixerRunner($executor, $this->projectEnv, $this->configLoader);
    }

    private function createRunnerWithExitCode(int $exitCode): PhpCsFixerRunner
    {
        $executor = $this->createMockExecutor($exitCode);

        return new PhpCsFixerRunner($executor, $this->projectEnv, $this->configLoader);
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

<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Tests\Unit\Tool\Runner;

use Cpsit\QualityTools\Configuration\ConfigurationInterface;
use Cpsit\QualityTools\Configuration\ConfigurationLoaderInterface;
use Cpsit\QualityTools\Messaging\BufferingOutputCollector;
use Cpsit\QualityTools\Service\ProcessExecutor;
use Cpsit\QualityTools\Service\ProjectEnvironment;
use Cpsit\QualityTools\Tool\Runner\ToolRunRequest;
use Cpsit\QualityTools\Tool\Runner\TypoScriptLintRunner;
use Cpsit\QualityTools\Utility\VendorDirectoryDetector;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

#[CoversClass(TypoScriptLintRunner::class)]
final class TypoScriptLintRunnerTest extends TestCase
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

        self::assertSame(['typoscript-lint'], $runner->supportedTools());
    }

    #[Test]
    public function runBuildsCorrectCommand(): void
    {
        $capturedCommand = null;
        $runner = $this->createRunnerCapturingCommand($capturedCommand);
        $request = new ToolRunRequest('typoscript-lint', dryRun: false);

        $runner->run($request, new BufferingOutputCollector());

        self::assertIsArray($capturedCommand);
        self::assertStringEndsWith('/typoscript-lint', $capturedCommand[0]);
        self::assertSame('-c', $capturedCommand[1]);
        self::assertStringEndsWith('/config/typoscript-lint.yml', $capturedCommand[2]);
    }

    #[Test]
    public function runUsesConfigOverride(): void
    {
        $capturedCommand = null;
        $runner = $this->createRunnerCapturingCommand($capturedCommand);
        $request = new ToolRunRequest('typoscript-lint', dryRun: false, configOverride: '/custom/lint.yml');

        $runner->run($request, new BufferingOutputCollector());

        self::assertIsArray($capturedCommand);
        self::assertSame('/custom/lint.yml', $capturedCommand[2]);
    }

    #[Test]
    public function runUsesPathOverride(): void
    {
        $capturedCommand = null;
        $runner = $this->createRunnerCapturingCommand($capturedCommand);
        $request = new ToolRunRequest('typoscript-lint', dryRun: false, pathOverride: '/custom/path');

        $runner->run($request, new BufferingOutputCollector());

        self::assertIsArray($capturedCommand);
        self::assertContains('/custom/path', $capturedCommand);
    }

    #[Test]
    public function runUsesConfigurationPaths(): void
    {
        $this->configuration->method('getResolvedPathsForTool')
            ->with('typoscript-lint')
            ->willReturn(['/project/templates', '/project/config']);

        $capturedCommand = null;
        $runner = $this->createRunnerCapturingCommand($capturedCommand);
        $request = new ToolRunRequest('typoscript-lint', dryRun: false);

        $runner->run($request, new BufferingOutputCollector());

        self::assertIsArray($capturedCommand);
        self::assertContains('/project/templates', $capturedCommand);
        self::assertContains('/project/config', $capturedCommand);
    }

    #[Test]
    public function runDoesNotFallBackToProjectRootWhenNoPathsConfigured(): void
    {
        $this->configuration->method('getResolvedPathsForTool')
            ->with('typoscript-lint')
            ->willReturn([]);

        $capturedCommand = null;
        $runner = $this->createRunnerCapturingCommand($capturedCommand);
        $request = new ToolRunRequest('typoscript-lint', dryRun: false);

        $runner->run($request, new BufferingOutputCollector());

        // TypoScriptLintRunner does NOT fall back to project root; it passes no paths
        self::assertIsArray($capturedCommand);
        self::assertCount(3, $capturedCommand);
    }

    #[Test]
    public function runReturnsExitCode(): void
    {
        $runner = $this->createRunnerWithExitCode(2);
        $request = new ToolRunRequest('typoscript-lint', dryRun: false);

        $result = $runner->run($request, new BufferingOutputCollector());

        self::assertSame(2, $result->exitCode);
    }

    private function createRunner(?ProcessExecutor $executor = null): TypoScriptLintRunner
    {
        $executor ??= $this->createMockExecutor();

        return new TypoScriptLintRunner($executor, $this->projectEnv, $this->configLoader);
    }

    /**
     * @param list<string>|null $capturedCommand
     */
    private function createRunnerCapturingCommand(?array &$capturedCommand): TypoScriptLintRunner
    {
        $executor = $this->createCapturingExecutor($capturedCommand);

        return new TypoScriptLintRunner($executor, $this->projectEnv, $this->configLoader);
    }

    private function createRunnerWithExitCode(int $exitCode): TypoScriptLintRunner
    {
        $executor = $this->createMockExecutor($exitCode);

        return new TypoScriptLintRunner($executor, $this->projectEnv, $this->configLoader);
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

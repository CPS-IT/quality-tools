<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Tests\Unit\Service;

use Cpsit\QualityTools\Messaging\BufferingOutputCollector;
use Cpsit\QualityTools\Service\ProcessExecutor;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

#[CoversClass(ProcessExecutor::class)]
final class ProcessExecutorTest extends TestCase
{
    #[Test]
    public function executeWithCollectorReturnsExitCode(): void
    {
        $executor = $this->createExecutorWithProcess(exitCode: 0);
        $collector = new BufferingOutputCollector();

        $result = $executor->executeWithCollector(['cmd'], '/tmp', [], $collector);

        self::assertSame(0, $result);
    }

    #[Test]
    public function executeWithCollectorReturnsNonZeroExitCode(): void
    {
        $executor = $this->createExecutorWithProcess(exitCode: 1);
        $collector = new BufferingOutputCollector();

        $result = $executor->executeWithCollector(['cmd'], '/tmp', [], $collector);

        self::assertSame(1, $result);
    }

    #[Test]
    public function executeWithCollectorForwardsStdoutToWrite(): void
    {
        $executor = $this->createExecutorWithProcess(exitCode: 0, stdout: 'hello');
        $collector = new BufferingOutputCollector();

        $executor->executeWithCollector(['cmd'], '/tmp', [], $collector);

        self::assertSame('hello', $collector->getOutput());
    }

    #[Test]
    public function executeWithCollectorForwardsStderrToWriteError(): void
    {
        $executor = $this->createExecutorWithProcess(exitCode: 1, stderr: 'oops');
        $collector = new BufferingOutputCollector();

        $executor->executeWithCollector(['cmd'], '/tmp', [], $collector);

        self::assertSame('oops', $collector->getErrorOutput());
    }

    #[Test]
    public function executeWithCollectorKeepsStdoutAndStderrSeparate(): void
    {
        $executor = $this->createExecutorWithProcess(exitCode: 0, stdout: 'out', stderr: 'err');
        $collector = new BufferingOutputCollector();

        $executor->executeWithCollector(['cmd'], '/tmp', [], $collector);

        self::assertSame('out', $collector->getOutput());
        self::assertSame('err', $collector->getErrorOutput());
    }

    #[Test]
    public function executeWithCollectorPassesArgumentsToFactory(): void
    {
        $capturedCommand = null;
        $capturedCwd = null;
        $capturedEnv = null;

        $factory = function (array $command, string $cwd, array $env) use (&$capturedCommand, &$capturedCwd, &$capturedEnv): Process {
            $capturedCommand = $command;
            $capturedCwd = $cwd;
            $capturedEnv = $env;

            return $this->createMockProcess(exitCode: 0);
        };

        $executor = new ProcessExecutor($factory);
        $collector = new BufferingOutputCollector();

        $executor->executeWithCollector(
            ['phpstan', 'analyse'],
            '/project',
            ['BAR' => 'baz'],
            $collector,
        );

        self::assertSame(['phpstan', 'analyse'], $capturedCommand);
        self::assertSame('/project', $capturedCwd);
        self::assertSame(['BAR' => 'baz'], $capturedEnv);
    }

    #[Test]
    public function defaultFactoryCreatesRealProcess(): void
    {
        $executor = new ProcessExecutor();
        $collector = new BufferingOutputCollector();

        $result = $executor->executeWithCollector(['echo', 'test'], '/tmp', [], $collector);

        self::assertSame(0, $result);
    }

    private function createExecutorWithProcess(
        int $exitCode,
        string $stdout = '',
        string $stderr = '',
    ): ProcessExecutor {
        $process = $this->createMockProcess($exitCode, $stdout, $stderr);

        return new ProcessExecutor(
            static fn (): Process => $process,
        );
    }

    private function createMockProcess(
        int $exitCode,
        string $stdout = '',
        string $stderr = '',
    ): Process {
        $process = $this->createMock(Process::class);
        $process->method('getExitCode')->willReturn($exitCode);
        $process->method('getCommandLine')->willReturn('mocked-command');
        $process->method('run')->willReturnCallback(
            function (?\Closure $callback) use ($stdout, $stderr): int {
                if ($callback !== null) {
                    if ($stdout !== '') {
                        $callback(Process::OUT, $stdout);
                    }
                    if ($stderr !== '') {
                        $callback(Process::ERR, $stderr);
                    }
                }

                return 0;
            },
        );

        return $process;
    }
}

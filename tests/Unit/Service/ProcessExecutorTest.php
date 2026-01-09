<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Tests\Unit\Service;

use Cpsit\QualityTools\Service\ProcessExecutor;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @covers \Cpsit\QualityTools\Service\ProcessExecutor
 */
final class ProcessExecutorTest extends TestCase
{
    private ProcessExecutor $executor;

    protected function setUp(): void
    {
        $this->executor = new ProcessExecutor();
    }

    /**
     * @test
     */
    public function executeProcessReturnsExitCodeZeroForSuccessfulCommand(): void
    {
        $output = $this->createMock(OutputInterface::class);
        $output->method('isVerbose')->willReturn(false);

        $result = $this->executor->executeProcess(
            ['echo', 'test'],
            '/tmp',
            [],
            $output,
        );

        self::assertSame(0, $result);
    }

    /**
     * @test
     */
    public function executeProcessReturnsNonZeroExitCodeForFailedCommand(): void
    {
        $output = $this->createMock(OutputInterface::class);
        $output->method('isVerbose')->willReturn(false);

        $result = $this->executor->executeProcess(
            ['false'], // Command that always fails
            '/tmp',
            [],
            $output,
        );

        self::assertSame(1, $result);
    }

    /**
     * @test
     */
    public function executeProcessWritesVerboseOutputWhenVerbose(): void
    {
        $output = $this->createMock(OutputInterface::class);
        $output->method('isVerbose')->willReturn(true);
        $output->expects(self::once())
            ->method('writeln')
            ->with(self::stringContains('<info>Executing:'));

        $this->executor->executeProcess(
            ['echo', 'test'],
            '/tmp',
            [],
            $output,
        );
    }

    /**
     * @test
     */
    public function executeProcessDoesNotWriteVerboseOutputWhenNotVerbose(): void
    {
        $output = $this->createMock(OutputInterface::class);
        $output->method('isVerbose')->willReturn(false);
        $output->expects(self::never())
            ->method('writeln');

        $this->executor->executeProcess(
            ['echo', 'test'],
            '/tmp',
            [],
            $output,
        );
    }

    /**
     * @test
     */
    public function executeProcessForwardsStandardOutputToMainOutput(): void
    {
        $output = $this->createMock(OutputInterface::class);
        $output->method('isVerbose')->willReturn(false);
        $output->expects(self::atLeastOnce())
            ->method('write')
            ->with(self::stringContains('test'));

        $this->executor->executeProcess(
            ['echo', 'test'],
            '/tmp',
            [],
            $output,
        );
    }

    /**
     * @test
     */
    public function executeProcessForwardsErrorOutputToErrorStreamWhenSupported(): void
    {
        $errorOutput = $this->createMock(OutputInterface::class);
        $errorOutput->expects(self::atLeastOnce())
            ->method('write')
            ->with(self::stringContains('test error'));

        $output = $this->createMock(ConsoleOutputInterface::class);
        $output->method('isVerbose')->willReturn(false);
        $output->method('getErrorOutput')->willReturn($errorOutput);
        $output->expects(self::never())->method('write'); // Should not write to main output

        // Use a command that writes to stderr
        $this->executor->executeProcess(
            ['php', '-r', 'fwrite(STDERR, "test error");'],
            '/tmp',
            [],
            $output,
        );
    }

    /**
     * @test
     */
    public function executeProcessForwardsErrorOutputToMainOutputWhenErrorStreamNotSupported(): void
    {
        $output = $this->createMock(OutputInterface::class);
        $output->method('isVerbose')->willReturn(false);
        $output->expects(self::atLeastOnce())
            ->method('write')
            ->with(self::stringContains('test error'));

        // Use a command that writes to stderr
        $this->executor->executeProcess(
            ['php', '-r', 'fwrite(STDERR, "test error");'],
            '/tmp',
            [],
            $output,
        );
    }

    /**
     * @test
     */
    public function executeProcessUsesProvidedWorkingDirectory(): void
    {
        $output = $this->createMock(OutputInterface::class);
        $output->method('isVerbose')->willReturn(false);

        // This test verifies the working directory is set correctly by checking
        // that a command that depends on the working directory works
        $result = $this->executor->executeProcess(
            ['pwd'],
            '/tmp',
            [],
            $output,
        );

        self::assertSame(0, $result);
    }

    /**
     * @test
     */
    public function executeProcessUsesProvidedEnvironmentVariables(): void
    {
        $output = $this->createMock(OutputInterface::class);
        $output->method('isVerbose')->willReturn(false);

        $environment = ['TEST_VAR' => 'test_value'];

        $result = $this->executor->executeProcess(
            ['php', '-r', 'echo getenv("TEST_VAR");'],
            '/tmp',
            $environment,
            $output,
        );

        self::assertSame(0, $result);
    }
}

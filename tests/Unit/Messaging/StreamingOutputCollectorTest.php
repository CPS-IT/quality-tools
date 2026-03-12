<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Tests\Unit\Messaging;

use Cpsit\QualityTools\Messaging\MessageSeverity;
use Cpsit\QualityTools\Messaging\OutputCollectorInterface;
use Cpsit\QualityTools\Messaging\StreamingOutputCollector;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[CoversClass(StreamingOutputCollector::class)]
final class StreamingOutputCollectorTest extends TestCase
{
    #[Test]
    public function implementsOutputCollector(): void
    {
        $reflection = new \ReflectionClass(StreamingOutputCollector::class);

        $this->assertTrue($reflection->implementsInterface(OutputCollectorInterface::class));
    }

    #[Test]
    public function writeForwardsTextToOutput(): void
    {
        $output = $this->createMock(OutputInterface::class);
        $output->expects($this->once())
            ->method('write')
            ->with('hello');

        $collector = new StreamingOutputCollector($output);
        $collector->write('hello');
    }

    #[Test]
    public function writeIgnoresSeverityParameter(): void
    {
        $output = $this->createMock(OutputInterface::class);
        $output->expects($this->once())
            ->method('write')
            ->with('text');

        $collector = new StreamingOutputCollector($output);
        $collector->write('text', MessageSeverity::Error);
    }

    #[Test]
    public function writeErrorUsesErrorOutputWhenAvailable(): void
    {
        $errorOutput = $this->createMock(OutputInterface::class);
        $errorOutput->expects($this->once())
            ->method('write')
            ->with('error text');

        $output = $this->createMock(ConsoleOutputInterface::class);
        $output->expects($this->once())
            ->method('getErrorOutput')
            ->willReturn($errorOutput);

        $collector = new StreamingOutputCollector($output);
        $collector->writeError('error text');
    }

    #[Test]
    public function writeErrorFallsBackToMainOutputWhenNotConsoleOutput(): void
    {
        $output = $this->createMock(OutputInterface::class);
        $output->expects($this->once())
            ->method('write')
            ->with('error text');

        $collector = new StreamingOutputCollector($output);
        $collector->writeError('error text');
    }
}

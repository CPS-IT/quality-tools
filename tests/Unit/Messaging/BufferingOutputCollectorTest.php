<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Tests\Unit\Messaging;

use Cpsit\QualityTools\Messaging\BufferingOutputCollector;
use Cpsit\QualityTools\Messaging\MessageSeverity;
use Cpsit\QualityTools\Messaging\OutputCollector;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(BufferingOutputCollector::class)]
final class BufferingOutputCollectorTest extends TestCase
{
    #[Test]
    public function implementsOutputCollector(): void
    {
        $collector = new BufferingOutputCollector();

        $this->assertInstanceOf(OutputCollector::class, $collector);
    }

    #[Test]
    public function writeCollectsTextWithDefaultSeverity(): void
    {
        $collector = new BufferingOutputCollector();

        $collector->write('hello');

        $collected = $collector->getCollected();
        $this->assertCount(1, $collected);
        $this->assertSame('hello', $collected[0]['text']);
        $this->assertSame(MessageSeverity::Info, $collected[0]['severity']);
    }

    #[Test]
    public function writeCollectsTextWithExplicitSeverity(): void
    {
        $collector = new BufferingOutputCollector();

        $collector->write('oops', MessageSeverity::Error);

        $collected = $collector->getCollected();
        $this->assertCount(1, $collected);
        $this->assertSame('oops', $collected[0]['text']);
        $this->assertSame(MessageSeverity::Error, $collected[0]['severity']);
    }

    #[Test]
    public function writeCollectsMultipleEntries(): void
    {
        $collector = new BufferingOutputCollector();

        $collector->write('first');
        $collector->write('second', MessageSeverity::Warning);
        $collector->write('third');

        $this->assertCount(3, $collector->getCollected());
    }

    #[Test]
    public function writeErrorCollectsErrorText(): void
    {
        $collector = new BufferingOutputCollector();

        $collector->writeError('error output');

        $errors = $collector->getErrors();
        $this->assertCount(1, $errors);
        $this->assertSame('error output', $errors[0]);
    }

    #[Test]
    public function writeErrorCollectsMultipleEntries(): void
    {
        $collector = new BufferingOutputCollector();

        $collector->writeError('first error');
        $collector->writeError('second error');

        $this->assertCount(2, $collector->getErrors());
    }

    #[Test]
    public function getOutputConcatenatesCollectedText(): void
    {
        $collector = new BufferingOutputCollector();

        $collector->write('hello ');
        $collector->write('world');

        $this->assertSame('hello world', $collector->getOutput());
    }

    #[Test]
    public function getOutputReturnsEmptyStringWhenNothingCollected(): void
    {
        $collector = new BufferingOutputCollector();

        $this->assertSame('', $collector->getOutput());
    }

    #[Test]
    public function getErrorOutputConcatenatesErrors(): void
    {
        $collector = new BufferingOutputCollector();

        $collector->writeError('err1');
        $collector->writeError('err2');

        $this->assertSame('err1err2', $collector->getErrorOutput());
    }

    #[Test]
    public function getErrorOutputReturnsEmptyStringWhenNoErrors(): void
    {
        $collector = new BufferingOutputCollector();

        $this->assertSame('', $collector->getErrorOutput());
    }

    #[Test]
    public function resetClearsBothCollections(): void
    {
        $collector = new BufferingOutputCollector();

        $collector->write('text');
        $collector->writeError('error');

        $collector->reset();

        $this->assertSame([], $collector->getCollected());
        $this->assertSame([], $collector->getErrors());
        $this->assertSame('', $collector->getOutput());
        $this->assertSame('', $collector->getErrorOutput());
    }

    #[Test]
    public function writeAndWriteErrorAreIndependent(): void
    {
        $collector = new BufferingOutputCollector();

        $collector->write('stdout');
        $collector->writeError('stderr');

        $this->assertCount(1, $collector->getCollected());
        $this->assertCount(1, $collector->getErrors());
        $this->assertSame('stdout', $collector->getOutput());
        $this->assertSame('stderr', $collector->getErrorOutput());
    }
}

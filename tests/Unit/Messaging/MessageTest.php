<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Tests\Unit\Messaging;

use Cpsit\QualityTools\Messaging\Message;
use Cpsit\QualityTools\Messaging\MessageSeverity;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Message::class)]
final class MessageTest extends TestCase
{
    #[Test]
    public function constructorSetsProperties(): void
    {
        $context = ['key' => 'value'];
        $message = new Message(MessageSeverity::Warning, 'test text', $context);

        $this->assertSame(MessageSeverity::Warning, $message->severity);
        $this->assertSame('test text', $message->text);
        $this->assertSame($context, $message->context);
    }

    #[Test]
    public function constructorDefaultsContextToEmptyArray(): void
    {
        $message = new Message(MessageSeverity::Info, 'text');

        $this->assertSame([], $message->context);
    }

    #[Test]
    public function infoFactoryCreatesInfoMessage(): void
    {
        $message = Message::info('something happened', ['file' => 'test.php']);

        $this->assertSame(MessageSeverity::Info, $message->severity);
        $this->assertSame('something happened', $message->text);
        $this->assertSame(['file' => 'test.php'], $message->context);
    }

    #[Test]
    public function infoFactoryDefaultsContextToEmptyArray(): void
    {
        $message = Message::info('text');

        $this->assertSame([], $message->context);
    }

    #[Test]
    public function warningFactoryCreatesWarningMessage(): void
    {
        $message = Message::warning('something is off', ['path' => '/tmp']);

        $this->assertSame(MessageSeverity::Warning, $message->severity);
        $this->assertSame('something is off', $message->text);
        $this->assertSame(['path' => '/tmp'], $message->context);
    }

    #[Test]
    public function warningFactoryDefaultsContextToEmptyArray(): void
    {
        $message = Message::warning('text');

        $this->assertSame([], $message->context);
    }

    #[Test]
    public function errorFactoryCreatesErrorMessage(): void
    {
        $message = Message::error('something broke', ['code' => 42]);

        $this->assertSame(MessageSeverity::Error, $message->severity);
        $this->assertSame('something broke', $message->text);
        $this->assertSame(['code' => 42], $message->context);
    }

    #[Test]
    public function errorFactoryDefaultsContextToEmptyArray(): void
    {
        $message = Message::error('text');

        $this->assertSame([], $message->context);
    }

    #[Test]
    public function isImmutable(): void
    {
        $message = Message::info('original');

        $reflection = new \ReflectionClass($message);
        $this->assertTrue($reflection->isReadOnly());
    }
}

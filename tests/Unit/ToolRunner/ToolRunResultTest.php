<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Tests\Unit\ToolRunner;

use Cpsit\QualityTools\Messaging\Message;
use Cpsit\QualityTools\Messaging\MessageSeverity;
use Cpsit\QualityTools\ToolRunner\ToolRunResult;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ToolRunResult::class)]
final class ToolRunResultTest extends TestCase
{
    #[Test]
    public function constructorSetsProperties(): void
    {
        $messages = [Message::info('done')];
        $result = new ToolRunResult(exitCode: 0, messages: $messages);

        $this->assertSame(0, $result->exitCode);
        $this->assertSame($messages, $result->messages);
    }

    #[Test]
    public function constructorDefaultsMessagesToEmptyArray(): void
    {
        $result = new ToolRunResult(exitCode: 1);

        $this->assertSame([], $result->messages);
    }

    #[Test]
    #[DataProvider('provideExitCodesForIsSuccessful')]
    public function isSuccessfulReturnsCorrectValue(int $exitCode, bool $expected): void
    {
        $result = new ToolRunResult(exitCode: $exitCode);

        $this->assertSame($expected, $result->isSuccessful());
    }

    public static function provideExitCodesForIsSuccessful(): \Generator
    {
        yield 'zero is successful' => [0, true];
        yield 'one is not successful' => [1, false];
        yield 'two is not successful' => [2, false];
        yield 'negative is not successful' => [-1, false];
    }

    #[Test]
    public function hasErrorsReturnsTrueWhenErrorMessagePresent(): void
    {
        $result = new ToolRunResult(
            exitCode: 1,
            messages: [
                Message::info('starting'),
                Message::error('something broke'),
            ],
        );

        $this->assertTrue($result->hasErrors());
    }

    #[Test]
    public function hasErrorsReturnsFalseWhenNoErrorMessages(): void
    {
        $result = new ToolRunResult(
            exitCode: 0,
            messages: [
                Message::info('starting'),
                Message::warning('minor issue'),
            ],
        );

        $this->assertFalse($result->hasErrors());
    }

    #[Test]
    public function hasErrorsReturnsFalseWhenNoMessages(): void
    {
        $result = new ToolRunResult(exitCode: 0);

        $this->assertFalse($result->hasErrors());
    }

    #[Test]
    public function hasWarningsReturnsTrueWhenWarningMessagePresent(): void
    {
        $result = new ToolRunResult(
            exitCode: 0,
            messages: [
                Message::info('starting'),
                Message::warning('minor issue'),
            ],
        );

        $this->assertTrue($result->hasWarnings());
    }

    #[Test]
    public function hasWarningsReturnsFalseWhenNoWarningMessages(): void
    {
        $result = new ToolRunResult(
            exitCode: 0,
            messages: [Message::info('all good')],
        );

        $this->assertFalse($result->hasWarnings());
    }

    #[Test]
    public function hasWarningsReturnsFalseWhenNoMessages(): void
    {
        $result = new ToolRunResult(exitCode: 0);

        $this->assertFalse($result->hasWarnings());
    }

    #[Test]
    public function getMessagesBySeverityFiltersCorrectly(): void
    {
        $info = Message::info('info message');
        $warning = Message::warning('warning message');
        $error = Message::error('error message');
        $anotherInfo = Message::info('another info');

        $result = new ToolRunResult(
            exitCode: 0,
            messages: [$info, $warning, $error, $anotherInfo],
        );

        $infoMessages = $result->getMessagesBySeverity(MessageSeverity::Info);
        $this->assertCount(2, $infoMessages);
        $this->assertSame($info, $infoMessages[0]);
        $this->assertSame($anotherInfo, $infoMessages[1]);

        $warningMessages = $result->getMessagesBySeverity(MessageSeverity::Warning);
        $this->assertCount(1, $warningMessages);
        $this->assertSame($warning, $warningMessages[0]);

        $errorMessages = $result->getMessagesBySeverity(MessageSeverity::Error);
        $this->assertCount(1, $errorMessages);
        $this->assertSame($error, $errorMessages[0]);
    }

    #[Test]
    public function getMessagesBySeverityReturnsEmptyArrayWhenNoMatch(): void
    {
        $result = new ToolRunResult(
            exitCode: 0,
            messages: [Message::info('only info')],
        );

        $this->assertSame([], $result->getMessagesBySeverity(MessageSeverity::Error));
    }

    #[Test]
    public function getMessagesBySeverityReturnsEmptyArrayWhenNoMessages(): void
    {
        $result = new ToolRunResult(exitCode: 0);

        $this->assertSame([], $result->getMessagesBySeverity(MessageSeverity::Info));
    }

    #[Test]
    public function isImmutable(): void
    {
        $result = new ToolRunResult(exitCode: 0);

        $reflection = new \ReflectionClass($result);
        $this->assertTrue($reflection->isReadOnly());
    }
}

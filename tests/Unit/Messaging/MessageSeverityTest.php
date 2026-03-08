<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Tests\Unit\Messaging;

use Cpsit\QualityTools\Messaging\MessageSeverity;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(MessageSeverity::class)]
final class MessageSeverityTest extends TestCase
{
    #[Test]
    public function enumHasThreeCases(): void
    {
        $cases = MessageSeverity::cases();

        $this->assertCount(3, $cases);
    }

    #[Test]
    #[DataProvider('provideSeverityCases')]
    public function caseHasExpectedValue(MessageSeverity $case, string $expectedValue): void
    {
        $this->assertSame($expectedValue, $case->value);
    }

    public static function provideSeverityCases(): \Generator
    {
        yield 'info' => [MessageSeverity::Info, 'info'];
        yield 'warning' => [MessageSeverity::Warning, 'warning'];
        yield 'error' => [MessageSeverity::Error, 'error'];
    }

    #[Test]
    #[DataProvider('provideSeverityFromString')]
    public function canBeCreatedFromString(string $value, MessageSeverity $expected): void
    {
        $this->assertSame($expected, MessageSeverity::from($value));
    }

    public static function provideSeverityFromString(): \Generator
    {
        yield 'info' => ['info', MessageSeverity::Info];
        yield 'warning' => ['warning', MessageSeverity::Warning];
        yield 'error' => ['error', MessageSeverity::Error];
    }

    #[Test]
    public function tryFromReturnsNullForInvalidValue(): void
    {
        $this->assertNull(MessageSeverity::tryFrom('invalid'));
    }
}

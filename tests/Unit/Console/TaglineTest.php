<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Tests\Unit\Console;

use Cpsit\QualityTools\Console\Tagline;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Tagline::class)]
final class TaglineTest extends TestCase
{
    #[Test]
    public function randomReturnsStringStartingWithPrefix(): void
    {
        $result = Tagline::random();

        $this->assertStringStartsWith('qt;) ', $result);
    }

    #[Test]
    public function randomReturnsKnownTagline(): void
    {
        $result = Tagline::random();
        $taglineText = substr($result, 5);

        $this->assertContains($taglineText, Tagline::all());
    }

    #[Test]
    public function allReturnsNonEmptyArray(): void
    {
        $taglines = Tagline::all();

        $this->assertNotEmpty($taglines);
        $this->assertContainsOnly('string', $taglines);
    }
}

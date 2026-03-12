<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Console;

final class Tagline
{
    /**
     * @var list<string>
     */
    private const array TAGLINES = [
        'I will help you cutify your code.',
        'Cutifying your code since 2026.',
        'Making your code qt, one fix at a time.',
        'Your code deserves to be a qt.',
        'Lint it till it is qt.',
        'Too qt to fail.',
        'Every codebase deserves a little qt time.',
        'Putting the qt in quality.',
        'Stay qt, stay clean.',
        'Because ugly code is not qt.',
        'Feeling qt today!',
    ];

    public static function random(): string
    {
        return 'qt;) ' . self::TAGLINES[array_rand(self::TAGLINES)];
    }

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return self::TAGLINES;
    }
}

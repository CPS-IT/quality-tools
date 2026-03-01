<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Console\Command;

/**
 * Interface for tool-specific commands.
 */
interface ToolCommandInterface
{
    /**
     * List of known quality tools.
     */
    public const KNOWN_TOOLS = [
        'rector',
        'phpstan',
        'fractor',
        'php-cs-fixer',
        'typoscript-lint',
        'composer-normalize',
        'editorconfig',
        'codeception',
    ];

    /**
     * Get the name of the tool (e.g., 'rector', 'fractor', 'phpstan').
     * Tool commands must return their tool name.
     */
    public function getToolName(): string;
}

<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Tool;

/**
 * Single source of truth for known quality tool names.
 *
 * Used by tool runners, commands, and configuration validators
 * to ensure consistent tool identification across the system.
 */
enum ToolName: string
{
    case Rector = 'rector';
    case PhpStan = 'phpstan';
    case Fractor = 'fractor';
    case PhpCsFixer = 'php-cs-fixer';
    case TypoScriptLint = 'typoscript-lint';
    case ComposerNormalize = 'composer-normalize';
    case EditorConfig = 'editorconfig';
    case Codeception = 'codeception';
}

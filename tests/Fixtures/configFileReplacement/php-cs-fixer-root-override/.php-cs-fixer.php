<?php

declare(strict_types=1);

$finder = PhpCsFixer\Finder::create()
    ->in([
        'custom-php-cs-fixer-root-path/',
    ])
    ->exclude(['vendor', 'node_modules']);

return (new PhpCsFixer\Config())
    ->setRules([
        '@PSR12' => true,
        'array_syntax' => ['syntax' => 'short'],
    ])
    ->setFinder($finder);
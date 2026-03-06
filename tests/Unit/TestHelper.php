<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Tests\Unit;

/**
 * Helper class for unit tests providing common utilities and test data.
 */
final class TestHelper
{
    /**
     * Create a temporary directory for testing.
     */
    public static function createTempDirectory(string $prefix = 'qt_test_'): string
    {
        $tempDir = sys_get_temp_dir() . '/' . $prefix . uniqid();

        if (!mkdir($tempDir, 0o777, true)) {
            throw new \RuntimeException("Failed to create temp directory: {$tempDir}");
        }

        return $tempDir;
    }

    /**
     * Remove a directory and all its contents recursively.
     */
    public static function removeDirectory(string $directory): void
    {
        if (!file_exists($directory)) {
            return;
        }

        // Handle symbolic links
        if (is_link($directory)) {
            unlink($directory);

            return;
        }

        if (!is_dir($directory)) {
            unlink($directory);

            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($iterator as $file) {
            if ($file->isLink()) {
                unlink($file->getPathname());
            } elseif ($file->isDir()) {
                rmdir($file->getPathname());
            } else {
                unlink($file->getPathname());
            }
        }

        rmdir($directory);
    }

    /**
     * Create a composer.json file with specified content.
     */
    public static function createComposerJson(string $directory, array $content): string
    {
        $composerFile = $directory . '/composer.json';
        $jsonContent = json_encode($content, JSON_PRETTY_PRINT);

        if (file_put_contents($composerFile, $jsonContent) === false) {
            throw new \RuntimeException("Failed to create composer.json in {$directory}");
        }

        return $composerFile;
    }

    /**
     * Get sample composer.json content for different project types.
     */
    public static function getComposerContent(string $type): array
    {
        return match ($type) {
            'typo3-core' => [
                'name' => 'test/typo3-core-project',
                'type' => 'project',
                'require' => [
                    'typo3/cms-core' => '^13.4',
                ],
            ],
            'typo3-minimal' => [
                'name' => 'test/typo3-minimal-project',
                'type' => 'project',
                'require' => [
                    'typo3/minimal' => '^13.4',
                ],
            ],
            'typo3-cms' => [
                'name' => 'test/typo3-cms-project',
                'type' => 'project',
                'require' => [
                    'typo3/cms' => '^13.4',
                ],
            ],
            'typo3-dev' => [
                'name' => 'test/typo3-dev-project',
                'type' => 'project',
                'require' => [
                    'symfony/console' => '^7.0',
                ],
                'require-dev' => [
                    'typo3/cms-core' => '^13.4',
                ],
            ],
            'non-typo3' => [
                'name' => 'test/non-typo3-project',
                'type' => 'project',
                'require' => [
                    'symfony/console' => '^7.0',
                    'doctrine/orm' => '^3.0',
                ],
            ],
            'empty' => [],
            default => throw new \InvalidArgumentException("Unknown project type: {$type}"),
        };
    }

    /**
     * Create a nested directory structure for testing traversal.
     */
    public static function createNestedStructure(string $basePath, int $depth): array
    {
        $paths = [$basePath];
        $currentPath = $basePath;

        mkdir($currentPath, 0o777, true);

        for ($i = 1; $i <= $depth; ++$i) {
            $currentPath .= '/level' . $i;
            mkdir($currentPath, 0o777, true);
            $paths[] = $currentPath;
        }

        return $paths;
    }

    /**
     * Assert that a string contains all expected substrings.
     */
    public static function assertStringContainsAll(array $needles, string $haystack, string $message = ''): void
    {
        foreach ($needles as $needle) {
            if (!str_contains($haystack, (string) $needle)) {
                throw new \PHPUnit\Framework\AssertionFailedError($message ?: "Failed asserting that '{$haystack}' contains '{$needle}'");
            }
        }
    }

    /**
     * Get the project root path for tests.
     */
    public static function getProjectRoot(): string
    {
        return \dirname(__DIR__, 2);
    }

    /**
     * Get the fixtures directory path.
     */
    public static function getFixturesPath(): string
    {
        return __DIR__ . '/../Fixtures';
    }

    /**
     * Backup and restore environment variables for testing.
     */
    public static function withEnvironment(array $variables, callable $callback): mixed
    {
        $originalPutenv = [];
        $originalEnv = [];
        $originalServer = [];

        // Backup and set across all superglobals
        foreach ($variables as $key => $value) {
            $originalPutenv[$key] = getenv($key);
            $originalEnv[$key] = $_ENV[$key] ?? null;
            $originalServer[$key] = $_SERVER[$key] ?? null;

            putenv($key . '=' . $value);
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }

        try {
            return $callback();
        } finally {
            // Restore original values
            foreach ($variables as $key => $ignored) {
                if ($originalPutenv[$key] === false) {
                    putenv($key);
                } else {
                    putenv($key . '=' . $originalPutenv[$key]);
                }

                if ($originalEnv[$key] === null) {
                    unset($_ENV[$key]);
                } else {
                    $_ENV[$key] = $originalEnv[$key];
                }

                if ($originalServer[$key] === null) {
                    unset($_SERVER[$key]);
                } else {
                    $_SERVER[$key] = $originalServer[$key];
                }
            }
        }
    }

    /**
     * Create vendor directory structure with cpsit/quality-tools package
     * This supports both app/vendor and vendor patterns for dynamic detection.
     */
    public static function createVendorStructure(string $projectRoot, bool $useAppVendor = false, bool $includeConfigFiles = false): string
    {
        $vendorDir = $useAppVendor ? $projectRoot . '/app/vendor' : $projectRoot . '/vendor';
        $qualityToolsDir = $vendorDir . '/cpsit/quality-tools';
        $configDir = $qualityToolsDir . '/config';
        $binDir = $vendorDir . '/bin';

        // Create directories
        mkdir($configDir, 0o777, true);
        mkdir($binDir, 0o777, true);

        // Create minimal configuration files for testing if requested
        if ($includeConfigFiles) {
            self::createMockConfigurationFiles($configDir);
        }

        return $vendorDir;
    }

    /**
     * Create minimal mock configuration files for tool testing.
     */
    public static function createMockConfigurationFiles(string $configDir): void
    {
        // PHP CS Fixer configuration
        $phpCsFixerConfig = <<<'PHP'
            <?php

            declare(strict_types=1);

            use PhpCsFixer\Config;

            return (new Config())
                ->setRules([
                    '@PSR12' => true,
                    'array_syntax' => ['syntax' => 'short'],
                    'binary_operator_spaces' => true,
                    'blank_line_after_namespace' => true,
                    'blank_line_after_opening_tag' => true,
                    'blank_line_before_statement' => [
                        'statements' => ['return'],
                    ],
                    'cast_spaces' => true,
                    'concat_space' => ['spacing' => 'one'],
                    'declare_equal_normalize' => true,
                    'function_typehint_space' => true,
                    'include' => true,
                    'lowercase_cast' => true,
                    'no_blank_lines_after_class_opening' => true,
                    'no_blank_lines_after_phpdoc' => true,
                    'no_empty_statement' => true,
                    'no_extra_blank_lines' => true,
                    'no_leading_import_slash' => true,
                    'no_leading_namespace_whitespace' => true,
                    'no_trailing_comma_in_singleline_array' => true,
                    'no_unused_imports' => true,
                    'no_whitespace_in_blank_line' => true,
                    'object_operator_without_whitespace' => true,
                    'ordered_imports' => ['sort_algorithm' => 'alpha'],
                    'return_type_declaration' => true,
                    'short_scalar_cast' => true,
                    'single_blank_line_before_namespace' => true,
                    'single_quote' => true,
                    'ternary_operator_spaces' => true,
                    'trailing_comma_in_multiline' => true,
                    'trim_array_spaces' => true,
                    'unary_operator_spaces' => true,
                    'whitespace_after_comma_in_array' => true,
                ])
                ->setFinder(
                    \PhpCsFixer\Finder::create()
                        ->in(__DIR__ . '/../../..')
                        ->exclude(['var', 'vendor', 'public', '_assets', 'fileadmin', 'typo3'])
                        ->name('*.php')
                        ->ignoreDotFiles(true)
                        ->ignoreVCS(true)
                );
            PHP;

        // Rector configuration
        $rectorConfig = <<<'PHP'
            <?php

            declare(strict_types=1);

            use Rector\Config\RectorConfig;

            return RectorConfig::configure()
                ->withPaths([
                    __DIR__ . '/../../..',
                ])
                ->withSkip([
                    __DIR__ . '/../../../var',
                    __DIR__ . '/../../../vendor',
                    __DIR__ . '/../../../public',
                    __DIR__ . '/../../../_assets',
                    __DIR__ . '/../../../fileadmin',
                    __DIR__ . '/../../../typo3',
                ])
                ->withPhpSets(php83: true)
                ->withPreparedSets(
                    deadCode: true,
                    codeQuality: true,
                    typeDeclarations: true,
                    earlyReturn: true,
                    strictBooleans: true
                );
            PHP;

        // PHPStan configuration
        $phpstanConfig = <<<'NEON'
            parameters:
                level: 6
                paths:
                    - %currentWorkingDirectory%/packages
                    - %currentWorkingDirectory%/config/system
                excludePaths:
                    - %currentWorkingDirectory%/packages/*/Tests/*
                    - %currentWorkingDirectory%/packages/*/tests/*
                    - %currentWorkingDirectory%/var/*
                    - %currentWorkingDirectory%/vendor/*
                    - %currentWorkingDirectory%/public/*
                checkGenericClassInNonGenericObjectType: false
                checkMissingIterableValueType: false
                treatPhpDocTypesAsCertain: false
                ignoreErrors: []
            NEON;

        // TypoScript Lint configuration
        $typoscriptLintConfig = <<<'YAML'
            paths:
              - packages/
              - config/sites/
            excludePatterns:
              - "*.backup"
              - "*~"
              - "*.orig"
              - "*.rej"
              - "*.swp"
            sniffs:
              - class: Indentation
                parameters:
                  indentPerLevel: 2
                  useSpaces: true
              - class: RepeatingRValue
              - class: DeadCode
              - class: OperatorWhitespace
              - class: DuplicateAssignment
              - class: EmptySection
              - class: InvalidCommentPosition
              - class: MissingVendorPrefix
            YAML;

        // Write configuration files
        file_put_contents($configDir . '/php-cs-fixer.php', $phpCsFixerConfig);
        file_put_contents($configDir . '/rector.php', $rectorConfig);
        file_put_contents($configDir . '/phpstan.neon', $phpstanConfig);
        file_put_contents($configDir . '/typoscript-lint.yml', $typoscriptLintConfig);
    }

    /**
     * Create mock executables in vendor/bin directory.
     */
    public static function createMockExecutables(string $vendorBinDir, array $executables): void
    {
        foreach ($executables as $executable) {
            $executablePath = $vendorBinDir . '/' . $executable;
            // Use the specific message format expected by tests
            $message = $executable === 'composer-normalize'
                ? 'Composer normalize executed successfully'
                : ucfirst((string) $executable) . ' executed successfully';
            file_put_contents($executablePath, "#!/bin/bash\necho '{$message}'\nexit 0\n");
            chmod($executablePath, 0o755);
        }
    }

    /**
     * Normalize console output by removing ANSI codes and formatting.
     */
    public static function normalizeConsoleOutput(string $output): string
    {
        // Strip ANSI escape codes
        $output = preg_replace('/\x1b\[[0-9;]*m/', '', $output);
        // Normalize line endings
        $output = str_replace(\PHP_EOL, "\n", $output);
        // Handle cases where a hyphen is split from a word by line break (preserve hyphen)
        $output = preg_replace('/(\w)-\s*\n\s*(\w)/', '$1-$2', $output);
        // Remove line breaks that split words without hyphens (like .quality)
        $output = preg_replace('/(\w|\.)\s*\n\s*(\w)/', '$1$2', (string) $output);
        // Compress whitespace
        $output = preg_replace('/\s+/', ' ', (string) $output);

        // Trim result
        return trim((string) $output);
    }
}

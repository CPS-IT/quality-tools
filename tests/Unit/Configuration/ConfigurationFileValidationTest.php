<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Tests\Unit\Configuration;

use Cpsit\QualityTools\Tests\Support\ConfigurationBuilder;
use Cpsit\QualityTools\Tests\Unit\TestHelper;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for configuration file validation across different tools.
 * 
 * Validates that custom tool configuration files have correct syntax
 * and can be parsed by their respective tools.
 */
final class ConfigurationFileValidationTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = TestHelper::createTempDirectory('config_file_validation_test_');
    }

    protected function tearDown(): void
    {
        TestHelper::removeDirectory($this->tempDir);
    }

    /**
     * Test that valid Rector configuration files are accepted.
     */
    #[DataProvider('validRectorConfigProvider')]
    public function testValidRectorConfigurationFiles(string $configContent, string $description): void
    {
        $configFile = $this->tempDir . '/rector.php';
        file_put_contents($configFile, $configContent);

        $this->assertTrue(
            $this->validateRectorConfigSyntax($configFile),
            "Valid Rector config should pass validation: {$description}"
        );
    }

    /**
     * Test that invalid Rector configuration files are rejected.
     */
    #[DataProvider('invalidRectorConfigProvider')]
    public function testInvalidRectorConfigurationFiles(string $configContent, string $description): void
    {
        $configFile = $this->tempDir . '/rector.php';
        file_put_contents($configFile, $configContent);

        $this->assertFalse(
            $this->validateRectorConfigSyntax($configFile),
            "Invalid Rector config should fail validation: {$description}"
        );
    }

    /**
     * Test that valid PHPStan configuration files are accepted.
     */
    #[DataProvider('validPhpstanConfigProvider')]
    public function testValidPhpstanConfigurationFiles(string $configContent, string $description): void
    {
        $configFile = $this->tempDir . '/phpstan.neon';
        file_put_contents($configFile, $configContent);

        $this->assertTrue(
            $this->validatePhpstanConfigSyntax($configFile),
            "Valid PHPStan config should pass validation: {$description}"
        );
    }

    /**
     * Test that invalid PHPStan configuration files are rejected.
     */
    #[DataProvider('invalidPhpstanConfigProvider')]
    public function testInvalidPhpstanConfigurationFiles(string $configContent, string $description): void
    {
        $configFile = $this->tempDir . '/phpstan.neon';
        file_put_contents($configFile, $configContent);

        $this->assertFalse(
            $this->validatePhpstanConfigSyntax($configFile),
            "Invalid PHPStan config should fail validation: {$description}"
        );
    }

    /**
     * Test that valid Fractor configuration files are accepted.
     */
    #[DataProvider('validFractorConfigProvider')]
    public function testValidFractorConfigurationFiles(string $configContent, string $description): void
    {
        $configFile = $this->tempDir . '/fractor.php';
        file_put_contents($configFile, $configContent);

        $this->assertTrue(
            $this->validateFractorConfigSyntax($configFile),
            "Valid Fractor config should pass validation: {$description}"
        );
    }

    /**
     * Test configuration file permission and accessibility.
     */
    public function testConfigurationFilePermissions(): void
    {
        $configFile = $this->tempDir . '/rector.php';
        $validConfig = self::validRectorConfigProvider()[0][0];
        file_put_contents($configFile, $validConfig);

        // Test readable file
        $this->assertTrue(
            $this->validateConfigFileAccess($configFile),
            'Readable config file should pass access validation'
        );

        // Test unreadable file (if we can make it unreadable)
        if (chmod($configFile, 0000)) {
            $this->assertFalse(
                $this->validateConfigFileAccess($configFile),
                'Unreadable config file should fail access validation'
            );
            chmod($configFile, 0644); // Restore for cleanup
        }
    }

    /**
     * Test configuration file size limits.
     */
    public function testConfigurationFileSizeLimits(): void
    {
        // Test normal size file
        $normalConfig = self::validRectorConfigProvider()[0][0];
        $normalFile = $this->tempDir . '/normal.php';
        file_put_contents($normalFile, $normalConfig);

        $this->assertTrue(
            $this->validateConfigFileSize($normalFile),
            'Normal size config file should pass size validation'
        );

        // Test oversized file (1MB limit for config files)
        $oversizedConfig = $normalConfig . str_repeat('// ' . str_repeat('x', 1000) . "\n", 1100);
        $oversizedFile = $this->tempDir . '/oversized.php';
        file_put_contents($oversizedFile, $oversizedConfig);

        $this->assertFalse(
            $this->validateConfigFileSize($oversizedFile),
            'Oversized config file should fail size validation'
        );
    }

    /**
     * Data provider for valid Rector configurations.
     */
    public static function validRectorConfigProvider(): array
    {
        return [
            'basic_rector_config' => [
                '<?php
declare(strict_types=1);
use Rector\Config\RectorConfig;
return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->paths([__DIR__ . "/src"]);
};',
                'Basic Rector configuration with paths'
            ],
            'rector_with_rules' => [
                '<?php
declare(strict_types=1);
use Rector\Config\RectorConfig;
use Rector\TypeDeclaration\Rector\Property\AddTypeDeclarationToPropertiesRector;
return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->paths([__DIR__ . "/src"]);
    $rectorConfig->rules([AddTypeDeclarationToPropertiesRector::class]);
};',
                'Rector configuration with specific rules'
            ],
            'rector_with_skip' => [
                '<?php
declare(strict_types=1);
use Rector\Config\RectorConfig;
return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->paths([__DIR__ . "/src"]);
    $rectorConfig->skip([__DIR__ . "/src/Legacy"]);
};',
                'Rector configuration with skip patterns'
            ],
        ];
    }

    /**
     * Data provider for invalid Rector configurations.
     */
    public static function invalidRectorConfigProvider(): array
    {
        return [
            'syntax_error' => [
                '<?php
declare(strict_types=1);
use Rector\Config\RectorConfig;
return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->paths([__DIR__ . "/src"]);
    // Missing semicolon
    $rectorConfig->rules([SomeRule::class])
};',
                'Rector config with syntax error'
            ],
            'no_php_tag' => [
                'return static function(): void {};',
                'Rector config without PHP opening tag'
            ],
            'empty_file' => [
                '',
                'Empty Rector config file'
            ],
        ];
    }

    /**
     * Data provider for valid PHPStan configurations.
     */
    public static function validPhpstanConfigProvider(): array
    {
        return [
            'basic_phpstan_config' => [
                'parameters:
	level: 6
	paths:
		- src/
',
                'Basic PHPStan configuration'
            ],
            'phpstan_with_excludes' => [
                'parameters:
	level: 8
	paths:
		- src/
	excludePaths:
		- src/Legacy/
		- */Tests/*
',
                'PHPStan configuration with excludes'
            ],
            'phpstan_with_extensions' => [
                'parameters:
	level: 6
	paths:
		- src/
extensions:
	- phpstan-strict-rules
',
                'PHPStan configuration with extensions'
            ],
        ];
    }

    /**
     * Data provider for invalid PHPStan configurations.
     */
    public static function invalidPhpstanConfigProvider(): array
    {
        return [
            'invalid_yaml_syntax' => [
                'parameters:
level: 6
  paths:
- src/',
                'PHPStan config with invalid YAML indentation'
            ],
            'invalid_level' => [
                'parameters:
	level: 15
	paths:
		- src/',
                'PHPStan config with invalid level (>8)'
            ],
            'empty_file' => [
                '',
                'Empty PHPStan config file'
            ],
        ];
    }

    /**
     * Data provider for valid Fractor configurations.
     */
    public static function validFractorConfigProvider(): array
    {
        return [
            'basic_fractor_config' => [
                '<?php
declare(strict_types=1);
use Fractor\Config\FractorConfig;
return static function (FractorConfig $fractorConfig): void {
    $fractorConfig->paths([__DIR__ . "/Configuration/TypoScript"]);
};',
                'Basic Fractor configuration'
            ],
        ];
    }

    /**
     * Validate Rector configuration syntax.
     */
    private function validateRectorConfigSyntax(string $filePath): bool
    {
        if (!file_exists($filePath) || !is_readable($filePath)) {
            return false;
        }

        // Check basic PHP syntax
        $output = [];
        $returnCode = 0;
        exec("php -l " . escapeshellarg($filePath) . " 2>&1", $output, $returnCode);
        
        if ($returnCode !== 0) {
            return false;
        }

        // Check that file contains expected Rector patterns
        $content = file_get_contents($filePath);
        
        if (!str_contains($content, '<?php') || 
            !str_contains($content, 'RectorConfig')) {
            return false;
        }

        return true;
    }

    /**
     * Validate PHPStan configuration syntax.
     */
    private function validatePhpstanConfigSyntax(string $filePath): bool
    {
        if (!file_exists($filePath) || !is_readable($filePath)) {
            return false;
        }

        try {
            $content = file_get_contents($filePath);
            
            // Basic YAML validation - check for parameters section
            if (empty(trim($content))) {
                return false;
            }

            // Try to parse basic YAML structure
            if (function_exists('yaml_parse')) {
                $parsed = yaml_parse($content);
                if ($parsed === false) {
                    return false;
                }
                
                // Additional validation for PHPStan specific structure
                if (isset($parsed['parameters']['level']) && $parsed['parameters']['level'] > 8) {
                    return false;
                }
                
                return true;
            }

            // Fallback: check for basic YAML structure and validate level
            if (!str_contains($content, 'parameters:') && !str_contains($content, 'level:')) {
                return false;
            }
            
            // Check for invalid level values
            if (preg_match('/level:\s*(\d+)/', $content, $matches) && (int)$matches[1] > 8) {
                return false;
            }
            
            // Basic YAML indentation check
            $lines = explode("\n", $content);
            foreach ($lines as $line) {
                if (trim($line) && !preg_match('/^(\t+|  +|\s*[-#]|\s*[a-zA-Z_])/', $line)) {
                    return false; // Invalid indentation
                }
            }
            
            return true;
            
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Validate Fractor configuration syntax.
     */
    private function validateFractorConfigSyntax(string $filePath): bool
    {
        if (!file_exists($filePath) || !is_readable($filePath)) {
            return false;
        }

        // Check basic PHP syntax
        $output = [];
        $returnCode = 0;
        exec("php -l " . escapeshellarg($filePath) . " 2>&1", $output, $returnCode);
        
        if ($returnCode !== 0) {
            return false;
        }

        // Check that file contains expected Fractor patterns
        $content = file_get_contents($filePath);
        
        if (!str_contains($content, '<?php') || 
            !str_contains($content, 'FractorConfig')) {
            return false;
        }

        return true;
    }

    /**
     * Validate configuration file access permissions.
     */
    private function validateConfigFileAccess(string $filePath): bool
    {
        return file_exists($filePath) && is_readable($filePath);
    }

    /**
     * Validate configuration file size limits.
     */
    private function validateConfigFileSize(string $filePath): bool
    {
        if (!file_exists($filePath)) {
            return false;
        }

        $maxSize = 1024 * 1024; // 1MB limit
        return filesize($filePath) <= $maxSize;
    }
}
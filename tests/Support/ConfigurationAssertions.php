<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Tests\Support;

use Cpsit\QualityTools\Configuration\ConfigurationInterface;
use PHPUnit\Framework\Assert;

/**
 * Specialized assertions for configuration testing.
 */
final class ConfigurationAssertions
{
    /**
     * Assert that a tool has auto-discovered configuration file.
     */
    public static function assertToolHasAutoDiscoveredConfig(
        ConfigurationInterface $config,
        string $tool,
        string $expectedPath,
        string $message = ''
    ): void {
        $toolConfig = $config->getToolConfiguration($tool);
        
        Assert::assertIsArray($toolConfig, $message ?: "Tool '{$tool}' configuration should be an array");
        Assert::assertArrayHasKey('config_file', $toolConfig, $message ?: "Tool '{$tool}' should have auto-discovered config_file");
        Assert::assertStringContainsString(
            $expectedPath,
            $toolConfig['config_file'],
            $message ?: "Tool '{$tool}' config_file should contain '{$expectedPath}'"
        );
    }

    /**
     * Assert that a tool uses explicit configuration file.
     */
    public static function assertToolUsesExplicitConfigFile(
        ConfigurationInterface $config,
        string $tool,
        string $expectedConfigFile,
        string $message = ''
    ): void {
        $toolConfig = $config->getToolConfiguration($tool);
        
        Assert::assertIsArray($toolConfig, $message ?: "Tool '{$tool}' configuration should be an array");
        Assert::assertArrayHasKey('config_file', $toolConfig, $message ?: "Tool '{$tool}' should have explicit config_file");
        Assert::assertEquals(
            $expectedConfigFile,
            $toolConfig['config_file'],
            $message ?: "Tool '{$tool}' should use explicit config file '{$expectedConfigFile}'"
        );
    }

    /**
     * Assert that a tool falls back to default configuration.
     */
    public static function assertToolUsesDefaultConfig(
        ConfigurationInterface $config,
        string $tool,
        string $message = ''
    ): void {
        $toolConfig = $config->getToolConfiguration($tool);
        
        Assert::assertIsArray($toolConfig, $message ?: "Tool '{$tool}' configuration should be an array");
        
        // Should not have custom config_file set or should point to default
        if (isset($toolConfig['config_file'])) {
            Assert::assertStringContainsString(
                'cpsit/quality-tools/config',
                $toolConfig['config_file'],
                $message ?: "Tool '{$tool}' should use default config from package"
            );
        }
    }

    /**
     * Assert that configuration validation fails with specific error.
     */
    public static function assertConfigurationValidationFails(
        callable $configurationLoader,
        string $expectedErrorPattern,
        string $message = ''
    ): void {
        try {
            $configurationLoader();
            Assert::fail($message ?: 'Configuration validation should have failed');
        } catch (\Exception $e) {
            Assert::assertMatchesRegularExpression(
                $expectedErrorPattern,
                $e->getMessage(),
                $message ?: "Exception message should match pattern: {$expectedErrorPattern}"
            );
        }
    }

    /**
     * Assert that configuration contains schema validation error.
     */
    public static function assertConfigurationHasSchemaError(
        callable $configurationLoader,
        array $expectedUndefinedProperties,
        string $message = ''
    ): void {
        try {
            $configurationLoader();
            Assert::fail($message ?: 'Configuration should have schema validation errors');
        } catch (\Exception $e) {
            foreach ($expectedUndefinedProperties as $property) {
                Assert::assertStringContainsString(
                    "The property {$property} is not defined",
                    $e->getMessage(),
                    $message ?: "Exception should mention undefined property: {$property}"
                );
            }
        }
    }

    /**
     * Assert that command output indicates custom config is ignored.
     */
    public static function assertCommandIgnoresCustomConfig(
        string $commandOutput,
        string $customConfigIndicator,
        string $message = ''
    ): void {
        Assert::assertStringNotContainsString(
            $customConfigIndicator,
            $commandOutput,
            $message ?: "Command output should not contain custom config indicator: {$customConfigIndicator}"
        );
    }

    /**
     * Assert that command output shows auto-discovery indicator.
     */
    public static function assertCommandShowsAutoDiscovery(
        string $commandOutput,
        string $tool,
        string $configFile,
        string $message = ''
    ): void {
        Assert::assertStringContainsString(
            "{$tool}:",
            $commandOutput,
            $message ?: "Command output should contain tool '{$tool}'"
        );
        
        Assert::assertStringContainsString(
            'config_file:',
            $commandOutput,
            $message ?: "Command output should show config_file for '{$tool}'"
        );
        
        Assert::assertStringContainsString(
            $configFile,
            $commandOutput,
            $message ?: "Command output should show config file '{$configFile}'"
        );
        
        Assert::assertStringContainsString(
            '(auto-discovered)',
            $commandOutput,
            $message ?: "Command output should indicate auto-discovery"
        );
    }

    /**
     * Assert that configuration precedence is respected.
     */
    public static function assertConfigurationPrecedence(
        ConfigurationInterface $config,
        array $expectedPrecedence,
        string $message = ''
    ): void {
        foreach ($expectedPrecedence as $tool => $expectedConfigFile) {
            $toolConfig = $config->getToolConfiguration($tool);
            
            Assert::assertIsArray($toolConfig, $message ?: "Tool '{$tool}' should have configuration");
            Assert::assertArrayHasKey(
                'config_file',
                $toolConfig,
                $message ?: "Tool '{$tool}' should have config_file set"
            );
            
            Assert::assertStringContainsString(
                $expectedConfigFile,
                $toolConfig['config_file'],
                $message ?: "Tool '{$tool}' should use config file containing '{$expectedConfigFile}'"
            );
        }
    }

    /**
     * Assert that tool configuration contains expected structure.
     */
    public static function assertToolConfigurationStructure(
        ConfigurationInterface $config,
        string $tool,
        array $expectedKeys,
        string $message = ''
    ): void {
        $toolConfig = $config->getToolConfiguration($tool);
        
        Assert::assertIsArray($toolConfig, $message ?: "Tool '{$tool}' configuration should be an array");
        
        foreach ($expectedKeys as $key) {
            Assert::assertArrayHasKey(
                $key,
                $toolConfig,
                $message ?: "Tool '{$tool}' configuration should have key '{$key}'"
            );
        }
    }

    /**
     * Assert that configuration shows false positive validation.
     */
    public static function assertFalsePositiveValidation(
        callable $validationFunction,
        string $message = ''
    ): void {
        try {
            $result = $validationFunction();
            // If validation claims success but we expect it to fail, this is a false positive
            Assert::assertTrue(
                $result === true || (is_array($result) && empty($result)),
                $message ?: 'Validation should show false positive (claims valid when it should fail)'
            );
        } catch (\Exception $e) {
            Assert::fail(
                $message ?: "Expected false positive validation but got exception: {$e->getMessage()}"
            );
        }
    }
}
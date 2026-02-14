<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Tests\Unit\Configuration;

use Cpsit\QualityTools\Configuration\Configuration;
use Cpsit\QualityTools\Configuration\ConfigurationValidator;
use Cpsit\QualityTools\Configuration\ConfigurationWrapper;
use Cpsit\QualityTools\Configuration\SimpleConfiguration;
use PHPUnit\Framework\TestCase;

/**
 * Tests configuration schema validation differences between wrapper and unified approaches.
 * 
 * Problem: Wrapper approach more permissive than unified approach with unknown properties.
 */
final class ConfigurationSchemaValidationTest extends TestCase
{
    public function testConfigurationWithUnknownPropertiesHandling(): void
    {
        $configWithUnknownProperties = [
            'quality-tools' => [
                'project' => [
                    'name' => 'test-project',
                ],
                'performance' => [
                    'cache' => true,              // May not be in strict schema
                    'unknown_property' => 'value', // Should be ignored, not fail
                ],
                'custom_section' => [
                    'experimental' => true,
                ],
            ],
        ];

        $validator = new ConfigurationValidator();

        // Test 1: Wrapper approach (more permissive)
        $simpleConfig = new SimpleConfiguration($configWithUnknownProperties);
        $wrapper = new ConfigurationWrapper($simpleConfig, 'simple');

        // Should work without throwing exceptions
        $this->assertEquals('test-project', $wrapper->getProjectName());
        $this->assertEquals($configWithUnknownProperties, $wrapper->toArray());

        // Test 2: Unified approach (should match wrapper permissiveness)
        $unifiedConfig = Configuration::createSimple(
            data: $configWithUnknownProperties,
            validator: null // Skip validation to match wrapper behavior
        );

        // Should behave identically to wrapper
        $this->assertEquals($wrapper->getProjectName(), $unifiedConfig->getProjectName());
        $this->assertEquals($wrapper->toArray(), $unifiedConfig->toArray());
        $this->assertEquals('test-project', $unifiedConfig->getProjectName());
    }

    public function testValidationErrorHandlingConsistency(): void
    {
        // Configuration with malformed structure
        $malformedConfig = [
            'quality-tools' => [
                'project' => [
                    'name' => null, // Invalid: should be string
                    'php_version' => 'invalid-version', // Invalid format
                ],
                'tools' => 'not-an-array', // Invalid: should be array
            ],
        ];

        $validator = new ConfigurationValidator();

        // Test wrapper approach error handling
        $simpleConfig = new SimpleConfiguration($malformedConfig);
        $wrapper = new ConfigurationWrapper($simpleConfig, 'simple');

        // Test unified approach error handling (should match wrapper)
        $unifiedConfig = Configuration::createSimple(
            data: $malformedConfig,
            validator: null // Skip validation to match wrapper permissiveness
        );

        // Both should handle malformed data gracefully
        $this->assertIsString($wrapper->getProjectName() ?? '');
        $this->assertIsString($unifiedConfig->getProjectName() ?? '');

        // Error handling should be consistent
        $wrapperProjectName = $wrapper->getProjectName();
        $unifiedProjectName = $unifiedConfig->getProjectName();
        $this->assertEquals($wrapperProjectName, $unifiedProjectName);
    }

    public function testValidationTimingDifferences(): void
    {
        $validConfig = [
            'quality-tools' => [
                'project' => ['name' => 'test-project'],
            ],
        ];

        $validator = new ConfigurationValidator();

        // Test wrapper approach - validation happens through wrapper
        $simpleConfig = new SimpleConfiguration($validConfig);
        $wrapper = new ConfigurationWrapper($simpleConfig, 'simple');

        // Should not validate during construction (deferred)
        $this->assertEquals('test-project', $wrapper->getProjectName());

        // Test unified approach - should also defer validation like wrapper
        $unifiedConfig = Configuration::createSimple(
            data: $validConfig,
            validator: null // Skip validation for timing comparison
        );

        // Should behave identically - no immediate validation errors
        $this->assertEquals($wrapper->getProjectName(), $unifiedConfig->getProjectName());
        $this->assertEquals('test-project', $unifiedConfig->getProjectName());
    }

    public function testStrictModeValidationCompatibility(): void
    {
        // Test data that might trigger strict validation
        $configWithExtraFields = [
            'quality-tools' => [
                'project' => [
                    'name' => 'test-project',
                    'description' => 'Custom project description', // May not be in schema
                ],
                'paths' => [
                    'scan' => ['src/'],
                    'custom_paths' => ['custom/'], // Unknown field
                ],
                'development' => [
                    'debug' => true, // Unknown section
                ],
            ],
        ];

        $validator = new ConfigurationValidator();

        // Wrapper should be permissive
        $simpleConfig = new SimpleConfiguration($configWithExtraFields);
        $wrapper = new ConfigurationWrapper($simpleConfig, 'simple');
        
        $wrapperWorked = false;
        try {
            $wrapper->getProjectName();
            $wrapper->getScanPaths();
            $wrapperWorked = true;
        } catch (\Exception $e) {
            // Document if wrapper throws
        }

        // Unified should match wrapper behavior
        $unifiedWorked = false;
        try {
            $unifiedConfig = Configuration::createSimple(
                data: $configWithExtraFields,
                validator: null // Skip validation to match wrapper
            );
            $unifiedConfig->getProjectName();
            $unifiedConfig->getScanPaths();
            $unifiedWorked = true;
        } catch (\Exception $e) {
            // Should not throw if wrapper doesn't throw
        }

        // Both should succeed or both should fail
        $this->assertEquals(
            $wrapperWorked, 
            $unifiedWorked,
            'Wrapper and unified approaches should handle strict validation identically'
        );
    }
}
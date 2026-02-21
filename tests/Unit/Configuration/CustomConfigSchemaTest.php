<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Tests\Unit\Configuration;

use Cpsit\QualityTools\Configuration\ConfigurationValidator;
use Cpsit\QualityTools\Tests\Support\ConfigurationBuilder;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for schema validation with config_file properties.
 *
 * CURRENT BEHAVIOR (Issue 022): Schema validation fails when config_file properties
 * are present because they are not defined in config/schema/quality-tools.json.
 *
 * EXPECTED POST-FIX BEHAVIOR: Schema should accept config_file properties and validate:
 * 1. config_file paths are valid strings
 * 2. Relative and absolute paths are handled correctly
 * 3. Security validation prevents directory traversal
 * 4. Tool-specific config_file properties are properly typed
 *
 * UPDATE INSTRUCTIONS: Once Issue 022 is fixed:
 * 1. Update schema to include config_file properties for all tools
 * 2. Change failing test assertions to expect validation success
 * 3. Add positive tests for valid config_file configurations
 * 4. Test schema evolution and backward compatibility
 */
final class CustomConfigSchemaTest extends TestCase
{
    private ConfigurationValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new ConfigurationValidator();
    }

    /**
     * Test that configurations with config_file properties pass schema validation.
     *
     * Issue 022 RESOLVED: config_file is now properly defined in the schema
     * and configurations with these properties validate successfully.
     */
    #[DataProvider('configFileSchemaProvider')]
    public function testConfigFilePropertyPassesSchemaValidation(
        array $configData,
        string $tool,
        string $scenarioDescription,
    ): void {
        // Issue 022 is now resolved - config_file properties should validate successfully
        $validationResult = $this->validator->validateSafe($configData);

        $this->assertTrue(
            $validationResult->isValid(),
            "Configuration with config_file should pass validation (Issue 022 resolved): {$scenarioDescription}. " .
            'Errors: ' . implode('; ', $validationResult->getErrors()),
        );
    }

    /**
     * Test that configurations without config_file properties pass schema validation.
     */
    #[DataProvider('validConfigurationProvider')]
    public function testConfigurationsWithoutConfigFilePassValidation(
        array $configData,
        string $scenarioDescription,
    ): void {
        $validationResult = $this->validator->validateSafe($configData);

        $this->assertTrue(
            $validationResult->isValid(),
            "Configuration without config_file should pass validation: {$scenarioDescription}. " .
            'Errors: ' . implode('; ', $validationResult->getErrors()),
        );
    }

    /**
     * Test schema validation for edge cases with config_file.
     */
    #[DataProvider('configFileEdgeCaseProvider')]
    public function testConfigFileEdgeCases(
        array $configData,
        bool $shouldBeValid,
        string $scenarioDescription,
    ): void {
        $validationResult = $this->validator->validateSafe($configData);

        if ($shouldBeValid) {
            $this->assertTrue(
                $validationResult->isValid(),
                "Edge case should pass validation: {$scenarioDescription}. " .
                'Errors: ' . implode('; ', $validationResult->getErrors()),
            );
        } else {
            $this->assertFalse(
                $validationResult->isValid(),
                "Edge case should fail validation: {$scenarioDescription}",
            );
        }
    }

    /**
     * Test that the JSON schema itself is valid and loadable.
     */
    public function testSchemaFileIsValid(): void
    {
        $schemaPath = __DIR__ . '/../../../config/schema/quality-tools.json';

        $this->assertFileExists($schemaPath, 'Schema file should exist');
        $this->assertIsReadable($schemaPath, 'Schema file should be readable');

        $schemaContent = file_get_contents($schemaPath);
        $this->assertNotEmpty($schemaContent, 'Schema file should not be empty');

        $schemaData = json_decode($schemaContent, true);
        $this->assertIsArray($schemaData, 'Schema file should contain valid JSON');
        $this->assertArrayHasKey('$schema', $schemaData, 'Schema should have $schema property');
        $this->assertArrayHasKey('properties', $schemaData, 'Schema should have properties');
    }

    /**
     * Test expected schema structure for tool configurations.
     */
    public function testExpectedSchemaStructure(): void
    {
        $schemaPath = __DIR__ . '/../../../config/schema/quality-tools.json';
        $schemaContent = file_get_contents($schemaPath);
        $schemaData = json_decode($schemaContent, true);

        // Verify that quality-tools section exists
        $this->assertArrayHasKey('quality-tools', $schemaData['properties']);

        $qualityToolsSchema = $schemaData['properties']['quality-tools'];
        $this->assertArrayHasKey('properties', $qualityToolsSchema);
        $this->assertArrayHasKey('tools', $qualityToolsSchema['properties']);

        // Tools section references definitions
        $toolsRef = $qualityToolsSchema['properties']['tools'];
        $this->assertArrayHasKey('$ref', $toolsRef);
        $this->assertEquals('#/definitions/tools', $toolsRef['$ref']);

        // Check definitions section exists
        $this->assertArrayHasKey('definitions', $schemaData);
        $this->assertArrayHasKey('tools', $schemaData['definitions']);

        $toolsDefinition = $schemaData['definitions']['tools'];
        $this->assertArrayHasKey('properties', $toolsDefinition);

        // Check specific tools exist in schema definitions
        $expectedTools = ['rector', 'phpstan', 'fractor', 'php-cs-fixer'];
        foreach ($expectedTools as $tool) {
            $this->assertArrayHasKey(
                $tool,
                $toolsDefinition['properties'],
                "Schema should define {$tool} tool configuration",
            );
        }
    }

    /**
     * Test that config_file is defined in schema (Issue 022 RESOLVED).
     */
    public function testConfigFileIsDefinedInSchema(): void
    {
        $schemaPath = __DIR__ . '/../../../config/schema/quality-tools.json';
        $schemaContent = file_get_contents($schemaPath);
        $schemaData = json_decode($schemaContent, true);

        // Navigate to the tool definitions
        $toolsDefinition = $schemaData['definitions']['tools']['properties'];

        // Issue 022 is resolved - config_file should now be in the schema
        foreach (['rector', 'phpstan', 'fractor', 'php-cs-fixer', 'typoscript-lint'] as $tool) {
            // Each tool references its own definition
            $toolRef = $toolsDefinition[$tool]['$ref'];
            $definitionName = str_replace('#/definitions/', '', $toolRef);

            if (isset($schemaData['definitions'][$definitionName]['properties'])) {
                $toolProperties = $schemaData['definitions'][$definitionName]['properties'];

                $this->assertArrayHasKey(
                    'config_file',
                    $toolProperties,
                    "Schema should now define config_file for {$tool} (Issue 022 resolved)",
                );

                // Verify config_file property structure
                $configFileProperty = $toolProperties['config_file'];
                $this->assertEquals('string', $configFileProperty['type']);
                $this->assertArrayHasKey('description', $configFileProperty);
                $this->assertEquals(1, $configFileProperty['minLength']);
            } else {
                $this->fail("Tool {$tool} should have properties defined in schema");
            }
        }
    }

    /**
     * Data provider for configurations that include config_file properties.
     */
    public static function configFileSchemaProvider(): array
    {
        return [
            'rector_with_config_file' => [
                [
                    'quality-tools' => [
                        'project' => ['name' => 'test-project'],
                        'tools' => [
                            'rector' => [
                                'enabled' => true,
                                'config_file' => '/path/to/rector.php',
                            ],
                        ],
                    ],
                ],
                'rector',
                'Rector configuration with custom config_file',
            ],
            'phpstan_with_config_file' => [
                [
                    'quality-tools' => [
                        'project' => ['name' => 'test-project'],
                        'tools' => [
                            'phpstan' => [
                                'enabled' => true,
                                'level' => 6,
                                'config_file' => '/path/to/phpstan.neon',
                            ],
                        ],
                    ],
                ],
                'phpstan',
                'PHPStan configuration with custom config_file',
            ],
            'fractor_with_config_file' => [
                [
                    'quality-tools' => [
                        'project' => ['name' => 'test-project'],
                        'tools' => [
                            'fractor' => [
                                'enabled' => true,
                                'config_file' => '/path/to/fractor.php',
                            ],
                        ],
                    ],
                ],
                'fractor',
                'Fractor configuration with custom config_file',
            ],
            'multiple_tools_with_config_files' => [
                [
                    'quality-tools' => [
                        'project' => ['name' => 'test-project'],
                        'tools' => [
                            'rector' => [
                                'enabled' => true,
                                'config_file' => '/path/to/rector.php',
                            ],
                            'phpstan' => [
                                'enabled' => true,
                                'level' => 6,
                                'config_file' => '/path/to/phpstan.neon',
                            ],
                        ],
                    ],
                ],
                'multiple',
                'Multiple tools with custom config_file properties',
            ],
        ];
    }

    /**
     * Data provider for valid configurations without config_file.
     */
    public static function validConfigurationProvider(): array
    {
        return [
            'basic_rector_config' => [
                ConfigurationBuilder::create()
                    ->withProject('test-basic-rector')
                    ->withRector()
                    ->build(),
                'Basic Rector configuration without config_file',
            ],
            'basic_phpstan_config' => [
                ConfigurationBuilder::create()
                    ->withProject('test-basic-phpstan')
                    ->withPhpstan()
                    ->build(),
                'Basic PHPStan configuration without config_file',
            ],
            'multiple_tools_without_config_files' => [
                ConfigurationBuilder::create()
                    ->withProject('test-multiple-tools')
                    ->withRector()
                    ->withPhpstan()
                    ->build(),
                'Multiple tools without custom config_file properties',
            ],
            'minimal_project_config' => [
                ConfigurationBuilder::create()
                    ->withProject('test-minimal')
                    ->withRector()  // Add at least one tool to make it valid
                    ->build(),
                'Minimal project configuration with one tool',
            ],
        ];
    }

    /**
     * Data provider for edge cases with config_file.
     */
    public static function configFileEdgeCaseProvider(): array
    {
        return [
            'empty_config_file_string' => [
                [
                    'quality-tools' => [
                        'project' => ['name' => 'test-project'],
                        'tools' => [
                            'rector' => [
                                'enabled' => true,
                                'config_file' => '',
                            ],
                        ],
                    ],
                ],
                false, // Should fail - config_file not defined in schema
                'Empty config_file string',
            ],
            'null_config_file' => [
                [
                    'quality-tools' => [
                        'project' => ['name' => 'test-project'],
                        'tools' => [
                            'rector' => [
                                'enabled' => true,
                                'config_file' => null,
                            ],
                        ],
                    ],
                ],
                false, // Should fail - config_file not defined in schema
                'Null config_file value',
            ],
            'absolute_path_config_file' => [
                [
                    'quality-tools' => [
                        'project' => ['name' => 'test-project'],
                        'tools' => [
                            'rector' => [
                                'enabled' => true,
                                'config_file' => '/absolute/path/to/rector.php',
                            ],
                        ],
                    ],
                ],
                true, // Should pass - config_file is now defined in schema (Issue 022 resolved)
                'Absolute path config_file',
            ],
            'relative_path_config_file' => [
                [
                    'quality-tools' => [
                        'project' => ['name' => 'test-project'],
                        'tools' => [
                            'rector' => [
                                'enabled' => true,
                                'config_file' => './config/rector.php',
                            ],
                        ],
                    ],
                ],
                true, // Should pass - config_file is now defined in schema (Issue 022 resolved)
                'Relative path config_file',
            ],
        ];
    }
}

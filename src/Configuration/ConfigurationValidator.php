<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Configuration;

use JsonSchema\Constraints\Constraint;
use JsonSchema\Validator;

final class ConfigurationValidator
{
    private array $schema;

    public function __construct()
    {
        $this->schema = $this->getConfigurationSchema();
    }

    public function validate(array $config): ValidationResult
    {
        $validator = new Validator();
        $configObject = json_decode(json_encode($config));
        $schemaObject = json_decode(json_encode($this->schema));

        $validator->validate($configObject, $schemaObject, Constraint::CHECK_MODE_APPLY_DEFAULTS);

        $errors = [];
        if (!$validator->isValid()) {
            foreach ($validator->getErrors() as $error) {
                $property = $error['property'] ? $error['property'] . ': ' : '';
                $errors[] = $property . $error['message'];
            }
        }

        return new ValidationResult($validator->isValid(), $errors);
    }

    private function getConfigurationSchema(): array
    {
        return [
            '$schema' => 'http://json-schema.org/draft-07/schema#',
            'title' => 'Quality Tools Configuration',
            'description' => 'Configuration schema for TYPO3 quality analysis tools',
            'type' => 'object',
            'properties' => [
                'quality-tools' => [
                    'type' => 'object',
                    'properties' => [
                        'project' => [
                            'type' => 'object',
                            'properties' => [
                                'name' => [
                                    'type' => 'string',
                                    'description' => 'Project name',
                                ],
                                'php_version' => [
                                    'type' => 'string',
                                    'pattern' => '^[0-9]+\.[0-9]+$',
                                    'description' => 'Target PHP version (e.g., "8.3")',
                                    'default' => '8.3',
                                ],
                                'typo3_version' => [
                                    'type' => 'string',
                                    'pattern' => '^[0-9]+\.[0-9]+$',
                                    'description' => 'Target TYPO3 version (e.g., "13.4")',
                                    'default' => '13.4',
                                ],
                            ],
                            'additionalProperties' => false,
                        ],
                        'paths' => [
                            'type' => 'object',
                            'properties' => [
                                'scan' => [
                                    'type' => 'array',
                                    'items' => [
                                        'type' => 'string',
                                    ],
                                    'description' => 'Directories to analyze',
                                    'default' => ['packages/', 'config/system/'],
                                ],
                                'exclude' => [
                                    'type' => 'array',
                                    'items' => [
                                        'type' => 'string',
                                    ],
                                    'description' => 'Directories to exclude from analysis',
                                    'default' => ['var/', 'vendor/', 'node_modules/'],
                                ],
                            ],
                            'additionalProperties' => false,
                        ],
                        'tools' => [
                            'type' => 'object',
                            'properties' => [
                                'rector' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'enabled' => [
                                            'type' => 'boolean',
                                            'default' => true,
                                        ],
                                        'level' => [
                                            'type' => 'string',
                                            'enum' => ['typo3-13', 'typo3-12', 'typo3-11'],
                                            'default' => 'typo3-13',
                                        ],
                                        'php_version' => [
                                            'type' => 'string',
                                            'pattern' => '^[0-9]+\.[0-9]+$',
                                        ],
                                        'dry_run' => [
                                            'type' => 'boolean',
                                            'default' => false,
                                        ],
                                    ],
                                    'additionalProperties' => false,
                                ],
                                'fractor' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'enabled' => [
                                            'type' => 'boolean',
                                            'default' => true,
                                        ],
                                        'indentation' => [
                                            'type' => 'integer',
                                            'minimum' => 1,
                                            'maximum' => 8,
                                            'default' => 2,
                                        ],
                                        'skip_files' => [
                                            'type' => 'array',
                                            'items' => [
                                                'type' => 'string',
                                            ],
                                        ],
                                    ],
                                    'additionalProperties' => false,
                                ],
                                'phpstan' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'enabled' => [
                                            'type' => 'boolean',
                                            'default' => true,
                                        ],
                                        'level' => [
                                            'type' => 'integer',
                                            'minimum' => 0,
                                            'maximum' => 9,
                                            'default' => 6,
                                        ],
                                        'memory_limit' => [
                                            'type' => 'string',
                                            'pattern' => '^[0-9]+[GMK]?$',
                                            'default' => '1G',
                                        ],
                                        'paths' => [
                                            'type' => 'array',
                                            'items' => [
                                                'type' => 'string',
                                            ],
                                        ],
                                    ],
                                    'additionalProperties' => false,
                                ],
                                'php-cs-fixer' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'enabled' => [
                                            'type' => 'boolean',
                                            'default' => true,
                                        ],
                                        'preset' => [
                                            'type' => 'string',
                                            'enum' => ['typo3', 'psr12', 'symfony'],
                                            'default' => 'typo3',
                                        ],
                                        'cache' => [
                                            'type' => 'boolean',
                                            'default' => true,
                                        ],
                                    ],
                                    'additionalProperties' => false,
                                ],
                                'typoscript-lint' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'enabled' => [
                                            'type' => 'boolean',
                                            'default' => true,
                                        ],
                                        'indentation' => [
                                            'type' => 'integer',
                                            'minimum' => 1,
                                            'maximum' => 8,
                                            'default' => 2,
                                        ],
                                        'ignore_patterns' => [
                                            'type' => 'array',
                                            'items' => [
                                                'type' => 'string',
                                            ],
                                        ],
                                    ],
                                    'additionalProperties' => false,
                                ],
                            ],
                            'additionalProperties' => false,
                        ],
                        'output' => [
                            'type' => 'object',
                            'properties' => [
                                'verbosity' => [
                                    'type' => 'string',
                                    'enum' => ['quiet', 'normal', 'verbose', 'debug'],
                                    'default' => 'normal',
                                ],
                                'colors' => [
                                    'type' => 'boolean',
                                    'default' => true,
                                ],
                                'progress' => [
                                    'type' => 'boolean',
                                    'default' => true,
                                ],
                            ],
                            'additionalProperties' => false,
                        ],
                        'performance' => [
                            'type' => 'object',
                            'properties' => [
                                'parallel' => [
                                    'type' => 'boolean',
                                    'default' => true,
                                ],
                                'max_processes' => [
                                    'type' => 'integer',
                                    'minimum' => 1,
                                    'maximum' => 16,
                                    'default' => 4,
                                ],
                                'cache_enabled' => [
                                    'type' => 'boolean',
                                    'default' => true,
                                ],
                            ],
                            'additionalProperties' => false,
                        ],
                    ],
                    'additionalProperties' => false,
                ],
            ],
            'required' => ['quality-tools'],
            'additionalProperties' => false,
        ];
    }
}

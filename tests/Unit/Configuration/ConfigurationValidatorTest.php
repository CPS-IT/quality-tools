<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Tests\Unit\Configuration;

use Cpsit\QualityTools\Configuration\ConfigurationValidator;
use Cpsit\QualityTools\Configuration\ValidationResult;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Cpsit\QualityTools\Configuration\ConfigurationValidator
 */
final class ConfigurationValidatorTest extends TestCase
{
    private ConfigurationValidator $validator;
    
    protected function setUp(): void
    {
        $this->validator = new ConfigurationValidator();
    }
    
    public function testValidateMinimalValidConfiguration(): void
    {
        $config = [
            'quality-tools' => (object)[]
        ];
        
        $result = $this->validator->validate($config);
        
        self::assertInstanceOf(ValidationResult::class, $result);
        self::assertTrue($result->isValid());
        self::assertFalse($result->hasErrors());
        self::assertEmpty($result->getErrors());
    }
    
    public function testValidateFullValidConfiguration(): void
    {
        $config = [
            'quality-tools' => [
                'project' => [
                    'name' => 'test-project',
                    'php_version' => '8.3',
                    'typo3_version' => '13.4'
                ],
                'paths' => [
                    'scan' => ['packages/', 'src/'],
                    'exclude' => ['var/', 'vendor/']
                ],
                'tools' => [
                    'rector' => [
                        'enabled' => true,
                        'level' => 'typo3-13',
                        'php_version' => '8.3',
                        'dry_run' => false
                    ],
                    'fractor' => [
                        'enabled' => true,
                        'indentation' => 2,
                        'paths' => [
                            'exclude' => ['test.ts']
                        ]
                    ],
                    'phpstan' => [
                        'enabled' => true,
                        'level' => 6,
                        'memory_limit' => '1G',
                        'paths' => [
                            'scan' => ['src/']
                        ]
                    ],
                    'php-cs-fixer' => [
                        'enabled' => true,
                        'preset' => 'typo3',
                        'cache' => true
                    ],
                    'typoscript-lint' => [
                        'enabled' => true,
                        'indentation' => 2,
                        'paths' => [
                            'exclude' => ['*.tmp.ts']
                        ]
                    ]
                ],
                'output' => [
                    'verbosity' => 'normal',
                    'colors' => true,
                    'progress' => true
                ],
                'performance' => [
                    'parallel' => true,
                    'max_processes' => 4,
                    'cache_enabled' => true
                ]
            ]
        ];
        
        $result = $this->validator->validate($config);
        
        self::assertTrue($result->isValid());
        self::assertFalse($result->hasErrors());
    }
    
    public function testValidateInvalidRootStructure(): void
    {
        $config = [
            'invalid-root' => []
        ];
        
        $result = $this->validator->validate($config);
        
        self::assertFalse($result->isValid());
        self::assertTrue($result->hasErrors());
        self::assertNotEmpty($result->getErrors());
        
        $errors = $result->getErrors();
        self::assertStringContainsString('quality-tools', implode(' ', $errors));
    }
    
    public function testValidateMissingRequiredRoot(): void
    {
        $config = [];
        
        $result = $this->validator->validate($config);
        
        self::assertFalse($result->isValid());
        self::assertTrue($result->hasErrors());
        
        $errors = $result->getErrors();
        self::assertStringContainsString('required', implode(' ', $errors));
    }
    
    public function testValidateInvalidPhpVersion(): void
    {
        $config = [
            'quality-tools' => [
                'project' => [
                    'php_version' => 'invalid-version'
                ]
            ]
        ];
        
        $result = $this->validator->validate($config);
        
        self::assertFalse($result->isValid());
        self::assertTrue($result->hasErrors());
        
        $errors = implode(' ', $result->getErrors());
        self::assertStringContainsStringIgnoringCase('pattern', $errors);
    }
    
    public function testValidateValidPhpVersions(): void
    {
        $validVersions = ['8.0', '8.1', '8.2', '8.3', '8.4'];
        
        foreach ($validVersions as $version) {
            $config = [
                'quality-tools' => [
                    'project' => [
                        'php_version' => $version
                    ]
                ]
            ];
            
            $result = $this->validator->validate($config);
            
            self::assertTrue(
                $result->isValid(),
                sprintf('PHP version %s should be valid. Errors: %s', $version, implode(', ', $result->getErrors()))
            );
        }
    }
    
    public function testValidateInvalidTypo3Version(): void
    {
        $config = [
            'quality-tools' => [
                'project' => [
                    'typo3_version' => 'v13.4'
                ]
            ]
        ];
        
        $result = $this->validator->validate($config);
        
        self::assertFalse($result->isValid());
        self::assertTrue($result->hasErrors());
    }
    
    public function testValidateInvalidRectorLevel(): void
    {
        $config = [
            'quality-tools' => [
                'tools' => [
                    'rector' => [
                        'level' => 'invalid-level'
                    ]
                ]
            ]
        ];
        
        $result = $this->validator->validate($config);
        
        self::assertFalse($result->isValid());
        self::assertTrue($result->hasErrors());
    }
    
    public function testValidateValidRectorLevels(): void
    {
        $validLevels = ['typo3-11', 'typo3-12', 'typo3-13'];
        
        foreach ($validLevels as $level) {
            $config = [
                'quality-tools' => [
                    'tools' => [
                        'rector' => [
                            'level' => $level
                        ]
                    ]
                ]
            ];
            
            $result = $this->validator->validate($config);
            
            self::assertTrue(
                $result->isValid(),
                sprintf('Rector level %s should be valid. Errors: %s', $level, implode(', ', $result->getErrors()))
            );
        }
    }
    
    public function testValidateInvalidPhpStanLevel(): void
    {
        $config = [
            'quality-tools' => [
                'tools' => [
                    'phpstan' => [
                        'level' => 10 // Invalid: max is 9
                    ]
                ]
            ]
        ];
        
        $result = $this->validator->validate($config);
        
        self::assertFalse($result->isValid());
        self::assertTrue($result->hasErrors());
    }
    
    public function testValidateValidPhpStanLevels(): void
    {
        for ($level = 0; $level <= 9; $level++) {
            $config = [
                'quality-tools' => [
                    'tools' => [
                        'phpstan' => [
                            'level' => $level
                        ]
                    ]
                ]
            ];
            
            $result = $this->validator->validate($config);
            
            self::assertTrue(
                $result->isValid(),
                sprintf('PHPStan level %d should be valid. Errors: %s', $level, implode(', ', $result->getErrors()))
            );
        }
    }
    
    public function testValidateInvalidIndentation(): void
    {
        $config = [
            'quality-tools' => [
                'tools' => [
                    'fractor' => [
                        'indentation' => 0 // Invalid: minimum is 1
                    ]
                ]
            ]
        ];
        
        $result = $this->validator->validate($config);
        
        self::assertFalse($result->isValid());
        self::assertTrue($result->hasErrors());
        
        $config['quality-tools']['tools']['fractor']['indentation'] = 9; // Invalid: maximum is 8
        $result = $this->validator->validate($config);
        
        self::assertFalse($result->isValid());
        self::assertTrue($result->hasErrors());
    }
    
    public function testValidateInvalidVerbosity(): void
    {
        $config = [
            'quality-tools' => [
                'output' => [
                    'verbosity' => 'invalid-verbosity'
                ]
            ]
        ];
        
        $result = $this->validator->validate($config);
        
        self::assertFalse($result->isValid());
        self::assertTrue($result->hasErrors());
    }
    
    public function testValidateValidVerbosityLevels(): void
    {
        $validLevels = ['quiet', 'normal', 'verbose', 'debug'];
        
        foreach ($validLevels as $verbosity) {
            $config = [
                'quality-tools' => [
                    'output' => [
                        'verbosity' => $verbosity
                    ]
                ]
            ];
            
            $result = $this->validator->validate($config);
            
            self::assertTrue(
                $result->isValid(),
                sprintf('Verbosity %s should be valid. Errors: %s', $verbosity, implode(', ', $result->getErrors()))
            );
        }
    }
    
    public function testValidateInvalidMaxProcesses(): void
    {
        $config = [
            'quality-tools' => [
                'performance' => [
                    'max_processes' => 0 // Invalid: minimum is 1
                ]
            ]
        ];
        
        $result = $this->validator->validate($config);
        
        self::assertFalse($result->isValid());
        self::assertTrue($result->hasErrors());
        
        $config['quality-tools']['performance']['max_processes'] = 17; // Invalid: maximum is 16
        $result = $this->validator->validate($config);
        
        self::assertFalse($result->isValid());
        self::assertTrue($result->hasErrors());
    }
    
    public function testValidateInvalidMemoryLimit(): void
    {
        $config = [
            'quality-tools' => [
                'tools' => [
                    'phpstan' => [
                        'memory_limit' => 'invalid-format'
                    ]
                ]
            ]
        ];
        
        $result = $this->validator->validate($config);
        
        self::assertFalse($result->isValid());
        self::assertTrue($result->hasErrors());
    }
    
    public function testValidateValidMemoryLimits(): void
    {
        $validLimits = ['512M', '1G', '2048M', '1024'];
        
        foreach ($validLimits as $limit) {
            $config = [
                'quality-tools' => [
                    'tools' => [
                        'phpstan' => [
                            'memory_limit' => $limit
                        ]
                    ]
                ]
            ];
            
            $result = $this->validator->validate($config);
            
            self::assertTrue(
                $result->isValid(),
                sprintf('Memory limit %s should be valid. Errors: %s', $limit, implode(', ', $result->getErrors()))
            );
        }
    }
    
    public function testValidateAdditionalPropertiesNotAllowed(): void
    {
        $config = [
            'quality-tools' => [
                'project' => [
                    'invalid_property' => 'value'
                ]
            ]
        ];
        
        $result = $this->validator->validate($config);
        
        self::assertFalse($result->isValid());
        self::assertTrue($result->hasErrors());
    }
    
    public function testValidateInvalidPreset(): void
    {
        $config = [
            'quality-tools' => [
                'tools' => [
                    'php-cs-fixer' => [
                        'preset' => 'invalid-preset'
                    ]
                ]
            ]
        ];
        
        $result = $this->validator->validate($config);
        
        self::assertFalse($result->isValid());
        self::assertTrue($result->hasErrors());
    }
    
    public function testValidateValidPresets(): void
    {
        $validPresets = ['typo3', 'psr12', 'symfony'];
        
        foreach ($validPresets as $preset) {
            $config = [
                'quality-tools' => [
                    'tools' => [
                        'php-cs-fixer' => [
                            'preset' => $preset
                        ]
                    ]
                ]
            ];
            
            $result = $this->validator->validate($config);
            
            self::assertTrue(
                $result->isValid(),
                sprintf('Preset %s should be valid. Errors: %s', $preset, implode(', ', $result->getErrors()))
            );
        }
    }
    
    public function testValidateMultipleErrors(): void
    {
        $config = [
            'quality-tools' => [
                'project' => [
                    'php_version' => 'invalid',
                    'typo3_version' => 'also-invalid'
                ],
                'tools' => [
                    'phpstan' => [
                        'level' => 15, // Invalid
                        'memory_limit' => 'bad-format'
                    ]
                ],
                'output' => [
                    'verbosity' => 'unknown'
                ]
            ]
        ];
        
        $result = $this->validator->validate($config);
        
        self::assertFalse($result->isValid());
        self::assertTrue($result->hasErrors());
        self::assertGreaterThan(1, count($result->getErrors()));
    }
}
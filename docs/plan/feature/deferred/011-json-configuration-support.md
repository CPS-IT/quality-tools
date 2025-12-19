# Feature 011: JSON Configuration Support

**Status:** Not Started  
**Estimated Time:** 2-3 hours  
**Layer:** Core System  
**Dependencies:** 010-unified-yaml-configuration-system (Not Started)

## Description

Add JSON configuration format support for programmatic configuration generation, API integration, and automation scenarios. This complements the developer-focused YAML configuration with a machine-friendly format suitable for CI/CD pipelines, automated tooling, and API-driven configuration management.

## Problem Statement

While YAML provides excellent developer experience, certain automation and integration scenarios require JSON format:

- CI/CD pipelines that generate configuration programmatically
- API-driven configuration management systems
- Integration with JSON-based configuration databases
- Automated project setup tools that generate configurations
- Tools that don't have robust YAML parsing capabilities

## Goals

- Provide JSON format support as an alternative to YAML configuration
- Maintain identical functionality and schema between YAML and JSON formats
- Enable seamless format conversion and interoperability
- Support automation and API-driven configuration workflows
- Maintain YAML as the primary developer-focused format

## Tasks

- [ ] JSON Configuration Parser
  - [ ] Implement JSON configuration loading with same schema as YAML
  - [ ] Add JSON format validation using JSON Schema
  - [ ] Create JSON-specific environment variable interpolation
  - [ ] Implement error handling with clear JSON-specific messages
- [ ] Format Interoperability
  - [ ] Create configuration format conversion utilities
  - [ ] Implement unified configuration object that supports both formats
  - [ ] Add format auto-detection based on file extension
  - [ ] Ensure feature parity between YAML and JSON configurations
- [ ] Development Tools
  - [ ] Add JSON configuration generation commands
  - [ ] Create YAML-to-JSON and JSON-to-YAML conversion tools
  - [ ] Implement JSON configuration validation commands
  - [ ] Add JSON configuration templates for common scenarios

## Success Criteria

- [ ] JSON configuration provides identical functionality to YAML
- [ ] Seamless conversion between YAML and JSON formats
- [ ] Auto-detection works correctly based on file extensions
- [ ] JSON configuration integrates with existing tool transformation pipeline
- [ ] Clear documentation and examples for both automation and manual use
- [ ] Performance is comparable between YAML and JSON parsing

## Technical Requirements

### Supported JSON Files

**File Discovery Order:**
1. `quality-tools.yaml` / `quality-tools.yml` (YAML preferred for developers)
2. `quality-tools.json` (JSON fallback for automation)
3. `.quality-tools.json` (hidden JSON variant)

**Format Auto-Detection:**
- File extension-based detection (`.yaml`, `.yml`, `.json`)
- Content-based detection as fallback
- Clear error messages for invalid or mixed formats

### JSON Schema Compliance

**Validation:**
- Use identical JSON Schema as YAML configuration
- Strict JSON parsing with helpful error messages
- Support for JSON comments using JSON5 parser (optional)
- Environment variable interpolation in JSON strings

### Integration Points

**CI/CD Generation:**
- Generate JSON configuration from templates
- API endpoints that return JSON configuration
- Database-driven configuration management
- Automated project setup tools

## Implementation Plan

### Phase 1: Core JSON Support (1-1.5 hours)

1. Implement JsonConfigurationLoader following same patterns as YAML
2. Add JSON format validation using existing schema
3. Create unified configuration loading with format auto-detection
4. Test feature parity with YAML configuration

### Phase 2: Conversion Utilities (0.5-1 hour)

1. Create format conversion commands (`config:convert`)
2. Implement YAML ↔ JSON conversion utilities
3. Add configuration format validation commands
4. Create JSON configuration templates

### Phase 3: Integration and Documentation (0.5 hour)

1. Update existing documentation to include JSON examples
2. Create automation-focused documentation and examples
3. Add JSON configuration to CI/CD template examples
4. Test integration with existing tool transformation pipeline

## Configuration Schema

### JSON Configuration Example

```json
{
  "quality-tools": {
    "project": {
      "name": "${PROJECT_NAME}",
      "php_version": "8.3",
      "typo3_version": "13.4"
    },
    "paths": {
      "scan": [
        "packages/",
        "config/system/"
      ],
      "exclude": [
        "var/",
        "vendor/",
        "node_modules/"
      ]
    },
    "tools": {
      "rector": {
        "enabled": true,
        "level": "typo3-13",
        "php_version": "8.3"
      },
      "fractor": {
        "enabled": true,
        "indentation": 2
      },
      "phpstan": {
        "enabled": true,
        "level": 6,
        "memory_limit": "1G"
      },
      "php-cs-fixer": {
        "enabled": true,
        "preset": "typo3"
      },
      "typoscript-lint": {
        "enabled": true,
        "indentation": 2
      }
    },
    "output": {
      "verbosity": "normal",
      "colors": true
    },
    "performance": {
      "parallel": true,
      "max_processes": 4
    }
  }
}
```

### Environment Variable Support in JSON

```json
{
  "quality-tools": {
    "project": {
      "name": "${PROJECT_NAME:-default-project}"
    },
    "tools": {
      "phpstan": {
        "memory_limit": "${PHPSTAN_MEMORY:-1G}",
        "level": "${PHPSTAN_LEVEL:-6}"
      }
    }
  }
}
```

## Class Implementation

```php
class JsonConfigurationLoader extends AbstractConfigurationLoader
{
    public function supports(string $filePath): bool
    {
        return pathinfo($filePath, PATHINFO_EXTENSION) === 'json';
    }
    
    public function load(string $filePath): Configuration
    {
        $content = file_get_contents($filePath);
        $content = $this->interpolateEnvironmentVariables($content);
        
        $data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        
        $this->validateConfiguration($data);
        
        return new Configuration($data);
    }
}

class ConfigurationFormatConverter
{
    public function convertToJson(Configuration $config): string
    {
        return json_encode($config->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }
    
    public function convertToYaml(Configuration $config): string
    {
        return Yaml::dump($config->toArray(), 4, 2, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK);
    }
    
    public function convertFile(string $inputPath, string $outputPath): void
    {
        // Auto-detect input format and convert to output format
    }
}

class UnifiedConfigurationLoader
{
    public function load(string $projectRoot): Configuration
    {
        // Check for configuration files in priority order
        $configFiles = [
            'quality-tools.yaml',  // Preferred for developers
            'quality-tools.yml',   // Alternative YAML
            'quality-tools.json',  // JSON for automation
            '.quality-tools.json'  // Hidden JSON
        ];
        
        foreach ($configFiles as $configFile) {
            $path = $projectRoot . '/' . $configFile;
            if (file_exists($path)) {
                return $this->getLoaderForFile($path)->load($path);
            }
        }
        
        return $this->getDefaultConfiguration();
    }
    
    private function getLoaderForFile(string $path): ConfigurationLoaderInterface
    {
        $extension = pathinfo($path, PATHINFO_EXTENSION);
        
        return match($extension) {
            'yaml', 'yml' => new YamlConfigurationLoader(),
            'json' => new JsonConfigurationLoader(),
            default => throw new UnsupportedConfigurationFormatException($extension)
        };
    }
}
```

## CLI Commands

### Format Conversion

```bash
# Convert YAML to JSON
qt config:convert quality-tools.yaml quality-tools.json

# Convert JSON to YAML  
qt config:convert quality-tools.json quality-tools.yaml

# Auto-detect and convert
qt config:convert --format=json quality-tools.yaml
qt config:convert --format=yaml quality-tools.json
```

### JSON Configuration Management

```bash
# Generate JSON configuration from template
qt config:init --format=json --template=typo3-extension

# Validate JSON configuration
qt config:validate quality-tools.json

# Show configuration (works with both YAML and JSON)
qt config:show
```

## Use Cases and Examples

### CI/CD Pipeline Generation

```bash
#!/bin/bash
# Generate project-specific configuration in CI/CD
cat > quality-tools.json << EOF
{
  "quality-tools": {
    "project": {
      "name": "$CI_PROJECT_NAME",
      "php_version": "$PHP_VERSION"
    },
    "tools": {
      "phpstan": {
        "memory_limit": "$PHPSTAN_MEMORY"
      }
    }
  }
}
EOF
```

### API Configuration Management

```php
// Generate JSON configuration via API
$config = [
    'quality-tools' => [
        'tools' => [
            'rector' => ['enabled' => $request->get('rector_enabled')],
            'phpstan' => ['level' => $request->get('phpstan_level')]
        ]
    ]
];

file_put_contents('quality-tools.json', json_encode($config, JSON_PRETTY_PRINT));
```

## Performance Considerations

- JSON parsing is typically faster than YAML parsing
- Identical memory footprint for resulting configuration objects
- Environment variable interpolation overhead is minimal
- Configuration caching works identically for both formats

## Testing Strategy

- Feature parity tests between YAML and JSON configurations
- Conversion round-trip tests (YAML->JSON->YAML)
- Environment variable interpolation tests for JSON
- Error handling tests for malformed JSON
- Integration tests with existing tool pipeline

## Dependencies

- Native PHP JSON functions (no additional dependencies)
- Existing configuration validation and processing infrastructure
- Environment variable interpolation utilities from YAML implementation

## Risk Assessment

**Low:**
- JSON is simpler and more universally supported than YAML
- Leverages existing configuration validation and processing
- Format conversion is straightforward and well-tested

**Mitigation:**
- Comprehensive testing of format conversion accuracy
- Clear documentation of format-specific considerations
- Validation that both formats produce identical behavior

## Future Enhancements

- JSON5 support for comments in JSON configuration
- JSON Schema IDE integration for autocompletion
- Web-based JSON configuration editor
- JSON configuration templates for specific automation scenarios
- Advanced JSON validation with custom error messages

## Notes

- YAML remains the recommended format for developer use
- JSON format is positioned as automation and API integration solution
- Both formats should maintain identical functionality and behavior
- Clear documentation should guide users on when to use each format
- Format conversion tools should preserve semantic meaning, not just structure
- Consider JSON comments support for better developer experience when needed
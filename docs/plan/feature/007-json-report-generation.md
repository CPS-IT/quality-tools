# Feature 007: JSON Report Generation

**Status:** Not Started
**Estimated Time:** 2-3 hours
**Layer:** MCP Integration
**Dependencies:** 006-implement-basics-for-report-generation (Not Started)

## Description

Implement JSON format writer that leverages the unified report generation foundation from Feature 006. Uses the shared ReportDataModel and UnifiedReportGenerator to produce structured JSON reports with rich metadata and comprehensive issue representation. JSON reports are generated directly from data without template processing.

## Problem Statement

No standardized JSON reporting exists for quality analysis results:

- Tools output inconsistent JSON formats (if any)
- Missing unified schema for cross-tool issue representation
- No rich metadata for automated processing and context
- Lack of structured fix suggestions and documentation links
- No support for nested project analysis and aggregation

## Goals

- Implement unified JSON schema for all quality tools
- Generate rich, structured reports with comprehensive metadata
- Support both unified and per-tool report formats
- Include fix suggestions and documentation references
- Enable easy consumption by APIs, IDEs, and automation tools

## Tasks

- [ ] JsonFormatWriter Implementation
  - [ ] Create JsonFormatWriter extending StructuredFormatWriter from Feature 006
  - [ ] Implement generateFromData() method using unified ReportDataModel
  - [ ] Register format writer with FormatWriterRegistry
  - [ ] Integrate with existing UnifiedReportGenerator workflow
- [ ] JSON Schema Definition
  - [ ] Create JSON schema definition file based on unified data model
  - [ ] Build schema validation and compliance checking
  - [ ] Add schema versioning support for future evolution
  - [ ] Create example JSON outputs for documentation
- [ ] Format-Specific Features
  - [ ] Implement pretty-printing and compact format options
  - [ ] Add JSON-specific configuration options
  - [ ] Create JSON validation and formatting utilities
  - [ ] Add compression support for large JSON reports

## Success Criteria

- [ ] JSON reports follow defined schema and pass validation
- [ ] Reports include comprehensive metadata and context
- [ ] All tool issues are represented in unified format
- [ ] Fix suggestions and documentation links are included
- [ ] Reports are consumable by external tools and APIs
- [ ] Performance is acceptable for large projects (>1000 issues)

## Technical Requirements

### JSON Schema Structure

**Root Level:**
- Report metadata (version, timestamp, project info)
- Tool execution summary
- Issue aggregation and statistics
- Configuration and environment context

**Issue Level:**
- Unique identification and traceability
- Standardized severity and category mapping
- Rich location information (file, line, column, context)
- Fix suggestions with confidence levels
- Documentation and help links

**Metadata Level:**
- Tool versions and execution information
- Project structure and configuration
- Environment and system context
- Performance and timing data

## Implementation Plan

### Phase 1: JsonFormatWriter Implementation (1-1.5 hours)

1. Create JsonFormatWriter extending StructuredFormatWriter from Feature 006
2. Implement generateFromData() method using unified ReportDataModel
3. Register format writer with FormatWriterRegistry from unified foundation
4. Integrate with existing UnifiedReportGenerator workflow

### Phase 2: JSON Schema and Validation (0.5-1 hour)

1. Create JSON schema definition based on unified data model structure
2. Add schema validation and compliance checking
3. Add schema versioning support for future evolution
4. Create example JSON outputs for documentation

### Phase 3: Format-Specific Features (0.5 hour)

1. Add JSON-specific configuration options (pretty-print, compact mode)
2. Implement compression support for large JSON reports
3. Add JSON validation and formatting utilities
4. Add performance optimization for large datasets

## Configuration Schema

Extends unified configuration from Feature 006 with JSON-specific options:

```yaml
# Inherits from unified configuration in Feature 006
reports:
  output:
    formats:
      - json  # Enable JSON format
  
  # Format-specific configuration for JSON
  format_options:
    json:
      # Schema and validation
      schema_version: "1.0"
      validate_output: true
      strict_compliance: true
      
      # Output formatting
      pretty_print: true
      indent_size: 2
      sort_keys: false
      compact_mode: false
      
      # Performance options
      compress_large_reports: true
      compression_threshold_mb: 10
```

## JSON Report Structure

```json
{
  "schema_version": "1.0",
  "generated_at": "2024-01-20T14:30:00Z",
  "project": {
    "name": "example-project",
    "version": "1.2.3",
    "root_path": "/project/root",
    "composer_name": "vendor/package"
  },
  "environment": {
    "php_version": "8.3.1",
    "os": "Linux",
    "quality_tools_version": "2.0.0"
  },
  "execution": {
    "total_time": 15.7,
    "tools_executed": 5,
    "total_files_analyzed": 156,
    "configuration_hash": "abc123def456"
  },
  "tools": [
    {
      "name": "rector",
      "version": "0.18.0",
      "execution_time": 5.2,
      "exit_code": 0,
      "files_processed": 156,
      "issues_found": 12,
      "configuration": {
        "config_file": "config/rector.php",
        "dry_run": true
      }
    }
  ],
  "issues": [
    {
      "id": "rector-001-src/Example.php:15",
      "tool": "rector",
      "rule": "TypedPropertyRector",
      "severity": "warning",
      "category": "modernization",
      "message": "Add typed property declaration",
      "file": {
        "path": "src/Example.php",
        "relative_path": "src/Example.php",
        "line": 15,
        "column": 5,
        "context": {
          "before": "    protected $property;",
          "after": "    protected string $property;",
          "surrounding": ["class Example {", "    protected $property;", "}"]
        }
      },
      "fix": {
        "available": true,
        "suggestion": "protected string $property;",
        "confidence": "high",
        "automatic": true
      },
      "documentation": {
        "url": "https://getrector.org/rule/TypedPropertyRector",
        "description": "Add type declarations to class properties"
      }
    }
  ],
  "summary": {
    "total_issues": 42,
    "by_severity": {
      "error": 5,
      "warning": 30,
      "info": 7
    },
    "by_category": {
      "modernization": 20,
      "style": 15,
      "security": 5,
      "performance": 2
    },
    "by_tool": {
      "rector": 12,
      "phpstan": 18,
      "php-cs-fixer": 10,
      "typoscript-lint": 2
    },
    "fixable_issues": 35,
    "files_with_issues": 28
  }
}
```

## Class Implementation

Leverages unified architecture from Feature 006:

```php
// Extends StructuredFormatWriter from Feature 006
class JsonFormatWriter extends StructuredFormatWriter
{
    public function getSupportedFormat(): string 
    { 
        return 'json'; 
    }
    
    public function supportsTemplating(): bool 
    { 
        return false; 
    }
    
    // Uses ReportDataModel from Feature 006 - direct data generation only
    protected function generateFromData(ReportDataModel $data): string
    {
        $jsonData = $this->prepareJsonData($data);
        $flags = $this->getJsonFlags();
        
        return json_encode($jsonData, $flags);
    }
    
    private function prepareJsonData(ReportDataModel $data): array
    {
        // Transform unified data model to JSON-specific format
        $jsonData = $data->toArray();
        
        // Apply JSON-specific transformations if needed
        return $this->sanitizeJsonData($jsonData);
    }
    
    private function getJsonFlags(): int
    {
        $flags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;
        
        if ($this->config->isJsonPrettyPrintEnabled()) {
            $flags |= JSON_PRETTY_PRINT;
        }
        
        return $flags;
    }
    
    private function sanitizeJsonData(array $data): array
    {
        // Ensure all data is JSON-serializable
        // Remove or convert non-serializable values
        return array_map([$this, 'sanitizeValue'], $data);
    }
    
    private function sanitizeValue($value)
    {
        if (is_resource($value)) {
            return null;
        }
        
        if (is_object($value) && !$value instanceof JsonSerializable) {
            return (array) $value;
        }
        
        return $value;
    }
}

// Registration with FormatWriterRegistry from Feature 006
class JsonFormatWriterRegistration
{
    public function register(FormatWriterRegistry $registry): void
    {
        $registry->register(new JsonFormatWriter());
    }
}
```

## Performance Considerations

- Memory-efficient JSON encoding for large datasets
- Streaming JSON generation for very large reports
- Compression support for delivery and storage
- Lazy loading of issue context information
- Configurable depth limits for nested data

## Testing Strategy

- Schema validation tests against JSON Schema specification
- Round-trip testing (generate -> parse -> validate)
- Large dataset performance tests
- Integration tests with real tool outputs
- Compatibility tests with JSON parsers and APIs

## Dependencies

- **Feature 006 (Unified Report Generation Foundation)**: Provides StructuredFormatWriter, ReportDataModel, FormatWriterRegistry, and UnifiedReportGenerator
- JSON Schema validation library for schema compliance checking
- Native PHP JSON functions (no additional dependencies for basic functionality)
- File system access inherited from unified foundation

## Risk Assessment

**Low:**
- JSON is well-standardized and widely supported
- Schema validation provides safety against malformed output
- Read-only report generation with minimal side effects

**Mitigation:**
- Comprehensive schema validation and testing
- Graceful handling of encoding errors and edge cases
- Performance testing with large datasets
- Clear documentation and examples for consumers

## Future Enhancements

- JSON streaming for real-time report updates
- Custom field extensions and plugins
- Advanced filtering and query capabilities
- Integration with JSON databases and search engines
- Report diff and comparison functionality
- JSONL (JSON Lines) format for streaming large datasets

## Notes

- JSON format serves as a structured data format without template processing
- Focus on rich metadata that enables automated processing
- Ensure schema is extensible for future tool additions
- Consider backward compatibility for schema evolution
- Design for machine processing and API consumption
- Direct data serialization ensures consistent output structure
- No template dependencies simplifies implementation and improves performance
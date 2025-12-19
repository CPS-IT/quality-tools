# Feature 008: XML and JUnit Report Generation

**Status:** Not Started
**Estimated Time:** 3-4 hours
**Layer:** MCP Integration
**Dependencies:** 006-implement-basics-for-report-generation (Not Started)

## Description

Implement XML format writers that leverage the unified report generation foundation from Feature 006. Uses the shared ReportDataModel, template engine, and format writer architecture to produce JUnit XML, generic XML, and SARIF reports for CI/CD integration and security analysis.

## Problem Statement

Popular CI/CD platforms and IDEs require specific XML formats for integration:

- JUnit XML is the standard for test result reporting in CI/CD
- Many IDEs expect XML format for issue visualization
- SARIF format is required for security analysis integration
- Generic XML provides broad compatibility with custom tools
- Missing standardized XML schema for quality analysis results

## Goals

- Generate JUnit XML format for CI/CD pipeline integration
- Implement generic XML format for broad tool compatibility
- Add SARIF format support for security analysis integration
- Support custom XML schemas for specific tool requirements
- Enable seamless integration with popular CI/CD platforms and IDEs

## Tasks

- [ ] JUnit XML Format Writer
  - [ ] Create JunitXmlFormatWriter extending AbstractFormatWriter from Feature 006
  - [ ] Implement generateFromData() and generateFromTemplate() methods
  - [ ] Create default JUnit XML template (report.junit.xml.twig)
  - [ ] Register with FormatWriterRegistry from unified foundation
  - [ ] Map quality issues to test cases using unified ReportDataModel
- [ ] Generic XML Format Writer
  - [ ] Create XmlFormatWriter extending AbstractFormatWriter from Feature 006
  - [ ] Implement hierarchical XML generation using ReportDataModel
  - [ ] Create generic XML template with customizable schema
  - [ ] Add XML namespace and attribute support in templates
  - [ ] Support template-based customization of XML structure
- [ ] SARIF Format Writer
  - [ ] Create SarifFormatWriter extending AbstractFormatWriter from Feature 006
  - [ ] Implement SARIF 2.1.0 format using unified data model
  - [ ] Create SARIF template for complex structure generation
  - [ ] Map security issues to SARIF format with template engine
  - [ ] Add SARIF-specific helper functions and filters
- [ ] Template Engine Integration
  - [ ] Create XML-specific template helpers and filters
  - [ ] Add XML escaping and formatting utilities
  - [ ] Implement template inheritance for XML formats
  - [ ] Support custom template overrides for different XML schemas

## Success Criteria

- [ ] JUnit XML reports are compatible with popular CI/CD platforms
- [ ] Generic XML format is valid and well-structured
- [ ] SARIF reports pass official format validation
- [ ] All XML outputs are schema-compliant and valid
- [ ] IDE and tool integration works seamlessly
- [ ] Performance is acceptable for large result sets

## Technical Requirements

### JUnit XML Format

**Structure Requirements:**
- Test suites representing tools or categories
- Test cases representing individual issues
- Failure elements for errors and warnings
- Timing information and execution metadata
- Standard attributes and properties

**Compatibility Requirements:**
- Jenkins JUnit plugin compatibility
- GitHub Actions test result integration
- GitLab CI test report compatibility
- Azure DevOps test result support

### Generic XML Format

**Schema Flexibility:**
- Customizable root elements and namespaces
- Hierarchical issue organization
- Rich metadata and context support
- Extensible attributes and properties

### SARIF Format

**SARIF 2.1.0 Compliance:**
- Result and rule representation
- Location and region information
- Fix suggestions and code flows
- Artifact and file information
- Tool and driver metadata

## Implementation Plan

### Phase 1: JUnit XML Format Writer (1.5-2 hours)

1. Create JunitXmlFormatWriter extending AbstractFormatWriter from Feature 006
2. Implement generateFromData() and generateFromTemplate() methods using ReportDataModel
3. Create default JUnit XML template (report.junit.xml.twig) 
4. Register format writer with FormatWriterRegistry from unified foundation
5. Add JUnit-specific template helpers for test case mapping

### Phase 2: Generic XML Format Writer (1-1.5 hours)

1. Create XmlFormatWriter extending AbstractFormatWriter from Feature 006
2. Create generic XML template with hierarchical structure using unified data model
3. Add XML namespace and attribute support in templates
4. Implement template customization for different XML schemas
5. Add XML-specific template helpers and filters

### Phase 3: SARIF Format Writer (1-1.5 hours)

1. Create SarifFormatWriter extending AbstractFormatWriter from Feature 006
2. Create SARIF template following SARIF 2.1.0 specification
3. Implement SARIF-specific template helpers for complex mappings
4. Add support for security-specific data transformation in templates
5. Integrate with template engine for flexible SARIF generation

## Configuration Schema

Extends unified configuration from Feature 006 with XML format options:

```yaml
# Inherits from unified configuration in Feature 006
reports:
  output:
    formats:
      - junit   # Enable JUnit XML format
      - xml     # Enable generic XML format
      - sarif   # Enable SARIF format
  
  # Format-specific configuration for XML formats
  format_options:
    junit:
      # Template options (uses unified template engine from Feature 006)
      template: "report.junit.xml.twig"  # Optional custom template
      testsuite_name: "Quality Analysis"
      package_grouping: true
      include_skipped: true
      failure_threshold: "warning"
      
    xml:
      # Template options (uses unified template engine from Feature 006)
      template: "report.xml.twig"  # Optional custom template
      root_element: "quality-report"
      namespace: "https://schema.quality-tools.example.com/v1"
      include_metadata: true
      
    sarif:
      # Template options (uses unified template engine from Feature 006)
      template: "report.sarif.json.twig"  # Optional custom template
      schema_version: "2.1.0"
      include_snippets: true
      include_fixes: true
      driver_name: "CPSIT Quality Tools"
```

## Format Examples

### JUnit XML Output

```xml
<?xml version="1.0" encoding="UTF-8"?>
<testsuites name="Quality Analysis" tests="42" failures="35" errors="5" time="15.7">
  <properties>
    <property name="quality-tools-version" value="2.0.0"/>
    <property name="project-name" value="example-project"/>
  </properties>
  
  <testsuite name="rector" tests="12" failures="10" errors="2" time="5.2" package="modernization">
    <testcase name="TypedPropertyRector" classname="src.Example" time="0.1">
      <failure type="warning" message="Add typed property declaration">
        File: src/Example.php:15
        Rule: TypedPropertyRector
        Fix available: Yes
      </failure>
    </testcase>
    <testcase name="ValidCodeExample" classname="src.Valid" time="0.05"/>
  </testsuite>
  
  <testsuite name="phpstan" tests="18" failures="15" errors="3" time="8.1" package="static-analysis">
    <!-- test cases for PHPStan issues -->
  </testsuite>
</testsuites>
```

### Generic XML Output

```xml
<?xml version="1.0" encoding="UTF-8"?>
<quality-report xmlns="https://schema.quality-tools.example.com/v1" 
                version="1.0" generated="2024-01-20T14:30:00Z">
  <metadata>
    <project name="example-project" version="1.2.3"/>
    <environment php-version="8.3.1" os="Linux"/>
    <execution total-time="15.7" files-analyzed="156"/>
  </metadata>
  
  <tools>
    <tool name="rector" version="0.18.0" execution-time="5.2">
      <issues count="12">
        <issue id="rector-001" severity="warning" category="modernization">
          <rule>TypedPropertyRector</rule>
          <message>Add typed property declaration</message>
          <location file="src/Example.php" line="15" column="5"/>
          <fix available="true" automatic="true">protected string $property;</fix>
        </issue>
      </issues>
    </tool>
  </tools>
  
  <summary total-issues="42" total-tools="5"/>
</quality-report>
```

### SARIF Output

```json
{
  "$schema": "https://raw.githubusercontent.com/oasis-tcs/sarif-spec/master/Schemata/sarif-schema-2.1.0.json",
  "version": "2.1.0",
  "runs": [
    {
      "tool": {
        "driver": {
          "name": "CPSIT Quality Tools",
          "version": "2.0.0",
          "rules": [
            {
              "id": "TypedPropertyRector",
              "shortDescription": {"text": "Add typed property declaration"},
              "helpUri": "https://getrector.org/rule/TypedPropertyRector"
            }
          ]
        }
      },
      "results": [
        {
          "ruleId": "TypedPropertyRector",
          "level": "warning",
          "message": {"text": "Add typed property declaration"},
          "locations": [
            {
              "physicalLocation": {
                "artifactLocation": {"uri": "src/Example.php"},
                "region": {"startLine": 15, "startColumn": 5}
              }
            }
          ],
          "fixes": [
            {
              "description": {"text": "Add string type declaration"},
              "artifactChanges": [
                {
                  "artifactLocation": {"uri": "src/Example.php"},
                  "replacements": [
                    {
                      "deletedRegion": {"startLine": 15, "startColumn": 5, "endColumn": 22},
                      "insertedContent": {"text": "protected string $property;"}
                    }
                  ]
                }
              ]
            }
          ]
        }
      ]
    }
  ]
}
```

## Class Implementation

Leverages unified architecture from Feature 006:

```php
// JUnit XML Format Writer
class JunitXmlFormatWriter extends AbstractFormatWriter
{
    public function getSupportedFormat(): string { return 'junit'; }
    public function supportsTemplating(): bool { return true; }
    
    // Uses ReportDataModel from Feature 006
    protected function generateFromData(ReportDataModel $data): string
    {
        // Direct JUnit XML generation using unified data model
        return $this->buildJunitXmlFromData($data->toArray());
    }
    
    // Uses template engine from Feature 006
    protected function generateFromTemplate(ReportDataModel $data): string
    {
        return $this->templateEngine->render('report.junit.xml.twig', $data->toArray());
    }
}

// Generic XML Format Writer  
class XmlFormatWriter extends AbstractFormatWriter
{
    public function getSupportedFormat(): string { return 'xml'; }
    public function supportsTemplating(): bool { return true; }
    
    protected function generateFromData(ReportDataModel $data): string
    {
        return $this->buildGenericXmlFromData($data->toArray());
    }
    
    protected function generateFromTemplate(ReportDataModel $data): string
    {
        return $this->templateEngine->render('report.xml.twig', $data->toArray());
    }
}

// SARIF Format Writer
class SarifFormatWriter extends AbstractFormatWriter  
{
    public function getSupportedFormat(): string { return 'sarif'; }
    public function supportsTemplating(): bool { return true; }
    
    protected function generateFromData(ReportDataModel $data): string
    {
        $sarifData = $this->transformToSarifFormat($data->toArray());
        return json_encode($sarifData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }
    
    protected function generateFromTemplate(ReportDataModel $data): string
    {
        return $this->templateEngine->render('report.sarif.json.twig', $data->toArray());
    }
}

// Registration with FormatWriterRegistry from Feature 006
class XmlFormatWritersRegistration
{
    public function register(FormatWriterRegistry $registry): void
    {
        $registry->register(new JunitXmlFormatWriter($this->templateEngine));
        $registry->register(new XmlFormatWriter($this->templateEngine));
        $registry->register(new SarifFormatWriter($this->templateEngine));
    }
}
```

## Performance Considerations

- Efficient DOM manipulation for large XML documents
- Memory management for large result sets
- Streaming XML generation where possible
- Schema validation performance optimization
- Compression support for large XML files

## Testing Strategy

- Schema validation tests against official specifications
- Integration tests with CI/CD platforms (Jenkins, GitHub Actions)
- IDE compatibility testing
- Performance tests with large datasets
- Round-trip testing for data integrity

## Dependencies

- **Feature 006 (Unified Report Generation Foundation)**: Provides AbstractFormatWriter, ReportDataModel, TemplateEngineInterface, FormatWriterRegistry, and UnifiedReportGenerator
- XML DOM manipulation libraries for direct XML generation
- Template engine integration from Feature 006 (Twig recommended)
- Schema validation capabilities (XSD for XML, JSON Schema for SARIF)
- File system access inherited from unified foundation
- CI/CD platform testing environments for validation

## Risk Assessment

**Medium:**
- XML format complexity and schema compliance requirements
- Different platform expectations and compatibility issues
- SARIF specification complexity and evolving standards

**Mitigation:**
- Comprehensive testing against official schemas and specifications
- Platform-specific compatibility testing
- Clear documentation for each format and its use cases
- Fallback mechanisms for schema validation failures
- Regular validation against updated specifications

## Future Enhancements

- Custom XML schema plugins for specific tools
- Advanced SARIF features (code flows, graph traversal)
- XML transformation and XSLT support
- Integration with XML-based quality databases
- Multi-format output with cross-references

## Notes

- JUnit XML is the priority format for CI/CD integration
- SARIF format should focus on security-related tools initially
- Generic XML provides flexibility for custom integrations
- All formats should maintain traceability to source JSON format
- Consider memory usage and streaming for very large outputs
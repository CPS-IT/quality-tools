# Feature 006: Unified Report Generation Foundation

**Status:** Not Started
**Estimated Time:** 6-8 hours
**Layer:** MCP Integration
**Dependencies:** 005-report-format-research-and-standards (Not Started)

## Description

Implement the foundational infrastructure for unified report generation supporting all output formats (JSON, XML, JUnit, SARIF, HTML, Markdown, Text). This creates a single, consistent data model and generation engine that all format-specific features build upon, eliminating artificial distinctions between "machine-readable" and "human-readable" reports.

## Problem Statement

Current approach creates unnecessary complexity with separate systems for different report formats:

- Artificial distinction between machine and human-readable reports
- No unified data model across all output formats
- Duplicated infrastructure for data collection and normalization
- Inconsistent configuration approaches between formats
- Complex dependency chains between reporting features

## Goals

- Create single, unified report generation infrastructure for all formats
- Establish common data model used by JSON, XML, HTML, text, and all other formats
- Build format-agnostic report engine with consistent configuration
- Provide template engine support for all formats (not just HTML/text)
- Enable easy addition of new formats without architectural changes

## Tasks

- [ ] Unified Core Infrastructure
  - [ ] Create UnifiedReportGenerator for all formats
  - [ ] Implement ToolOutputCollector for data gathering
  - [ ] Build IssueNormalizer for unified data transformation
  - [ ] Create ReportDataModel as single source of truth
  - [ ] Implement unified configuration system for all formats
- [ ] Data Collection and Normalization
  - [ ] Build tool output parsing abstraction
  - [ ] Implement CLI output parsing (common baseline)
  - [ ] Add comprehensive error handling and validation
  - [ ] Create data sanitization and enrichment
- [ ] Template Engine Foundation
  - [ ] Integrate template engine support for all formats
  - [ ] Create template abstraction layer (Twig, Handlebars, etc.)
  - [ ] Build format writer interface with template support
  - [ ] Implement template inheritance and customization
  - [ ] Add template caching and performance optimization
- [ ] Format Writer Architecture
  - [ ] Design pluggable format writer system
  - [ ] Create base classes for structured and templated formats
  - [ ] Implement format registration and discovery
  - [ ] Add cross-format validation and consistency checking
- [ ] Testing and Validation Framework
  - [ ] Create comprehensive unit tests for all components
  - [ ] Build integration tests with real tool outputs
  - [ ] Add template rendering and format output tests
  - [ ] Implement performance tests for large datasets
  - [ ] Create cross-format consistency validation tests

## Success Criteria

- [ ] Single data model serves all report formats (JSON, XML, HTML, text, etc.)
- [ ] Unified configuration system works consistently across all formats
- [ ] Template engine supports structured data formats as well as presentation formats
- [ ] Tool outputs are collected and normalized once, used everywhere
- [ ] New formats can be added without changing core infrastructure
- [ ] Performance is acceptable for all format combinations
- [ ] Cross-format consistency is maintained and validated

## Technical Requirements

### Unified Data Model

**ReportDataModel:**
- Single canonical representation of analysis results
- Supports all tool types and issue categories
- Rich metadata and execution context
- Extensible schema for future tool additions
- Format-agnostic data structure

**Data Flow:**
```
Tool Outputs -> Normalization -> Unified Data Model -> Format Writers -> Various Outputs
```

### Core Components

**UnifiedReportGenerator:**
- Single entry point for all report generation
- Format-agnostic processing of unified data model
- Plugin architecture for format writers
- Template engine integration for all formats
- Configuration-driven behavior with unified schema

**ToolOutputCollector:**
- Unified interface for tool result collection
- Support for native formats and CLI parsing
- Error recovery and partial result handling
- Metadata extraction and enrichment
- Concurrent tool execution support

**Template Engine Integration:**
- Template support for ALL formats (JSON, XML, HTML, text)
- Multiple template engine support (Twig, Handlebars, etc.)
- Template inheritance and customization system
- Caching and performance optimization
- Format-specific helper functions and filters

### Format Writer Architecture

**Base Classes:**
- `AbstractFormatWriter`: Common functionality for all formats
- `StructuredFormatWriter`: For JSON, XML, SARIF (data-driven)
- `TemplatedFormatWriter`: For HTML, Markdown, Text (template-driven)
- `HybridFormatWriter`: For formats that can use both approaches

## Implementation Plan

### Phase 1: Unified Data Model and Core Infrastructure (3-4 hours)

1. Design and implement ReportDataModel as single source of truth
2. Create UnifiedReportGenerator with format writer registration
3. Build ToolOutputCollector with CLI output parsing baseline
4. Implement IssueNormalizer for unified data transformation
5. Add unified configuration system core (format-agnostic parts)

### Phase 2: Template Engine Foundation (2-3 hours)

1. Implement template engine abstraction layer (Twig recommended)
2. Create template loading and caching system
3. Build format writer base classes (Abstract, Structured, Templated)
4. Add template inheritance and customization capabilities
5. Implement common template helper functions and filters

### Phase 3: Format Writer Architecture (1-2 hours)

1. Design pluggable format writer registration system
2. Create cross-format validation and consistency checking
3. Build format discovery and enumeration capabilities
4. Add format writer interface and base implementations
5. Implement format writer factory pattern

### Phase 4: Testing and Integration (1 hour)

1. Create comprehensive unit tests for all components
2. Build integration tests with real tool outputs
3. Add template rendering and cross-format consistency tests
4. Implement performance tests for various format combinations
5. Create end-to-end workflow validation tests

## Unified Configuration Schema

```yaml
reports:
  # Unified configuration for ALL formats
  
  # Core engine settings
  engine:
    max_concurrent_tools: 4
    timeout_seconds: 300
    retry_attempts: 2
    template_engine: "twig"  # twig, handlebars, pug, plates
    template_cache: true
    
  # Data collection and normalization
  collection:
    prefer_native_formats: true
    fallback_to_cli: true
    validate_outputs: true
    sanitize_paths: true
    include_metadata: true
    include_execution_context: true
    
  # Output configuration
  output:
    base_directory: "reports/"
    
    # Formats to generate (all use same data model)
    formats:
      - json
      - xml
      - junit
      - sarif
      - html
      - markdown
      - text
    
    # Format-specific settings (handled by individual format features)
    # Common template configuration
    templates:
      base_template_dir: "templates/"
      custom_template_dir: "custom/templates/"
      cache_templates: true
  
  # Unified branding (applies to all formats)
  branding:
    project_name: "${PROJECT_NAME:-Quality Analysis}"
    organization: "CPSIT AG"
    logo_path: "assets/logo.png"
    custom_footer: "Generated by CPSIT Quality Tools"
    
  # Content options (consistent across formats)
  content:
    include_code_snippets: true
    max_snippet_lines: 10
    show_recommendations: true
    group_by_severity: true
    show_file_paths: true
    
  # Error handling
  error_handling:
    continue_on_tool_failure: true
    log_level: "info"
    save_debug_info: false
```

## Unified Architecture Overview

```
src/Report/
├── Generator/
│   ├── UnifiedReportGenerator.php        # Single entry point for all formats
│   ├── ReportDataModel.php               # Unified data model
│   └── Configuration/
│       └── UnifiedReportConfig.php       # Single config for all formats
├── Collection/
│   ├── ToolOutputCollector.php
│   ├── Parsers/
│   │   ├── ParserInterface.php
│   │   └── CliParser.php           # Common baseline parser
│   └── Normalizers/
│       ├── IssueNormalizer.php
│       └── MetadataNormalizer.php
├── Template/
│   ├── TemplateEngineInterface.php       # Template abstraction
│   ├── TwigTemplateEngine.php           # Recommended implementation
│   ├── HandlebarsTemplateEngine.php     # Alternative option
│   └── TemplateEngineFactory.php
├── Formats/
│   ├── FormatWriterInterface.php
│   ├── AbstractFormatWriter.php          # Base for all writers
│   ├── StructuredFormatWriter.php        # Base for data-driven formats
│   ├── TemplatedFormatWriter.php         # Base for template-driven formats
│   └── FormatWriterRegistry.php          # Format registration system
└── Validation/
    ├── SchemaValidator.php
    ├── ReportValidator.php
    └── CrossFormatValidator.php           # Ensures consistency
```

## Unified Class Structure

```php
class UnifiedReportGenerator
{
    public function __construct(
        private ToolOutputCollector $collector,
        private TemplateEngineFactory $templateFactory,
        private FormatWriterRegistry $writerRegistry
    ) {}
    
    public function generateAllReports(UnifiedReportConfig $config): array
    {
        // 1. Collect and normalize tool outputs
        $toolResults = $this->collector->collectAll();
        
        // 2. Create unified data model
        $reportData = new ReportDataModel($toolResults, $config);
        
        // 3. Generate all configured formats
        $reports = [];
        foreach ($config->getEnabledFormats() as $format) {
            $writer = $this->writerRegistry->getWriter($format);
            $reports[$format] = $writer->generate($reportData, $config);
        }
        
        return $reports;
    }
}

class ReportDataModel
{
    // Single canonical data structure used by ALL formats
    public readonly array $project;
    public readonly array $execution;
    public readonly array $environment;
    public readonly array $tools;
    public readonly array $issues;
    public readonly array $summary;
    public readonly array $branding;
    
    public function toArray(): array
    {
        // Returns data in format suitable for ALL output types
    }
}

interface FormatWriterInterface
{
    public function generate(ReportDataModel $data, UnifiedReportConfig $config): string;
    public function getSupportedFormat(): string;
    public function supportsTemplating(): bool;
}

abstract class AbstractFormatWriter implements FormatWriterInterface
{
    protected TemplateEngineInterface $templateEngine;
    protected UnifiedReportConfig $config;
    
    public function generate(ReportDataModel $data, UnifiedReportConfig $config): string
    {
        $this->config = $config;
        
        if ($this->supportsTemplating() && $config->hasTemplate($this->getSupportedFormat())) {
            return $this->generateFromTemplate($data);
        }
        
        return $this->generateFromData($data);
    }
    
    abstract protected function generateFromData(ReportDataModel $data): string;
    abstract protected function generateFromTemplate(ReportDataModel $data): string;
}

// Even JSON can use templates for consistency
class JsonFormatWriter extends StructuredFormatWriter
{
    public function getSupportedFormat(): string { return 'json'; }
    
    protected function generateFromTemplate(ReportDataModel $data): string
    {
        return $this->templateEngine->render('report.json.twig', $data->toArray());
    }
    
    protected function generateFromData(ReportDataModel $data): string
    {
        return json_encode($data->toArray(), JSON_PRETTY_PRINT);
    }
}

class HtmlFormatWriter extends TemplatedFormatWriter
{
    public function getSupportedFormat(): string { return 'html'; }
    
    protected function generateFromTemplate(ReportDataModel $data): string
    {
        return $this->templateEngine->render('report.html.twig', $data->toArray());
    }
}
```

## Unified Data Flow

```
1. Unified Configuration Loading
   ↓
2. Tool Execution & Output Collection (once)
   ↓
3. Output Parsing & Normalization (once)
   ↓
4. Unified Data Model Creation (single source of truth)
   ↓
5. Template Engine Initialization
   ↓
6. Parallel Format Generation (JSON, XML, HTML, Text, etc.)
   ↓
7. Cross-Format Validation & Consistency Checks
   ↓
8. Output Writing & Final Validation
```

**Key Benefits:**
- Data collected and normalized only once
- Same data model serves all formats
- Template engine supports ALL formats (even JSON/XML)
- Parallel generation of multiple formats
- Consistent branding and configuration across formats

## CLI Integration

```bash
# Generate all configured formats
qt report:generate

# Generate specific formats
qt report:generate --format=html,json,markdown

# Use custom template
qt report:generate --template=corporate --format=html

# Generate with custom configuration
qt report:generate --config=custom-reports.yaml
```

## Performance Considerations

- **Single Data Collection:** Tool outputs collected and normalized once, reused for all formats
- **Template Caching:** Compiled templates cached across all formats
- **Parallel Generation:** Multiple formats generated concurrently
- **Memory Efficiency:** Unified data model prevents duplication
- **Lazy Loading:** Large datasets processed incrementally
- **Format-Specific Optimization:** Each writer optimized for its format

## Testing Strategy

- **Unified Data Model Tests:** Validate single source of truth
- **Cross-Format Consistency Tests:** Ensure all formats contain same data
- **Template Rendering Tests:** Validate template engine with all formats
- **Integration Tests:** Real tool outputs with multiple format generation
- **Performance Tests:** Large codebases with multiple concurrent formats
- **Configuration Tests:** Unified configuration system validation

## Dependencies

- **Feature 005:** Report Format Research and Standards (for unified schema)
- **Template Engine:** Twig (recommended), alternatives as needed
- **JSON/XML Libraries:** Native PHP support, additional validation libraries
- **File System Access:** For template loading and multi-format output
- **Process Execution:** For tool integration and data collection

## Risk Assessment

**Low-Medium:**
- **Architectural Complexity:** Single unified system vs. separate format systems
- **Template Engine Dependency:** All formats depend on template engine working
- **Performance Impact:** Multiple format generation may be resource-intensive

**Mitigation:**
- **Comprehensive Testing:** Extensive validation of unified architecture
- **Template Engine Fallback:** Direct data generation when templates fail
- **Performance Monitoring:** Optimize unified data model and parallel generation
- **Format Independence:** Each writer isolated, failures don't cascade
- **Gradual Rollout:** Start with core formats, add others incrementally

## Future Enhancements

- **Plugin System:** Custom format writers and template engines
- **Real-time Generation:** Live report updates during analysis
- **Format Conversion:** Convert between existing report formats
- **Advanced Templates:** Template inheritance, includes, and macros
- **API Integration:** RESTful API for report generation and retrieval
- **Report Comparison:** Side-by-side analysis of different report runs

## Benefits of Unified Approach

1. **Consistency:** All formats contain identical data and follow same structure
2. **Maintainability:** Single codebase for data collection and normalization
3. **Performance:** No duplicate processing, parallel format generation
4. **Flexibility:** Template engine supports all formats, easy customization
5. **Extensibility:** New formats added easily without architectural changes
6. **Configuration:** Single, consistent configuration schema for all formats
7. **Testing:** Unified testing approach, cross-format validation built-in

## Notes

- **Template-First Approach:** Even structured formats (JSON/XML) can use templates
- **Data Model Priority:** Focus on rich, comprehensive unified data model
- **Format Agnostic:** Core logic independent of output format
- **Consistent Configuration:** Same configuration schema for all formats
- **Performance Focus:** Optimize for multiple concurrent format generation
- **Future-Proof:** Architecture supports new formats without refactoring
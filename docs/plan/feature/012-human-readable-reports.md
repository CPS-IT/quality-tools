# Feature 012: Human-Readable Reports

**Status:** Not Started  
**Estimated Time:** 6-8 hours  
**Layer:** MCP Integration  
**Dependencies:** 006-implement-basics-for-report-generation (Not Started)

## Description

Implement human-readable format writers that leverage the unified report generation foundation from Feature 006. Uses the shared ReportDataModel, template engine, and format writer architecture to produce HTML, Markdown, and text reports suitable for different audiences and documentation needs.

## Problem Statement

Structured report data needs human-readable presentation for different audiences:

- Stakeholders and managers need visual, accessible reports
- Documentation systems require Markdown or HTML formats
- Print and text outputs needed for archival and simple consumption
- Different audiences require different levels of technical detail
- Organizational branding and styling requirements

## Goals

- Leverage unified ReportDataModel from Feature 006 for human-readable formats
- Use template engine from unified foundation for flexible report generation  
- Support HTML, Markdown, and text formats with consistent underlying data
- Provide customizable templates and styling through template engine
- Create format writers that integrate with unified report generation architecture
- Enable easy template customization and organizational branding

## Tasks

- [ ] HTML Format Writer
  - [ ] Create HtmlFormatWriter extending AbstractFormatWriter from Feature 006
  - [ ] Implement generateFromTemplate() using unified template engine
  - [ ] Create responsive HTML template (report.html.twig) with CSS framework
  - [ ] Add HTML-specific template helpers for interactivity and styling
  - [ ] Register format writer with FormatWriterRegistry
- [ ] Markdown Format Writer  
  - [ ] Create MarkdownFormatWriter extending AbstractFormatWriter from Feature 006
  - [ ] Implement GitHub/GitLab compatible Markdown generation using ReportDataModel
  - [ ] Create Markdown template (report.md.twig) with proper structure
  - [ ] Add Markdown-specific template helpers for tables and formatting
  - [ ] Support template-based table of contents and navigation
- [ ] Text Format Writer
  - [ ] Create TextFormatWriter extending AbstractFormatWriter from Feature 006
  - [ ] Implement console-friendly text generation using unified data model
  - [ ] Create text template (report.txt.twig) with ASCII formatting
  - [ ] Add text-specific template helpers for alignment and tables
  - [ ] Support terminal color output through template engine
- [ ] Template Engine Integration
  - [ ] Use existing template engine from Feature 006 (Twig recommended)
  - [ ] Create format-specific template helper functions and filters
  - [ ] Implement template inheritance for human-readable formats
  - [ ] Add branding and customization support through templates

## Success Criteria

- [ ] Unified ReportDataModel from Feature 006 supports all human-readable formats
- [ ] Template engine from unified foundation provides flexible report generation
- [ ] HTML reports are visually appealing, responsive, and interactive
- [ ] Markdown reports are suitable for documentation systems and Git platforms  
- [ ] Text reports are clear and console-friendly
- [ ] Templates are easily customizable for branding through template engine
- [ ] All format writers integrate seamlessly with UnifiedReportGenerator

## Technical Requirements

### Data Source Integration

**Unified Data Model Usage:**
- Use ReportDataModel from Feature 006 as the single source of truth
- Leverage existing data normalization and collection from unified foundation
- Support all unified schema elements and metadata
- Maintain data consistency across all human-readable formats

### Template Engine Integration

**Uses Feature 006 Foundation:**
- Leverages existing TemplateEngineInterface from unified foundation
- Uses configured template engine (Twig recommended) from Feature 006
- Builds upon existing template loading and caching system
- Extends template helper and filter infrastructure

### Output Formats

**HTML Reports:**
- Responsive design with CSS framework (Bootstrap or Tailwind CSS)
- Interactive JavaScript features (filtering, sorting, search)
- Syntax-highlighted code snippets using Prism.js or highlight.js
- Collapsible sections and expandable issue details
- Charts and visual summaries using Chart.js or D3.js
- Print-friendly CSS for PDF generation
- Custom CSS support for branding

**Markdown Reports:**
- GitHub/GitLab/Bitbucket compatible format
- Automatic table of contents generation
- Proper heading hierarchy and navigation
- Code blocks with language-specific syntax highlighting
- Summary tables and statistics in markdown format
- Suitable for documentation systems (GitBook, GitLab Pages, etc.)

**Text Reports:**
- Console-friendly formatting with proper spacing
- ANSI color support for terminal output
- ASCII tables and visual separators
- Structured sections with clear hierarchy
- Suitable for CI/CD logs and email notifications

## Implementation Plan

### Phase 1: Format Writers Implementation (2-3 hours)

1. Create HtmlFormatWriter, MarkdownFormatWriter, and TextFormatWriter extending AbstractFormatWriter from Feature 006
2. Implement generateFromTemplate() methods using unified template engine
3. Register format writers with FormatWriterRegistry from unified foundation
4. Test integration with UnifiedReportGenerator workflow

### Phase 2: Template Development (2-3 hours)

1. Create responsive HTML template (report.html.twig) with CSS framework
2. Implement GitHub-compatible Markdown template (report.md.twig)
3. Build console-friendly text template (report.txt.twig)
4. Add template inheritance using existing template engine capabilities

### Phase 3: Enhanced Features (1-2 hours)

1. Create format-specific template helper functions and filters
2. Add interactive JavaScript features and syntax highlighting for HTML
3. Implement charts and visual summaries using template engine
4. Add branding and customization support through templates

### Phase 4: Integration and Testing (1 hour)

1. Test all format writers with unified report generation pipeline
2. Validate template rendering performance and error handling
3. Create template documentation and customization examples
4. Ensure seamless integration with Feature 006 architecture

## Configuration Schema

Extends unified configuration from Feature 006 with human-readable format options:

```yaml
# Inherits from unified configuration in Feature 006
reports:
  output:
    formats:
      - html      # Enable HTML format
      - markdown  # Enable Markdown format  
      - text      # Enable text format
  
  # Format-specific configuration for human-readable formats
  format_options:
    html:
      # Template options (uses unified template engine from Feature 006)
      template: "report.html.twig"  # Optional custom template
      theme: "default"              # default, dark, minimal, corporate
      css_framework: "bootstrap"    # bootstrap, tailwind, custom
      custom_css: "assets/custom.css"
      include_charts: true
      interactive_features: true
      syntax_highlighting: "prism"  # prism, highlight, none
        
    markdown:
      # Template options (uses unified template engine from Feature 006)
      template: "report.md.twig"  # Optional custom template
      include_toc: true
      heading_style: "atx"  # atx (#), setext (===)
      github_compatible: true
      
    text:
      # Template options (uses unified template engine from Feature 006)
      template: "report.txt.twig"  # Optional custom template
      console_colors: true
      max_line_length: 120
      ascii_tables: true
```

## File Structure

```
reports/
├── quality-report.json        # Source JSON data (from Feature 007)
├── human/
│   ├── index.html            # Main HTML report
│   ├── README.md             # Markdown report  
│   ├── report.txt            # Text report
│   ├── assets/
│   │   ├── css/
│   │   │   ├── bootstrap.min.css
│   │   │   ├── report.css
│   │   │   └── prism.css
│   │   ├── js/
│   │   │   ├── chart.min.js
│   │   │   ├── prism.min.js
│   │   │   └── report.js
│   │   └── images/
│   │       └── logo.png
│   └── templates/
│       ├── base.html.twig     # Base HTML layout
│       ├── report.html.twig   # HTML report template
│       ├── report.md.twig     # Markdown template
│       └── report.txt.twig    # Text template
└── templates/
    ├── default/               # Default theme templates
    ├── dark/                  # Dark theme templates
    └── custom/                # Custom organization templates
```

## Template Examples

### Base HTML Template (Twig)

```twig
{# templates/base.html.twig #}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{% block title %}{{ project.name }} - Quality Analysis Report{% endblock %}</title>
    
    {% if config.html.css_framework == 'bootstrap' %}
        <link href="assets/css/bootstrap.min.css" rel="stylesheet">
    {% endif %}
    
    {% if config.html.syntax_highlighting == 'prism' %}
        <link href="assets/css/prism.css" rel="stylesheet">
    {% endif %}
    
    <link href="assets/css/report.css" rel="stylesheet">
    
    {% if config.html.custom_css %}
        <link href="{{ config.html.custom_css }}" rel="stylesheet">
    {% endif %}
</head>
<body>
    <div class="container-fluid">
        {% block header %}
            <header class="report-header">
                {% if branding.logo_path %}
                    <img src="{{ branding.logo_path }}" alt="Logo" class="logo">
                {% endif %}
                <h1>{{ branding.project_name }}</h1>
                <p class="report-subtitle">Quality Analysis Report</p>
                <p class="report-meta">Generated on {{ execution.timestamp|date('Y-m-d H:i:s') }}</p>
            </header>
        {% endblock %}
        
        {% block content %}{% endblock %}
        
        {% block footer %}
            <footer class="report-footer">
                <p>{{ branding.custom_footer }}</p>
                {% if branding.organization %}
                    <p>&copy; {{ branding.organization }}</p>
                {% endif %}
            </footer>
        {% endblock %}
    </div>
    
    {% if config.html.include_charts %}
        <script src="assets/js/chart.min.js"></script>
    {% endif %}
    
    {% if config.html.syntax_highlighting == 'prism' %}
        <script src="assets/js/prism.min.js"></script>
    {% endif %}
    
    {% if config.html.interactive_features %}
        <script src="assets/js/report.js"></script>
    {% endif %}
</body>
</html>
```

### Markdown Template (Twig)

```twig
{# templates/report.md.twig #}
# {{ branding.project_name }} - Quality Analysis Report

> Generated on {{ execution.timestamp|date('Y-m-d H:i:s') }} by {{ branding.organization }}

{% if config.markdown.include_toc %}
## Table of Contents

- [Executive Summary](#executive-summary)
- [Quality Metrics](#quality-metrics)
- [Tools Analysis](#tools-analysis)
{% for tool in tools %}
  - [{{ tool.name|title }}](#{{ tool.name|lower|replace({' ': '-'}) }})
{% endfor %}
- [Issues by Severity](#issues-by-severity)
- [Recommendations](#recommendations)

{% endif %}

## Executive Summary

**Total Issues Found:** {{ summary.total_issues }}
**Files Analyzed:** {{ execution.total_files_analyzed }}
**Execution Time:** {{ execution.total_time }}s

### Issues by Severity

| Severity | Count | Percentage |
|----------|-------|------------|
{% for severity, count in summary.by_severity %}
| {{ severity|title }} | {{ count }} | {{ ((count / summary.total_issues) * 100)|round(1) }}% |
{% endfor %}

## Quality Metrics

{% if config.html.include_charts %}
*Charts available in HTML report*
{% endif %}

### Tool Performance

| Tool | Issues Found | Execution Time | Status |
|------|-------------|----------------|---------|
{% for tool in tools %}
| {{ tool.name }} | {{ tool.issues_found }} | {{ tool.execution_time }}s | {% if tool.exit_code == 0 %}Success{% else %}Issues{% endif %} |
{% endfor %}

## Tools Analysis

{% for tool in tools %}
### {{ tool.name|title }}

**Version:** {{ tool.version }}  
**Configuration:** {{ tool.configuration.config_file|default('Default') }}  
**Issues Found:** {{ tool.issues_found }}

{% if tool.issues|length > 0 %}
#### Issues

{% for issue in tool.issues %}
- **{{ issue.severity|upper }}** in `{{ issue.file.path }}:{{ issue.file.line }}`
  - **Rule:** {{ issue.rule }}
  - **Message:** {{ issue.message }}
  {% if issue.fix.available %}
  - **Fix Available:** {{ issue.fix.suggestion }}
  {% endif %}
  {% if issue.documentation.url %}
  - **Documentation:** [{{ issue.rule }}]({{ issue.documentation.url }})
  {% endif %}

{% endfor %}
{% else %}
No issues found! 🎉
{% endif %}

{% endfor %}
```

## Class Implementation

```php
class HumanReadableReportGenerator
{
    public function __construct(
        private TemplateEngineFactory $templateEngineFactory,
        private JsonReportLoader $jsonLoader,
        private ReportConfiguration $config
    ) {}
    
    public function generateReports(string $jsonFilePath): array
    {
        $jsonData = $this->jsonLoader->load($jsonFilePath);
        $templateEngine = $this->templateEngineFactory->create($this->config->getTemplateEngine());
        
        $reports = [];
        
        foreach ($this->config->getFormats() as $format) {
            $reports[$format] = $this->generateFormat($templateEngine, $jsonData, $format);
        }
        
        return $reports;
    }
    
    private function generateFormat(TemplateEngineInterface $engine, array $data, string $format): string
    {
        $templatePath = $this->config->getTemplate($format);
        $context = $this->prepareTemplateContext($data);
        
        return $engine->render($templatePath, $context);
    }
    
    private function prepareTemplateContext(array $jsonData): array
    {
        return [
            'project' => $jsonData['project'],
            'execution' => $jsonData['execution'],
            'tools' => $jsonData['tools'],
            'issues' => $jsonData['issues'],
            'summary' => $jsonData['summary'],
            'branding' => $this->config->getBranding(),
            'config' => $this->config->getFormatSettings()
        ];
    }
}

interface TemplateEngineInterface
{
    public function render(string $templatePath, array $context): string;
    public function addFilter(string $name, callable $filter): void;
    public function addFunction(string $name, callable $function): void;
}

class TwigTemplateEngine implements TemplateEngineInterface
{
    private Environment $twig;
    
    public function __construct(string $templateDirectory, bool $cache = true)
    {
        $loader = new FilesystemLoader($templateDirectory);
        $this->twig = new Environment($loader, [
            'cache' => $cache ? sys_get_temp_dir() . '/twig_cache' : false,
            'auto_reload' => true
        ]);
        
        $this->addCustomFilters();
        $this->addCustomFunctions();
    }
    
    public function render(string $templatePath, array $context): string
    {
        return $this->twig->render($templatePath, $context);
    }
    
    private function addCustomFilters(): void
    {
        $this->twig->addFilter(new TwigFilter('severity_class', function ($severity) {
            return match($severity) {
                'error' => 'danger',
                'warning' => 'warning',
                'info' => 'info',
                default => 'secondary'
            };
        }));
        
        $this->twig->addFilter(new TwigFilter('file_extension', function ($filePath) {
            return pathinfo($filePath, PATHINFO_EXTENSION);
        }));
    }
}

class TemplateEngineFactory
{
    public function create(string $engineType): TemplateEngineInterface
    {
        return match($engineType) {
            'twig' => new TwigTemplateEngine($this->getTemplateDirectory()),
            'handlebars' => new HandlebarsTemplateEngine($this->getTemplateDirectory()),
            'pug' => new PugTemplateEngine($this->getTemplateDirectory()),
            'plates' => new PlatesTemplateEngine($this->getTemplateDirectory()),
            default => throw new UnsupportedTemplateEngineException($engineType)
        };
    }
}
```

## CLI Command Integration

```bash
# Generate human-readable reports from existing JSON
qt report:generate --format=html,markdown,text

# Generate with custom template
qt report:generate --format=html --template=corporate

# Specify custom JSON source
qt report:generate --json-source=custom-report.json --format=markdown
```

## Performance Considerations

- **Template Caching:** Compiled templates cached for repeated use
- **Lazy Loading:** Large JSON datasets loaded incrementally
- **Asset Optimization:** Minified CSS/JS for faster HTML report loading
- **Memory Efficiency:** Streaming template rendering for very large reports
- **CDN Assets:** Option to use CDN for CSS/JS frameworks

## Testing Strategy

- **Template Rendering Tests:** Validate output for each template engine
- **JSON Data Integration Tests:** Ensure proper data transformation from Feature 007
- **Cross-Format Consistency Tests:** Verify content consistency across formats
- **Visual Regression Tests:** HTML report layout and styling validation
- **Performance Tests:** Template rendering speed with large datasets
- **Accessibility Tests:** Ensure HTML reports meet WCAG guidelines

## Dependencies

- **Feature 006 (Unified Report Generation Foundation)**: Provides AbstractFormatWriter, ReportDataModel, TemplateEngineInterface, FormatWriterRegistry, and UnifiedReportGenerator
- Template engine integration from Feature 006 (Twig recommended)
- **CSS Framework:** Bootstrap or Tailwind CSS for responsive design
- **JavaScript Libraries:** Chart.js for visualizations, Prism.js for syntax highlighting
- File system access inherited from unified foundation

## Risk Assessment

**Low:**
- Template-based approach provides flexibility and maintainability
- JSON input from Feature 007 provides stable, validated data source
- Read-only report generation with minimal side effects
- Multiple format support reduces vendor lock-in

**Mitigation:**
- Comprehensive template testing and validation
- Graceful fallback to simple text format if advanced formats fail
- Clear error messaging for template and rendering issues
- Performance monitoring for large report generation

## Future Enhancements

- **PDF Generation:** HTML-to-PDF conversion for professional reports
- **Interactive Dashboards:** Real-time filtering and analysis capabilities
- **Report Comparison:** Side-by-side comparison of quality reports over time
- **Custom Themes:** Organization-specific branding and styling
- **Multi-language Support:** Internationalization for global teams
- **Email Integration:** Automated report distribution via email

## Notes

- **Template Engine Choice:** Twig recommended for Symfony ecosystem compatibility
- **Data Source:** Completely dependent on Feature 007 JSON format
- **Performance Focus:** Optimize for typical TYPO3 project sizes (1000-5000 files)
- **Accessibility:** Ensure HTML reports are accessible to all users
- **Mobile-First:** Responsive design for viewing on all devices
- **Documentation:** Comprehensive template customization guides

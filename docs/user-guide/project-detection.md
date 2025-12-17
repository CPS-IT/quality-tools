# Project Detection

CPSIT Quality Tools uses an intelligent project detection system to automatically locate your TYPO3 project root. This guide explains how the detection works and how to troubleshoot detection issues.

## How Project Detection Works

### Detection Algorithm

The tool uses a multi-step process to find your TYPO3 project:

1. **Environment Variable Check**: First checks for the `QT_PROJECT_ROOT` environment variable
2. **Filesystem Traversal**: Starts from current working directory and searches upward
3. **Composer.json Analysis**: Examines each `composer.json` file found
4. **TYPO3 Dependency Validation**: Confirms presence of TYPO3 packages

### Step-by-Step Process

```
Current Directory: /path/to/your/project/packages/your-extension
                          ↓
1. Check QT_PROJECT_ROOT environment variable
                          ↓
2. Look for composer.json in current directory
   - Found: /path/to/your/project/packages/your-extension/composer.json
   - Check if contains TYPO3 dependencies: NO
                          ↓  
3. Move up one level: /path/to/your/project/packages/
   - Look for composer.json: NOT FOUND
                          ↓
4. Move up one level: /path/to/your/project/
   - Look for composer.json: FOUND
   - Check if contains TYPO3 dependencies: YES
                          ↓
5. Project root found: /path/to/your/project/
```

## TYPO3 Project Identification

### Recognized TYPO3 Dependencies

The tool identifies TYPO3 projects by checking for these Composer packages in either `require` or `require-dev` sections:

| Package | Description |
|---------|-------------|
| `typo3/cms-core` | TYPO3 Core framework |
| `typo3/cms` | Complete TYPO3 CMS distribution |
| `typo3/minimal` | Minimal TYPO3 installation |

### Example Valid composer.json

```json
{
    "name": "your/typo3-project",
    "require": {
        "typo3/cms-core": "^13.4",
        "other-dependencies": "..."
    }
}
```

Or:

```json
{
    "name": "your/typo3-project", 
    "require-dev": {
        "typo3/minimal": "^13.4",
        "dev-dependencies": "..."
    }
}
```

## Directory Traversal Details

### Maximum Search Levels

To prevent infinite loops, the tool limits upward traversal to **10 directory levels**. If no TYPO3 project is found within 10 levels, detection fails.

### Filesystem Root Detection

The algorithm stops when it reaches the filesystem root:
- **Unix/Linux/macOS**: Stops at `/`
- **Windows**: Stops at drive root (e.g., `C:\`)

### Search Path Example

Starting from: `/home/user/projects/mysite/packages/extension/src/Controller/`

Search order:
1. `/home/user/projects/mysite/packages/extension/src/Controller/composer.json`
2. `/home/user/projects/mysite/packages/extension/src/composer.json`
3. `/home/user/projects/mysite/packages/extension/composer.json`
4. `/home/user/projects/mysite/packages/composer.json`
5. `/home/user/projects/mysite/composer.json` ← **TYPO3 project found here**

## Supported Project Structures

### Standard TYPO3 Composer Project

```
your-project/
├── composer.json           # Contains typo3/cms-core
├── vendor/
├── config/
│   ├── sites/
│   └── system/
├── packages/               # Custom packages
│   ├── sitepackage/
│   └── custom-extension/
└── var/
```

### TYPO3 with Separate Web Root

```
your-project/
├── composer.json           # Contains TYPO3 dependencies
├── vendor/
├── config/
├── packages/
└── web/                    # Web document root
    ├── index.php
    └── typo3/
```

### Monorepo with Multiple TYPO3 Sites

```
monorepo/
├── site-a/
│   ├── composer.json       # Contains TYPO3 deps
│   ├── config/
│   └── packages/
├── site-b/
│   ├── composer.json       # Contains TYPO3 deps  
│   ├── config/
│   └── packages/
└── shared/
```

## Environment Variable Override

### Using QT_PROJECT_ROOT

You can override automatic detection by setting the `QT_PROJECT_ROOT` environment variable:

```bash
# Set for current session
export QT_PROJECT_ROOT=/path/to/your/typo3/project
vendor/bin/qt --version

# Set for single command
QT_PROJECT_ROOT=/path/to/project vendor/bin/qt --version

# Windows
set QT_PROJECT_ROOT=C:\path\to\project
vendor\bin\qt --version
```

### When to Use Override

Use the environment variable when:
- Automatic detection fails
- Working with non-standard project structures
- Running from outside the project directory
- Integrating with CI/CD systems

### Override Validation

The tool validates that the override path:
- Exists and is readable
- Is a directory
- Contains a valid TYPO3 project structure

## Error Handling

### Detection Failure

When project detection fails, you'll see this error:

```
TYPO3 project root not found. Please run this command from within a TYPO3 project directory, or set the QT_PROJECT_ROOT environment variable.
```

### Common Causes and Solutions

| Problem | Cause | Solution |
|---------|-------|----------|
| No composer.json found | Missing composer.json in project hierarchy | Ensure composer.json exists in project root |
| No TYPO3 dependencies | composer.json doesn't contain TYPO3 packages | Add typo3/cms-core to require section |
| Permission denied | File system permissions prevent reading composer.json | Check file/directory permissions |
| Invalid JSON | Malformed composer.json file | Validate and fix JSON syntax |
| Too deeply nested | Starting directory more than 10 levels from project root | Use QT_PROJECT_ROOT or run closer to root |

## Debugging Detection Issues

### Enable Debug Mode

Get detailed information about the detection process:

```bash
QT_DEBUG=true vendor/bin/qt --version
```

This will show:
- Directory traversal steps
- Composer.json files examined  
- TYPO3 dependency check results
- Full error stack traces

### Manual Verification

Test your project structure manually:

```bash
# Check if composer.json exists
ls -la composer.json

# Verify TYPO3 dependencies
grep -E "typo3/(cms-core|cms|minimal)" composer.json

# Check current directory
pwd

# Test environment variable
echo $QT_PROJECT_ROOT
```

## Best Practices

### Recommended Setup

1. **Keep composer.json in project root**: Place your main composer.json with TYPO3 dependencies at the top level
2. **Use standard structure**: Follow TYPO3 best practices for directory organization
3. **Avoid deep nesting**: Don't run commands from deeply nested subdirectories unnecessarily
4. **Version control**: Keep composer.json in version control to ensure consistency

### Performance Considerations

- Detection runs once per command execution
- Results are cached during a single command run
- Filesystem traversal is limited to prevent performance issues
- Environment variable override is fastest option
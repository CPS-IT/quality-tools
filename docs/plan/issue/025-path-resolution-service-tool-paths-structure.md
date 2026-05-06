# Issue 025: PathResolutionService Tool-Specific Paths Return Nested Structure

|               |                                                        |
|---------------|--------------------------------------------------------|
| **Status:**   | Open                                                   |
| **GitLab:**   | GL#9 (https://gitlab.321.works/DevOps/testing/quality-tools/-/work_items/9) |
| **GitHub:**   | GH#7 (https://github.com/CPS-IT/quality-tools/issues/7) |
| **Priority:** | High                                                   |
| **Effort:**   | Low (1-2h)                                             |
| **Impact:**   | High                                                   |

## Description

`PathResolutionService::getToolPaths()` returns the raw `paths` object from tool configuration as a nested associative array (`['scan' => ['path/']]`) instead of a flat list of strings. This is inconsistent with the global path resolution codepath which returns `['/abs/path1', '/abs/path2']`. The inconsistency causes TypeErrors at runtime and prevents uniform handling of resolved paths.

## Root Cause

`getToolPaths()` returns `$toolsConfig[$tool]['paths']` directly (line 78), which preserves the full YAML structure including the `scan` key. In contrast, `getScanPaths()` extracts `$pathsConfig['scan']` and normalizes each entry. `getResolvedPathsForTool()` uses both methods interchangeably via an `if (!empty($toolPaths))` branch, but the two return fundamentally different shapes.

Additionally, the tool-specific path is missing two processing steps that the global path receives:
1. No `normalizePath()` call (no `./` prefix stripping)
2. No `PathScanner::resolvePaths()` call (no glob expansion, no absolute path resolution)

## Error Details

**Error Message:**
```
TypeError: str_contains(): Argument #1 ($haystack) must be of type string, array given
```

**Location:** `src/Service/PathResolutionService.php:55-68` (getResolvedPathsForTool)
**Trigger:** Any tool runner calling `getResolvedPathsForTool()` when the YAML has tool-specific `paths.scan`

## Impact Analysis

**Affected Components:**
- `PathResolutionService::getResolvedPathsForTool()`
- `PathResolutionService::getToolPaths()`
- `Configuration::getResolvedPathsForTool()`
- All tool runners (RectorRunner, PhpStanRunner, PhpCsFixerRunner, FractorRunner, TypoScriptLintRunner, ComposerNormalizeRunner)

**User Impact:**
- Tool-specific path overrides in `.quality-tools.yaml` produce TypeErrors or wrong command arguments
- Users cannot use `tools.rector.paths.scan` etc. to configure per-tool scan paths

**Technical Impact:**
- Runners iterate over resolved paths and append them as command arguments; an array element causes TypeError or the literal string "Array" to be passed to the tool binary
- Relative paths (no absolute resolution) may resolve differently depending on working directory
- Vendor namespace patterns in tool-specific paths are never expanded

## Possible Solutions

### Solution 1: Fix getToolPaths() to extract and normalize scan paths
- **Description:** Change `getToolPaths()` to extract `$toolsConfig[$tool]['paths']['scan']` and apply `normalizePath()`, consistent with `getScanPaths()`
- **Effort:** Low
- **Impact:** High - makes tool-specific paths behave identically to global paths at extraction level
- **Pros:** Minimal change, fixes the immediate type mismatch
- **Cons:** Does not add glob/vendor expansion or absolute resolution

### Solution 2: Route tool-specific paths through PathScanner::resolvePaths()
- **Description:** In `getResolvedPathsForTool()`, pass tool-specific scan paths through the same `PathScanner::resolvePaths()` pipeline as global paths
- **Effort:** Low
- **Impact:** High - full parity with global path handling including glob expansion and absolute paths
- **Pros:** Complete fix; vendor patterns and globs work in tool-specific paths
- **Cons:** Slightly more complex; may change behavior for existing configs that rely on relative paths

### Solution 3: Merge tool-specific and global paths
- **Description:** When tool-specific paths exist, merge them with global scan paths and resolve everything together
- **Effort:** Medium
- **Impact:** Medium - additive behavior may not match user intent (override vs. extend)
- **Pros:** Most flexible; users get both global and tool-specific paths
- **Cons:** Ambiguous semantics; harder to reason about precedence

## Recommended Solution

**Choice:** Solution 2 - Route tool-specific paths through PathScanner::resolvePaths()

This provides full parity: normalization, glob expansion, vendor pattern resolution, and absolute path conversion all apply regardless of whether paths come from global or tool-specific configuration.

**Implementation Steps:**
1. Change `getToolPaths()` to return `$toolsConfig[$tool]['paths']['scan'] ?? []`
2. Apply `normalizePath()` to the extracted paths
3. In `getResolvedPathsForTool()`, pass tool-specific paths through `PathScanner::resolvePaths()` instead of returning them raw
4. Update existing unit tests for the changed return type
5. Remove workaround assertions in `ToolCommandPathConfigurationTest`

## Validation Plan

- [ ] `getResolvedPathsForTool()` returns `list<string>` for both global and tool-specific paths
- [ ] Tool-specific paths are absolute after resolution
- [ ] Vendor namespace patterns in tool-specific paths expand correctly
- [ ] Normalization (e.g. `./` stripping) applies to tool-specific paths
- [ ] Integration tests in `ToolCommandPathConfigurationTest` pass without nested-structure workarounds
- [ ] No regressions in existing unit tests

## Dependencies

- None; this is a self-contained fix in PathResolutionService

## Workarounds

Avoid using `tools.{tool}.paths.scan` in `.quality-tools.yaml`. Use global `paths.scan` instead, which works correctly for all tools.

## Related Issues

- Issue 021: Missing Integration Test Coverage (discovered during integration testing)
- Issue 019: Configuration Class Hierarchy Simplification (introduced PathResolutionService)

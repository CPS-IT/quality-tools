# [ADR-0007] Run the Tools from qt's Own Installation

## Status

Proposed

## Context

qt wraps Rector, PHPStan, Fractor, PHP CS Fixer and TypoScript Lint. Until now it started them from the **project's** `vendor/bin`. The tools and all their dependencies therefore had to be dependencies of the project, resolved in the same lock file as the production packages.

On current TYPO3 v13 projects with Symfony 8 this no longer resolves:

| Package | Needs | Project has |
|---|---|---|
| `ssch/typo3-rector` | `symfony/string` < 8 | 8.1 |
| `helmich/typo3-typoscript-lint` | `symfony/event-dispatcher` < 8 | 8.1 |
| `cpsit/quality-tools` itself | `symfony/event-dispatcher` 7, `justinrainbow/json-schema` 5 | 8, 6 |

Rector and TypoScript Lint could not run through qt at all, and qt itself could not be added to `require-dev`. Projects worked around it with a mix of `require-dev`, separate installs for single tools and CI jobs that bypass qt. In the reverse direction, a tool update can force a downgrade of a production package (Fractor 1.0 pinned `webmozart/assert` to 1.x).

At the same time, the toolset CI component already installs qt separately (`composer global require`). qt then *had* the tools in its own installation, but did not use them.

## Decision

qt always runs the tools from the installation it runs from itself, never from the project.

1. **Tool location.** `ProjectEnvironment::getToolBinPath()` returns Composer's bin directory of the running qt (`$_composer_bin_dir`, falling back to the directory of the loaded Composer `ClassLoader`). All tool runners use it. `QT_TOOL_BIN_PATH` overrides it.
2. **Project classes.** When qt runs from a separate installation, it passes the project's Composer autoloader to Rector (`--autoload-file`). PHPStan loads the project's autoloader on its own; Fractor, PHP CS Fixer and TypoScript Lint do not need the project's classes.
3. **Recommended setup for projects with conflicting dependencies:** an isolated installation per project, e.g. `.build/quality-tools/composer.json` with its own committed `composer.lock`. PHPStan extensions are installed there, next to PHPStan; a `replace` for `typo3/cms-core` and `typo3/cms-extbase` keeps a second copy of TYPO3 out.
4. **No change for `require-dev`.** With qt in the project's `require-dev`, qt's installation is the project's, so the tools come from the same directory as before.

## Consequences

### Positive Consequences
- Tool versions no longer constrain the project's production dependencies, and vice versa.
- Rector and TypoScript Lint can run on Symfony 8 projects.
- The tool versions are pinned per project in one lock file; local runs and CI use the same versions.
- Fewer project-specific CI overrides; the component can run every tool through qt.

### Negative Consequences
- Behaviour change for projects that install qt globally and the tools in the project: the tools now come from the global installation, possibly in other versions. This needs a changelog entry and a decision on the version (see Implementation Notes).
- PHPStan extensions have to be installed next to qt, not in the project.

### Neutral Consequences
- `composer normalize` is a Composer plugin and keeps running through the project's Composer.
- The test suite places fake tools via `QT_TOOL_BIN_PATH` instead of the project's `vendor/bin`.

## Alternatives Considered

### Alternative 1: Keep the project's vendor/bin, add an opt-in fallback to qt's own tools
Use qt's tools only when a tool is missing in the project. Smaller change, but which binary runs then depends on what happens to be installed; the conflicts remain for every project that keeps the tools in `require-dev`. Rejected in favour of one rule.

### Alternative 2: Keep the current model, document separate installs per tool
Projects install conflicting tools in their own directories and override the CI jobs. This is the workaround in use today; it duplicates setup in every project and bypasses qt's configuration and optimisation. Rejected.

## Implementation Notes

- Proof of concept: branch `poc/isolated-tool-install` (fork `v.golbig/quality-tools`). It also allows `a9f/typo3-fractor` in any 1.x and resolves TypoScript Lint's default config from qt's own config directory.
- Tested in the GitLab CI of a TYPO3 13.4 / PHP 8.5 / Symfony 8 project through the toolset component: all five tools green, Rector and TypoScript Lint for the first time; results identical to the previous separate installs.
- Open: release as a minor (opt-in through the isolated installation) or as a major; whether the CI component should install qt from `.build/quality-tools` when that directory exists.

## References

- Installation guide: `docs/user-guide/installation.md` ("Isolated Installation per Project", "Where the Tools Come From")
- Related: process timeout fix (#41, MR !32)

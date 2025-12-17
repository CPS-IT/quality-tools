TYPO3 Fractor
=============

## Usage

### Default Configuration

Scan all files in the `config` and `packages` directory of your project.
This doesn't change any file but will only report what would be changed if you
run `fractor process` without the `--dry-run` flag.

```shell
$  app/vendor/bin/fractor process --dry-run -c app/vendor/cpsit/quality-tools/config/fractor.php
```

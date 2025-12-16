TYPO3 Rector
=============

## Usage

### Default Configuration

Scan all files in the `config/system` and `packages` directory of your project. This doesn't change any file but will only report what would be changed if you run `rector` without the `--dry-run` flag.

```shell
$ app/vendor/bin/rector -c app/vendor/cpsit/quality-tools/config/rector.php --dry-run
```
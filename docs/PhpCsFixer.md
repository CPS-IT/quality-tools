PHP CS Fixer
=============

## Usage

### Default Configuration

Scan all files in the `config` and `packages` directory of your project.
This doesn't change any file but will only report what would be changed if you
run `php-cs-fixer fix` without the `--dry-run` flag.

```shell
$ app/vendor/bin/php-cs-fixer fix --dry-run --config=app/vendor/cpsit/quality-tools/config/php-cs-fixer.php
```

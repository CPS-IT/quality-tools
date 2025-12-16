PHPStan
=======

## Usage

### Default Configuration

Scan all PHP files in the packages directory in your project with the default
configuration.

```shell
$ app/vendor/bin/phpstan analyse -c app/vendor/cpsit/quality-tools/config/phpstan.neon
```

### Custom Path

Scan all PHP files in  `./<path to extension>/` directory with a custom
configuration file
```shell
$ app/vendor/bin/phpstan analyse -c phpstan.neon \
  ./<path to extension>
```
### Custom Level

Scan all PHP files in the packages directory in your project with the default
configuration and level 2.
```shell
$ app/vendor/bin/phpstan analyse \
  -c app/vendor/cpsit/quality-tools/config/phpstan.neon
  --level=2
```

TypoScript Lint
===============


## Usage

### Default Configuration

Scan all TypoScript files in the `Configuration/TypoScript/` directory of each
package in the packages directory in your project

```shell
$ vendor/bin/typoscript-lint -c vendor/cpsit/quality-tools/config/typoscript-lint.yml
```

### Custom Path

Scan all TypoScript files in the `Configuration/TypoScript/` directory of the
extension located at `./<path to extension>/`
```shell
$ vendor/bin/typoscript-lint \
  -c vendor/cpsit/quality-tools/config/typoscript-lint.yml \
  --path ./<path to extension>/Configuration/TypoScript/
```

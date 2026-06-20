# Contributing to flysystem-retry

## Workflow

1. **Issue first** — open a GitHub issue before starting any work
2. **Branch** — `feat/GH-{nr}-description`, `fix/GH-{nr}-description`
3. **Code** — follow the standards below
4. **Test** — `composer test` must pass; add tests for new behaviour
5. **PR** — open a pull request linking the issue
6. **Review** — all PRs require review before merge

## Standards

- PHP 8.2+, strict types, PSR-12 code style
- No personal paths in committed files
- LF line endings, UTF-8
- Conventional Commits: `feat: description (#nr)`, `fix: description (#nr)`

## Build & Test

```bash
composer install
vendor/bin/phpunit
vendor/bin/phpstan analyse src tests --level=8
```

## License

By contributing you agree your work is licensed under Apache-2.0.

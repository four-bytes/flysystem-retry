# flysystem-retry — AGENTS.md

## Project Overview

Generic Flysystem v3 retry decorator. Wraps any `FilesystemAdapter` with configurable exponential backoff and jitter. No Bunny-specific code — works with any adapter.

- **Package**: `four-bytes/flysystem-retry`
- **Namespace**: `Four\Flysystem\Retry\`
- **PHP**: 8.2+
- **Flysystem**: ^3.0

## Architecture

```
src/
├── RetryAdapter.php      # Implements FilesystemAdapter, delegates to inner adapter with retry loop
├── RetryPolicy.php       # maxAttempts, baseDelayMs, multiplier, maxDelayMs, jitterFactor
└── RetryClassifier.php   # Decides which exceptions are retryable (injectable transient class list)
```

## Key Design Decisions

- `RetryClassifier` takes a list of exception class names; any exception that is an `instanceof` one of those classes is retryable. Defaults to `[\RuntimeException::class]`.
- `RetryPolicy::delayFor(int $attempt)` returns milliseconds with jitter applied. Attempt 1 = `baseDelayMs`, attempt 2 = `baseDelayMs * multiplier`, etc., capped at `maxDelayMs`.
- `usleep()` is called with `delayMs * 1000`. Pass `baseDelayMs: 0` in tests to avoid real delays.
- The retry loop logs a `warning` on each retry and an `error` on final exhaustion via `LoggerInterface` (defaults to `NullLogger`).
- `listContents` returns an `iterable` — the retry wrapper materialises this by returning whatever the inner adapter returns; consumers should be aware that a generator is not re-entrant if partially consumed before an error.

## Development Commands

```bash
composer install
composer test
composer phpstan
```

Add to `composer.json` scripts:
```json
"scripts": {
    "test": "phpunit",
    "phpstan": "phpstan analyse src tests --level=8"
}
```

## Running Tests

```bash
vendor/bin/phpunit
```

Tests use a `FailingAdapter` stub (defined in test file) that throws a configurable number of times before succeeding. All tests use `RetryPolicy(baseDelayMs: 0)` to prevent real sleep.

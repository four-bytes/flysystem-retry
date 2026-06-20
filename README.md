# flysystem-retry

Flysystem v3 retry decorator. Wraps any `FilesystemAdapter` with configurable exponential backoff and jitter. Works with any adapter — no provider-specific code.

## Installation

```bash
composer require four-bytes/flysystem-retry
```

## Usage

```php
use Four\Flysystem\Retry\RetryAdapter;
use Four\Flysystem\Retry\RetryClassifier;
use Four\Flysystem\Retry\RetryPolicy;
use League\Flysystem\Filesystem;

$adapter = new RetryAdapter(
    inner: $yourAdapter,
    policy: new RetryPolicy(maxAttempts: 3, baseDelayMs: 100),
);

$filesystem = new Filesystem($adapter);
```

### Custom retry policy

```php
$policy = new RetryPolicy(
    maxAttempts: 5,
    baseDelayMs: 200,
    multiplier: 2.0,   // 200ms → 400ms → 800ms ...
    maxDelayMs: 5000,
    jitterFactor: 0.2, // ±20% randomness
);
```

### Custom exception classification

By default **nothing is retried** — the retryable exception list is empty. Pass a `RetryClassifier` to opt in to specific exception classes:

```php
use Your\Package\TransientStorageException;

$classifier = new RetryClassifier([
    TransientStorageException::class,
]);

$adapter = new RetryAdapter($yourAdapter, $policy, $classifier);
```

This opt-in design prevents accidentally retrying Flysystem's own permanent exceptions (`UnableToReadFile`, `UnableToWriteFile`, etc.), which also extend `\RuntimeException`. Permanent errors (401, 403, 404) should never be in the retryable list.

### With logging

```php
use Psr\Log\LoggerInterface;

$adapter = new RetryAdapter($yourAdapter, $policy, $classifier, $logger);
```

Logs a `warning` on each retry attempt and an `error` on final exhaustion.

## Requirements

- PHP 8.2+
- `league/flysystem` ^3.0
- `psr/log` ^3.0

## License

Apache License 2.0 — see [LICENSE](LICENSE).

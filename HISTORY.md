# History

## Unreleased

- `RetryAdapter` wrapping any `FilesystemAdapter` with configurable exponential backoff and jitter
- `RetryPolicy`: configurable `maxAttempts`, `baseDelayMs`, `multiplier`, `maxDelayMs`, `jitterFactor`
- `RetryClassifier`: opt-in transient exception list (empty by default — must explicitly configure)
- PSR-3 logger integration for retry events
- Apache-2.0 license

# Roadmap: four-bytes/flysystem-retry

Generic Flysystem v3 retry decorator. Wraps any `FilesystemAdapter` with configurable exponential backoff. No Bunny dependency — works with any adapter.

## Phase 0 — Namespace & scaffold verification

- [ ] Confirm PSR-4 root `Four\Flysystem\Retry\` matches `composer.json` and all `src/` files
- [ ] Run `composer validate` and `composer install`

## Phase 1 — RetryClassifier

`src/RetryClassifier.php` — current implementation retries all `\RuntimeException`; needs proper scope:

- [ ] Retry on `TransientBunnyException` (or any user-supplied transient class list)
- [ ] Retry on network-level exceptions: `\Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface` if present
- [ ] Never retry on Flysystem permanent exceptions: `UnableToReadFile` with 401/403/404 cause
- [ ] Make the transient class list injectable so consumers can configure it without subclassing
- [ ] Add `isRetryable(\Throwable $e, string $operation): bool` — some operations (e.g. `readStream`) may need different policy

## Phase 2 — RetryPolicy

`src/RetryPolicy.php` — complete jitter and validation:

- [ ] Validate `maxAttempts >= 1`, `baseDelayMs >= 0` on construction
- [ ] Ensure `delayFor()` never returns negative
- [ ] Add `RetryPolicy::default(): self` named constructor
- [ ] Add `RetryPolicy::noRetry(): self` for testing / disabling

## Phase 3 — RetryAdapter

`src/RetryAdapter.php` — complete the implementation:

- [ ] `retry()` private method must reset stream position before each retry of `writeStream` (stream may be partially consumed)
- [ ] `listContents()` returns `iterable` — materialise to array before retry so a failed generator does not leave a partial cursor
- [ ] Log attempt number, operation, path, delay on each retry (already wired, verify format)
- [ ] Log at `error` level (not `warning`) on final exhaustion

## Phase 4 — Tests

- [ ] `RetryAdapterTest` using `league/flysystem-memory` as inner adapter
  - successful operation passes through on first try
  - transient failure retries up to `maxAttempts`, then throws
  - non-retryable exception propagates immediately without retry
  - delay between retries is within expected range (mock `usleep`)
- [ ] `RetryPolicyTest` — backoff values, jitter bounds, edge cases
- [ ] `RetryClassifierTest` — retryable vs non-retryable exception types

## Phase 5 — Hardening & CI

- [ ] PHPStan level 8
- [ ] GitHub Actions: PHP 8.2 + 8.3, PHPUnit, PHPStan
- [ ] `composer.json` keywords, homepage, minimum-stability

## Acceptance criteria

- Wraps any `FilesystemAdapter` without Bunny-specific code
- Correctly classifies transient vs permanent errors
- Stream retry is safe (no partial-read corruption)
- PHPStan level 8 clean
- Ships with unit tests covering retry exhaustion and early exit

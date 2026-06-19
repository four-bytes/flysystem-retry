# Roadmap: four-bytes/flysystem-retry

Generic Flysystem v3 retry decorator. Wraps any `FilesystemAdapter` with configurable exponential backoff. No Bunny dependency — works with any adapter.

## Phase 0 — Namespace & scaffold verification ✅

- [x] Confirm PSR-4 root `Four\Flysystem\Retry\` matches `composer.json` and all `src/` files
- [x] Run `composer validate` and `composer install`

## Phase 1 — RetryClassifier ✅

`src/RetryClassifier.php`:

- [x] Default transient class list changed from `[\RuntimeException::class]` to `[]` (opt-in) to avoid accidentally retrying Flysystem's `UnableToXxx` exceptions (which also extend `RuntimeException`)
- [x] Transient class list is injectable — pass `new RetryClassifier([\TransientBunnyException::class])` from the consumer
- [x] `isRetryable(\Throwable $e): bool` — checks `instanceof` against all configured classes
- [ ] Add `isRetryable(\Throwable $e, string $operation): bool` — per-operation policy (future; not yet needed)

## Phase 2 — RetryPolicy ✅

`src/RetryPolicy.php` — exponential backoff with jitter:

- [x] Configurable `maxAttempts`, `baseDelayMs`, `multiplier`, `maxDelayMs`, `jitterFactor`
- [x] `delayFor(int $attempt): int` — returns capped exponential delay with jitter
- [x] PHP 8.4 compatible jitter using `(new \Random\Randomizer())->getFloat(0.0, 1.0)` (replaces deprecated `lcg_value()`)
- [ ] Validate `maxAttempts >= 1`, `baseDelayMs >= 0` on construction
- [ ] `RetryPolicy::noRetry(): self` named constructor for tests/disabling

## Phase 3 — RetryAdapter ✅

`src/RetryAdapter.php` — wraps all `FilesystemAdapter` methods with the retry loop:

- [x] All 13 interface methods covered
- [x] Retry loop: attempt until `maxAttempts` reached or non-retryable exception; exponential backoff via `usleep`
- [x] PSR logger wired for retry events
- [ ] `writeStream` retry: reset stream position (`rewind`) before each retry (currently does not rewind — partially consumed stream on retry)
- [ ] `listContents` retry: materialise generator to array before returning so a failed mid-stream generator does not leave a partial cursor
- [ ] Log at `error` level on final exhaustion (currently logs `warning`)

## Phase 4 — Tests ✅

- [x] `RetryAdapterTest` — 20 tests covering pass-through, retry until success, exhaustion, non-retryable propagation, all major operations
- [x] `RetryPolicyTest` — backoff values and jitter bounds
- [x] `RetryClassifierTest` — retryable vs non-retryable; default retries nothing

## Phase 5 — Hardening & CI

- [ ] PHPStan level 8
- [ ] GitHub Actions: PHP 8.2 + 8.3, PHPUnit, PHPStan
- [ ] `composer.json` keywords, homepage, minimum-stability
- [ ] Tag v0.1.0 and submit to Packagist

## Known limitations

- `writeStream` retry is unsafe: stream position is not reset between attempts; caller should pass a rewinding stream or wrap with a buffer
- `listContents` returns the raw generator from the inner adapter — a mid-iteration failure cannot be retried transparently

## Acceptance criteria

- Wraps any `FilesystemAdapter` without Bunny-specific code
- Correctly classifies transient vs permanent errors
- PHPStan level 8 clean
- Ships with unit tests covering retry exhaustion and early exit

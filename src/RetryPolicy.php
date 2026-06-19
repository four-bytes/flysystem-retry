<?php

declare(strict_types=1);

namespace Four\Flysystem\Retry;

final readonly class RetryPolicy
{
    public function __construct(
        public int $maxAttempts = 3,
        public int $baseDelayMs = 100,
        public float $multiplier = 2.0,
        public int $maxDelayMs = 5000,
        public float $jitterFactor = 0.2,
    ) {}

    public function delayFor(int $attempt): int
    {
        $delay = (int) min($this->baseDelayMs * ($this->multiplier ** ($attempt - 1)), $this->maxDelayMs);
        $jitter = (int) ($delay * $this->jitterFactor * ((new \Random\Randomizer())->getFloat(0.0, 1.0) * 2 - 1));

        return max(0, $delay + $jitter);
    }
}

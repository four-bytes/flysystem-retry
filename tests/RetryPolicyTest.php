<?php

declare(strict_types=1);

namespace Four\Flysystem\Retry\Tests;

use Four\Flysystem\Retry\RetryPolicy;
use PHPUnit\Framework\TestCase;

final class RetryPolicyTest extends TestCase
{
    public function testFirstAttemptDelayEqualsBaseDelay(): void
    {
        $policy = new RetryPolicy(baseDelayMs: 100, jitterFactor: 0.0);

        $this->assertSame(100, $policy->delayFor(1));
    }

    public function testSecondAttemptDelayIsMultiplied(): void
    {
        $policy = new RetryPolicy(baseDelayMs: 100, multiplier: 2.0, jitterFactor: 0.0);

        $this->assertSame(200, $policy->delayFor(2));
    }

    public function testDelayIsCapedAtMaxDelay(): void
    {
        $policy = new RetryPolicy(baseDelayMs: 1000, multiplier: 10.0, maxDelayMs: 3000, jitterFactor: 0.0);

        $this->assertSame(3000, $policy->delayFor(3));
    }

    public function testDelayIsNeverNegative(): void
    {
        $policy = new RetryPolicy(baseDelayMs: 0, jitterFactor: 1.0);

        for ($i = 1; $i <= 5; $i++) {
            $this->assertGreaterThanOrEqual(0, $policy->delayFor($i));
        }
    }

    public function testJitterIsWithinExpectedRange(): void
    {
        $policy = new RetryPolicy(baseDelayMs: 100, jitterFactor: 0.2, multiplier: 1.0, maxDelayMs: 200);

        for ($i = 0; $i < 50; $i++) {
            $delay = $policy->delayFor(1);
            $this->assertGreaterThanOrEqual(0, $delay);
            $this->assertLessThanOrEqual(200, $delay);
        }
    }

    public function testZeroBaseDelayReturnsZero(): void
    {
        $policy = new RetryPolicy(baseDelayMs: 0);

        $this->assertSame(0, $policy->delayFor(1));
    }
}

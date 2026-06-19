<?php

declare(strict_types=1);

namespace Four\Flysystem\Retry\Tests;

use Four\Flysystem\Retry\RetryClassifier;
use PHPUnit\Framework\TestCase;

final class RetryClassifierTest extends TestCase
{
    public function testDefaultClassifierRetiesRuntimeException(): void
    {
        $classifier = new RetryClassifier();

        $this->assertTrue($classifier->isRetryable(new \RuntimeException('transient')));
    }

    public function testDefaultClassifierDoesNotRetryLogicException(): void
    {
        $classifier = new RetryClassifier();

        $this->assertFalse($classifier->isRetryable(new \LogicException('programming error')));
    }

    public function testDefaultClassifierRetiesRuntimeExceptionSubclass(): void
    {
        $classifier = new RetryClassifier();
        $e = new class extends \RuntimeException {};

        $this->assertTrue($classifier->isRetryable($e));
    }

    public function testCustomTransientClassIsRetryable(): void
    {
        $classifier = new RetryClassifier([\OverflowException::class]);

        $this->assertTrue($classifier->isRetryable(new \OverflowException()));
    }

    public function testCustomTransientClassDoesNotRetryOtherException(): void
    {
        $classifier = new RetryClassifier([\OverflowException::class]);

        $this->assertFalse($classifier->isRetryable(new \RuntimeException()));
    }

    public function testMultipleTransientClasses(): void
    {
        $classifier = new RetryClassifier([\OverflowException::class, \UnderflowException::class]);

        $this->assertTrue($classifier->isRetryable(new \OverflowException()));
        $this->assertTrue($classifier->isRetryable(new \UnderflowException()));
        $this->assertFalse($classifier->isRetryable(new \RuntimeException()));
    }
}

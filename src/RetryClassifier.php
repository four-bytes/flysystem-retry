<?php

declare(strict_types=1);

namespace Four\Flysystem\Retry;

use League\Flysystem\FilesystemException;

final class RetryClassifier
{
    /** @var array<class-string<\Throwable>> */
    private array $transientClasses;

    /** @param array<class-string<\Throwable>> $transientClasses */
    public function __construct(array $transientClasses = [])
    {
        $this->transientClasses = $transientClasses ?: [
            \RuntimeException::class,
        ];
    }

    public function isRetryable(\Throwable $e): bool
    {
        foreach ($this->transientClasses as $class) {
            if ($e instanceof $class) {
                return true;
            }
        }

        return false;
    }
}

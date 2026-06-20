<?php

declare(strict_types=1);

namespace Four\Flysystem\Retry\Tests;

use Four\Flysystem\Retry\RetryAdapter;
use Four\Flysystem\Retry\RetryClassifier;
use Four\Flysystem\Retry\RetryPolicy;
use League\Flysystem\Config;
use League\Flysystem\FileAttributes;
use League\Flysystem\FilesystemAdapter;
use PHPUnit\Framework\TestCase;

final class RetryAdapterTest extends TestCase
{
    private static function noDelayPolicy(int $maxAttempts = 3): RetryPolicy
    {
        return new RetryPolicy(maxAttempts: $maxAttempts, baseDelayMs: 0);
    }

    private static function adapter(
        FilesystemAdapter $inner,
        int $maxAttempts = 3,
        ?RetryClassifier $classifier = null,
    ): RetryAdapter {
        return new RetryAdapter($inner, self::noDelayPolicy($maxAttempts), $classifier ?? new RetryClassifier());
    }

    private static function runtimeAdapter(FilesystemAdapter $inner, int $maxAttempts = 3): RetryAdapter
    {
        return new RetryAdapter(
            $inner,
            self::noDelayPolicy($maxAttempts),
            new RetryClassifier([\RuntimeException::class]),
        );
    }

    public function testSuccessOnFirstTryPassesThrough(): void
    {
        $inner = $this->createMock(FilesystemAdapter::class);
        $inner->expects($this->once())->method('write');

        self::adapter($inner)->write('a.txt', 'hi', new Config());
    }

    public function testTransientFailureIsRetriedUntilSuccess(): void
    {
        $calls = 0;
        $inner = $this->createMock(FilesystemAdapter::class);
        $inner->method('write')->willReturnCallback(function () use (&$calls): void {
            $calls++;
            if ($calls < 3) {
                throw new \RuntimeException('transient');
            }
        });

        self::runtimeAdapter($inner)->write('a.txt', 'hi', new Config());

        $this->assertSame(3, $calls);
    }

    public function testExhaustsMaxAttemptsAndRethrows(): void
    {
        $inner = $this->createMock(FilesystemAdapter::class);
        $inner->method('write')->willThrowException(new \RuntimeException('always fails'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('always fails');

        self::runtimeAdapter($inner, maxAttempts: 2)->write('a.txt', 'hi', new Config());
    }

    public function testNonRetryableExceptionPropagatesImmediately(): void
    {
        $calls = 0;
        $inner = $this->createMock(FilesystemAdapter::class);
        $inner->method('write')->willReturnCallback(function () use (&$calls): void {
            $calls++;
            throw new \LogicException('permanent');
        });

        $adapter = new RetryAdapter(
            $inner,
            self::noDelayPolicy(),
            new RetryClassifier([\RuntimeException::class]),
        );

        $this->expectException(\LogicException::class);

        $adapter->write('a.txt', 'hi', new Config());

        $this->assertSame(1, $calls, 'Non-retryable exception must not trigger a retry');
    }

    public function testReadReturnsValueAfterRetry(): void
    {
        $calls = 0;
        $inner = $this->createMock(FilesystemAdapter::class);
        $inner->method('read')->willReturnCallback(function () use (&$calls): string {
            $calls++;
            if ($calls < 2) {
                throw new \RuntimeException('transient');
            }
            return 'content';
        });

        $result = self::runtimeAdapter($inner)->read('a.txt');

        $this->assertSame('content', $result);
        $this->assertSame(2, $calls);
    }

    public function testFileExistsReturnsValueAfterRetry(): void
    {
        $calls = 0;
        $inner = $this->createMock(FilesystemAdapter::class);
        $inner->method('fileExists')->willReturnCallback(function () use (&$calls): bool {
            $calls++;
            if ($calls < 2) {
                throw new \RuntimeException('transient');
            }
            return true;
        });

        $this->assertTrue(self::runtimeAdapter($inner)->fileExists('a.txt'));
    }

    public function testDeleteRetriesOnTransientFailure(): void
    {
        $calls = 0;
        $inner = $this->createMock(FilesystemAdapter::class);
        $inner->method('delete')->willReturnCallback(function () use (&$calls): void {
            $calls++;
            if ($calls < 2) {
                throw new \RuntimeException('transient');
            }
        });

        self::runtimeAdapter($inner)->delete('a.txt');

        $this->assertSame(2, $calls);
    }

    public function testVisibilityReturnsFileAttributes(): void
    {
        $inner = $this->createMock(FilesystemAdapter::class);
        $inner->method('visibility')->willReturn(new FileAttributes('a.txt'));

        $result = self::adapter($inner)->visibility('a.txt');

        $this->assertInstanceOf(FileAttributes::class, $result);
    }

    public function testListContentsReturnsDelegatedIterable(): void
    {
        $expected = [new FileAttributes('a.txt')];
        $inner = $this->createMock(FilesystemAdapter::class);
        $inner->method('listContents')->willReturn($expected);

        $result = self::adapter($inner)->listContents('/', false);

        $this->assertSame($expected, $result);
    }

    public function testMaxAttemptsOfOneNeverRetries(): void
    {
        $calls = 0;
        $inner = $this->createMock(FilesystemAdapter::class);
        $inner->method('write')->willReturnCallback(function () use (&$calls): void {
            $calls++;
            throw new \RuntimeException('fail');
        });

        $this->expectException(\RuntimeException::class);

        self::runtimeAdapter($inner, maxAttempts: 1)->write('a.txt', '', new Config());

        $this->assertSame(1, $calls);
    }

    public function testWriteStreamRewoundsSeekableStreamBeforeEachRetry(): void
    {
        $stream = fopen('php://memory', 'r+');
        if ($stream === false) {
            self::fail('Could not open memory stream');
        }
        fwrite($stream, 'hello');
        rewind($stream);

        $attempt = 0;
        $inner = $this->createMock(FilesystemAdapter::class);
        $inner->method('writeStream')->willReturnCallback(
            function (string $path, mixed $contents) use (&$attempt): void {
                $attempt++;
                $read = stream_get_contents($contents); // consume the stream
                if ($attempt === 1) {
                    throw new \RuntimeException('transient');
                }
                // On retry the adapter must have rewound: full content is available again
                $this->assertSame('hello', $read);
            }
        );

        self::runtimeAdapter($inner, maxAttempts: 2)->writeStream('f.txt', $stream, new Config());

        fclose($stream);
        $this->assertSame(2, $attempt);
    }

    public function testWriteStreamDoesNotFailOnNonSeekableStream(): void
    {
        // popen() returns int(0) from ftell() — not false — so it exercises the path where a naive
        // ftell()-based probe would incorrectly classify the stream as seekable. stream_get_meta_data()
        // correctly reports seekable=false, preventing the fseek() call.
        $stream = popen('true', 'r');
        if ($stream === false) {
            self::markTestSkipped('popen not available');
        }

        $inner = $this->createMock(FilesystemAdapter::class);
        $inner->expects($this->once())->method('writeStream');

        self::runtimeAdapter($inner)->writeStream('f.txt', $stream, new Config());

        pclose($stream);
    }
}

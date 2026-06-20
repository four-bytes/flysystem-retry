<?php

declare(strict_types=1);

namespace Four\Flysystem\Retry;

use League\Flysystem\Config;
use League\Flysystem\FileAttributes;
use League\Flysystem\FilesystemAdapter;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

final class RetryAdapter implements FilesystemAdapter
{
    public function __construct(
        private readonly FilesystemAdapter $inner,
        private readonly RetryPolicy $policy = new RetryPolicy(),
        private readonly RetryClassifier $classifier = new RetryClassifier(),
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {}

    private function retry(string $operation, string $path, callable $fn): mixed
    {
        $attempt = 0;
        while (true) {
            try {
                return $fn();
            } catch (\Throwable $e) {
                $attempt++;
                if ($attempt >= $this->policy->maxAttempts || !$this->classifier->isRetryable($e)) {
                    $this->logger->error('Flysystem retry exhausted', [
                        'operation' => $operation,
                        'path' => $path,
                        'attempts' => $attempt,
                        'error' => $e->getMessage(),
                    ]);
                    throw $e;
                }

                $delayMs = $this->policy->delayFor($attempt);
                $this->logger->warning('Flysystem transient failure, retrying', [
                    'operation' => $operation,
                    'path' => $path,
                    'attempt' => $attempt,
                    'delay_ms' => $delayMs,
                ]);

                usleep($delayMs * 1000);
            }
        }
    }

    public function fileExists(string $path): bool
    {
        return $this->retry('fileExists', $path, fn () => $this->inner->fileExists($path));
    }

    public function directoryExists(string $path): bool
    {
        return $this->retry('directoryExists', $path, fn () => $this->inner->directoryExists($path));
    }

    public function write(string $path, string $contents, Config $config): void
    {
        $this->retry('write', $path, fn () => $this->inner->write($path, $contents, $config));
    }

    public function writeStream(string $path, $contents, Config $config): void
    {
        // Capture the stream position before the first attempt so each retry starts from the same offset.
        // Non-seekable streams (pipes, sockets) have seekable=false in metadata — ftell() alone is not
        // a reliable probe because popen()/stream_socket_pair() return int(0) rather than false.
        $seekable = is_resource($contents) && stream_get_meta_data($contents)['seekable'];
        $startPos = $seekable ? ftell($contents) : false;

        $this->retry('writeStream', $path, function () use ($path, $contents, $config, $startPos): void {
            if ($startPos !== false) {
                fseek($contents, $startPos);
            }
            $this->inner->writeStream($path, $contents, $config);
        });
    }

    public function read(string $path): string
    {
        return $this->retry('read', $path, fn () => $this->inner->read($path));
    }

    public function readStream(string $path)
    {
        return $this->retry('readStream', $path, fn () => $this->inner->readStream($path));
    }

    public function delete(string $path): void
    {
        $this->retry('delete', $path, fn () => $this->inner->delete($path));
    }

    public function deleteDirectory(string $path): void
    {
        $this->retry('deleteDirectory', $path, fn () => $this->inner->deleteDirectory($path));
    }

    public function createDirectory(string $path, Config $config): void
    {
        $this->retry('createDirectory', $path, fn () => $this->inner->createDirectory($path, $config));
    }

    public function setVisibility(string $path, string $visibility): void
    {
        $this->retry('setVisibility', $path, fn () => $this->inner->setVisibility($path, $visibility));
    }

    public function visibility(string $path): FileAttributes
    {
        return $this->retry('visibility', $path, fn () => $this->inner->visibility($path));
    }

    public function mimeType(string $path): FileAttributes
    {
        return $this->retry('mimeType', $path, fn () => $this->inner->mimeType($path));
    }

    public function lastModified(string $path): FileAttributes
    {
        return $this->retry('lastModified', $path, fn () => $this->inner->lastModified($path));
    }

    public function fileSize(string $path): FileAttributes
    {
        return $this->retry('fileSize', $path, fn () => $this->inner->fileSize($path));
    }

    public function listContents(string $path, bool $deep): iterable
    {
        // Retry covers generator creation only — failures during iteration are not retried.
        return $this->retry('listContents', $path, fn () => $this->inner->listContents($path, $deep));
    }

    public function move(string $source, string $destination, Config $config): void
    {
        $this->retry('move', $source, fn () => $this->inner->move($source, $destination, $config));
    }

    public function copy(string $source, string $destination, Config $config): void
    {
        $this->retry('copy', $source, fn () => $this->inner->copy($source, $destination, $config));
    }
}

<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Support;

use DimitrienkoV\LaravelModules\Exceptions\ManifestWriteException;
use JsonException;
use Throwable;

final readonly class AtomicJsonWriter
{
    /**
     * @param array<string, mixed> $data
     */
    public function write(string $path, array $data): void
    {
        $directory = \dirname($path);

        if (! is_dir($directory) && ! mkdir($directory, 0755, true) && ! is_dir($directory)) {
            throw ManifestWriteException::forPath($path, "directory [{$directory}] could not be created.");
        }

        $lock = $this->openLock($path);
        $temporaryPath = null;

        try {
            if (! flock($lock, LOCK_EX)) {
                throw ManifestWriteException::forPath($path, 'exclusive file lock could not be acquired.');
            }

            $temporaryPath = $this->temporaryPath($directory, $path);
            $this->writeTemporaryFile($temporaryPath, $this->encode($data, $path));

            if (is_file($path)) {
                $permissions = fileperms($path);
                if ($permissions !== false) {
                    chmod($temporaryPath, $permissions & 0777);
                }
            }

            if (is_dir($path) || ! rename($temporaryPath, $path)) {
                throw ManifestWriteException::forPath($path, 'temporary file could not be renamed atomically.');
            }

            $temporaryPath = null;
        } catch (ManifestWriteException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw ManifestWriteException::forPath($path, $exception->getMessage(), $exception);
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);

            if ($temporaryPath !== null && is_file($temporaryPath)) {
                unlink($temporaryPath);
            }
        }
    }

    /**
     * @return resource
     */
    private function openLock(string $path)
    {
        $lock = fopen($path . '.lock', 'c');

        if ($lock === false) {
            throw ManifestWriteException::forPath($path, 'lock file could not be opened.');
        }

        return $lock;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function encode(array $data, string $path): string
    {
        try {
            return json_encode(
                $data,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
            ) . PHP_EOL;
        } catch (JsonException $exception) {
            throw ManifestWriteException::forPath($path, $exception->getMessage(), $exception);
        }
    }

    private function temporaryPath(string $directory, string $path): string
    {
        $temporaryPath = tempnam($directory, basename($path) . '.tmp.');

        if ($temporaryPath === false) {
            throw ManifestWriteException::forPath($path, 'temporary file could not be created.');
        }

        return $temporaryPath;
    }

    private function writeTemporaryFile(string $temporaryPath, string $contents): void
    {
        $handle = fopen($temporaryPath, 'wb');

        if ($handle === false) {
            throw ManifestWriteException::forPath($temporaryPath, 'temporary file could not be opened.');
        }

        try {
            $bytesWritten = fwrite($handle, $contents);
            if ($bytesWritten === false || $bytesWritten !== \strlen($contents)) {
                throw ManifestWriteException::forPath($temporaryPath, 'temporary file write was incomplete.');
            }

            if (! fflush($handle)) {
                throw ManifestWriteException::forPath($temporaryPath, 'temporary file could not be flushed.');
            }
        } finally {
            fclose($handle);
        }
    }
}

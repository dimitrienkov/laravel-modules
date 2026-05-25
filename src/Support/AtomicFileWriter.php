<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Support;

use DimitrienkoV\LaravelModules\Exceptions\AtomicWriteException;
use Throwable;

final readonly class AtomicFileWriter
{
    public function write(string $path, string $contents): void
    {
        $directory = \dirname($path);

        if (! is_dir($directory) && ! mkdir($directory, 0755, true) && ! is_dir($directory)) {
            throw AtomicWriteException::forPath($path, "directory [{$directory}] could not be created.");
        }

        $lock = $this->openLock($path);
        $temporaryPath = null;

        try {
            if (! flock($lock, LOCK_EX)) {
                throw AtomicWriteException::forPath($path, 'exclusive file lock could not be acquired.');
            }

            $temporaryPath = $this->temporaryPath($directory, $path);
            $this->writeTemporaryFile($temporaryPath, $contents, $path);

            if (is_file($path)) {
                $permissions = fileperms($path);
                if ($permissions !== false) {
                    chmod($temporaryPath, $permissions & 0777);
                }
            }

            if (is_dir($path) || ! rename($temporaryPath, $path)) {
                throw AtomicWriteException::forPath($path, 'temporary file could not be renamed atomically.');
            }

            $temporaryPath = null;
        } catch (AtomicWriteException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw AtomicWriteException::forPath($path, $exception->getMessage(), $exception);
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
            throw AtomicWriteException::forPath($path, 'lock file could not be opened.');
        }

        return $lock;
    }

    private function temporaryPath(string $directory, string $path): string
    {
        $temporaryPath = tempnam($directory, basename($path) . '.tmp.');

        if ($temporaryPath === false) {
            throw AtomicWriteException::forPath($path, 'temporary file could not be created.');
        }

        return $temporaryPath;
    }

    private function writeTemporaryFile(string $temporaryPath, string $contents, string $targetPath): void
    {
        $handle = fopen($temporaryPath, 'wb');

        if ($handle === false) {
            throw AtomicWriteException::forPath($targetPath, 'temporary file could not be opened.');
        }

        try {
            $bytesWritten = fwrite($handle, $contents);
            if ($bytesWritten === false || $bytesWritten !== \strlen($contents)) {
                throw AtomicWriteException::forPath($targetPath, 'temporary file write was incomplete.');
            }

            if (! fflush($handle)) {
                throw AtomicWriteException::forPath($targetPath, 'temporary file could not be flushed.');
            }
        } finally {
            fclose($handle);
        }
    }
}

<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Support;

use DimitrienkoV\LaravelModules\Exceptions\AtomicWriteException;
use DimitrienkoV\LaravelModules\Exceptions\ManifestWriteException;
use JsonException;

final readonly class AtomicJsonWriter
{
    public function __construct(
        private AtomicFileWriter $fileWriter = new AtomicFileWriter(),
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public function write(string $path, array $data): void
    {
        try {
            $this->fileWriter->write($path, $this->encode($data, $path));
        } catch (AtomicWriteException $exception) {
            throw ManifestWriteException::forPath($path, $exception->getMessage(), $exception);
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    private function encode(array $data, string $path): string
    {
        try {
            return json_encode(
                $data,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
            ) . PHP_EOL;
        } catch (JsonException $exception) {
            throw ManifestWriteException::forPath($path, $exception->getMessage(), $exception);
        }
    }
}

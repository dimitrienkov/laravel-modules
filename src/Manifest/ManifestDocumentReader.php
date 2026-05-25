<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Manifest;

use DimitrienkoV\LaravelModules\Exceptions\InvalidManifestException;
use JsonException;

final readonly class ManifestDocumentReader
{
    /**
     * @return array<string, mixed>
     */
    public function read(string $manifestPath): array
    {
        $contents = file_get_contents($manifestPath);

        if ($contents === false) {
            throw InvalidManifestException::forPath($manifestPath, 'manifest could not be read.');
        }

        try {
            $manifest = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw InvalidManifestException::forPath($manifestPath, $exception->getMessage());
        }

        if (! \is_array($manifest) || array_is_list($manifest)) {
            throw InvalidManifestException::forPath($manifestPath, 'manifest root must be a JSON object.');
        }

        /** @var array<string, mixed> $manifest */
        return $manifest;
    }
}

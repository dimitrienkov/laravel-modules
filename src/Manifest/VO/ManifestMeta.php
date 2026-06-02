<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Manifest\VO;

use DimitrienkoV\LaravelModules\Exceptions\InvalidManifestException;
use DimitrienkoV\LaravelModules\Manifest\Enums\ModuleKind;
use DimitrienkoV\LaravelModules\Manifest\Parsing\ManifestFieldReader;
use InvalidArgumentException;

final readonly class ManifestMeta
{
    private const array ALLOWED_KEYS = [
        'name' => true,
        'display_name' => true,
        'kind' => true,
        'group' => true,
        'version' => true,
        'author' => true,
        'description' => true,
        'license' => true,
        'dependencies' => true,
    ];

    public function __construct(
        public string $name,
        public string $displayName,
        public ModuleKind $kind,
        public Version $version,
        public ?string $author,
        public ?string $description,
        public ?string $license,
        public ModuleDependencies $dependencies,
        public ?ModuleGroup $group = null,
    ) {}

    /**
     * @param array<string, mixed> $meta
     */
    public static function fromArray(array $meta, string $manifestPath): self
    {
        ManifestFieldReader::assertAllowedKeys($meta, self::ALLOWED_KEYS, 'meta', $manifestPath);

        $name = ManifestFieldReader::requiredString($meta, 'name', 'meta', $manifestPath);
        ManifestFieldReader::assertModuleName($name, 'meta.name', $manifestPath);

        $kindRaw = ManifestFieldReader::requiredString($meta, 'kind', 'meta', $manifestPath);
        $kind = ModuleKind::tryFrom($kindRaw);

        if ($kind === null) {
            $allowed = implode(', ', array_column(ModuleKind::cases(), 'value'));

            throw InvalidManifestException::forPath(
                $manifestPath,
                "meta.kind [{$kindRaw}] is not valid; allowed values: {$allowed}.",
            );
        }

        $versionRaw = ManifestFieldReader::requiredString($meta, 'version', 'meta', $manifestPath);

        try {
            $version = new Version($versionRaw);
        } catch (InvalidArgumentException $e) {
            throw InvalidManifestException::forPath($manifestPath, $e->getMessage(), $e);
        }

        $displayName = ManifestFieldReader::optionalString($meta, 'display_name', 'meta', $manifestPath) ?? $name;
        $author = ManifestFieldReader::optionalString($meta, 'author', 'meta', $manifestPath);
        $description = ManifestFieldReader::optionalString($meta, 'description', 'meta', $manifestPath);
        $license = ManifestFieldReader::optionalString($meta, 'license', 'meta', $manifestPath);

        $groupRaw = ManifestFieldReader::optionalString($meta, 'group', 'meta', $manifestPath);

        try {
            $group = $groupRaw === null ? null : new ModuleGroup($groupRaw);
        } catch (InvalidArgumentException $e) {
            throw InvalidManifestException::forPath($manifestPath, $e->getMessage(), $e);
        }

        $dependenciesRaw = $meta['dependencies'] ?? [];
        if (! \is_array($dependenciesRaw)) {
            throw InvalidManifestException::forPath($manifestPath, 'meta.dependencies must be an array.');
        }

        return new self(
            name: $name,
            displayName: $displayName,
            kind: $kind,
            version: $version,
            author: $author,
            description: $description,
            license: $license,
            dependencies: ModuleDependencies::fromArray($dependenciesRaw, $manifestPath),
            group: $group,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $meta = [
            'name' => $this->name,
            'display_name' => $this->displayName,
            'kind' => $this->kind->value,
            'version' => $this->version->value,
            'dependencies' => $this->dependencies->toArray(),
        ];

        if ($this->author !== null) {
            $meta['author'] = $this->author;
        }

        if ($this->description !== null) {
            $meta['description'] = $this->description;
        }

        if ($this->license !== null) {
            $meta['license'] = $this->license;
        }

        if ($this->group instanceof ModuleGroup) {
            $meta['group'] = $this->group->value;
        }

        return $meta;
    }
}

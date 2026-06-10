<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\MoonShine\Data;

use DimitrienkoV\LaravelModules\Manifest\VO\FeatureValues;
use DimitrienkoV\LaravelModules\Manifest\VO\Module;
use DimitrienkoV\LaravelModules\Manifest\VO\ModuleOrigin;

/**
 * Flat presentation snapshot of a module for the MoonShine admin UI.
 *
 * Pure data-carrier: it depends only on the package's own value objects, never
 * on MoonShine contracts, so the arch boundary stays one-directional
 * (MoonShine -> Manifest VO). MoonShine reads field values via
 * `data_get($dto, '<column>')`, so every column the index/detail/form renders is
 * exposed either as a public property or as a key under {@see self::$featureValues}
 * (dot-path column `featureValues.<key>`). The explicit {@see self::toArray()}
 * guarantees `MixedDataWrapper::toArray()` returns every field rather than the
 * lossy `(array)` cast.
 */
final readonly class ModuleAdminDto
{
    /**
     * Property/column name under which feature values are nested. Form/detail
     * fields use the dot-path column `featureValues.<key>` so MoonShine resolves a
     * value via `data_get($dto, 'featureValues.<key>')`, and the write side maps
     * that column back to the bare feature key on submit. Build and parse the
     * dot-path only through {@see self::featureColumn()} /
     * {@see self::featureKeyFromColumn()} so the convention lives in one place.
     */
    public const string FEATURE_VALUES_COLUMN = 'featureValues';

    /**
     * The dot-path column a feature key is rendered/submitted under.
     */
    public static function featureColumn(string $key): string
    {
        return self::FEATURE_VALUES_COLUMN . '.' . $key;
    }

    /**
     * The bare feature key behind a column, or null when the column is not a
     * feature-value column.
     */
    public static function featureKeyFromColumn(string $column): ?string
    {
        $prefix = self::FEATURE_VALUES_COLUMN . '.';

        if (! str_starts_with($column, $prefix)) {
            return null;
        }

        return substr($column, \strlen($prefix));
    }

    /**
     * @param array<string, string>               $dependencies  module name => Composer constraint
     * @param array<string, bool|int|string|null> $featureValues feature key => effective value (override or default)
     */
    public function __construct(
        public string $name,
        public string $displayName,
        public string $version,
        public string $kind,
        public ?string $group,
        public bool $enabled,
        public string $namespace,
        public string $path,
        public int $loadOrder,
        public array $dependencies,
        public array $featureValues,
        public ?string $provenanceKind = null,
        public ?string $provenanceVersion = null,
        public ?string $provenanceChecksum = null,
    ) {}

    /**
     * Neutral instance for MoonShine's "create" path. The admin UI never creates
     * modules, but the CRUD form pipeline asks the resource for a data instance;
     * returning a typed empty DTO keeps the caster's data shape consistent.
     */
    public static function empty(): self
    {
        return new self(
            name: '',
            displayName: '',
            version: '',
            kind: '',
            group: null,
            enabled: false,
            namespace: '',
            path: '',
            loadOrder: 0,
            dependencies: [],
            featureValues: [],
        );
    }

    /**
     * @param int $loadOrder zero-based position in the dependency-ordered registry snapshot
     */
    public static function fromModule(
        Module $module,
        FeatureValues $values,
        ?ModuleOrigin $source,
        int $loadOrder,
    ): self {
        return new self(
            name: $module->name,
            displayName: $module->displayName,
            version: $module->meta->version->value,
            kind: $module->meta->kind->value,
            group: $module->meta->group?->value,
            enabled: $module->isEnabled(),
            namespace: $module->namespace,
            path: $module->path,
            loadOrder: $loadOrder,
            dependencies: $module->meta->dependencies->all(),
            featureValues: self::effectiveValues($module, $values),
            provenanceKind: $source?->kind->value,
            provenanceVersion: $source?->installedVersion->value,
            provenanceChecksum: $source?->checksum?->value,
        );
    }

    /**
     * Effective value per schema key: the explicit override when present,
     * otherwise the schema default, otherwise null (no default, no override).
     * Defaults are read for display only — they are never persisted back as
     * explicit values.
     *
     * @return array<string, bool|int|string|null>
     */
    private static function effectiveValues(Module $module, FeatureValues $values): array
    {
        $explicit = $values->explicitValues();
        $effective = [];

        foreach ($module->features->all() as $key => $definition) {
            if (\array_key_exists($key, $explicit)) {
                $effective[$key] = $explicit[$key];

                continue;
            }

            $effective[$key] = $definition->hasDefault ? $definition->default : null;
        }

        return $effective;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'displayName' => $this->displayName,
            'version' => $this->version,
            'kind' => $this->kind,
            'group' => $this->group,
            'enabled' => $this->enabled,
            'namespace' => $this->namespace,
            'path' => $this->path,
            'loadOrder' => $this->loadOrder,
            'dependencies' => $this->dependencies,
            'featureValues' => $this->featureValues,
            'provenanceKind' => $this->provenanceKind,
            'provenanceVersion' => $this->provenanceVersion,
            'provenanceChecksum' => $this->provenanceChecksum,
        ];
    }
}

<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\MoonShine\Resources;

use Override;
use MoonShine\Contracts\Core\PageContract;
use DimitrienkoV\LaravelModules\Contracts\ModuleRegistryInterface;
use DimitrienkoV\LaravelModules\Contracts\ModuleStateRepositoryInterface;
use DimitrienkoV\LaravelModules\Manifest\Enums\FeatureType;
use DimitrienkoV\LaravelModules\Manifest\VO\FeatureDefinition;
use DimitrienkoV\LaravelModules\Manifest\VO\FeatureValues;
use DimitrienkoV\LaravelModules\Manifest\VO\Module;
use DimitrienkoV\LaravelModules\MoonShine\Data\ModuleAdminDto;
use DimitrienkoV\LaravelModules\MoonShine\Pages\ModuleDetailPage;
use DimitrienkoV\LaravelModules\MoonShine\Pages\ModuleFormPage;
use DimitrienkoV\LaravelModules\MoonShine\Pages\ModuleIndexPage;
use Illuminate\Contracts\Config\Repository;
use MoonShine\Contracts\Core\DependencyInjection\CoreContract;
use MoonShine\Contracts\Core\DependencyInjection\FieldsContract;
use MoonShine\Contracts\Core\TypeCasts\DataWrapperContract;
use MoonShine\Crud\Resources\CrudResource;
use MoonShine\MenuManager\Attributes\CanSee;
use RuntimeException;

/**
 * MoonShine admin resource backed by the in-memory module registry rather than
 * Eloquent.
 *
 * It extends the data-agnostic {@see CrudResource} (never `ModelResource`): the
 * five CRUD operations are implemented against {@see ModuleRegistryInterface} and
 * {@see ModuleStateRepositoryInterface}. Items are {@see ModuleAdminDto} value
 * objects keyed by `name` (`$casterKeyName`), so the default {@see \MoonShine\Core\TypeCasts\MixedDataCaster}
 * yields a non-null `getKey()` for row actions and routing.
 *
 * Write boundaries are deliberately narrow: `save()` persists ONLY feature values
 * through `ModuleStateRepository::writeValues()` and never touches `enabled`
 * (enable/disable is a lifecycle use case wired in the index page), and the
 * default delete/mass-delete are no-op stubs because removal is exposed only as
 * an explicit Backup-only action.
 *
 * Menu visibility is config-driven: the `#[CanSee('menuVisible')]` attribute makes
 * MoonShine's menu autoloader re-evaluate {@see self::menuVisible()} per request
 * (state flushed on `RequestHandled`), so toggling `modules.moonshine.menu` takes
 * effect without a rebuild. The `modules` alias keeps the URI key clean
 * (`modules` instead of `modules-resource`).
 *
 * @extends CrudResource<CoreContract, ModuleAdminDto, ModuleIndexPage, ModuleFormPage, ModuleDetailPage>
 */
#[CanSee('menuVisible')]
final class ModulesResource extends CrudResource
{
    protected ?string $casterKeyName = 'name';

    protected ?string $alias = 'modules';

    public function __construct(
        CoreContract $core,
        private readonly ModuleRegistryInterface $registry,
        private readonly ModuleStateRepositoryInterface $state,
        private readonly Repository $config,
    ) {
        parent::__construct($core);
    }

    /**
     * Whether the resource appears in MoonShine's auto-loaded menu. Read through
     * the injected config Repository (never the `config()` helper) and re-evaluated
     * per request by the `#[CanSee]` machinery.
     */
    public function menuVisible(): bool
    {
        return $this->config->get('modules.moonshine.menu', true) === true;
    }

    /**
     * @return list<class-string<PageContract>>
     */
    #[Override]
    protected function pages(): array
    {
        return [
            ModuleIndexPage::class,
            ModuleFormPage::class,
            ModuleDetailPage::class,
        ];
    }

    /**
     * @return list<ModuleAdminDto>
     */
    public function getItems(): iterable
    {
        $items = [];
        $order = 0;

        foreach ($this->registry->all() as $module) {
            // The index only renders name/version/enabled, so values/provenance are
            // not read per row here; findItem() reads fresh state for form/detail.
            $items[] = ModuleAdminDto::fromModule(
                module: $module,
                values: new FeatureValues($module->features, []),
                source: null,
                loadOrder: $order,
            );

            ++$order;
        }

        return $items;
    }

    /**
     * @return ($orFail is true ? DataWrapperContract<ModuleAdminDto> : DataWrapperContract<ModuleAdminDto>|null)
     */
    public function findItem(bool $orFail = false): ?DataWrapperContract
    {
        $id = $this->getItemID();

        if ($id === null) {
            return $orFail ? throw new RuntimeException('No module selected.') : null;
        }

        $name = (string) $id;

        if (! $this->registry->has($name)) {
            return $orFail ? throw new RuntimeException("Module [{$name}] was not found.") : null;
        }

        return $this->getCaster()->cast($this->toDto($this->registry->find($name)));
    }

    public function save(DataWrapperContract $item, ?FieldsContract $fields = null): DataWrapperContract
    {
        $module = $this->registry->find((string) $item->getKey());

        // Persist feature values only — enabled stays untouched here.
        $this->writeFeatureValues($module, $this->submittedFeatureValues($fields));

        return $this->getCaster()->cast($this->toDto($module));
    }

    public function delete(DataWrapperContract $item, ?FieldsContract $fields = null): bool
    {
        // Removal is exposed only through the explicit Backup-only action; the
        // default delete button is removed from the index, so this is a safe stub.
        return false;
    }

    /**
     * @param array<int|string> $ids
     */
    public function massDelete(array $ids): void
    {
        // Bulk removal is intentionally not exposed in the admin UI.
    }

    #[Override]
    public function getDataInstance(): mixed
    {
        return ModuleAdminDto::empty();
    }

    private function toDto(Module $module): ModuleAdminDto
    {
        // findItem/save read fresh state.json so the form and detail page always
        // reflect live enabled + values + provenance (modules:optimize never caches
        // settings.values, so no extra cache invalidation is needed).
        $document = $this->state->read($module->name, $module);

        return ModuleAdminDto::fromModule(
            module: $module->withState($document->state),
            values: $document->values,
            source: $document->source,
            loadOrder: $this->loadOrderOf($module->name),
        );
    }

    private function loadOrderOf(string $name): int
    {
        $order = 0;

        foreach ($this->registry->all() as $module) {
            if ($module->name === $name) {
                return $order;
            }

            ++$order;
        }

        return $order;
    }

    /**
     * Read submitted feature values from the form fields. Each feature field's
     * column is `featureValues.<key>` (see FeatureFieldFactory); the bare key is
     * recovered here. A field absent from the request is skipped so unchanged
     * defaults are never forced into the explicit value set.
     *
     * @return array<string, mixed> feature key => raw submitted value
     */
    private function submittedFeatureValues(?FieldsContract $fields): array
    {
        $fields ??= $this->getFormFields()->onlyFields(withApplyWrappers: true);

        $prefix = ModuleAdminDto::FEATURE_VALUES_KEY . '.';
        $submitted = [];

        foreach ($fields as $field) {
            $column = $field->getColumn();
            if (! str_starts_with($column, $prefix)) {
                continue;
            }
            if (! $field->hasRequestValue()) {
                continue;
            }

            $value = $field->getRequestValue();
            $submitted[substr($column, \strlen($prefix))] = $value === false ? null : $value;
        }

        return $submitted;
    }

    /**
     * @param array<string, mixed> $submitted feature key => raw submitted value
     */
    private function writeFeatureValues(Module $module, array $submitted): void
    {
        $manifestPath = $module->manifestPath();
        $explicit = [];

        foreach ($module->features->all() as $key => $definition) {
            if (! \array_key_exists($key, $submitted)) {
                continue;
            }

            // Form transport delivers raw scalars (strings, "on" toggles); coerce to
            // the schema's PHP type before the strict normalizer validates ranges.
            $normalized = $definition->normalize(
                $this->coerce($definition, $submitted[$key]),
                $manifestPath,
            );

            // Defaults stay in the schema; only genuine overrides are persisted.
            if ($definition->hasDefault && $normalized === $definition->default) {
                continue;
            }

            $explicit[$key] = $normalized;
        }

        $this->state->writeValues(
            $module,
            FeatureValues::fromArray($explicit, $module->features, $module->name, $manifestPath),
        );
    }

    /**
     * Coerce a raw submitted value to the PHP type the feature schema expects.
     * Range/option enforcement stays in {@see FeatureDefinition::normalize()}; a
     * value that cannot be coerced is passed through unchanged so the normalizer
     * rejects it with the canonical error.
     */
    private function coerce(FeatureDefinition $definition, mixed $value): mixed
    {
        return match ($definition->type) {
            FeatureType::Boolean => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            FeatureType::Integer => is_numeric($value) ? (int) $value : $value,
            FeatureType::Enum, FeatureType::String => \is_scalar($value) ? (string) $value : $value,
        };
    }
}

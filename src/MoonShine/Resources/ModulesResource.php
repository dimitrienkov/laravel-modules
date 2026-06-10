<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\MoonShine\Resources;

use Override;
use MoonShine\Contracts\Core\PageContract;
use DimitrienkoV\LaravelModules\Contracts\ModuleRegistryInterface;
use DimitrienkoV\LaravelModules\Contracts\ModuleStateRepositoryInterface;
use DimitrienkoV\LaravelModules\Exceptions\ModuleNotFoundException;
use DimitrienkoV\LaravelModules\Manifest\VO\FeatureValues;
use DimitrienkoV\LaravelModules\Manifest\VO\Module;
use DimitrienkoV\LaravelModules\MoonShine\Data\ModuleAdminDto;
use DimitrienkoV\LaravelModules\MoonShine\Pages\ModuleDetailPage;
use DimitrienkoV\LaravelModules\MoonShine\Pages\ModuleFormPage;
use DimitrienkoV\LaravelModules\MoonShine\Pages\ModuleIndexPage;
use DimitrienkoV\LaravelModules\MoonShine\Support\FeatureValueWriter;
use Illuminate\Contracts\Config\Repository;
use MoonShine\Contracts\Core\DependencyInjection\CoreContract;
use MoonShine\Contracts\Core\DependencyInjection\FieldsContract;
use MoonShine\Contracts\Core\TypeCasts\DataWrapperContract;
use MoonShine\Crud\Resources\CrudResource;
use MoonShine\MenuManager\Attributes\CanSee;

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
        private readonly FeatureValueWriter $valueWriter,
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

        foreach ($this->registry->all() as $module) {
            // The index only renders name/version/enabled, so values/provenance and
            // the per-row load order are not computed here (loadOrder stays 0);
            // findItem() reads fresh state and the real load order for form/detail.
            $items[] = ModuleAdminDto::fromModule(
                module: $module,
                values: new FeatureValues($module->features, []),
                source: null,
                loadOrder: 0,
            );
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
            return $orFail ? throw ModuleNotFoundException::noSelection() : null;
        }

        $name = (string) $id;

        if (! $this->registry->has($name)) {
            return $orFail ? throw ModuleNotFoundException::forName($name) : null;
        }

        return $this->getCaster()->cast($this->toDto($this->registry->find($name)));
    }

    public function save(DataWrapperContract $item, ?FieldsContract $fields = null): DataWrapperContract
    {
        $name = (string) $item->getKey();

        // A module that vanished between render and submit yields a clean typed
        // error (and toast) instead of a TypeError from the registry's miss.
        if (! $this->registry->has($name)) {
            throw ModuleNotFoundException::forName($name);
        }

        $module = $this->registry->find($name);
        $fields ??= $this->getFormFields()->onlyFields(withApplyWrappers: true);

        $this->valueWriter->write($module, $this->valueWriter->submittedFeatureValues($fields));

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
}

<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\MoonShine\Pages;

use Override;
use DimitrienkoV\LaravelModules\MoonShine\Resources\ModulesResource;
use DimitrienkoV\LaravelModules\MoonShine\Data\ModuleAdminDto;
use DimitrienkoV\LaravelModules\MoonShine\Support\ModuleDependentsResolver;
use Illuminate\Contracts\Translation\Translator;
use MoonShine\Contracts\Core\DependencyInjection\CoreContract;
use MoonShine\Contracts\UI\ComponentContract;
use MoonShine\Crud\Pages\DetailPage;
use MoonShine\UI\Fields\Preview;

/**
 * Read-only debug detail page for {@see ModulesResource}.
 *
 * Shows only debug information — paths, namespace, version, declared dependencies,
 * computed dependents, deterministic load order, source provenance and the current
 * effective feature values. It never reads logs and never exposes editable fields.
 * Freshness comes for free: the resource's `findItem()` reads `state.json` on every
 * render and `modules:optimize` never caches `settings.values`, so the values shown
 * are always live without any extra cache invalidation.
 *
 * @extends DetailPage<ModulesResource>
 */
final class ModuleDetailPage extends DetailPage
{
    public function __construct(
        CoreContract $core,
        private readonly ModuleDependentsResolver $dependents,
        private readonly Translator $translator,
    ) {
        parent::__construct($core);
    }

    /**
     * @return list<ComponentContract>
     */
    #[Override]
    protected function fields(): iterable
    {
        return [
            Preview::make($this->t('columns.name'), 'displayName'),
            Preview::make($this->t('columns.namespace'), 'namespace'),
            Preview::make($this->t('columns.version'), 'version'),
            Preview::make($this->t('columns.kind'), 'kind'),
            Preview::make($this->t('columns.group'), 'group'),
            Preview::make($this->t('columns.enabled'), 'enabled', fn(ModuleAdminDto $dto): string => $this->boolLabel($dto->enabled)),
            Preview::make($this->t('columns.path'), 'path'),
            Preview::make($this->t('columns.load_order'), 'loadOrder'),
            Preview::make($this->t('columns.dependencies'), 'dependencies', fn(ModuleAdminDto $dto): string => $this->dependencies($dto)),
            Preview::make($this->t('columns.dependents'), 'name', fn(ModuleAdminDto $dto): string => $this->dependentsOf($dto)),
            Preview::make($this->t('columns.feature_values'), 'featureValues', fn(ModuleAdminDto $dto): string => $this->featureValues($dto)),
            Preview::make($this->t('provenance.kind'), 'provenanceKind'),
            Preview::make($this->t('provenance.version'), 'provenanceVersion'),
            Preview::make($this->t('provenance.checksum'), 'provenanceChecksum'),
        ];
    }

    private function dependencies(ModuleAdminDto $dto): string
    {
        if ($dto->dependencies === []) {
            return $this->t('values.none');
        }

        $lines = [];

        foreach ($dto->dependencies as $name => $constraint) {
            $lines[] = "{$name}: {$constraint}";
        }

        return implode(', ', $lines);
    }

    private function dependentsOf(ModuleAdminDto $dto): string
    {
        $names = $this->dependents->removeBlockers($dto->name);

        return $names === [] ? $this->t('values.none') : implode(', ', $names);
    }

    private function featureValues(ModuleAdminDto $dto): string
    {
        if ($dto->featureValues === []) {
            return $this->t('values.none');
        }

        $lines = [];

        foreach ($dto->featureValues as $key => $value) {
            $lines[] = "{$key} = " . $this->scalarLabel($value);
        }

        return implode(', ', $lines);
    }

    private function scalarLabel(bool|int|string|null $value): string
    {
        if (\is_bool($value)) {
            return $this->boolLabel($value);
        }

        return $value === null ? $this->t('values.none') : (string) $value;
    }

    private function boolLabel(bool $value): string
    {
        return $this->t($value ? 'values.yes' : 'values.no');
    }

    private function t(string $key): string
    {
        $label = $this->translator->get("module-loader::admin.{$key}");

        return \is_string($label) ? $label : $key;
    }
}

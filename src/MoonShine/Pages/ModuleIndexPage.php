<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\MoonShine\Pages;

use Override;
use MoonShine\Contracts\Core\ResourceContract;
use DimitrienkoV\LaravelModules\MoonShine\Resources\ModulesResource;
use DimitrienkoV\LaravelModules\Application\Enums\RemoveStrategy;
use DimitrienkoV\LaravelModules\Application\UseCases\DisableModuleUseCase;
use DimitrienkoV\LaravelModules\Application\UseCases\EnableModuleUseCase;
use DimitrienkoV\LaravelModules\Application\UseCases\RemoveModuleUseCase;
use DimitrienkoV\LaravelModules\Manifest\Enums\ModuleKind;
use DimitrienkoV\LaravelModules\Manifest\VO\ModuleGroup;
use DimitrienkoV\LaravelModules\MoonShine\Data\ModuleAdminDto;
use DimitrienkoV\LaravelModules\MoonShine\Support\ModuleDependentsResolver;
use DimitrienkoV\LaravelModules\MoonShine\Support\ModuleIndexGrouping;
use DimitrienkoV\LaravelModules\MoonShine\Support\ModuleKindLabelResolver;
use DimitrienkoV\LaravelModules\Application\Support\ModuleGroupLabelResolver;
use Closure;
use Illuminate\Contracts\Translation\Translator;
use MoonShine\Contracts\Core\DependencyInjection\CoreContract;
use MoonShine\Contracts\Core\TypeCasts\DataCasterContract;
use MoonShine\Contracts\Core\TypeCasts\DataWrapperContract;
use MoonShine\Contracts\UI\ActionButtonContract;
use MoonShine\Contracts\UI\ComponentContract;
use MoonShine\Crud\Pages\IndexPage;
use MoonShine\Laravel\MoonShineRequest;
use MoonShine\Support\AlpineJs;
use MoonShine\Support\Attributes\AsyncMethod;
use MoonShine\Support\Enums\JsEvent;
use MoonShine\UI\Components\ActionButton;
use MoonShine\UI\Components\Layout\Box;
use MoonShine\UI\Components\Table\TableBuilder;
use MoonShine\UI\Components\Tabs;
use MoonShine\UI\Components\Tabs\Tab;
use MoonShine\UI\Fields\Switcher;
use MoonShine\UI\Fields\Text;

/**
 * Index page for {@see ModulesResource}.
 *
 * The default CRUD list layout is replaced: outer tabs by {@see ModuleKind}, and
 * inside each tab one read-only table per `meta.group`. Each row carries the
 * display name, version, an async enable/disable Switcher (wired to the lifecycle
 * use cases, NOT the resource's value-only save), and Detail/Settings/Remove
 * actions. Controls that would violate the dependency graph are preventively
 * disabled with an explanatory tooltip; the authoritative enforcement still lives
 * in the use cases' {@see \DimitrienkoV\LaravelModules\Application\Support\ModuleDependencyGuard}.
 *
 * @extends IndexPage<ModulesResource>
 */
final class ModuleIndexPage extends IndexPage
{
    public function __construct(
        CoreContract $core,
        private readonly EnableModuleUseCase $enableModule,
        private readonly DisableModuleUseCase $disableModule,
        private readonly RemoveModuleUseCase $removeModule,
        private readonly ModuleKindLabelResolver $kindLabels,
        private readonly ModuleGroupLabelResolver $groupLabels,
        private readonly ModuleDependentsResolver $dependents,
        private readonly ModuleIndexGrouping $grouping,
        private readonly Translator $translator,
    ) {
        parent::__construct($core);
    }

    /**
     * @return list<ComponentContract>
     */
    #[Override]
    protected function components(): iterable
    {
        $resource = $this->getResource();

        if (! $resource instanceof ResourceContract) {
            return [];
        }

        $caster = $resource->getCaster();
        $byKind = $this->grouping->byKind($resource->getItems());

        $tabs = [];

        foreach (ModuleIndexGrouping::KIND_ORDER as $kind) {
            $kindItems = $byKind[$kind->value] ?? [];

            if ($kindItems === []) {
                continue;
            }

            $tabs[] = Tab::make($this->kindLabels->label($kind), $this->groupBoxes($kind, $kindItems, $caster));
        }

        if ($tabs === []) {
            return [];
        }

        return [Tabs::make($tabs)];
    }

    #[AsyncMethod]
    public function toggleEnabled(MoonShineRequest $request): void
    {
        $name = (string) $request->getItemID();

        // The new desired state is the Switcher's submitted value. Exceptions from
        // the use cases (e.g. enabled dependents) propagate to MoonShine, which
        // renders an error toast and persists nothing.
        if ($request->boolean('enabled')) {
            $this->enableModule->execute($name);

            return;
        }

        $this->disableModule->execute($name);
    }

    #[AsyncMethod]
    public function removeModule(MoonShineRequest $request): void
    {
        // Backup-only removal: Permanent is never exposed in the UI.
        $this->removeModule->execute((string) $request->getItemID(), RemoveStrategy::Backup);
    }

    /**
     * @param list<ModuleAdminDto> $items
     *
     * @return list<ComponentContract>
     */
    private function groupBoxes(ModuleKind $kind, array $items, DataCasterContract $caster): array
    {
        $boxes = [];

        foreach ($this->grouping->groups($items) as $groupCode => $groupItems) {
            $boxes[] = Box::make(
                $this->groupHeading($groupCode),
                [$this->groupTable($kind, $groupCode, $groupItems, $caster)],
            );
        }

        return $boxes;
    }

    private function groupHeading(string $groupCode): string
    {
        if ($groupCode === ModuleIndexGrouping::UNGROUPED) {
            return $this->adminLabel('ungrouped');
        }

        return $this->groupLabels->displayLabel(new ModuleGroup($groupCode));
    }

    private function tableName(ModuleKind $kind, string $groupCode): string
    {
        $suffix = $groupCode === ModuleIndexGrouping::UNGROUPED ? 'ungrouped' : $groupCode;

        return 'modules-' . $kind->value . '-' . $suffix;
    }

    /**
     * @param list<ModuleAdminDto> $items
     */
    private function groupTable(ModuleKind $kind, string $groupCode, array $items, DataCasterContract $caster): TableBuilder
    {
        $tableName = $this->tableName($kind, $groupCode);

        return TableBuilder::make(items: $items)
            ->name($tableName)
            ->fields([
                Text::make($this->adminLabel('columns.name'), 'displayName'),
                Text::make($this->adminLabel('columns.version'), 'version'),
                $this->enabledSwitcher($tableName),
            ])
            ->cast($caster)
            ->buttons($this->rowButtons())
            ->preview();
    }

    private function enabledSwitcher(string $tableName): Switcher
    {
        return Switcher::make($this->adminLabel('columns.enabled'), 'enabled')
            ->updateOnPreview()
            ->onChangeMethod(
                'toggleEnabled',
                events: [AlpineJs::event(JsEvent::TABLE_ROW_UPDATED, $tableName)],
                page: $this,
            )
            ->afterFill(function (Switcher $field): Switcher {
                $dto = $field->getData()?->getOriginal();

                if (! $dto instanceof ModuleAdminDto) {
                    return $field;
                }

                $blockers = $this->dependents->disableBlockers($dto->name);

                if ($blockers === []) {
                    return $field;
                }

                $field->disabled(true)->customAttributes([
                    'title' => $this->guardTooltip('guard.disable_blocked', $blockers),
                ]);

                return $field;
            });
    }

    /**
     * @return list<ActionButton>
     */
    private function rowButtons(): array
    {
        return [
            ActionButton::make(
                $this->adminLabel('actions.detail'),
                fn(mixed $item, ?DataWrapperContract $casted): string => $this->detailUrl($casted),
            )->icon('eye'),
            ActionButton::make(
                $this->adminLabel('actions.settings'),
                fn(mixed $item, ?DataWrapperContract $casted): string => $this->formUrl($casted),
            )->icon('cog'),
            $this->removeButton(),
        ];
    }

    private function removeButton(): ActionButton
    {
        return ActionButton::make($this->adminLabel('actions.remove'), '#')
            ->icon('trash')
            ->method('removeModule', page: $this)
            ->withConfirm()
            ->onAfterSet(function (?DataWrapperContract $casted, ActionButtonContract $button): void {
                $dto = $casted?->getOriginal();

                if (! $dto instanceof ModuleAdminDto) {
                    return;
                }

                $blockers = $this->dependents->removeBlockers($dto->name);

                if ($blockers === []) {
                    return;
                }

                $button->customAttributes([
                    'disabled' => true,
                    'class' => 'btn-disabled',
                    'title' => $this->guardTooltip('guard.remove_blocked', $blockers),
                    '@click.prevent' => '',
                ]);
            });
    }

    private function detailUrl(?DataWrapperContract $casted): string
    {
        return $this->pageUrl($casted, fn(int|string $key): ?string => $this->getResource()?->getDetailPageUrl($key));
    }

    private function formUrl(?DataWrapperContract $casted): string
    {
        return $this->pageUrl($casted, fn(int|string $key): ?string => $this->getResource()?->getFormPageUrl($key));
    }

    /**
     * Resolve a row's page URL through the already-chosen resource method, so the
     * route is selected by the caller (detail/form) rather than by a string type.
     *
     * @param Closure(int|string): ?string $toUrl maps a resolved row key to a URL
     */
    private function pageUrl(?DataWrapperContract $casted, Closure $toUrl): string
    {
        if (! $casted instanceof DataWrapperContract) {
            return '#';
        }

        $url = $toUrl($casted->getKey() ?? '');

        return $url ?? '#';
    }

    /**
     * @param list<string> $names
     */
    private function guardTooltip(string $key, array $names): string
    {
        $label = $this->translator->get(
            "module-loader::admin.{$key}",
            ['modules' => implode(', ', $names)],
        );

        // Fall back to the key (as adminLabel does) so a translation defect stays
        // visible/traceable instead of rendering an empty tooltip.
        return \is_string($label) ? $label : $key;
    }

    private function adminLabel(string $key): string
    {
        $label = $this->translator->get("module-loader::admin.{$key}");

        return \is_string($label) ? $label : $key;
    }
}

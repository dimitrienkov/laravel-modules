<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Tests\Feature\MoonShine;

use DimitrienkoV\LaravelModules\Contracts\ModuleRegistryInterface;
use DimitrienkoV\LaravelModules\Manifest\Enums\ModuleKind;
use DimitrienkoV\LaravelModules\MoonShine\Pages\ModuleIndexPage;
use DimitrienkoV\LaravelModules\MoonShine\Resources\ModulesResource;
use DimitrienkoV\LaravelModules\Providers\ModuleLoaderServiceProvider;
use DimitrienkoV\LaravelModules\Tests\Support\FakeModuleRegistry;
use DimitrienkoV\LaravelModules\Tests\Support\ModuleFactory;
use MoonShine\Contracts\UI\ComponentContract;
use MoonShine\Laravel\Providers\MoonShineServiceProvider;
use MoonShine\UI\Components\ActionButton;
use MoonShine\UI\Components\Layout\Box;
use MoonShine\UI\Components\Table\TableBuilder;
use MoonShine\UI\Components\Tabs;
use MoonShine\UI\Components\Tabs\Tab;
use MoonShine\UI\Fields\Switcher;
use Orchestra\Testbench\TestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;

/**
 * Covers the index page layout contract: outer tabs by {@see ModuleKind}, one
 * read-only table per `meta.group` inside each tab, the Name/Version/Enabled
 * column set, and alphabetical row ordering by display name.
 */
#[Group('feature')]
final class ModuleIndexPageStructureTest extends TestCase
{
    /**
     * @param \Illuminate\Foundation\Application $app
     *
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [MoonShineServiceProvider::class, ModuleLoaderServiceProvider::class];
    }

    #[Test]
    public function buildsOneTabPerPresentKindWithTranslatedLabels(): void
    {
        $tabs = $this->renderTabs($this->mixedRegistry());

        // Integrations has no modules in the fixture, so only two tabs appear and
        // they follow the subsystem -> module declaration order.
        $labels = array_map(static fn(Tab $tab): string => $tab->getLabel(), $tabs);
        self::assertSame(['Subsystems', 'Modules'], $labels);
    }

    #[Test]
    public function splitsEachTabIntoOneTablePerGroup(): void
    {
        $tabs = $this->renderTabs($this->mixedRegistry());

        // The Modules tab holds two groups: "commerce" and the ungrouped bucket.
        $modulesTab = $tabs[1];
        $tableNames = array_map(
            static fn(TableBuilder $table): string => $table->getName(),
            $this->tablesOf($modulesTab),
        );

        // ksort over group codes puts the empty (ungrouped) bucket before "commerce".
        self::assertSame(
            ['modules-module-ungrouped', 'modules-module-commerce'],
            $tableNames,
        );
    }

    #[Test]
    public function eachTableExposesNameVersionAndEnabledColumns(): void
    {
        $tabs = $this->renderTabs($this->mixedRegistry());

        $table = $this->tablesOf($tabs[0])[0];
        $columns = array_map(
            static fn(object $field): string => $field->getColumn(),
            iterator_to_array($table->getFields()),
        );

        self::assertSame(['displayName', 'version', 'enabled'], $columns);
    }

    #[Test]
    public function ordersRowsAlphabeticallyByDisplayName(): void
    {
        $registry = new FakeModuleRegistry();
        $registry->add(ModuleFactory::make(name: 'zebra', kind: ModuleKind::Module, group: 'commerce'));
        $registry->add(ModuleFactory::make(name: 'alpha', kind: ModuleKind::Module, group: 'commerce'));
        $registry->add(ModuleFactory::make(name: 'mike', kind: ModuleKind::Module, group: 'commerce'));

        $tabs = $this->renderTabs($registry);
        $table = $this->tablesOf($tabs[0])[0];

        /** @var array<int, \DimitrienkoV\LaravelModules\MoonShine\Data\ModuleAdminDto> $items */
        $items = iterator_to_array($table->getItems());
        $names = array_map(static fn(object $dto): string => $dto->displayName, $items);

        self::assertSame(['Alpha', 'Mike', 'Zebra'], $names);
    }

    #[Test]
    public function preventivelyBlocksTheSwitcherAndRemoveButtonForADependedOnModule(): void
    {
        // 'app' (enabled) depends on 'core' (enabled): core can be neither disabled
        // nor removed while app needs it; app itself has no dependents.
        $registry = new FakeModuleRegistry();
        $registry->add(ModuleFactory::make(name: 'core', kind: ModuleKind::Module, enabled: true));
        $registry->add(ModuleFactory::make(name: 'app', kind: ModuleKind::Module, enabled: true, dependencies: ['core' => '^1.0']));

        $this->app->instance(ModuleRegistryInterface::class, $registry);
        $resource = $this->app->make(ModulesResource::class);
        $page = $this->app->make(ModuleIndexPage::class);
        $page->setResource($resource);

        $caster = $resource->getCaster();
        $rows = [];
        foreach ($resource->getItems() as $dto) {
            $rows[$dto->name] = $caster->cast($dto);
        }

        // Switcher: the depended-on 'core' is disabled with a tooltip; the free
        // 'app' is untouched. Exercises the real afterFill closure, not just the
        // dependents resolver.
        $blockedSwitcher = $this->switcher($page)->fillCast($rows['core'], $caster);
        self::assertTrue($blockedSwitcher->getAttributes()->get('disabled'));
        self::assertNotEmpty($blockedSwitcher->getAttributes()->get('title'));

        $freeSwitcher = $this->switcher($page)->fillCast($rows['app'], $caster);
        self::assertNull($freeSwitcher->getAttributes()->get('title'));

        // Remove button: same blocking, exercising the real onAfterSet closure.
        $blockedRemove = $this->removeButton($page);
        $blockedRemove->setData($rows['core']);
        self::assertTrue($blockedRemove->getAttributes()->get('disabled'));
        self::assertNotEmpty($blockedRemove->getAttributes()->get('title'));

        $freeRemove = $this->removeButton($page);
        $freeRemove->setData($rows['app']);
        self::assertNull($freeRemove->getAttributes()->get('title'));
    }

    private function switcher(ModuleIndexPage $page): Switcher
    {
        $method = new ReflectionMethod($page, 'enabledSwitcher');
        $method->setAccessible(true);

        /** @var Switcher */
        return $method->invoke($page, 'modules-module-ungrouped');
    }

    private function removeButton(ModuleIndexPage $page): ActionButton
    {
        $method = new ReflectionMethod($page, 'removeButton');
        $method->setAccessible(true);

        /** @var ActionButton */
        return $method->invoke($page);
    }

    private function mixedRegistry(): FakeModuleRegistry
    {
        $registry = new FakeModuleRegistry();
        $registry->add(ModuleFactory::make(name: 'core', kind: ModuleKind::Subsystem, group: 'foundation'));
        $registry->add(ModuleFactory::make(name: 'shop', kind: ModuleKind::Module, group: 'commerce'));
        $registry->add(ModuleFactory::make(name: 'blog', kind: ModuleKind::Module));

        return $registry;
    }

    /**
     * @return list<Tab>
     */
    private function renderTabs(ModuleRegistryInterface $registry): array
    {
        $this->app->instance(ModuleRegistryInterface::class, $registry);

        $resource = $this->app->make(ModulesResource::class);
        $page = $this->app->make(ModuleIndexPage::class);
        $page->setResource($resource);

        $method = new ReflectionMethod($page, 'components');
        $method->setAccessible(true);
        /** @var list<ComponentContract> $components */
        $components = $method->invoke($page);

        self::assertCount(1, $components);
        self::assertInstanceOf(Tabs::class, $components[0]);

        return array_values(iterator_to_array($components[0]->getTabs()));
    }

    /**
     * @return list<TableBuilder>
     */
    private function tablesOf(Tab $tab): array
    {
        $tables = [];

        foreach ($tab->getComponents() as $component) {
            self::assertInstanceOf(Box::class, $component);

            foreach ($component->getComponents() as $inner) {
                if ($inner instanceof TableBuilder) {
                    $tables[] = $inner;
                }
            }
        }

        return $tables;
    }
}

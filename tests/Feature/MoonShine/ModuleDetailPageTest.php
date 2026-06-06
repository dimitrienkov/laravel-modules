<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Tests\Feature\MoonShine;

use DimitrienkoV\LaravelModules\Contracts\ModuleRegistryInterface;
use DimitrienkoV\LaravelModules\Manifest\Enums\FeatureType;
use DimitrienkoV\LaravelModules\Manifest\VO\FeatureDefinition;
use DimitrienkoV\LaravelModules\Manifest\VO\FeatureSchema;
use DimitrienkoV\LaravelModules\Manifest\VO\FeatureValues;
use DimitrienkoV\LaravelModules\MoonShine\Data\ModuleAdminDto;
use DimitrienkoV\LaravelModules\MoonShine\Pages\ModuleDetailPage;
use DimitrienkoV\LaravelModules\MoonShine\Resources\ModulesResource;
use DimitrienkoV\LaravelModules\Providers\ModuleLoaderServiceProvider;
use DimitrienkoV\LaravelModules\Tests\Support\FakeModuleRegistry;
use DimitrienkoV\LaravelModules\Tests\Support\ModuleFactory;
use MoonShine\Contracts\UI\FieldContract;
use MoonShine\Laravel\Providers\MoonShineServiceProvider;
use MoonShine\UI\Fields\Preview;
use Orchestra\Testbench\TestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;

/**
 * Covers the read-only debug detail page: the field set it renders and the
 * computed columns (dependents, dependencies, feature values) derived from the
 * module and the live registry.
 */
#[Group('feature')]
final class ModuleDetailPageTest extends TestCase
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
    public function rendersTheDebugFieldSetAsReadOnlyPreviews(): void
    {
        $fields = $this->fields();

        self::assertContainsOnlyInstancesOf(Preview::class, $fields);

        $columns = array_map(static fn(FieldContract $f): string => $f->getColumn(), $fields);

        self::assertSame([
            'displayName', 'namespace', 'version', 'kind', 'group', 'enabled',
            'path', 'loadOrder', 'dependencies', 'name', 'featureValues',
            'provenanceKind', 'provenanceVersion', 'provenanceChecksum',
        ], $columns);
    }

    #[Test]
    public function computesDependentsFromTheLiveRegistry(): void
    {
        $value = $this->formatColumn('name', $this->dtoFor('users'));

        self::assertSame('Blog', $value);
    }

    #[Test]
    public function rendersDeclaredDependenciesAndFallsBackToNone(): void
    {
        self::assertSame('users: ^1.0', $this->formatColumn('dependencies', $this->dtoFor('blog')));
        self::assertSame('None', $this->formatColumn('dependencies', $this->dtoFor('users')));
    }

    #[Test]
    public function rendersEffectiveFeatureValues(): void
    {
        $module = ModuleFactory::make(
            name: 'cacheable',
            features: new FeatureSchema([
                'driver' => new FeatureDefinition('driver', FeatureType::String, true, 'redis', null, null, [], null, null, null),
            ]),
        );

        $dto = ModuleAdminDto::fromModule($module, new FeatureValues($module->features, []), null, 0);

        self::assertSame('driver = redis', $this->formatColumn('featureValues', $dto));
    }

    /**
     * @return list<FieldContract>
     */
    private function fields(): array
    {
        $page = $this->page();
        $method = new ReflectionMethod($page, 'fields');
        $method->setAccessible(true);

        /** @var list<FieldContract> $fields */
        $fields = $method->invoke($page);

        return $fields;
    }

    private function formatColumn(string $column, ModuleAdminDto $dto): string
    {
        foreach ($this->fields() as $field) {
            if ($field->getColumn() !== $column) {
                continue;
            }

            $callback = $field->getFormattedValueCallback();
            self::assertNotNull($callback, "Column {$column} must carry a formatting callback.");

            return (string) $callback($dto, 0, $field);
        }

        self::fail("No detail field for column {$column}.");
    }

    private function dtoFor(string $name): ModuleAdminDto
    {
        $module = $this->registry()->find($name);

        return ModuleAdminDto::fromModule($module, new FeatureValues($module->features, []), null, 0);
    }

    private function page(): ModuleDetailPage
    {
        $this->app->instance(ModuleRegistryInterface::class, $this->registry());

        $resource = $this->app->make(ModulesResource::class);
        $resource->setItemID('users');

        $page = $this->app->make(ModuleDetailPage::class);
        $page->setResource($resource);

        return $page;
    }

    private function registry(): FakeModuleRegistry
    {
        $registry = new FakeModuleRegistry();
        $registry->add(ModuleFactory::make(name: 'users', enabled: true));
        $registry->add(ModuleFactory::make(name: 'blog', enabled: true, dependencies: ['users' => '^1.0']));

        return $registry;
    }
}

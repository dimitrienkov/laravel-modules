<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Tests\Feature\MoonShine;

use DimitrienkoV\LaravelModules\Contracts\ModuleStateRepositoryInterface;
use DimitrienkoV\LaravelModules\Manifest\Enums\FeatureType;
use DimitrienkoV\LaravelModules\Manifest\VO\FeatureDefinition;
use DimitrienkoV\LaravelModules\Manifest\VO\FeatureSchema;
use DimitrienkoV\LaravelModules\Manifest\VO\FeatureValues;
use DimitrienkoV\LaravelModules\Manifest\VO\Module;
use DimitrienkoV\LaravelModules\Manifest\VO\ModuleStateDocument;
use DimitrienkoV\LaravelModules\MoonShine\Data\ModuleAdminDto;
use DimitrienkoV\LaravelModules\MoonShine\Resources\ModulesResource;
use DimitrienkoV\LaravelModules\MoonShine\Support\FeatureFieldFactory;
use DimitrienkoV\LaravelModules\Providers\ModuleLoaderServiceProvider;
use DimitrienkoV\LaravelModules\Tests\Support\FakeModuleRegistry;
use DimitrienkoV\LaravelModules\Tests\Support\ModuleFactory;
use Mockery;
use MoonShine\Contracts\Core\DependencyInjection\RequestContract;
use MoonShine\Crud\Collections\Fields;
use MoonShine\Laravel\Providers\MoonShineServiceProvider;
use Orchestra\Testbench\TestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

/**
 * Covers the write boundary of the form: {@see ModulesResource::save()} persists
 * only explicit overrides through `ModuleStateRepository::writeValues()` as a
 * {@see FeatureValues} object (never a raw array), and values equal to the schema
 * default are stripped so defaults are never duplicated into the value set.
 */
#[Group('feature')]
final class ModulesResourceSaveTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

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
    public function persistsOnlyExplicitOverridesAndStripsDefaults(): void
    {
        $module = ModuleFactory::make(name: 'blog', features: $this->schema());

        $registry = new FakeModuleRegistry();
        $registry->add($module);

        // Submitted: retries overrides the default (3 -> 7); timeout matches its
        // default (30) and must be stripped from the persisted value set.
        $this->bindRequest(['retries' => '7', 'timeout' => '30']);

        $captured = null;
        $state = Mockery::mock(ModuleStateRepositoryInterface::class);
        $state->shouldReceive('writeValues')->once()
            ->with(Mockery::type(Module::class), Mockery::on(static function (FeatureValues $values) use (&$captured): bool {
                $captured = $values;

                return true;
            }));
        $state->shouldReceive('read')->once()->andReturn(
            new ModuleStateDocument($module->state, new FeatureValues($module->features, []), null),
        );

        $resource = new ModulesResource($this->core(), $registry, $state, $this->app->make(\Illuminate\Contracts\Config\Repository::class));
        $item = $resource->getCaster()->cast(
            ModuleAdminDto::fromModule($module, new FeatureValues($module->features, []), null, 0),
        );

        $fields = new Fields([
            $this->factory()->field($module->features->definition('blog', 'retries')),
            $this->factory()->field($module->features->definition('blog', 'timeout')),
        ]);

        $resource->save($item, $fields);

        self::assertInstanceOf(FeatureValues::class, $captured);
        self::assertSame(['retries' => 7], $captured->explicitValues());
    }

    private function schema(): FeatureSchema
    {
        return new FeatureSchema([
            'retries' => new FeatureDefinition('retries', FeatureType::Integer, true, 3, 1, 10, [], null, null, null),
            'timeout' => new FeatureDefinition('timeout', FeatureType::Integer, true, 30, null, null, [], null, null, null),
        ]);
    }

    private function factory(): FeatureFieldFactory
    {
        return $this->app->make(FeatureFieldFactory::class);
    }

    /**
     * Bind a {@see RequestContract} whose has()/get() answer for the feature
     * columns, keyed by the bare feature name (matched as a dot-name suffix so
     * any MoonShine request-key prefix still resolves).
     *
     * @param array<string, string> $values feature key => submitted raw value
     */
    private function bindRequest(array $values): void
    {
        $request = Mockery::mock(RequestContract::class);

        $request->shouldReceive('has')->andReturnUsing(
            static fn(string $key): bool => self::matchKey($key, $values) !== null,
        );
        $request->shouldReceive('get')->andReturnUsing(
            static fn(string $key, mixed $default = null): mixed => self::matchKey($key, $values) ?? $default,
        );

        $this->app->instance(RequestContract::class, $request);
    }

    /**
     * @param array<string, string> $values
     */
    private static function matchKey(string $key, array $values): ?string
    {
        foreach ($values as $name => $value) {
            if (str_ends_with($key, $name)) {
                return $value;
            }
        }

        return null;
    }

    private function core(): \MoonShine\Contracts\Core\DependencyInjection\CoreContract&Mockery\MockInterface
    {
        /** @var \MoonShine\Contracts\Core\DependencyInjection\CoreContract&Mockery\MockInterface */
        return Mockery::mock(\MoonShine\Contracts\Core\DependencyInjection\CoreContract::class);
    }
}

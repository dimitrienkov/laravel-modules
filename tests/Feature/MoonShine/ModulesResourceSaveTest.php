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
use DimitrienkoV\LaravelModules\MoonShine\Support\FeatureValueWriter;
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
        $state->shouldReceive('readValues')->once()->with(Mockery::type(Module::class))->andReturn(
            new FeatureValues($module->features, []),
        );
        $state->shouldReceive('writeValues')->once()
            ->with(Mockery::type(Module::class), Mockery::on(static function (FeatureValues $values) use (&$captured): bool {
                $captured = $values;

                return true;
            }));
        $state->shouldReceive('read')->once()->andReturn(
            new ModuleStateDocument($module->state, new FeatureValues($module->features, []), null),
        );

        $resource = new ModulesResource($this->core(), $registry, $state, new FeatureValueWriter($state), $this->app->make(\Illuminate\Contracts\Config\Repository::class));
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

    #[Test]
    public function partialSubmitPreservesAnUnrelatedExistingOverride(): void
    {
        $module = ModuleFactory::make(name: 'blog', features: $this->intEnumSchema());

        $registry = new FakeModuleRegistry();
        $registry->add($module);

        // state.json already holds driver=file; the form posts only retries. The
        // unrelated driver override must survive — not be wiped because its field
        // was absent from this submit.
        $this->bindRequest(['retries' => '7']);

        [$state, $capture] = $this->stateCapturing(
            $module,
            FeatureValues::fromArray(['driver' => 'file'], $module->features, 'blog', $module->manifestPath()),
        );

        $resource = new ModulesResource($this->core(), $registry, $state, new FeatureValueWriter($state), $this->configRepository());
        $item = $resource->getCaster()->cast(
            ModuleAdminDto::fromModule($module, new FeatureValues($module->features, []), null, 0),
        );

        $resource->save($item, new Fields([$this->factory()->field($module->features->definition('blog', 'retries'))]));

        self::assertInstanceOf(FeatureValues::class, $capture->value);
        self::assertSame(['driver' => 'file', 'retries' => 7], $capture->value->explicitValues());
    }

    #[Test]
    public function clearingOneFieldKeepsOtherExistingOverrides(): void
    {
        $module = ModuleFactory::make(name: 'blog', features: $this->intEnumSchema());

        $registry = new FakeModuleRegistry();
        $registry->add($module);

        // state.json holds driver=file and retries=7; the form clears retries.
        // Only retries is dropped; driver=file is preserved.
        $this->bindRequest(['retries' => null]);

        [$state, $capture] = $this->stateCapturing(
            $module,
            FeatureValues::fromArray(['driver' => 'file', 'retries' => 7], $module->features, 'blog', $module->manifestPath()),
        );

        $resource = new ModulesResource($this->core(), $registry, $state, new FeatureValueWriter($state), $this->configRepository());
        $item = $resource->getCaster()->cast(
            ModuleAdminDto::fromModule($module, new FeatureValues($module->features, []), null, 0),
        );

        $resource->save($item, new Fields([$this->factory()->field($module->features->definition('blog', 'retries'))]));

        self::assertInstanceOf(FeatureValues::class, $capture->value);
        self::assertSame(['driver' => 'file'], $capture->value->explicitValues());
    }

    #[Test]
    public function revertsClearedIntegerAndEnumFieldsToDefaultWithoutThrowing(): void
    {
        $module = ModuleFactory::make(name: 'blog', features: $this->intEnumSchema());

        $registry = new FakeModuleRegistry();
        $registry->add($module);

        // Both fields are cleared in the form: ConvertEmptyStringsToNull (or a
        // disabled middleware) delivers '' / null. save() must revert to the schema
        // default instead of letting the strict normalizer reject '' with a 500.
        $this->bindRequest(['retries' => '', 'driver' => '']);

        [$state, $capture] = $this->stateCapturing($module);

        $resource = new ModulesResource($this->core(), $registry, $state, new FeatureValueWriter($state), $this->configRepository());
        $item = $resource->getCaster()->cast(
            ModuleAdminDto::fromModule($module, new FeatureValues($module->features, []), null, 0),
        );

        $fields = new Fields([
            $this->factory()->field($module->features->definition('blog', 'retries')),
            $this->factory()->field($module->features->definition('blog', 'driver')),
        ]);

        $resource->save($item, $fields);

        self::assertInstanceOf(FeatureValues::class, $capture->value);
        self::assertSame([], $capture->value->explicitValues());
    }

    #[Test]
    public function clearingAnExistingOverrideViaNullRevertsToDefault(): void
    {
        $module = ModuleFactory::make(name: 'blog', features: $this->intEnumSchema());

        $registry = new FakeModuleRegistry();
        $registry->add($module);

        // state.json already holds an explicit override; the form clears the field
        // and submits null. has()==true exercises the clear guard, not the
        // unchanged-field skip — so this proves the revert, not mere key absence.
        $this->bindRequest(['retries' => null]);

        [$state, $capture] = $this->stateCapturing(
            $module,
            FeatureValues::fromArray(['retries' => 7], $module->features, 'blog', $module->manifestPath()),
        );

        $resource = new ModulesResource($this->core(), $registry, $state, new FeatureValueWriter($state), $this->configRepository());
        $item = $resource->getCaster()->cast(
            ModuleAdminDto::fromModule($module, new FeatureValues($module->features, []), null, 0),
        );

        $fields = new Fields([
            $this->factory()->field($module->features->definition('blog', 'retries')),
        ]);

        $resource->save($item, $fields);

        self::assertInstanceOf(FeatureValues::class, $capture->value);
        self::assertArrayNotHasKey('retries', $capture->value->explicitValues());
    }

    #[Test]
    public function persistsBooleanOffOverrideAgainstADefaultTrue(): void
    {
        $module = ModuleFactory::make(name: 'blog', features: $this->booleanSchema(default: true));

        $registry = new FakeModuleRegistry();
        $registry->add($module);

        // Off-switch arrives as '0'; against default true it is a genuine override
        // and must be persisted as explicit false (regression guard for the T1 fix
        // — Boolean is exempt from the clear guard).
        $this->bindRequest(['flag' => '0']);

        [$state, $capture] = $this->stateCapturing($module);

        $resource = new ModulesResource($this->core(), $registry, $state, new FeatureValueWriter($state), $this->configRepository());
        $item = $resource->getCaster()->cast(
            ModuleAdminDto::fromModule($module, new FeatureValues($module->features, []), null, 0),
        );

        $resource->save($item, new Fields([$this->factory()->field($module->features->definition('blog', 'flag'))]));

        self::assertInstanceOf(FeatureValues::class, $capture->value);
        self::assertSame(['flag' => false], $capture->value->explicitValues());
    }

    #[Test]
    public function stripsBooleanOffWhenItMatchesADefaultFalse(): void
    {
        $module = ModuleFactory::make(name: 'blog', features: $this->booleanSchema(default: false));

        $registry = new FakeModuleRegistry();
        $registry->add($module);

        // Off-switch equal to the default false is stripped, mirroring the
        // strips-defaults contract for every other type.
        $this->bindRequest(['flag' => '0']);

        [$state, $capture] = $this->stateCapturing($module);

        $resource = new ModulesResource($this->core(), $registry, $state, new FeatureValueWriter($state), $this->configRepository());
        $item = $resource->getCaster()->cast(
            ModuleAdminDto::fromModule($module, new FeatureValues($module->features, []), null, 0),
        );

        $resource->save($item, new Fields([$this->factory()->field($module->features->definition('blog', 'flag'))]));

        self::assertInstanceOf(FeatureValues::class, $capture->value);
        self::assertSame([], $capture->value->explicitValues());
    }

    /**
     * Wire a {@see ModuleStateRepositoryInterface} double that captures the single
     * {@see FeatureValues} passed to writeValues() and answers the post-write
     * read() used to build the returned DTO.
     *
     * @return array{0: ModuleStateRepositoryInterface&Mockery\MockInterface, 1: object{value: ?FeatureValues}}
     */
    private function stateCapturing(Module $module, ?FeatureValues $existing = null): array
    {
        $capture = new class {
            public ?FeatureValues $value = null;
        };

        $state = Mockery::mock(ModuleStateRepositoryInterface::class);
        // readValues() feeds the writer the pre-existing explicit overrides it must
        // merge this submit onto; read() answers the post-write DTO rebuild.
        $state->shouldReceive('readValues')->once()->with(Mockery::type(Module::class))->andReturn(
            $existing ?? new FeatureValues($module->features, []),
        );
        $state->shouldReceive('writeValues')->once()
            ->with(Mockery::type(Module::class), Mockery::on(static function (FeatureValues $values) use ($capture): bool {
                $capture->value = $values;

                return true;
            }));
        $state->shouldReceive('read')->once()->andReturn(
            new ModuleStateDocument(
                $module->state,
                $existing ?? new FeatureValues($module->features, []),
                null,
            ),
        );

        return [$state, $capture];
    }

    private function configRepository(): \Illuminate\Contracts\Config\Repository
    {
        return $this->app->make(\Illuminate\Contracts\Config\Repository::class);
    }

    private function schema(): FeatureSchema
    {
        return new FeatureSchema([
            'retries' => new FeatureDefinition('retries', FeatureType::Integer, true, 3, 1, 10, [], null, null, null),
            'timeout' => new FeatureDefinition('timeout', FeatureType::Integer, true, 30, null, null, [], null, null, null),
        ]);
    }

    private function intEnumSchema(): FeatureSchema
    {
        return new FeatureSchema([
            'retries' => new FeatureDefinition('retries', FeatureType::Integer, true, 3, 1, 10, [], null, null, null),
            'driver' => new FeatureDefinition('driver', FeatureType::Enum, true, 'redis', null, null, ['redis', 'file'], null, null, null),
        ]);
    }

    private function booleanSchema(bool $default): FeatureSchema
    {
        return new FeatureSchema([
            'flag' => new FeatureDefinition('flag', FeatureType::Boolean, true, $default, null, null, [], null, null, null),
        ]);
    }

    private function factory(): FeatureFieldFactory
    {
        return $this->app->make(FeatureFieldFactory::class);
    }

    /**
     * Bind a {@see RequestContract} whose has()/get() answer for the feature
     * columns, keyed by the bare feature name (matched as a dot-name suffix so
     * any MoonShine request-key prefix still resolves). Presence is tracked
     * independently of the value, so a bound `null` still reports `has() === true`
     * — a present-but-cleared field, not an absent one.
     *
     * @param array<string, mixed> $values feature key => submitted raw value
     */
    private function bindRequest(array $values): void
    {
        $request = Mockery::mock(RequestContract::class);

        $request->shouldReceive('has')->andReturnUsing(
            static fn(string $key): bool => self::matchKey($key, $values)[0],
        );
        $request->shouldReceive('get')->andReturnUsing(
            static function (string $key, mixed $default = null) use ($values): mixed {
                [$found, $value] = self::matchKey($key, $values);

                return $found ? $value : $default;
            },
        );

        $this->app->instance(RequestContract::class, $request);
    }

    /**
     * @param array<string, mixed> $values
     *
     * @return array{bool, mixed} [matched, value]
     */
    private static function matchKey(string $key, array $values): array
    {
        foreach ($values as $name => $value) {
            if (str_ends_with($key, $name)) {
                return [true, $value];
            }
        }

        return [false, null];
    }

    private function core(): \MoonShine\Contracts\Core\DependencyInjection\CoreContract&Mockery\MockInterface
    {
        /** @var \MoonShine\Contracts\Core\DependencyInjection\CoreContract&Mockery\MockInterface */
        return Mockery::mock(\MoonShine\Contracts\Core\DependencyInjection\CoreContract::class);
    }
}

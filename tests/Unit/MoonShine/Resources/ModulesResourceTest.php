<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Tests\Unit\MoonShine\Resources;

use DimitrienkoV\LaravelModules\Contracts\ModuleStateRepositoryInterface;
use DimitrienkoV\LaravelModules\Exceptions\ModuleNotFoundException;
use DimitrienkoV\LaravelModules\Manifest\VO\FeatureValues;
use DimitrienkoV\LaravelModules\Manifest\VO\Module;
use DimitrienkoV\LaravelModules\Manifest\VO\ModuleState;
use DimitrienkoV\LaravelModules\Manifest\VO\ModuleStateDocument;
use DimitrienkoV\LaravelModules\MoonShine\Data\ModuleAdminDto;
use DimitrienkoV\LaravelModules\MoonShine\Resources\ModulesResource;
use DimitrienkoV\LaravelModules\MoonShine\Support\FeatureValueWriter;
use DimitrienkoV\LaravelModules\Tests\Support\FakeModuleRegistry;
use DimitrienkoV\LaravelModules\Tests\Support\ModuleFactory;
use Illuminate\Config\Repository;
use Mockery;
use Mockery\MockInterface;
use MoonShine\Contracts\Core\DependencyInjection\CoreContract;
use MoonShine\Crud\Collections\Fields;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ModulesResource::class)]
#[Group('moonshine')]
final class ModulesResourceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    #[Test]
    public function getItemsMapsRegistryToDtosInRegistryOrderWithNonNullKey(): void
    {
        $registry = new FakeModuleRegistry();
        $registry->add(ModuleFactory::make(name: 'users', enabled: true));
        $registry->add(ModuleFactory::make(name: 'blog', enabled: false));

        $resource = $this->makeResource($registry);

        $items = $resource->getItems();
        self::assertCount(2, $items);

        // Dependency order is preserved as the array order; the index never renders
        // per-row load order, so loadOrder stays 0 (findItem computes the real one).
        self::assertSame('users', $items[0]->name);
        self::assertSame(0, $items[0]->loadOrder);
        self::assertTrue($items[0]->enabled);

        self::assertSame('blog', $items[1]->name);
        self::assertSame(0, $items[1]->loadOrder);
        self::assertFalse($items[1]->enabled);

        // casterKeyName='name' must yield a non-null key for row actions/routing.
        self::assertSame('users', $resource->getCaster()->cast($items[0])->getKey());
    }

    #[Test]
    public function findItemReadsFreshStateForTheSelectedModule(): void
    {
        // Registry snapshot says disabled; state.json (read in findItem) says enabled.
        $module = ModuleFactory::make(name: 'blog', enabled: false);
        $registry = new FakeModuleRegistry();
        $registry->add($module);

        $document = new ModuleStateDocument(
            new ModuleState(enabled: true, installedAt: '2026-01-01T00:00:00+00:00'),
            new FeatureValues($module->features, []),
            null,
        );

        $state = Mockery::mock(ModuleStateRepositoryInterface::class);
        $state->shouldReceive('read')->once()
            ->with('blog', Mockery::type(Module::class))
            ->andReturn($document);

        $resource = $this->makeResource($registry, $state);
        $resource->setItemID('blog');

        $found = $resource->findItem();

        self::assertNotNull($found);
        self::assertSame('blog', $found->getKey());

        /** @var ModuleAdminDto $dto */
        $dto = $found->getOriginal();
        self::assertTrue($dto->enabled, 'findItem must reflect fresh state.json, not the registry snapshot.');
        self::assertSame(0, $dto->loadOrder);
    }

    #[Test]
    public function findItemReturnsNullForUnknownModule(): void
    {
        $resource = $this->makeResource(new FakeModuleRegistry());
        $resource->setItemID('ghost');

        self::assertNull($resource->findItem());
    }

    #[Test]
    public function findItemThrowsModuleNotFoundWhenOrFailAndModuleMissing(): void
    {
        $resource = $this->makeResource(new FakeModuleRegistry());
        $resource->setItemID('ghost');

        $this->expectException(ModuleNotFoundException::class);

        $resource->findItem(orFail: true);
    }

    #[Test]
    public function findItemThrowsNoSelectionWhenOrFailAndNoModuleSelected(): void
    {
        $resource = $this->makeResource(new FakeModuleRegistry());
        // false is the sentinel that makes getItemID() resolve to null without
        // touching the (mocked) request.
        $resource->setItemID(false);

        $this->expectException(ModuleNotFoundException::class);
        $this->expectExceptionMessage('No module was selected.');

        $resource->findItem(orFail: true);
    }

    #[Test]
    public function saveThrowsModuleNotFoundForAModuleThatVanishedBeforeSubmit(): void
    {
        $registry = new FakeModuleRegistry();
        $state = Mockery::mock(ModuleStateRepositoryInterface::class);
        $state->shouldNotReceive('writeValues');

        $resource = $this->makeResource($registry, $state);

        // The DTO carries a key for a module the registry no longer holds.
        $module = ModuleFactory::make(name: 'ghost');
        $item = $resource->getCaster()->cast(
            ModuleAdminDto::fromModule($module, new FeatureValues($module->features, []), null, 0),
        );

        $this->expectException(ModuleNotFoundException::class);

        $resource->save($item, new Fields([]));
    }

    #[Test]
    public function saveWritesOnlyFeatureValuesAndNeverChangesEnabled(): void
    {
        $module = ModuleFactory::make(name: 'blog', enabled: true);
        $registry = new FakeModuleRegistry();
        $registry->add($module);

        $document = new ModuleStateDocument(
            new ModuleState(enabled: true, installedAt: '2026-01-01T00:00:00+00:00'),
            new FeatureValues($module->features, []),
            null,
        );

        $state = Mockery::mock(ModuleStateRepositoryInterface::class);
        $state->shouldReceive('readValues')->once()->with(Mockery::type(Module::class))
            ->andReturn(new FeatureValues($module->features, []));
        $state->shouldReceive('writeValues')->once()
            ->with(Mockery::type(Module::class), Mockery::type(FeatureValues::class));
        $state->shouldReceive('read')->once()->andReturn($document);
        $state->shouldNotReceive('writeState');
        $state->shouldNotReceive('writeDocument');

        $resource = $this->makeResource($registry, $state);

        $item = $resource->getCaster()->cast(
            ModuleAdminDto::fromModule($module, new FeatureValues($module->features, []), null, 0),
        );

        $result = $resource->save($item, new Fields([]));

        self::assertSame('blog', $result->getKey());
    }

    #[Test]
    public function deleteAndMassDeleteAreSafeStubs(): void
    {
        $module = ModuleFactory::make(name: 'blog');
        $registry = new FakeModuleRegistry();
        $registry->add($module);

        $resource = $this->makeResource($registry);

        $item = $resource->getCaster()->cast(
            ModuleAdminDto::fromModule($module, new FeatureValues($module->features, []), null, 0),
        );

        self::assertFalse($resource->delete($item));
        $resource->massDelete(['blog']);
    }

    #[Test]
    public function menuVisibilityFollowsTheConfigFlag(): void
    {
        $registry = new FakeModuleRegistry();
        $state = Mockery::mock(ModuleStateRepositoryInterface::class);

        $visible = $this->makeResource($registry, $state, new Repository(['modules' => ['moonshine' => ['menu' => true]]]));
        $hidden = $this->makeResource($registry, $state, new Repository(['modules' => ['moonshine' => ['menu' => false]]]));

        self::assertTrue($visible->menuVisible());
        self::assertFalse($hidden->menuVisible());
    }

    /**
     * Build the resource, defaulting the state double and wiring a writer over the
     * same state so save() and the writer share one captured repository.
     */
    private function makeResource(
        FakeModuleRegistry $registry,
        ?ModuleStateRepositoryInterface $state = null,
        ?Repository $config = null,
    ): ModulesResource {
        $state ??= Mockery::mock(ModuleStateRepositoryInterface::class);

        return new ModulesResource(
            $this->core(),
            $registry,
            $state,
            new FeatureValueWriter($state),
            $config ?? $this->config(),
        );
    }

    private function core(): CoreContract&MockInterface
    {
        /** @var CoreContract&MockInterface */
        return Mockery::mock(CoreContract::class);
    }

    private function config(): Repository
    {
        return new Repository(['modules' => ['moonshine' => ['enabled' => true, 'menu' => true]]]);
    }
}

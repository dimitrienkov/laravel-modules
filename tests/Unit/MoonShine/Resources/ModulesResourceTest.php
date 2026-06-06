<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Tests\Unit\MoonShine\Resources;

use DimitrienkoV\LaravelModules\Contracts\ModuleStateRepositoryInterface;
use DimitrienkoV\LaravelModules\Manifest\VO\FeatureValues;
use DimitrienkoV\LaravelModules\Manifest\VO\Module;
use DimitrienkoV\LaravelModules\Manifest\VO\ModuleState;
use DimitrienkoV\LaravelModules\Manifest\VO\ModuleStateDocument;
use DimitrienkoV\LaravelModules\MoonShine\Data\ModuleAdminDto;
use DimitrienkoV\LaravelModules\MoonShine\Resources\ModulesResource;
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
use RuntimeException;

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
    public function getItemsMapsRegistryToDtosInLoadOrderWithNonNullKey(): void
    {
        $registry = new FakeModuleRegistry();
        $registry->add(ModuleFactory::make(name: 'users', enabled: true));
        $registry->add(ModuleFactory::make(name: 'blog', enabled: false));

        $resource = new ModulesResource(
            $this->core(),
            $registry,
            Mockery::mock(ModuleStateRepositoryInterface::class),
            $this->config(),
        );

        $items = $resource->getItems();
        self::assertCount(2, $items);

        self::assertSame('users', $items[0]->name);
        self::assertSame(0, $items[0]->loadOrder);
        self::assertTrue($items[0]->enabled);

        self::assertSame('blog', $items[1]->name);
        self::assertSame(1, $items[1]->loadOrder);
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

        $resource = new ModulesResource($this->core(), $registry, $state, $this->config());
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
        $resource = new ModulesResource(
            $this->core(),
            new FakeModuleRegistry(),
            Mockery::mock(ModuleStateRepositoryInterface::class),
            $this->config(),
        );
        $resource->setItemID('ghost');

        self::assertNull($resource->findItem());
    }

    #[Test]
    public function findItemThrowsWhenOrFailAndModuleMissing(): void
    {
        $resource = new ModulesResource(
            $this->core(),
            new FakeModuleRegistry(),
            Mockery::mock(ModuleStateRepositoryInterface::class),
            $this->config(),
        );
        $resource->setItemID('ghost');

        $this->expectException(RuntimeException::class);

        $resource->findItem(orFail: true);
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
        $state->shouldReceive('writeValues')->once()
            ->with(Mockery::type(Module::class), Mockery::type(FeatureValues::class));
        $state->shouldReceive('read')->once()->andReturn($document);
        $state->shouldNotReceive('writeState');
        $state->shouldNotReceive('writeDocument');

        $resource = new ModulesResource($this->core(), $registry, $state, $this->config());

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

        $resource = new ModulesResource(
            $this->core(),
            $registry,
            Mockery::mock(ModuleStateRepositoryInterface::class),
            $this->config(),
        );

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

        $visible = new ModulesResource($this->core(), $registry, $state, new Repository(['modules' => ['moonshine' => ['menu' => true]]]));
        $hidden = new ModulesResource($this->core(), $registry, $state, new Repository(['modules' => ['moonshine' => ['menu' => false]]]));

        self::assertTrue($visible->menuVisible());
        self::assertFalse($hidden->menuVisible());
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

<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Tests\Unit\Loaders;

use DimitrienkoV\LaravelModules\Loaders\ObserverLoader;
use DimitrienkoV\LaravelModules\Support\ModuleLayout;
use DimitrienkoV\LaravelModules\Tests\Support\ModuleFactory;
use DimitrienkoV\LaravelModules\Tests\Support\UsesTempDirectory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Events\Dispatcher;
use Illuminate\Filesystem\Filesystem;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ObserverLoader::class)]
#[Group('loaders')]
final class ObserverLoaderTest extends TestCase
{
    use UsesTempDirectory;

    /** @var list<callable> */
    private array $autoloaders = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->createTempDirectory('observer-loader');
        Model::setEventDispatcher(new Dispatcher());
    }

    protected function tearDown(): void
    {
        foreach ($this->autoloaders as $autoloader) {
            spl_autoload_unregister($autoloader);
        }

        $this->autoloaders = [];
        Model::unsetEventDispatcher();
        $this->deleteTempDirectory();

        parent::tearDown();
    }

    #[Test]
    public function registersObserverForMatchingModel(): void
    {
        $modulePath = $this->tempDir . '/Blog';
        $observersDir = $modulePath . '/Domain/Observers';
        $modelsDir = $modulePath . '/Domain/Models';
        mkdir($observersDir, 0755, true);
        mkdir($modelsDir, 0755, true);
        file_put_contents(
            $modelsDir . '/Post.php',
            '<?php namespace App\\Modules\\Blog\\Domain\\Models; class Post extends \\Illuminate\\Database\\Eloquent\\Model { protected $guarded = []; }',
        );
        file_put_contents(
            $observersDir . '/PostObserver.php',
            '<?php namespace App\\Modules\\Blog\\Domain\\Observers; class PostObserver { public function creating($model): void {} }',
        );
        $this->registerAutoloader($modelsDir . '/Post.php', 'App\\Modules\\Blog\\Domain\\Models\\Post');
        $this->registerAutoloader($observersDir . '/PostObserver.php', 'App\\Modules\\Blog\\Domain\\Observers\\PostObserver');

        (new ObserverLoader(new Filesystem(), new ModuleLayout()))
            ->load(ModuleFactory::make(path: $modulePath, namespace: 'App\\Modules\\Blog'));

        /** @var Dispatcher $dispatcher */
        $dispatcher = Model::getEventDispatcher();
        self::assertTrue($dispatcher->hasListeners('eloquent.creating: App\\Modules\\Blog\\Domain\\Models\\Post'));
    }

    #[Test]
    public function skipsWhenModelClassDoesNotExist(): void
    {
        $modulePath = $this->tempDir . '/Blog';
        $observersDir = $modulePath . '/Domain/Observers';
        mkdir($observersDir, 0755, true);
        file_put_contents(
            $observersDir . '/CommentObserver.php',
            '<?php namespace App\\Modules\\Blog\\Domain\\Observers; class CommentObserver { public function creating($model): void {} }',
        );
        $this->registerAutoloader($observersDir . '/CommentObserver.php', 'App\\Modules\\Blog\\Domain\\Observers\\CommentObserver');

        (new ObserverLoader(new Filesystem(), new ModuleLayout()))
            ->load(ModuleFactory::make(path: $modulePath, namespace: 'App\\Modules\\Blog'));

        /** @var Dispatcher $dispatcher */
        $dispatcher = Model::getEventDispatcher();
        self::assertFalse($dispatcher->hasListeners('eloquent.creating: App\\Modules\\Blog\\Domain\\Models\\Comment'));
    }

    #[Test]
    public function skipsWhenModelIsNotEloquentModel(): void
    {
        $modulePath = $this->tempDir . '/Blog';
        $observersDir = $modulePath . '/Domain/Observers';
        $modelsDir = $modulePath . '/Domain/Models';
        mkdir($observersDir, 0755, true);
        mkdir($modelsDir, 0755, true);
        file_put_contents(
            $modelsDir . '/Tag.php',
            '<?php namespace App\\Modules\\Blog\\Domain\\Models; class Tag {}',
        );
        file_put_contents(
            $observersDir . '/TagObserver.php',
            '<?php namespace App\\Modules\\Blog\\Domain\\Observers; class TagObserver { public function creating($model): void {} }',
        );
        $this->registerAutoloader($modelsDir . '/Tag.php', 'App\\Modules\\Blog\\Domain\\Models\\Tag');
        $this->registerAutoloader($observersDir . '/TagObserver.php', 'App\\Modules\\Blog\\Domain\\Observers\\TagObserver');

        (new ObserverLoader(new Filesystem(), new ModuleLayout()))
            ->load(ModuleFactory::make(path: $modulePath, namespace: 'App\\Modules\\Blog'));

        /** @var Dispatcher $dispatcher */
        $dispatcher = Model::getEventDispatcher();
        self::assertFalse($dispatcher->hasListeners('eloquent.creating: App\\Modules\\Blog\\Domain\\Models\\Tag'));
    }

    #[Test]
    public function returnsEarlyWhenObserversDirectoryIsMissing(): void
    {
        (new ObserverLoader(new Filesystem(), new ModuleLayout()))
            ->load(ModuleFactory::make(path: $this->tempDir . '/Missing'));

        /** @var Dispatcher $dispatcher */
        $dispatcher = Model::getEventDispatcher();
        self::assertFalse($dispatcher->hasListeners('eloquent.creating: *'));
    }

    #[Test]
    public function registersObserversFromTwoModulesWithoutDuplication(): void
    {
        $blogPath = $this->tempDir . '/Blog';
        $blogObserversDir = $blogPath . '/Domain/Observers';
        $blogModelsDir = $blogPath . '/Domain/Models';
        mkdir($blogObserversDir, 0755, true);
        mkdir($blogModelsDir, 0755, true);
        file_put_contents(
            $blogModelsDir . '/Post.php',
            '<?php namespace App\\Modules\\Blog\\Domain\\Models; class Post extends \\Illuminate\\Database\\Eloquent\\Model { protected $guarded = []; }',
        );
        file_put_contents(
            $blogObserversDir . '/PostObserver.php',
            '<?php namespace App\\Modules\\Blog\\Domain\\Observers; class PostObserver { public function creating($model): void {} }',
        );
        $this->registerAutoloader($blogModelsDir . '/Post.php', 'App\\Modules\\Blog\\Domain\\Models\\Post');
        $this->registerAutoloader($blogObserversDir . '/PostObserver.php', 'App\\Modules\\Blog\\Domain\\Observers\\PostObserver');

        $shopPath = $this->tempDir . '/Shop';
        $shopObserversDir = $shopPath . '/Domain/Observers';
        $shopModelsDir = $shopPath . '/Domain/Models';
        mkdir($shopObserversDir, 0755, true);
        mkdir($shopModelsDir, 0755, true);
        file_put_contents(
            $shopModelsDir . '/Order.php',
            '<?php namespace App\\Modules\\Shop\\Domain\\Models; class Order extends \\Illuminate\\Database\\Eloquent\\Model { protected $guarded = []; }',
        );
        file_put_contents(
            $shopObserversDir . '/OrderObserver.php',
            '<?php namespace App\\Modules\\Shop\\Domain\\Observers; class OrderObserver { public function creating($model): void {} }',
        );
        $this->registerAutoloader($shopModelsDir . '/Order.php', 'App\\Modules\\Shop\\Domain\\Models\\Order');
        $this->registerAutoloader($shopObserversDir . '/OrderObserver.php', 'App\\Modules\\Shop\\Domain\\Observers\\OrderObserver');

        $loader = new ObserverLoader(new Filesystem(), new ModuleLayout());
        $loader->load(ModuleFactory::make(name: 'blog', path: $blogPath, namespace: 'App\\Modules\\Blog'));
        $loader->load(ModuleFactory::make(name: 'shop', path: $shopPath, namespace: 'App\\Modules\\Shop'));

        /** @var Dispatcher $dispatcher */
        $dispatcher = Model::getEventDispatcher();
        self::assertTrue($dispatcher->hasListeners('eloquent.creating: App\\Modules\\Blog\\Domain\\Models\\Post'));
        self::assertTrue($dispatcher->hasListeners('eloquent.creating: App\\Modules\\Shop\\Domain\\Models\\Order'));
    }

    private function registerAutoloader(string $file, string $class): void
    {
        $autoloader = static function (string $requested) use ($file, $class): void {
            if ($requested === $class) {
                require_once $file;
            }
        };

        spl_autoload_register($autoloader);
        $this->autoloaders[] = $autoloader;
    }
}

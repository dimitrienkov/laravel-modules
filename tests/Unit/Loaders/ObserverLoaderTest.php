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
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

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
    public function it_registers_observer_for_matching_model(): void
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
    public function it_skips_when_model_class_does_not_exist(): void
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
    public function it_skips_when_model_is_not_eloquent_model(): void
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
    public function it_returns_early_when_observers_directory_is_missing(): void
    {
        (new ObserverLoader(new Filesystem(), new ModuleLayout()))
            ->load(ModuleFactory::make(path: $this->tempDir . '/Missing'));

        /** @var Dispatcher $dispatcher */
        $dispatcher = Model::getEventDispatcher();
        self::assertFalse($dispatcher->hasListeners('eloquent.creating: *'));
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

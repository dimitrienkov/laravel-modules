<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Tests\Unit\Loaders;

use DimitrienkoV\LaravelModules\Loaders\LangLoader;
use DimitrienkoV\LaravelModules\Support\ContainerLifecycleHooks;
use DimitrienkoV\LaravelModules\Support\ModuleLayout;
use DimitrienkoV\LaravelModules\Tests\Support\ModuleFactory;
use DimitrienkoV\LaravelModules\Tests\Support\UsesTempDirectory;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Application;
use Illuminate\Translation\FileLoader;
use Illuminate\Translation\Translator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(LangLoader::class)]
#[Group('loaders')]
final class LangLoaderTest extends TestCase
{
    use UsesTempDirectory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createTempDirectory('lang-loader');
    }

    protected function tearDown(): void
    {
        $this->deleteTempDirectory();

        parent::tearDown();
    }

    #[Test]
    public function registersTranslationNamespaceForModule(): void
    {
        $modulePath = $this->tempDir . '/Blog';
        $langDir = $modulePath . '/Lang';
        mkdir($langDir . '/en', 0755, true);
        file_put_contents($langDir . '/en/messages.php', '<?php return ["hello" => "world"];');
        $fileLoader = new FileLoader(new Filesystem(), $langDir);
        $translator = new Translator($fileLoader, 'en');
        $app = new Application($this->tempDir);
        $app->singleton('translator', static fn (): Translator => $translator);

        $this->loader($app)
            ->load(ModuleFactory::make(name: 'blog', path: $modulePath));

        self::assertFalse($app->resolved('translator'));

        $app->make('translator');

        self::assertArrayHasKey('blog', $fileLoader->namespaces());
        self::assertSame($langDir, $fileLoader->namespaces()['blog']);
    }

    #[Test]
    public function registersTranslationNamespaceWhenTranslatorWasAlreadyResolved(): void
    {
        $modulePath = $this->tempDir . '/Blog';
        $langDir = $modulePath . '/Lang';
        mkdir($langDir . '/en', 0755, true);
        $fileLoader = new FileLoader(new Filesystem(), $langDir);
        $translator = new Translator($fileLoader, 'en');
        $app = new Application($this->tempDir);
        $app->instance('translator', $translator);
        $app->make('translator');

        $this->loader($app)
            ->load(ModuleFactory::make(name: 'blog', path: $modulePath));

        self::assertArrayHasKey('blog', $fileLoader->namespaces());
        self::assertSame($langDir, $fileLoader->namespaces()['blog']);
    }

    #[Test]
    public function returnsEarlyWhenLangDirectoryIsMissing(): void
    {
        $fileLoader = new FileLoader(new Filesystem(), $this->tempDir);
        $translator = new Translator($fileLoader, 'en');
        $app = new Application($this->tempDir);
        $app->singleton('translator', static fn (): Translator => $translator);

        $this->loader($app)
            ->load(ModuleFactory::make(path: $this->tempDir . '/Missing'));

        $app->make('translator');

        self::assertSame([], $fileLoader->namespaces());
    }

    private function loader(Application $app): LangLoader
    {
        return new LangLoader(new ContainerLifecycleHooks($app), new Filesystem(), new ModuleLayout());
    }
}

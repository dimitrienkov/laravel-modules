<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Tests\Unit\Loaders;

use DimitrienkoV\LaravelModules\Loaders\LangLoader;
use DimitrienkoV\LaravelModules\Support\ModuleLayout;
use DimitrienkoV\LaravelModules\Tests\Support\ModuleFactory;
use DimitrienkoV\LaravelModules\Tests\Support\UsesTempDirectory;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Translation\FileLoader;
use Illuminate\Translation\Translator;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

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
    public function it_registers_translation_namespace_for_module(): void
    {
        $modulePath = $this->tempDir . '/Blog';
        $langDir = $modulePath . '/Lang';
        mkdir($langDir . '/en', 0755, true);
        file_put_contents($langDir . '/en/messages.php', '<?php return ["hello" => "world"];');
        $fileLoader = new FileLoader(new Filesystem(), $langDir);
        $translator = new Translator($fileLoader, 'en');

        (new LangLoader($translator, new Filesystem(), new ModuleLayout()))
            ->load(ModuleFactory::make(name: 'blog', path: $modulePath));

        self::assertArrayHasKey('blog', $fileLoader->namespaces());
        self::assertSame($langDir, $fileLoader->namespaces()['blog']);
    }

    #[Test]
    public function it_returns_early_when_lang_directory_is_missing(): void
    {
        $fileLoader = new FileLoader(new Filesystem(), $this->tempDir);
        $translator = new Translator($fileLoader, 'en');

        (new LangLoader($translator, new Filesystem(), new ModuleLayout()))
            ->load(ModuleFactory::make(path: $this->tempDir . '/Missing'));

        self::assertSame([], $fileLoader->namespaces());
    }
}

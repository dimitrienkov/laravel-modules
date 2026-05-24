<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Tests\Unit\Loaders;

use DimitrienkoV\LaravelModules\Loaders\LangLoader;
use DimitrienkoV\LaravelModules\Support\ModuleLayout;
use DimitrienkoV\LaravelModules\Tests\Support\ModuleFactory;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Translation\FileLoader;
use Illuminate\Translation\Translator;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class LangLoaderTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tempDir = sys_get_temp_dir() . '/laravel-modules-lang-loader-' . bin2hex(random_bytes(6));
        mkdir($this->tempDir, 0755, true);
    }

    protected function tearDown(): void
    {
        $this->deleteDirectory($this->tempDir);

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
    public function it_uses_snake_case_namespace_for_multi_word_module_name(): void
    {
        $modulePath = $this->tempDir . '/UserProfile';
        $langDir = $modulePath . '/Lang';
        mkdir($langDir, 0755, true);
        $fileLoader = new FileLoader(new Filesystem(), $langDir);
        $translator = new Translator($fileLoader, 'en');

        (new LangLoader($translator, new Filesystem(), new ModuleLayout()))
            ->load(ModuleFactory::make(name: 'UserProfile', path: $modulePath));

        self::assertArrayHasKey('user_profile', $fileLoader->namespaces());
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

    private function deleteDirectory(string $directory): void
    {
        if (! is_dir($directory)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($iterator as $fileInfo) {
            if ($fileInfo->isDir()) {
                rmdir($fileInfo->getPathname());

                continue;
            }

            unlink($fileInfo->getPathname());
        }

        rmdir($directory);
    }
}

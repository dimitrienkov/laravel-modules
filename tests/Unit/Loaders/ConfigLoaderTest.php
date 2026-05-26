<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Tests\Unit\Loaders;

use DimitrienkoV\LaravelModules\Loaders\ConfigLoader;
use DimitrienkoV\LaravelModules\Support\ModuleLayout;
use DimitrienkoV\LaravelModules\Tests\Support\ModuleFactory;
use Illuminate\Config\Repository;
use Illuminate\Filesystem\Filesystem;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ConfigLoaderTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tempDir = sys_get_temp_dir() . '/laravel-modules-config-loader-' . bin2hex(random_bytes(6));
        mkdir($this->tempDir, 0755, true);
    }

    protected function tearDown(): void
    {
        (new Filesystem())->deleteDirectory($this->tempDir);

        parent::tearDown();
    }

    #[Test]
    public function it_merges_module_config_files_under_scoped_key(): void
    {
        $modulePath = $this->tempDir . '/Blog';
        mkdir($modulePath . '/Config', 0755, true);
        file_put_contents($modulePath . '/Config/settings.php', '<?php return ["enabled" => true, "nested" => ["new" => true]];');
        $config = new Repository([
            'blog' => [
                'settings' => [
                    'nested' => [
                        'old' => true,
                    ],
                ],
            ],
        ]);

        (new ConfigLoader($config, new Filesystem(), new ModuleLayout()))
            ->load(ModuleFactory::make(name: 'blog', path: $modulePath));

        self::assertSame([
            'nested' => [
                'old' => true,
                'new' => true,
            ],
            'enabled' => true,
        ], $config->get('blog.settings'));
    }

    #[Test]
    public function it_does_not_pollute_global_config_key(): void
    {
        $modulePath = $this->tempDir . '/Blog';
        mkdir($modulePath . '/Config', 0755, true);
        file_put_contents($modulePath . '/Config/cache.php', '<?php return ["driver" => "redis"];');
        $config = new Repository(['cache' => ['driver' => 'file']]);

        (new ConfigLoader($config, new Filesystem(), new ModuleLayout()))
            ->load(ModuleFactory::make(name: 'blog', path: $modulePath));

        self::assertSame('file', $config->get('cache.driver'));
        self::assertSame('redis', $config->get('blog.cache.driver'));
    }

    #[Test]
    public function it_returns_early_when_config_directory_is_missing(): void
    {
        $config = new Repository();

        (new ConfigLoader($config, new Filesystem(), new ModuleLayout()))
            ->load(ModuleFactory::make(path: $this->tempDir . '/Missing'));

        self::assertNull($config->get('blog'));
    }

}

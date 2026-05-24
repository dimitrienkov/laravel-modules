<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Tests\Unit\Loaders;

use DimitrienkoV\LaravelModules\Loaders\PolicyLoader;
use DimitrienkoV\LaravelModules\Support\ModuleLayout;
use DimitrienkoV\LaravelModules\Tests\Support\ModuleFactory;
use Illuminate\Auth\Access\Gate;
use Illuminate\Container\Container;
use Illuminate\Filesystem\Filesystem;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PolicyLoaderTest extends TestCase
{
    private string $tempDir;

    /** @var list<callable> */
    private array $autoloaders = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->tempDir = sys_get_temp_dir() . '/laravel-modules-policy-loader-' . bin2hex(random_bytes(6));
        mkdir($this->tempDir, 0755, true);
    }

    protected function tearDown(): void
    {
        foreach ($this->autoloaders as $autoloader) {
            spl_autoload_unregister($autoloader);
        }

        $this->autoloaders = [];
        $this->deleteDirectory($this->tempDir);

        parent::tearDown();
    }

    #[Test]
    public function it_registers_policy_for_matching_model(): void
    {
        $modulePath = $this->tempDir . '/Blog';
        $policiesDir = $modulePath . '/Domain/Policies';
        $modelsDir = $modulePath . '/Domain/Models';
        mkdir($policiesDir, 0755, true);
        mkdir($modelsDir, 0755, true);
        file_put_contents(
            $modelsDir . '/Post.php',
            '<?php namespace App\\Modules\\Blog\\Domain\\Models; class Post {}',
        );
        file_put_contents(
            $policiesDir . '/PostPolicy.php',
            '<?php namespace App\\Modules\\Blog\\Domain\\Policies; class PostPolicy { public function view($user, $post): bool { return true; } }',
        );
        $this->registerAutoloader($modelsDir . '/Post.php', 'App\\Modules\\Blog\\Domain\\Models\\Post');
        $this->registerAutoloader($policiesDir . '/PostPolicy.php', 'App\\Modules\\Blog\\Domain\\Policies\\PostPolicy');
        $gate = new Gate(new Container(), static fn () => null);

        (new PolicyLoader($gate, new Filesystem(), new ModuleLayout()))
            ->load(ModuleFactory::make(path: $modulePath, namespace: 'App\\Modules\\Blog'));

        $policies = $gate->policies();
        self::assertArrayHasKey('App\\Modules\\Blog\\Domain\\Models\\Post', $policies);
        self::assertSame('App\\Modules\\Blog\\Domain\\Policies\\PostPolicy', $policies['App\\Modules\\Blog\\Domain\\Models\\Post']);
    }

    #[Test]
    public function it_skips_when_model_class_does_not_exist(): void
    {
        $modulePath = $this->tempDir . '/Blog';
        $policiesDir = $modulePath . '/Domain/Policies';
        mkdir($policiesDir, 0755, true);
        file_put_contents(
            $policiesDir . '/CommentPolicy.php',
            '<?php namespace App\\Modules\\Blog\\Domain\\Policies; class CommentPolicy {}',
        );
        $this->registerAutoloader($policiesDir . '/CommentPolicy.php', 'App\\Modules\\Blog\\Domain\\Policies\\CommentPolicy');
        $gate = new Gate(new Container(), static fn () => null);

        (new PolicyLoader($gate, new Filesystem(), new ModuleLayout()))
            ->load(ModuleFactory::make(path: $modulePath, namespace: 'App\\Modules\\Blog'));

        self::assertSame([], $gate->policies());
    }

    #[Test]
    public function it_skips_when_policy_class_does_not_exist(): void
    {
        $modulePath = $this->tempDir . '/Blog';
        $policiesDir = $modulePath . '/Domain/Policies';
        $modelsDir = $modulePath . '/Domain/Models';
        mkdir($policiesDir, 0755, true);
        mkdir($modelsDir, 0755, true);
        file_put_contents(
            $modelsDir . '/Tag.php',
            '<?php namespace App\\Modules\\Blog\\Domain\\Models; class Tag {}',
        );
        $this->registerAutoloader($modelsDir . '/Tag.php', 'App\\Modules\\Blog\\Domain\\Models\\Tag');
        file_put_contents($policiesDir . '/TagPolicy.php', '<?php // no class defined');
        $gate = new Gate(new Container(), static fn () => null);

        (new PolicyLoader($gate, new Filesystem(), new ModuleLayout()))
            ->load(ModuleFactory::make(path: $modulePath, namespace: 'App\\Modules\\Blog'));

        self::assertSame([], $gate->policies());
    }

    #[Test]
    public function it_returns_early_when_policies_directory_is_missing(): void
    {
        $gate = new Gate(new Container(), static fn () => null);

        (new PolicyLoader($gate, new Filesystem(), new ModuleLayout()))
            ->load(ModuleFactory::make(path: $this->tempDir . '/Missing'));

        self::assertSame([], $gate->policies());
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

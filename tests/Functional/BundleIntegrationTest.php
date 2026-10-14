<?php

declare(strict_types=1);

namespace Yuuues\YuuuUi\Tests\Functional;

use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\Kernel;
use Twig\Environment;
use Yuuues\YuuuUi\StylesheetVersion;
use Yuuues\YuuuUi\YuuuUiBundle;

/**
 * Boots a real kernel and COMPILES the container: a unit test calling
 * `load()` never resolves a single reference, and a broken tag or a
 * missing dependency only shows here.
 */
final class BundleIntegrationTest extends TestCase
{
    /** @var list<string> */
    private array $temporary = [];

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->temporary);
        $this->temporary = [];
    }

    public function testTheTwigFunctionRendersTheVersionedUrl(): void
    {
        $kernel = $this->boot(withTwig: true);

        $twig = $kernel->getContainer()->get('test.twig');
        self::assertInstanceOf(Environment::class, $twig);

        $html = $twig->createTemplate('<link rel="stylesheet" href="{{ yuuu_ui_stylesheet() }}">')->render();

        self::assertSame(
            '<link rel="stylesheet" href="/bundles/yuuuui/css/yuuu-base.css?v='.StylesheetVersion::of(YuuuUiBundle::stylesheetPath()).'">',
            $html,
        );
    }

    /** Twig is what the bundle is for, but its absence must not break an app. */
    public function testTheContainerCompilesWithoutTwigBundle(): void
    {
        $kernel = $this->boot(withTwig: false);

        self::assertFalse($kernel->getContainer()->has('twig'));
    }

    /**
     * What a deploy runs: `assets:install public` with no options, which
     * is a hard copy. The published file must be byte for byte the one the
     * fingerprint was computed from.
     */
    public function testAssetsInstallPublishesTheStylesheetWhereTheUrlPoints(): void
    {
        $kernel = $this->boot(withTwig: true);
        $public = $kernel->getProjectDir().'/public';
        mkdir($public, 0o777, true);

        $application = new Application($kernel);
        $tester = new CommandTester($application->find('assets:install'));
        $tester->execute(['target' => $public]);
        restore_exception_handler();

        $tester->assertCommandIsSuccessful();
        $published = $public.'/bundles/yuuuui/css/yuuu-base.css';
        self::assertFileExists($published);
        self::assertFalse(is_link(\dirname($published, 2)), 'Expected a hard copy, not a symlink.');
        self::assertFileEquals(YuuuUiBundle::stylesheetPath(), $published);
    }

    private function boot(bool $withTwig): TestKernel
    {
        $root = sys_get_temp_dir().'/yuuu-ui-kernel-'.bin2hex(random_bytes(6));
        $this->temporary[] = $root;

        $kernel = new TestKernel($root, $withTwig);
        $kernel->boot();

        // FrameworkBundle::boot() installs a global exception handler that
        // PHPUnit reports as "risky" if the test leaves it in place. The
        // error handler is PHPUnit's own and stays.
        restore_exception_handler();

        return $kernel;
    }
}

/** The smallest app that uses the bundle: FrameworkBundle, and TwigBundle or not. */
final class TestKernel extends Kernel
{
    public function __construct(private readonly string $root, private readonly bool $withTwig)
    {
        parent::__construct('test', false);
    }

    public function registerBundles(): iterable
    {
        yield new FrameworkBundle();
        if ($this->withTwig) {
            yield new TwigBundle();
        }
        yield new YuuuUiBundle();
    }

    /** Everything the kernel writes lands in a temporary directory the test removes. */
    public function getProjectDir(): string
    {
        return $this->root;
    }

    public function registerContainerConfiguration(LoaderInterface $loader): void
    {
        $loader->load(function (ContainerBuilder $container): void {
            $container->loadFromExtension('framework', ['secret' => 'test']);

            if ($this->withTwig) {
                // `twig` is private: this public alias exists only so the
                // test can fetch it.
                $container->setAlias('test.twig', 'twig')->setPublic(true);
            }
        });
    }
}

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
use Symfony\Component\ErrorHandler\ErrorRenderer\ErrorRendererInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Kernel;
use Twig\Environment;
use Yuuues\YuuuUi\AssetVersion;
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
        $twig = $this->twig($this->boot(withTwig: true));

        $html = $twig->createTemplate('<link rel="stylesheet" href="{{ yuuu_ui_stylesheet() }}">')->render();

        self::assertSame(
            '<link rel="stylesheet" href="/bundles/yuuuui/css/yuuu-base.css?v='.AssetVersion::of(YuuuUiBundle::stylesheetPath()).'">',
            $html,
        );
    }

    public function testTheScriptFunctionRendersTheVersionedUrl(): void
    {
        $twig = $this->twig($this->boot(withTwig: true));

        $html = $twig->createTemplate('<script src="{{ yuuu_ui_script() }}" defer></script>')->render();

        self::assertSame(
            '<script src="/bundles/yuuuui/js/yuuu-ui.js?v='.AssetVersion::of(YuuuUiBundle::scriptPath()).'" defer></script>',
            $html,
        );
    }

    /**
     * `@YuuuUi` resolves without any configuration, and the template
     * renders with the variables TwigBundle's error renderer passes.
     */
    public function testTheErrorTemplateRendersWithItsEnglishDefaults(): void
    {
        $twig = $this->twig($this->boot(withTwig: true));

        $html = $twig->render('@YuuuUi/error.html.twig', ['status_code' => 404, 'status_text' => 'Not Found']);

        self::assertStringContainsString('<html lang="en">', $html);
        self::assertStringContainsString('<title>Page not found</title>', $html);
        self::assertStringContainsString('<h1>Page not found</h1>', $html);
        self::assertStringContainsString('Error 404', $html);
        self::assertStringContainsString('href="/bundles/yuuuui/css/yuuu-base.css?v='.AssetVersion::of(YuuuUiBundle::stylesheetPath()).'"', $html);
        self::assertStringContainsString('<body class="layout-card">', $html);
        self::assertStringContainsString('href="/"', $html);
        self::assertStringNotContainsString('<script', $html);
    }

    /** Rendered by hand, without the error renderer's variables. */
    public function testTheErrorTemplateDefaultsTo500WithoutVariables(): void
    {
        $html = $this->twig($this->boot(withTwig: true))->render('@YuuuUi/error.html.twig');

        self::assertStringContainsString('<h1>Something went wrong</h1>', $html);
        self::assertStringContainsString('Error 500', $html);
    }

    /** An unknown 4xx falls back to the status text. */
    public function testAnUnlistedClientErrorUsesTheStatusText(): void
    {
        $html = $this->twig($this->boot(withTwig: true))->render('@YuuuUi/error.html.twig', ['status_code' => 410, 'status_text' => 'Gone']);

        self::assertStringContainsString('<h1>Gone</h1>', $html);
    }

    /** The "page expired" pattern of the README: every text replaced by blocks. */
    public function testEveryTextCanBeReplacedByBlocks(): void
    {
        $twig = $this->twig($this->boot(withTwig: true));

        $html = $twig->createTemplate(<<<'TWIG'
            {% extends '@YuuuUi/error.html.twig' %}
            {% block lang %}es{% endblock %}
            {% block error_title %}Página caducada{% endblock %}
            {% block error_code %}{% endblock %}
            {% block error_message %}Vuelve atrás, recarga la página y repite la acción.{% endblock %}
            {% block error_action %}<p><a class="btn btn--primary" href="{{ back_url }}">Volver</a></p>{% endblock %}
            TWIG)->render(['back_url' => '/companies/7']);

        self::assertStringContainsString('<html lang="es">', $html);
        self::assertStringContainsString('<title>Página caducada</title>', $html);
        self::assertStringContainsString('<h1>Página caducada</h1>', $html);
        self::assertStringContainsString('href="/companies/7"', $html);
        self::assertStringNotContainsString('Error 500', $html);
        self::assertStringNotContainsString('Something went wrong', $html);
        self::assertStringNotContainsString('home page', $html);
    }

    /**
     * The README's instructions, end to end: the app's own
     * `templates/bundles/TwigBundle/Exception/error404.html.twig` extends
     * the bundle's page, and TwigBundle's error renderer (debug off, as in
     * production) picks it up for a 404.
     */
    public function testTheErrorRendererUsesTheAppOverrideThatExtendsTheBundlePage(): void
    {
        $kernel = $this->boot(withTwig: true, files: [
            'templates/bundles/TwigBundle/Exception/error404.html.twig' => "{% extends '@YuuuUi/error.html.twig' %}\n{% block error_title %}Nothing here{% endblock %}\n",
        ]);
        $renderer = $kernel->getContainer()->get('test.error_renderer');
        self::assertInstanceOf(ErrorRendererInterface::class, $renderer);

        $html = $renderer->render(new NotFoundHttpException('secret internal detail'))->getAsString();

        self::assertStringContainsString('<h1>Nothing here</h1>', $html);
        self::assertStringContainsString('Error 404', $html);
        self::assertStringContainsString('The page you are looking for does not exist', $html);
        self::assertStringNotContainsString('secret internal detail', $html);
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
    public function testAssetsInstallPublishesBothAssetsWhereTheUrlsPoint(): void
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

        $script = $public.'/bundles/yuuuui/js/yuuu-ui.js';
        self::assertFileExists($script);
        self::assertFileEquals(YuuuUiBundle::scriptPath(), $script);
    }

    private function twig(TestKernel $kernel): Environment
    {
        $twig = $kernel->getContainer()->get('test.twig');
        self::assertInstanceOf(Environment::class, $twig);

        return $twig;
    }

    /** @param array<string, string> $files app files to write before booting, by path */
    private function boot(bool $withTwig, array $files = []): TestKernel
    {
        $root = sys_get_temp_dir().'/yuuu-ui-kernel-'.bin2hex(random_bytes(6));
        $this->temporary[] = $root;

        foreach ($files as $path => $content) {
            (new Filesystem())->dumpFile($root.'/'.$path, $content);
        }

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
                $container->setAlias('test.error_renderer', 'error_renderer.html')->setPublic(true);
            }
        });
    }
}

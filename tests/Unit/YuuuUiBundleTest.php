<?php

declare(strict_types=1);

namespace Yuuues\YuuuUi\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Resource\FileResource;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Yuuues\YuuuUi\AssetVersion;
use Yuuues\YuuuUi\Twig\YuuuUiExtension;
use Yuuues\YuuuUi\YuuuUiBundle;

final class YuuuUiBundleTest extends TestCase
{
    public function testTheExtensionIsRegisteredWithTheVersionsAlreadyComputed(): void
    {
        $builder = $this->load();

        $definition = $builder->getDefinition(YuuuUiExtension::class);

        self::assertSame(
            [AssetVersion::of(YuuuUiBundle::stylesheetPath()), AssetVersion::of(YuuuUiBundle::scriptPath())],
            $definition->getArguments(),
        );
        self::assertTrue($definition->hasTag('twig.extension'));
    }

    /**
     * In debug mode Symfony rebuilds the container when one of its
     * resources changes. Without this, editing an asset would keep
     * serving the old fingerprint until a manual cache:clear.
     */
    public function testBothAssetsAreResourcesOfTheContainer(): void
    {
        $resources = array_map(
            static fn (object $resource): string => (string) $resource,
            array_filter($this->load()->getResources(), static fn (object $resource): bool => $resource instanceof FileResource),
        );

        self::assertContains(realpath(YuuuUiBundle::stylesheetPath()), $resources);
        self::assertContains(realpath(YuuuUiBundle::scriptPath()), $resources);
    }

    /** `assets:install` publishes `<bundle path>/public` under this name. */
    public function testThePublicDirectoryMatchesThePublishedUrls(): void
    {
        $bundle = new YuuuUiBundle();

        self::assertSame(realpath(\dirname(YuuuUiBundle::stylesheetPath(), 3)), realpath($bundle->getPath()));
        self::assertSame(realpath(\dirname(YuuuUiBundle::scriptPath(), 3)), realpath($bundle->getPath()));
        self::assertSame('yuuuui', preg_replace('/bundle$/', '', strtolower($bundle->getName())));
        self::assertSame('/bundles/yuuuui/css/yuuu-base.css', YuuuUiExtension::PATH);
        self::assertSame('/bundles/yuuuui/js/yuuu-ui.js', YuuuUiExtension::SCRIPT_PATH);
    }

    /** TwigBundle registers `<bundle path>/templates` as `@YuuuUi`. */
    public function testTheTemplatesLiveWhereTwigBundleLooks(): void
    {
        self::assertFileExists((new YuuuUiBundle())->getPath().'/templates/error.html.twig');
    }

    private function load(): ContainerBuilder
    {
        $builder = new ContainerBuilder();
        $extension = (new YuuuUiBundle())->getContainerExtension();
        self::assertNotNull($extension);
        $extension->load([], $builder);

        return $builder;
    }
}

<?php

declare(strict_types=1);

namespace Yuuues\YuuuUi\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Resource\FileResource;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Yuuues\YuuuUi\StylesheetVersion;
use Yuuues\YuuuUi\Twig\YuuuUiExtension;
use Yuuues\YuuuUi\YuuuUiBundle;

final class YuuuUiBundleTest extends TestCase
{
    public function testTheExtensionIsRegisteredWithTheVersionAlreadyComputed(): void
    {
        $builder = $this->load();

        $definition = $builder->getDefinition(YuuuUiExtension::class);

        self::assertSame([StylesheetVersion::of(YuuuUiBundle::stylesheetPath())], $definition->getArguments());
        self::assertTrue($definition->hasTag('twig.extension'));
    }

    /**
     * In debug mode Symfony rebuilds the container when one of its
     * resources changes. Without this, editing the stylesheet would keep
     * serving the old fingerprint until a manual cache:clear.
     */
    public function testTheStylesheetIsAResourceOfTheContainer(): void
    {
        $resources = array_map(
            static fn (object $resource): string => (string) $resource,
            array_filter($this->load()->getResources(), static fn (object $resource): bool => $resource instanceof FileResource),
        );

        self::assertContains(realpath(YuuuUiBundle::stylesheetPath()), $resources);
    }

    /** `assets:install` publishes `<bundle path>/public` under this name. */
    public function testThePublicDirectoryMatchesThePublishedUrl(): void
    {
        $bundle = new YuuuUiBundle();

        self::assertSame(realpath(\dirname(YuuuUiBundle::stylesheetPath(), 3)), realpath($bundle->getPath()));
        self::assertSame('yuuuui', preg_replace('/bundle$/', '', strtolower($bundle->getName())));
        self::assertStringStartsWith('/bundles/yuuuui/css/yuuu-base.css', YuuuUiExtension::PATH);
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

<?php

declare(strict_types=1);

namespace Yuuues\YuuuUi;

use Symfony\Component\Config\Resource\FileResource;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;
use Yuuues\YuuuUi\Twig\YuuuUiExtension;

/**
 * Ships `public/css/yuuu-base.css` (published by `assets:install` to
 * `public/bundles/yuuuui/css/yuuu-base.css`) and the Twig function
 * `yuuu_ui_stylesheet()` that links it with a content fingerprint.
 *
 * The fingerprint is computed here, ONCE, when the container is compiled,
 * and stored as a plain string argument of the Twig extension: a request
 * never touches the file. The stylesheet is registered as a resource of
 * the container, so in debug mode editing it rebuilds the container and
 * the fingerprint with it; in production the deploy's cache:clear does.
 *
 * The extension is tagged `twig.extension`. Without TwigBundle the tag is
 * simply ignored and the container still compiles.
 */
final class YuuuUiBundle extends AbstractBundle
{
    /** @param array<string, mixed> $config */
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $stylesheet = self::stylesheetPath();
        $builder->addResource(new FileResource($stylesheet));

        $builder->register(YuuuUiExtension::class, YuuuUiExtension::class)
            ->setArguments([StylesheetVersion::of($stylesheet)])
            ->addTag('twig.extension');
    }

    /** The source file, inside the package. Not the published copy. */
    public static function stylesheetPath(): string
    {
        return \dirname(__DIR__).'/public/css/yuuu-base.css';
    }
}

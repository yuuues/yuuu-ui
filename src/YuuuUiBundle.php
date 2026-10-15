<?php

declare(strict_types=1);

namespace Yuuues\YuuuUi;

use Symfony\Component\Config\Resource\FileResource;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;
use Yuuues\YuuuUi\Twig\YuuuUiExtension;

/**
 * Ships two assets, published by `assets:install` under
 * `public/bundles/yuuuui/`:
 *
 * - `public/css/yuuu-base.css`, linked with `yuuu_ui_stylesheet()`;
 * - `public/js/yuuu-ui.js`, loaded with `yuuu_ui_script()`;
 *
 * and the templates in `templates/` (the error page), which TwigBundle
 * registers by itself under the `@YuuuUi` namespace because the bundle
 * path is the package root.
 *
 * The fingerprints are computed here, ONCE, when the container is
 * compiled, and stored as plain string arguments of the Twig extension: a
 * request never touches the files. Both files are resources of the
 * container, so in debug mode editing one rebuilds the container and the
 * fingerprint with it; in production the deploy's cache:clear does.
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
        $script = self::scriptPath();
        $builder->addResource(new FileResource($stylesheet));
        $builder->addResource(new FileResource($script));

        $builder->register(YuuuUiExtension::class, YuuuUiExtension::class)
            ->setArguments([AssetVersion::of($stylesheet), AssetVersion::of($script)])
            ->addTag('twig.extension');
    }

    /** The source file, inside the package. Not the published copy. */
    public static function stylesheetPath(): string
    {
        return \dirname(__DIR__).'/public/css/yuuu-base.css';
    }

    /** The source file, inside the package. Not the published copy. */
    public static function scriptPath(): string
    {
        return \dirname(__DIR__).'/public/js/yuuu-ui.js';
    }
}

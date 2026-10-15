<?php

declare(strict_types=1);

namespace Yuuues\YuuuUi\Tests\Unit\Twig;

use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Yuuues\YuuuUi\AssetVersion;
use Yuuues\YuuuUi\Twig\YuuuUiExtension;
use Yuuues\YuuuUi\YuuuUiBundle;

final class YuuuUiExtensionTest extends TestCase
{
    public function testTheUrlPointsAtThePublishedAssetWithTheVersion(): void
    {
        self::assertSame(
            '/bundles/yuuuui/css/yuuu-base.css?v=0123456789',
            (new YuuuUiExtension('0123456789', 'abcdefabcd'))->stylesheetUrl(),
        );
    }

    public function testTheScriptUrlPointsAtThePublishedScriptWithItsOwnVersion(): void
    {
        self::assertSame(
            '/bundles/yuuuui/js/yuuu-ui.js?v=abcdefabcd',
            (new YuuuUiExtension('0123456789', 'abcdefabcd'))->scriptUrl(),
        );
    }

    public function testTheUrlsChangeWithTheirVersion(): void
    {
        self::assertNotSame(
            (new YuuuUiExtension('aaaaaaaaaa', 'cccccccccc'))->stylesheetUrl(),
            (new YuuuUiExtension('bbbbbbbbbb', 'cccccccccc'))->stylesheetUrl(),
        );
        self::assertNotSame(
            (new YuuuUiExtension('aaaaaaaaaa', 'cccccccccc'))->scriptUrl(),
            (new YuuuUiExtension('aaaaaaaaaa', 'dddddddddd'))->scriptUrl(),
        );
    }

    /** Built the 0.2 way, with one argument: the script still gets its fingerprint. */
    public function testWithoutAScriptVersionItIsComputedFromThePackagedFile(): void
    {
        self::assertSame(
            '/bundles/yuuuui/js/yuuu-ui.js?v='.AssetVersion::of(YuuuUiBundle::scriptPath()),
            (new YuuuUiExtension('0123456789'))->scriptUrl(),
        );
    }

    public function testItRendersInsideAttributesWithoutEscapingDamage(): void
    {
        $twig = new Environment(new ArrayLoader([
            'layout' => '<link rel="stylesheet" href="{{ yuuu_ui_stylesheet() }}"><script src="{{ yuuu_ui_script() }}" defer></script>',
        ]), ['autoescape' => 'html']);
        $twig->addExtension(new YuuuUiExtension('0123456789', 'abcdefabcd'));

        self::assertSame(
            '<link rel="stylesheet" href="/bundles/yuuuui/css/yuuu-base.css?v=0123456789"><script src="/bundles/yuuuui/js/yuuu-ui.js?v=abcdefabcd" defer></script>',
            $twig->render('layout'),
        );
    }
}

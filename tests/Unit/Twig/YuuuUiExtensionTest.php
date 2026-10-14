<?php

declare(strict_types=1);

namespace Yuuues\YuuuUi\Tests\Unit\Twig;

use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Yuuues\YuuuUi\Twig\YuuuUiExtension;

final class YuuuUiExtensionTest extends TestCase
{
    public function testTheUrlPointsAtThePublishedAssetWithTheVersion(): void
    {
        self::assertSame(
            '/bundles/yuuuui/css/yuuu-base.css?v=0123456789',
            (new YuuuUiExtension('0123456789'))->stylesheetUrl(),
        );
    }

    public function testTheUrlChangesWithTheVersion(): void
    {
        self::assertNotSame(
            (new YuuuUiExtension('aaaaaaaaaa'))->stylesheetUrl(),
            (new YuuuUiExtension('bbbbbbbbbb'))->stylesheetUrl(),
        );
    }

    public function testItRendersInsideAnHrefWithoutEscapingDamage(): void
    {
        $twig = new Environment(new ArrayLoader([
            'layout' => '<link rel="stylesheet" href="{{ yuuu_ui_stylesheet() }}">',
        ]), ['autoescape' => 'html']);
        $twig->addExtension(new YuuuUiExtension('0123456789'));

        self::assertSame(
            '<link rel="stylesheet" href="/bundles/yuuuui/css/yuuu-base.css?v=0123456789">',
            $twig->render('layout'),
        );
    }
}

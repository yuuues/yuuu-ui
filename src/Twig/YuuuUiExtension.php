<?php

declare(strict_types=1);

namespace Yuuues\YuuuUi\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * `{{ yuuu_ui_stylesheet() }}`: the URL of the published base stylesheet,
 * with its content fingerprint.
 *
 * The URL is root-relative and fixed: it is where `assets:install public`
 * puts the file. The fingerprint arrives already computed (see
 * YuuuUiBundle), so rendering is a string concatenation.
 */
final class YuuuUiExtension extends AbstractExtension
{
    public const string PATH = '/bundles/yuuuui/css/yuuu-base.css';

    public function __construct(private readonly string $version)
    {
    }

    /** @return list<TwigFunction> */
    public function getFunctions(): array
    {
        return [new TwigFunction('yuuu_ui_stylesheet', $this->stylesheetUrl(...))];
    }

    public function stylesheetUrl(): string
    {
        return self::PATH.'?v='.$this->version;
    }
}

<?php

declare(strict_types=1);

namespace Yuuues\YuuuUi\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;
use Yuuues\YuuuUi\AssetVersion;
use Yuuues\YuuuUi\YuuuUiBundle;

/**
 * `{{ yuuu_ui_stylesheet() }}` and `{{ yuuu_ui_script() }}`: the URLs of
 * the published assets, with their content fingerprints.
 *
 * The URLs are root-relative and fixed: they are where `assets:install
 * public` puts the files. The fingerprints arrive already computed (see
 * YuuuUiBundle), so rendering is a string concatenation.
 */
final class YuuuUiExtension extends AbstractExtension
{
    public const string PATH = '/bundles/yuuuui/css/yuuu-base.css';

    public const string SCRIPT_PATH = '/bundles/yuuuui/js/yuuu-ui.js';

    /**
     * @param ?string $scriptVersion optional only so that code written for
     *                               0.2 (one argument) keeps working; the
     *                               bundle always passes it
     */
    public function __construct(
        private readonly string $version,
        private ?string $scriptVersion = null,
    ) {
    }

    /** @return list<TwigFunction> */
    public function getFunctions(): array
    {
        return [
            new TwigFunction('yuuu_ui_stylesheet', $this->stylesheetUrl(...)),
            new TwigFunction('yuuu_ui_script', $this->scriptUrl(...)),
        ];
    }

    public function stylesheetUrl(): string
    {
        return self::PATH.'?v='.$this->version;
    }

    public function scriptUrl(): string
    {
        $this->scriptVersion ??= AssetVersion::of(YuuuUiBundle::scriptPath());

        return self::SCRIPT_PATH.'?v='.$this->scriptVersion;
    }
}

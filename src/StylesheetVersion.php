<?php

declare(strict_types=1);

namespace Yuuues\YuuuUi;

/**
 * The fingerprint of the stylesheet. Kept so that 0.2 code calling it
 * keeps working: since 0.3 the same code fingerprints the script too, and
 * lives in AssetVersion.
 *
 * @deprecated since 0.3, use AssetVersion::of()
 */
final class StylesheetVersion
{
    public const int LENGTH = AssetVersion::LENGTH;

    public static function of(string $path): string
    {
        return AssetVersion::of($path);
    }
}

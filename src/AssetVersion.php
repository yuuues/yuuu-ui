<?php

declare(strict_types=1);

namespace Yuuues\YuuuUi;

/**
 * The short fingerprint of an asset's content (the stylesheet, the
 * script), used as `?v=` to bust browser caches on every release that
 * changes the file, and only on those.
 *
 * xxh3 and not a cryptographic hash: nothing here is a secret or a trust
 * decision, it only has to change when the bytes change. Ten hex digits
 * (40 bits) leave a collision between two releases out of the question.
 */
final class AssetVersion
{
    public const int LENGTH = 10;

    public static function of(string $path): string
    {
        $hash = is_file($path) ? hash_file('xxh3', $path) : false;

        // A failure here happens at container compile time, on the
        // developer's machine or in the deploy, never on a request. A
        // missing file means a broken package: better loud than an
        // unstyled site.
        if (false === $hash) {
            throw new \LogicException(\sprintf('yuuu-ui: cannot read the asset at "%s".', $path));
        }

        return substr($hash, 0, self::LENGTH);
    }
}

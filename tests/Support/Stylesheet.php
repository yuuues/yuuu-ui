<?php

declare(strict_types=1);

namespace Yuuues\YuuuUi\Tests\Support;

use Yuuues\YuuuUi\YuuuUiBundle;

/**
 * Reads the real stylesheet for the tests: its source and the tokens it
 * declares. Colour values and contrast live in the shipped
 * Yuuues\YuuuUi\Contrast, which apps use too.
 */
final class Stylesheet
{
    /** The base stylesheet, or any other file given (tokens.css). */
    public static function source(?string $path = null): string
    {
        $path ??= YuuuUiBundle::stylesheetPath();
        $css = is_file($path) ? file_get_contents($path) : false;

        if (false === $css) {
            throw new \RuntimeException('Cannot read the stylesheet: '.$path);
        }

        return $css;
    }

    /** The source without comments, so that prose cannot fake or hide a rule. */
    public static function code(?string $path = null): string
    {
        return (string) preg_replace('#/\*.*?\*/#s', '', self::source($path));
    }

    /**
     * Every custom property declared in the light `:root` block, whatever
     * its value.
     *
     * @return list<string>
     */
    public static function declaredTokens(?string $path = null): array
    {
        if (1 !== preg_match('/^:root\s*\{(.*?)^\}/ms', self::code($path), $block)) {
            throw new \RuntimeException('Cannot find the :root block.');
        }

        preg_match_all('/^\s*(--[a-z0-9-]+)\s*:/m', $block[1], $names);

        return $names[1];
    }

    /** @return list<string> the tokens redefined in dark mode, colours or not */
    public static function darkTokens(?string $path = null): array
    {
        if (1 !== preg_match('/@media\s*\(prefers-color-scheme:\s*dark\)\s*\{\s*:root\s*\{(.*?)^    \}/ms', self::code($path), $block)) {
            throw new \RuntimeException('Cannot find the dark :root block.');
        }

        preg_match_all('/^\s*(--[a-z0-9-]+)\s*:/m', $block[1], $names);

        return $names[1];
    }
}

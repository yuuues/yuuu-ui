<?php

declare(strict_types=1);

namespace Yuuues\YuuuUi\Tests\Support;

use Yuuues\YuuuUi\YuuuUiBundle;

/**
 * Reads the real stylesheet for the tests: its source, its colour tokens
 * per colour scheme, and the WCAG 2.x contrast between two of them.
 */
final class Stylesheet
{
    public static function source(): string
    {
        $css = file_get_contents(YuuuUiBundle::stylesheetPath());

        if (false === $css) {
            throw new \RuntimeException('Cannot read the stylesheet.');
        }

        return $css;
    }

    /** The source without comments, so that prose cannot fake or hide a rule. */
    public static function code(): string
    {
        return (string) preg_replace('#/\*.*?\*/#s', '', self::source());
    }

    /**
     * The `#rrggbb` tokens of one colour scheme. Dark mode inherits every
     * light token it does not redefine, exactly like the browser does.
     *
     * @return array<string, string>
     */
    public static function colors(string $mode): array
    {
        $colors = self::declarations('/^:root\s*\{(.*?)^\}/ms');

        if ('dark' === $mode) {
            $colors = array_merge($colors, self::declarations('/@media\s*\(prefers-color-scheme:\s*dark\)\s*\{\s*:root\s*\{(.*?)^    \}/ms'));
        }

        return $colors;
    }

    /**
     * Every custom property declared in the light `:root` block, whatever
     * its value.
     *
     * @return list<string>
     */
    public static function declaredTokens(): array
    {
        if (1 !== preg_match('/^:root\s*\{(.*?)^\}/ms', self::code(), $block)) {
            throw new \RuntimeException('Cannot find the :root block.');
        }

        preg_match_all('/^\s*(--[a-z0-9-]+)\s*:/m', $block[1], $names);

        return $names[1];
    }

    /** @return list<string> the tokens redefined in dark mode, colours or not */
    public static function darkTokens(): array
    {
        if (1 !== preg_match('/@media\s*\(prefers-color-scheme:\s*dark\)\s*\{\s*:root\s*\{(.*?)^    \}/ms', self::code(), $block)) {
            throw new \RuntimeException('Cannot find the dark :root block.');
        }

        preg_match_all('/^\s*(--[a-z0-9-]+)\s*:/m', $block[1], $names);

        return $names[1];
    }

    public static function contrast(string $a, string $b): float
    {
        $la = self::luminance($a);
        $lb = self::luminance($b);

        return (max($la, $lb) + 0.05) / (min($la, $lb) + 0.05);
    }

    /** WCAG 2.x relative luminance of a `#rrggbb` colour. */
    public static function luminance(string $hex): float
    {
        if (1 !== preg_match('/^#[0-9a-f]{6}$/i', $hex)) {
            throw new \InvalidArgumentException(\sprintf('Not a #rrggbb colour: %s', $hex));
        }

        $channels = array_map(
            static function (string $pair): float {
                $value = hexdec($pair) / 255;

                return $value <= 0.04045 ? $value / 12.92 : (($value + 0.055) / 1.055) ** 2.4;
            },
            str_split(substr($hex, 1), 2),
        );

        return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
    }

    /** @return array<string, string> */
    private static function declarations(string $blockPattern): array
    {
        if (1 !== preg_match($blockPattern, self::code(), $block)) {
            throw new \RuntimeException('Cannot find the tokens block: '.$blockPattern);
        }

        preg_match_all('/(--[a-z0-9-]+)\s*:\s*(#[0-9a-fA-F]{6})\s*;/', $block[1], $pairs, \PREG_SET_ORDER);

        $colors = [];
        foreach ($pairs as $pair) {
            $colors[$pair[1]] = strtolower($pair[2]);
        }

        return $colors;
    }
}

<?php

declare(strict_types=1);

namespace Yuuues\YuuuUi;

/**
 * The contrast tiers of yuuu-ui (README §7), for the bundle's own tests
 * and for an app's test of its brand override (README §4):
 *
 * - reading text, 7:1 (WCAG 1.4.6, AAA);
 * - secondary text, 4.5:1 (WCAG 1.4.3, AA): muted metadata, and the text
 *   of a button under its hover and pressed `brightness()` filters;
 * - non-text, 3:1 (WCAG 1.4.11): control outlines, the focus ring, markers.
 *
 * Every pair below is a pair the base stylesheet paints together. The
 * colours come from the `#rrggbb` tokens of the stylesheets, laid over
 * each other as the browser cascades them.
 */
final class Contrast
{
    public const float READING = 7.0;

    public const float SECONDARY = 4.5;

    public const float NON_TEXT = 3.0;

    /**
     * Text that is read to work.
     *
     * @var list<array{string, string}> foreground, background
     */
    public const array READING_PAIRS = [
        // Body text, links, headings, table cells, field values, the nav,
        // .btn--secondary (transparent: on a card, on the page, and on
        // the page colour when hovered). The .modal (text on surface;
        // the backdrop carries no text), the active .pagination__link and
        // the current .breadcrumb item (on the page or on a card).
        ['--yuuu-color-text', '--yuuu-color-bg'],
        ['--yuuu-color-text', '--yuuu-color-surface'],
        // .field-error under a field, on a card or on the page.
        ['--yuuu-color-danger-text', '--yuuu-color-bg'],
        ['--yuuu-color-danger-text', '--yuuu-color-surface'],
        // Badges and flashes: their own text on their own background.
        ['--yuuu-color-success-text', '--yuuu-color-success-bg'],
        ['--yuuu-color-warning-text', '--yuuu-color-warning-bg'],
        ['--yuuu-color-danger-text', '--yuuu-color-danger-bg'],
        ['--yuuu-color-info-text', '--yuuu-color-info-bg'],
        ['--yuuu-color-neutral-text', '--yuuu-color-neutral-bg'],
        // A link (or a .btn--secondary) inside a flash keeps the neutral
        // text colour on the flash background.
        ['--yuuu-color-text', '--yuuu-color-success-bg'],
        ['--yuuu-color-text', '--yuuu-color-warning-bg'],
        ['--yuuu-color-text', '--yuuu-color-danger-bg'],
        ['--yuuu-color-text', '--yuuu-color-info-bg'],
        ['--yuuu-color-text', '--yuuu-color-neutral-bg'],
        // .btn / .btn--primary.
        ['--yuuu-color-primary-text', '--yuuu-color-primary'],
        // Not painted by the base: the pair offered to the apps.
        ['--yuuu-color-accent-text', '--yuuu-color-accent'],
        // .btn--danger.
        ['--yuuu-color-danger-solid-text', '--yuuu-color-danger-solid'],
        // The accent family: the accent as text on the page, on a card and
        // on its own tinted background (the current .side-nav__link, the
        // .avatar initials).
        ['--yuuu-color-accent-ink', '--yuuu-color-bg'],
        ['--yuuu-color-accent-ink', '--yuuu-color-surface'],
        ['--yuuu-color-accent-ink', '--yuuu-color-accent-soft'],
        // Not painted by the base: body text on the tinted background, a
        // pair of the override contract (README §4).
        ['--yuuu-color-text', '--yuuu-color-accent-soft'],
    ];

    /**
     * Text that is not needed to work.
     *
     * @var list<array{string, string}> foreground, background
     */
    public const array SECONDARY_PAIRS = [
        // Muted metadata (T-180): .muted, table headers, dt, .empty-state,
        // link hover, the file input's label, .stat__label and
        // .side-nav__group. .breadcrumb links and separators,
        // .pagination__current and the dead end of a .pagination (not a
        // control, but kept readable on purpose).
        ['--yuuu-color-text-muted', '--yuuu-color-bg'],
        ['--yuuu-color-text-muted', '--yuuu-color-surface'],
    ];

    /**
     * Text under the `filter: brightness()` of `.btn:hover` (0.92) and
     * `.btn:active` (0.85), at the secondary tier. The filter dims the
     * whole button, text included; when the button has its own background
     * the filter dims it too. `.btn--secondary` is transparent and only
     * filtered while pressed without the pointer on it (its hover rule
     * resets the filter), so its text is dimmed over an undimmed page.
     *
     * @var list<array{string, string, float, bool}> foreground, background, factor, background dimmed
     */
    public const array FILTERED_PAIRS = [
        ['--yuuu-color-primary-text', '--yuuu-color-primary', 0.92, true],
        ['--yuuu-color-primary-text', '--yuuu-color-primary', 0.85, true],
        ['--yuuu-color-danger-solid-text', '--yuuu-color-danger-solid', 0.92, true],
        ['--yuuu-color-danger-solid-text', '--yuuu-color-danger-solid', 0.85, true],
        ['--yuuu-color-text', '--yuuu-color-surface', 0.85, false],
        ['--yuuu-color-text', '--yuuu-color-bg', 0.85, false],
    ];

    /**
     * Non-text: what must be seen, not read.
     *
     * @var list<array{string, string}> foreground, background
     */
    public const array CONTROL_PAIRS = [
        // Field borders, the button ring, the ring of .pagination__link.
        ['--yuuu-color-border-strong', '--yuuu-color-surface'],
        ['--yuuu-color-border-strong', '--yuuu-color-bg'],
        // The invalid field border.
        ['--yuuu-color-danger-text', '--yuuu-color-surface'],
        ['--yuuu-color-danger-text', '--yuuu-color-bg'],
        // The danger button has no ring: its background is its edge.
        ['--yuuu-color-danger-solid', '--yuuu-color-surface'],
        ['--yuuu-color-danger-solid', '--yuuu-color-bg'],
        // The focus ring (on a focused .modal itself it is drawn inside
        // the edge: on the surface, never on the backdrop).
        ['--yuuu-color-text', '--yuuu-color-surface'],
        ['--yuuu-color-text', '--yuuu-color-bg'],
        // The accent as a marker (a rule, a dot, an icon) on a card.
        ['--yuuu-color-accent-ink', '--yuuu-color-surface'],
    ];

    /**
     * The `#rrggbb` tokens of one colour scheme, `light` or `dark`, of
     * stylesheets linked in this order (the base first, then the app's
     * layer). Each file's light `:root` applies in both schemes and its
     * `@media (prefers-color-scheme: dark) { :root { ... } }` in dark
     * only, exactly as the browser cascades them.
     *
     * @return array<string, string>
     */
    public static function colors(string $mode, string ...$stylesheets): array
    {
        if (!\in_array($mode, ['light', 'dark'], true)) {
            throw new \InvalidArgumentException(\sprintf('Unknown mode "%s": use light or dark.', $mode));
        }

        $colors = [];
        foreach ($stylesheets as $path) {
            $css = is_file($path) ? file_get_contents($path) : false;
            if (false === $css) {
                throw new \RuntimeException('Cannot read the stylesheet: '.$path);
            }

            $code = (string) preg_replace('#/\*.*?\*/#s', '', $css);
            $light = self::tokens('/^:root\s*\{([^}]*)\}/m', $code);
            $dark = self::tokens('/@media\s*\(prefers-color-scheme:\s*dark\)\s*\{\s*:root\s*\{([^}]*)\}/', $code);
            if ([] === $light && [] === $dark) {
                throw new \RuntimeException('No #rrggbb colour token found in '.$path);
            }

            $colors = array_merge($colors, $light, 'dark' === $mode ? $dark : []);
        }

        return $colors;
    }

    /**
     * Every pair of every tier that misses its minimum, as readable
     * lines: an empty list means the colours pass.
     *
     * @param array<string, string> $colors as returned by colors()
     *
     * @return list<string>
     */
    public static function failures(array $colors): array
    {
        $failures = [];
        $check = static function (string $what, string $text, string $behind, float $minimum) use (&$failures): void {
            $ratio = self::ratio($text, $behind);
            if ($ratio < $minimum) {
                $failures[] = \sprintf('%s: %.2f:1 (needs %.1f)', $what, $ratio, $minimum);
            }
        };

        foreach ([[self::READING_PAIRS, self::READING], [self::SECONDARY_PAIRS, self::SECONDARY], [self::CONTROL_PAIRS, self::NON_TEXT]] as [$pairs, $minimum]) {
            foreach ($pairs as [$foreground, $background]) {
                $check($foreground.' on '.$background, self::color($colors, $foreground), self::color($colors, $background), $minimum);
            }
        }

        foreach (self::FILTERED_PAIRS as [$foreground, $background, $factor, $backgroundDimmed]) {
            $behind = self::color($colors, $background);
            $check(
                \sprintf('%s on %s at brightness(%s)', $foreground, $background, $factor),
                self::brightness(self::color($colors, $foreground), $factor),
                $backgroundDimmed ? self::brightness($behind, $factor) : $behind,
                self::SECONDARY,
            );
        }

        return $failures;
    }

    /** The WCAG 2.x contrast ratio of two `#rrggbb` colours, from 1 to 21. */
    public static function ratio(string $a, string $b): float
    {
        $la = self::luminance($a);
        $lb = self::luminance($b);

        return (max($la, $lb) + 0.05) / (min($la, $lb) + 0.05);
    }

    /**
     * What CSS `filter: brightness($factor)` makes of a `#rrggbb` colour:
     * every sRGB channel multiplied by the factor, as browsers apply it.
     */
    public static function brightness(string $hex, float $factor): string
    {
        self::luminance($hex);

        return '#'.implode('', array_map(
            static fn (string $pair): string => \sprintf('%02x', min(255, (int) round(hexdec($pair) * $factor))),
            str_split(substr($hex, 1), 2),
        ));
    }

    /** WCAG 2.x relative luminance of a `#rrggbb` colour. */
    private static function luminance(string $hex): float
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

    /** @param array<string, string> $colors */
    private static function color(array $colors, string $token): string
    {
        return $colors[$token] ?? throw new \InvalidArgumentException(\sprintf('No #rrggbb value for %s.', $token));
    }

    /** @return array<string, string> */
    private static function tokens(string $blockPattern, string $code): array
    {
        if (1 !== preg_match($blockPattern, $code, $block)) {
            return [];
        }

        preg_match_all('/(--[a-z0-9-]+)\s*:\s*(#[0-9a-fA-F]{6})\s*;/', $block[1], $pairs, \PREG_SET_ORDER);

        $colors = [];
        foreach ($pairs as $pair) {
            $colors[$pair[1]] = strtolower($pair[2]);
        }

        return $colors;
    }
}

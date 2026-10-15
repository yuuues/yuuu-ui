<?php

declare(strict_types=1);

namespace Yuuues\YuuuUi\Tests\Unit\Styles;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Yuuues\YuuuUi\Tests\Support\Stylesheet;

/**
 * Contrast over the real token values, in light and in dark mode.
 *
 * Policy: WCAG AA is required, WCAG AAA is targeted where reasonable.
 *
 * - 1.4.3 (AA): text reaches 4.5:1 against what it is painted on, at rest
 *   and under the hover and pressed filters of `.btn`. Hard failure.
 * - 1.4.6 (AAA, 7:1) is a goal for the default values, not a gate: the
 *   README lists which pairs reach it. Lowering a default below 7:1 is a
 *   design decision to take on purpose, not something this test forbids.
 * - 1.4.11 (AA, no AAA level exists): the outline of a control (the
 *   border of a field, the ring of a button, the invalid border, the
 *   focus ring) reaches 3:1 against the surface and the page.
 *
 * Every pair below is a pair the stylesheet actually paints together, and
 * testEveryPaintedColourIsInATextPair() fails when a `color:` or a
 * `background:` token appears in the CSS without a pair here. The brand
 * tokens are checked with their DEFAULT values only: an app that
 * overrides them answers for its own pair.
 *
 * Not checked, on purpose: disabled controls (exempt in WCAG 1.4.3 and
 * 1.4.6; the stylesheet dims them to 60% opacity so they read as
 * unavailable), placeholders (not styled: the browser default applies),
 * the `.card` accent stripe (an ornament).
 */
final class StylesheetContrastTest extends TestCase
{
    private const float TEXT_MINIMUM = 4.5;

    private const float NON_TEXT_MINIMUM = 3.0;

    /** @var list<array{string, string}> foreground, background */
    private const array TEXT_PAIRS = [
        // Body text, links, headings, table cells, field values, the nav,
        // .btn--secondary (transparent: on a card, on the page, and on
        // the page colour when hovered). The .modal (text on surface;
        // the backdrop carries no text), the active .pagination__link and
        // the current .breadcrumb item (on the page or on a card).
        ['--yuuu-color-text', '--yuuu-color-bg'],
        ['--yuuu-color-text', '--yuuu-color-surface'],
        // .muted, table headers, dt, .empty-state, link hover, the file
        // input's label. .breadcrumb links and separators,
        // .pagination__current and the dead end of a .pagination (not a
        // control, but kept readable on purpose).
        ['--yuuu-color-text-muted', '--yuuu-color-bg'],
        ['--yuuu-color-text-muted', '--yuuu-color-surface'],
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
    ];

    /**
     * `.btn:hover` and `.btn:active` dim the whole button, text included,
     * with `filter: brightness()`. When the button has its own background
     * the filter dims it too; `.btn--secondary` is transparent and only
     * filtered while pressed without the pointer on it (its hover rule
     * resets the filter), so its text is dimmed over an undimmed page.
     *
     * @var list<array{string, string, float, bool}> foreground, background, factor, background dimmed
     */
    private const array FILTERED_TEXT_PAIRS = [
        ['--yuuu-color-primary-text', '--yuuu-color-primary', 0.92, true],
        ['--yuuu-color-primary-text', '--yuuu-color-primary', 0.85, true],
        ['--yuuu-color-danger-solid-text', '--yuuu-color-danger-solid', 0.92, true],
        ['--yuuu-color-danger-solid-text', '--yuuu-color-danger-solid', 0.85, true],
        ['--yuuu-color-text', '--yuuu-color-surface', 0.85, false],
        ['--yuuu-color-text', '--yuuu-color-bg', 0.85, false],
    ];

    /** @var list<array{string, string}> foreground, background */
    private const array CONTROL_PAIRS = [
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
    ];

    /** @return iterable<string, array{string}> */
    public static function modes(): iterable
    {
        yield 'light' => ['light'];
        yield 'dark' => ['dark'];
    }

    #[DataProvider('modes')]
    public function testTextReachesAa(string $mode): void
    {
        $colors = Stylesheet::colors($mode);
        $failures = [];

        foreach (self::TEXT_PAIRS as [$foreground, $background]) {
            $ratio = Stylesheet::contrast(self::color($colors, $foreground), self::color($colors, $background));
            if ($ratio < self::TEXT_MINIMUM) {
                $failures[] = \sprintf('%s on %s in %s: %.2f:1', $foreground, $background, $mode, $ratio);
            }
        }

        self::assertSame([], $failures, 'Text below 4.5:1 (WCAG 1.4.3).');
    }

    #[DataProvider('modes')]
    public function testTextReachesAaUnderTheButtonFilters(string $mode): void
    {
        $colors = Stylesheet::colors($mode);
        $failures = [];

        foreach (self::FILTERED_TEXT_PAIRS as [$foreground, $background, $factor, $backgroundDimmed]) {
            $text = Stylesheet::brightness(self::color($colors, $foreground), $factor);
            $behind = self::color($colors, $background);
            if ($backgroundDimmed) {
                $behind = Stylesheet::brightness($behind, $factor);
            }

            $ratio = Stylesheet::contrast($text, $behind);
            if ($ratio < self::TEXT_MINIMUM) {
                $failures[] = \sprintf('%s on %s at brightness(%s) in %s: %.2f:1', $foreground, $background, $factor, $mode, $ratio);
            }
        }

        self::assertSame([], $failures, 'Text below 4.5:1 (WCAG 1.4.3) on a hovered or pressed button.');
    }

    #[DataProvider('modes')]
    public function testControlOutlinesReachThreeToOne(string $mode): void
    {
        $colors = Stylesheet::colors($mode);
        $failures = [];

        foreach (self::CONTROL_PAIRS as [$foreground, $background]) {
            $ratio = Stylesheet::contrast(self::color($colors, $foreground), self::color($colors, $background));
            if ($ratio < self::NON_TEXT_MINIMUM) {
                $failures[] = \sprintf('%s on %s in %s: %.2f:1', $foreground, $background, $mode, $ratio);
            }
        }

        self::assertSame([], $failures, 'Control outline below 3:1 (WCAG 1.4.11).');
    }

    /**
     * Guards the lists above: every token the CSS paints as a text colour
     * is a foreground of some text pair, and every token it paints as a
     * background is the background of some text pair.
     */
    public function testEveryPaintedColourIsInATextPair(): void
    {
        $code = Stylesheet::code();
        preg_match_all('/(?<![a-z-])color\s*:\s*var\(\s*(--[a-z0-9-]+)\s*\)/', $code, $foregrounds);
        preg_match_all('/(?<![a-z-])background(?:-color)?\s*:\s*var\(\s*(--[a-z0-9-]+)\s*\)/', $code, $backgrounds);

        self::assertNotSame([], $foregrounds[1]);
        self::assertNotSame([], $backgrounds[1]);
        self::assertSame([], array_values(array_diff(array_unique($foregrounds[1]), array_column(self::TEXT_PAIRS, 0))), 'Text colours without a pair.');
        self::assertSame([], array_values(array_diff(array_unique($backgrounds[1]), array_column(self::TEXT_PAIRS, 1))), 'Backgrounds without a pair.');
    }

    /** Every brightness() filter in the CSS is checked above. */
    public function testEveryButtonFilterIsChecked(): void
    {
        preg_match_all('/brightness\(\s*([0-9.]+)\s*\)/', Stylesheet::code(), $filters);
        $checked = array_column(self::FILTERED_TEXT_PAIRS, 2);

        self::assertNotSame([], $filters[1]);
        foreach (array_unique($filters[1]) as $factor) {
            self::assertContains((float) $factor, $checked, \sprintf('brightness(%s) is not checked.', $factor));
        }
    }

    /**
     * Guards the test itself: if the parser stopped finding the dark
     * block, dark mode would silently be checked with the light values.
     */
    public function testDarkModeRedefinesEveryColourToken(): void
    {
        $light = array_keys(Stylesheet::colors('light'));
        $dark = Stylesheet::darkTokens();

        self::assertGreaterThan(20, \count($light));
        self::assertSame([], array_values(array_diff($light, $dark)), 'Colour tokens without a dark value.');
    }

    /** The formulas, against reference values. */
    public function testTheContrastFormula(): void
    {
        self::assertEqualsWithDelta(21.0, Stylesheet::contrast('#000000', '#ffffff'), 0.001);
        self::assertEqualsWithDelta(1.0, Stylesheet::contrast('#777777', '#777777'), 0.001);
        self::assertEqualsWithDelta(4.48, Stylesheet::contrast('#777777', '#ffffff'), 0.01);
        self::assertSame('#d9d9d9', Stylesheet::brightness('#ffffff', 0.85));
        self::assertSame('#000000', Stylesheet::brightness('#000000', 0.5));
    }

    /** @param array<string, string> $colors */
    private static function color(array $colors, string $token): string
    {
        self::assertArrayHasKey($token, $colors);

        return $colors[$token];
    }
}

<?php

declare(strict_types=1);

namespace Yuuues\YuuuUi\Tests\Unit\Styles;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Yuuues\YuuuUi\Tests\Support\Stylesheet;

/**
 * WCAG AA over the real token values, in light and in dark mode.
 *
 * - 1.4.3: text reaches 4.5:1 against what it is painted on.
 * - 1.4.11: the outline of a control (the border of a field, the ring of
 *   a button, the invalid border, the focus ring) reaches 3:1 against
 *   the backgrounds controls are painted on: the surface (cards, the nav)
 *   and the page.
 *
 * Every pair below is a pair the stylesheet actually paints together. A
 * new foreground/background combination in the CSS needs its line here.
 * The brand tokens are checked with their DEFAULT values only: an app
 * that overrides them answers for its own pair.
 */
final class StylesheetContrastTest extends TestCase
{
    private const array TEXT_PAIRS = [
        // Body text, links, headings, table cells, field values.
        ['--yuuu-color-text', '--yuuu-color-bg'],
        ['--yuuu-color-text', '--yuuu-color-surface'],
        // .muted, table headers, dt, .empty-state, link hover.
        ['--yuuu-color-text-muted', '--yuuu-color-bg'],
        ['--yuuu-color-text-muted', '--yuuu-color-surface'],
        // .field-error under a field, on a card or on the page.
        ['--yuuu-color-danger-text', '--yuuu-color-bg'],
        ['--yuuu-color-danger-text', '--yuuu-color-surface'],
        // Badges and flashes.
        ['--yuuu-color-success-text', '--yuuu-color-success-bg'],
        ['--yuuu-color-warning-text', '--yuuu-color-warning-bg'],
        ['--yuuu-color-danger-text', '--yuuu-color-danger-bg'],
        ['--yuuu-color-info-text', '--yuuu-color-info-bg'],
        ['--yuuu-color-neutral-text', '--yuuu-color-neutral-bg'],
        // Buttons.
        ['--yuuu-color-primary-text', '--yuuu-color-primary'],
        ['--yuuu-color-accent-text', '--yuuu-color-accent'],
        // (.btn--secondary is text on surface, or on bg when hovered.)
        ['--yuuu-color-danger-solid-text', '--yuuu-color-danger-solid'],
    ];

    private const array CONTROL_PAIRS = [
        // Field borders and the button ring.
        ['--yuuu-color-border-strong', '--yuuu-color-surface'],
        ['--yuuu-color-border-strong', '--yuuu-color-bg'],
        // The invalid field border.
        ['--yuuu-color-danger-text', '--yuuu-color-surface'],
        ['--yuuu-color-danger-text', '--yuuu-color-bg'],
        // The danger button has no ring: its background is its edge.
        ['--yuuu-color-danger-solid', '--yuuu-color-surface'],
        ['--yuuu-color-danger-solid', '--yuuu-color-bg'],
        // The focus ring.
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
    public function testTextReachesFourAndAHalfToOne(string $mode): void
    {
        $this->assertPairs($mode, self::TEXT_PAIRS, 4.5);
    }

    #[DataProvider('modes')]
    public function testControlOutlinesReachThreeToOne(string $mode): void
    {
        $this->assertPairs($mode, self::CONTROL_PAIRS, 3.0);
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

    /** The formula, against reference values. */
    public function testTheContrastFormula(): void
    {
        self::assertEqualsWithDelta(21.0, Stylesheet::contrast('#000000', '#ffffff'), 0.001);
        self::assertEqualsWithDelta(1.0, Stylesheet::contrast('#777777', '#777777'), 0.001);
        self::assertEqualsWithDelta(4.48, Stylesheet::contrast('#777777', '#ffffff'), 0.01);
    }

    /** @param list<array{string, string}> $pairs */
    private function assertPairs(string $mode, array $pairs, float $minimum): void
    {
        $colors = Stylesheet::colors($mode);
        $failures = [];

        foreach ($pairs as [$foreground, $background]) {
            self::assertArrayHasKey($foreground, $colors);
            self::assertArrayHasKey($background, $colors);

            $ratio = Stylesheet::contrast($colors[$foreground], $colors[$background]);
            if ($ratio < $minimum) {
                $failures[] = \sprintf('%s on %s in %s: %.2f:1 (needs %.1f:1)', $foreground, $background, $mode, $ratio, $minimum);
            }
        }

        self::assertSame([], $failures);
    }
}

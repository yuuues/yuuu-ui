<?php

declare(strict_types=1);

namespace Yuuues\YuuuUi\Tests\Unit\Styles;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Yuuues\YuuuUi\Contrast;
use Yuuues\YuuuUi\Tests\Support\Stylesheet;
use Yuuues\YuuuUi\YuuuUiBundle;

/**
 * Contrast over the real token values, in light and in dark mode, through
 * the shipped Contrast class: the same check an app runs on its override.
 *
 * Policy (README §7): reading text 7:1 (WCAG 1.4.6, AAA), secondary text
 * 4.5:1 (WCAG 1.4.3, AA: muted metadata and the hover and pressed filters
 * of `.btn`), non-text 3:1 (WCAG 1.4.11: control outlines, the focus ring,
 * markers). Every tier is a hard failure.
 *
 * Every pair in Contrast is a pair the stylesheet paints together (or one
 * of the override contract), and testEveryPaintedColourIsInATextPair()
 * fails when a `color:` or a `background:` token appears in the CSS
 * without a pair. The brand tokens are checked with their DEFAULT values
 * only: an app that overrides them answers for its own pairs.
 *
 * Not checked, on purpose: disabled controls (exempt in WCAG 1.4.3 and
 * 1.4.6; the stylesheet dims them to 60% opacity so they read as
 * unavailable), placeholders (not styled: the browser default applies),
 * the `.card` accent stripe (an ornament).
 */
final class StylesheetContrastTest extends TestCase
{
    /** The override example of the preview page (README §4). */
    private const string WARM_TEAL = __DIR__.'/../../../docs/preview/warm-teal.css';

    /** The colour tokens of the override contract (README §4): accent family and neutral tint. */
    private const array CONTRACT_COLOURS = [
        '--yuuu-color-primary',
        '--yuuu-color-primary-text',
        '--yuuu-color-accent',
        '--yuuu-color-accent-text',
        '--yuuu-color-accent-ink',
        '--yuuu-color-accent-soft',
        '--yuuu-color-bg',
        '--yuuu-color-surface',
        '--yuuu-color-border',
        '--yuuu-color-border-strong',
        '--yuuu-color-text',
        '--yuuu-color-text-muted',
    ];

    /** @return iterable<string, array{string}> */
    public static function modes(): iterable
    {
        yield 'light' => ['light'];
        yield 'dark' => ['dark'];
    }

    #[DataProvider('modes')]
    public function testTheDefaultsMeetEveryTier(string $mode): void
    {
        self::assertSame([], Contrast::failures(Contrast::colors($mode, YuuuUiBundle::stylesheetPath())), 'A default pair misses its tier.');
    }

    /**
     * The yuuuTime «warm teal» example of the preview page, laid over the
     * defaults exactly as the browser cascades it: its light `:root` comes
     * after the base's dark block, so in dark mode it wins wherever its own
     * dark block does not redefine a token. Every tier, and the button
     * filters, in both modes: the call README §4 shows for an app.
     */
    #[DataProvider('modes')]
    public function testWarmTealExampleMeetsTheTiers(string $mode): void
    {
        // The example must define the colour tokens of the contract itself
        // in this mode, or the check would silently test the defaults.
        $declared = 'light' === $mode ? array_keys(Contrast::colors('light', self::WARM_TEAL)) : Stylesheet::darkTokens(self::WARM_TEAL);
        foreach (self::CONTRACT_COLOURS as $token) {
            self::assertContains($token, $declared, \sprintf('warm-teal.css does not define %s in %s.', $token, $mode));
        }

        self::assertSame([], Contrast::failures(Contrast::colors($mode, YuuuUiBundle::stylesheetPath(), self::WARM_TEAL)), 'The warm teal example misses a contrast tier.');
    }

    /** A failure names the pair, the ratio and the minimum. */
    public function testAFailureIsReported(): void
    {
        $colors = Contrast::colors('light', YuuuUiBundle::stylesheetPath());
        $colors['--yuuu-color-text-muted'] = '#999999';

        self::assertSame([
            '--yuuu-color-text-muted on --yuuu-color-bg: 2.63:1 (needs 4.5)',
            '--yuuu-color-text-muted on --yuuu-color-surface: 2.85:1 (needs 4.5)',
        ], Contrast::failures($colors));
    }

    public function testAnUnknownModeIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Contrast::colors('sepia', YuuuUiBundle::stylesheetPath());
    }

    /**
     * Guards the lists: every token the CSS paints as a text colour is a
     * foreground of some text pair, and every token it paints as a
     * background is the background of some text pair.
     */
    public function testEveryPaintedColourIsInATextPair(): void
    {
        $code = Stylesheet::code();
        preg_match_all('/(?<![a-z-])color\s*:\s*var\(\s*(--[a-z0-9-]+)\s*\)/', $code, $foregrounds);
        preg_match_all('/(?<![a-z-])background(?:-color)?\s*:\s*var\(\s*(--[a-z0-9-]+)\s*\)/', $code, $backgrounds);

        self::assertNotSame([], $foregrounds[1]);
        self::assertNotSame([], $backgrounds[1]);
        $pairs = [...Contrast::READING_PAIRS, ...Contrast::SECONDARY_PAIRS];
        self::assertSame([], array_values(array_diff(array_unique($foregrounds[1]), array_column($pairs, 0))), 'Text colours without a pair.');
        self::assertSame([], array_values(array_diff(array_unique($backgrounds[1]), array_column($pairs, 1))), 'Backgrounds without a pair.');
    }

    /** Every brightness() filter in the CSS is checked. */
    public function testEveryButtonFilterIsChecked(): void
    {
        preg_match_all('/brightness\(\s*([0-9.]+)\s*\)/', Stylesheet::code(), $filters);
        $checked = array_column(Contrast::FILTERED_PAIRS, 2);

        self::assertNotSame([], $filters[1]);
        foreach (array_unique($filters[1]) as $factor) {
            self::assertContains((float) $factor, $checked, \sprintf('brightness(%s) is not checked.', $factor));
        }
    }

    /**
     * Guards the parser: if it stopped finding the dark block, dark mode
     * would silently be checked with the light values.
     */
    public function testDarkModeRedefinesEveryColourToken(): void
    {
        $light = Contrast::colors('light', YuuuUiBundle::stylesheetPath());
        $dark = Contrast::colors('dark', YuuuUiBundle::stylesheetPath());

        self::assertGreaterThan(20, \count($light));
        self::assertSame([], array_values(array_diff(array_keys($light), Stylesheet::darkTokens())), 'Colour tokens without a dark value.');
        self::assertSame(array_keys($light), array_keys($dark));
        self::assertSame([], array_intersect_assoc($light, $dark), 'Dark mode reads a light value.');
    }

    /** The formulas, against reference values. */
    public function testTheContrastFormula(): void
    {
        self::assertEqualsWithDelta(21.0, Contrast::ratio('#000000', '#ffffff'), 0.001);
        self::assertEqualsWithDelta(1.0, Contrast::ratio('#777777', '#777777'), 0.001);
        self::assertEqualsWithDelta(4.48, Contrast::ratio('#777777', '#ffffff'), 0.01);
        self::assertSame('#d9d9d9', Contrast::brightness('#ffffff', 0.85));
        self::assertSame('#000000', Contrast::brightness('#000000', 0.5));
    }
}

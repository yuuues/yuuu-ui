<?php

declare(strict_types=1);

namespace Yuuues\YuuuUi\Tests\Unit\Styles;

use PHPUnit\Framework\TestCase;
use Yuuues\YuuuUi\Tests\Support\Stylesheet;

/**
 * The rules that make the stylesheet safe to drop into any app of the
 * suite: nothing loaded from another origin (the apps run under CSP
 * `style-src 'self'`, `font-src 'self'`, `img-src 'self'`), no
 * `!important` beyond the two places that need it, no token used and
 * never declared, and no class without its entry in the README.
 *
 * Comments are stripped first: prose can mention `@import` freely.
 */
final class StylesheetHygieneTest extends TestCase
{
    /**
     * The only declarations allowed to carry `!important`, by selector.
     *
     * - `[hidden]`: any class that sets `display` would otherwise show a
     *   hidden element.
     * - the reduced-motion reset: it must beat every transition, whatever
     *   its specificity.
     */
    private const array IMPORTANT_ALLOWED = [
        '[hidden]' => ['display'],
        '*, *::before, *::after' => ['transition', 'animation'],
    ];

    public function testNothingIsImported(): void
    {
        self::assertDoesNotMatchRegularExpression('/@import\b/i', Stylesheet::code());
    }

    public function testNoWebFonts(): void
    {
        self::assertDoesNotMatchRegularExpression('/@font-face\b/i', Stylesheet::code());
    }

    public function testNoUrlPointsOutside(): void
    {
        $code = Stylesheet::code();

        self::assertDoesNotMatchRegularExpression('#url\(\s*[\'"]?\s*(https?:)?//#i', $code);
        // Stricter than the CSP needs, on purpose: the base ships no
        // images at all. A data: URI would be the only acceptable form.
        preg_match_all('/url\(\s*[\'"]?\s*([a-z]*:?)/i', $code, $urls);
        foreach ($urls[1] as $scheme) {
            self::assertSame('data:', strtolower($scheme), 'Only data: URIs are allowed in url().');
        }
    }

    public function testImportantOnlyWhereItIsNeeded(): void
    {
        preg_match_all('/([^{}]+)\{([^{}]*)\}/', Stylesheet::code(), $rules, \PREG_SET_ORDER);
        $found = 0;
        $failures = [];

        foreach ($rules as $rule) {
            $selector = (string) preg_replace('/\s*,\s*/', ', ', (string) preg_replace('/\s+/', ' ', trim($rule[1])));

            preg_match_all('/([a-z-]+)\s*:[^;]*!important/i', $rule[2], $declarations);
            foreach ($declarations[1] as $property) {
                ++$found;
                if (!\in_array($property, self::IMPORTANT_ALLOWED[$selector] ?? [], true)) {
                    $failures[] = $selector.' { '.$property.' }';
                }
            }
        }

        self::assertSame([], $failures, '!important outside the allow-list.');
        self::assertSame(3, $found, 'The allow-list is out of date: update it and this count together.');
    }

    public function testEveryTokenUsedIsDeclared(): void
    {
        preg_match_all('/var\(\s*(--[a-z0-9-]+)/', Stylesheet::code(), $used);
        $missing = array_values(array_diff(array_unique($used[1]), Stylesheet::declaredTokens()));

        self::assertSame([], $missing);
    }

    public function testDarkModeOnlyRedefinesExistingTokens(): void
    {
        $unknown = array_values(array_diff(Stylesheet::darkTokens(), Stylesheet::declaredTokens()));

        self::assertSame([], $unknown, 'A dark token without a light value is a typo.');
    }

    public function testBrandTokensKeepTheirPublicNames(): void
    {
        $tokens = Stylesheet::declaredTokens();

        foreach (['--yuuu-color-primary', '--yuuu-color-primary-text', '--yuuu-color-accent', '--yuuu-color-accent-text', '--yuuu-color-accent-ink', '--yuuu-color-accent-soft'] as $token) {
            self::assertContains($token, $tokens);
        }
    }

    public function testCompactHeightsOnlyForFinePointers(): void
    {
        $outside = Stylesheet::code();
        $inside = '';

        // `@media (pointer: fine) and (not (any-pointer: coarse)) { ... }` blocks, by brace matching: the
        // rules inside hold braces of their own.
        while (false !== ($start = strpos($outside, '@media (pointer: fine) and (not (any-pointer: coarse))'))) {
            $open = (int) strpos($outside, '{', $start);
            $depth = 0;
            $end = $open;
            for ($length = \strlen($outside); $end < $length; ++$end) {
                $depth += match ($outside[$end]) {
                    '{' => 1,
                    '}' => -1,
                    default => 0,
                };
                if (0 === $depth) {
                    break;
                }
            }
            $inside .= substr($outside, $open + 1, $end - $open - 1);
            $outside = substr($outside, 0, $start).substr($outside, $end + 1);
        }

        $compactHeights = static function (string $css): array {
            preg_match_all('/([^{}]*\[data-density="compact"\][^{}]*)\{([^{}]*)\}/', $css, $rules, \PREG_SET_ORDER);
            $found = [];
            foreach ($rules as $rule) {
                if (1 === preg_match('/(?<![a-z-])(?:min-)?height\s*:/', $rule[2])) {
                    $found[] = trim($rule[1]);
                }
            }

            return $found;
        };

        self::assertNotSame([], $compactHeights($inside), 'The compact heights must exist, inside the fine-pointer media query.');
        self::assertSame([], $compactHeights($outside), 'A compact height outside @media (pointer: fine) and (not (any-pointer: coarse)) would shrink touch targets.');
    }

    public function testTappableControlsAreAtLeast44px(): void
    {
        $code = Stylesheet::code();

        foreach (['.btn', '.side-nav__link', '.tabs__link', '.pagination__link', '.card__action'] as $selector) {
            preg_match_all('/(?:^|\})\s*'.preg_quote($selector, '/').'\s*\{([^{}]*)\}/', $code, $rules);
            $heights = [];
            foreach ($rules[1] as $body) {
                if (1 === preg_match('/min-height\s*:\s*([0-9.]+)rem/', $body, $match)) {
                    $heights[] = (float) $match[1];
                }
            }

            self::assertNotSame([], $heights, $selector.' has no min-height.');
            $tallest = [] === $heights ? 0.0 : max($heights);
            self::assertGreaterThanOrEqual(2.75, $tallest, $selector.' is below 44 px (2.75rem).');
        }
    }

    public function testEveryClassIsDocumentedInTheReadme(): void
    {
        $readme = (string) file_get_contents(\dirname(__DIR__, 3).'/README.md');

        preg_match_all('/\.([a-z][a-z0-9_-]*)/', (string) preg_replace('/\{[^{}]*\}/', '{}', Stylesheet::code()), $classes);
        $undocumented = [];

        foreach (array_unique($classes[1]) as $class) {
            if (1 !== preg_match('/\.'.preg_quote($class, '/').'(?![a-z0-9_-])/', $readme)) {
                $undocumented[] = $class;
            }
        }

        self::assertNotSame([], $classes[1]);
        self::assertSame([], $undocumented);
    }
}

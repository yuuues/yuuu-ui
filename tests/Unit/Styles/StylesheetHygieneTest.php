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

        foreach (['--yuuu-color-primary', '--yuuu-color-primary-text', '--yuuu-color-accent', '--yuuu-color-accent-text'] as $token) {
            self::assertContains($token, $tokens);
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

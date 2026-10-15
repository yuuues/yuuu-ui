<?php

declare(strict_types=1);

namespace Yuuues\YuuuUi\Tests\Unit\Scripts;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Yuuues\YuuuUi\YuuuUiBundle;

/**
 * The rules that make `yuuu-ui.js` safe to load in any app of the suite,
 * under CSP `script-src 'self'` (no `'unsafe-eval'`, no `'unsafe-inline'`)
 * and `style-src 'self'`: no string evaluation, no HTML parsing, no inline
 * styles, no inline handlers, nothing fetched, no globals, a size budget.
 *
 * PHPUnit cannot click a <dialog>: the behaviour itself is described in
 * the README and checked by hand in a browser. This only guards what can
 * be read from the source. Checked on the raw source, comments included:
 * stricter, and the file is small.
 */
final class ScriptHygieneTest extends TestCase
{
    /** 3 KiB, unminified and with comments: the file ships as written. */
    private const int SIZE_BUDGET = 3072;

    /** @return iterable<string, array{string}> */
    public static function forbidden(): iterable
    {
        // String evaluation: blocked by CSP without 'unsafe-eval'.
        yield 'eval' => ['/\beval\s*\(/'];
        yield 'new Function' => ['/\bFunction\s*\(/'];
        yield 'timer with a string' => ['/\bset(Timeout|Interval)\s*\(\s*[\'"`]/'];
        // HTML parsing: an injection sink, and never needed here.
        yield 'innerHTML / outerHTML' => ['/\b(inner|outer)HTML\b/'];
        yield 'insertAdjacentHTML' => ['/\binsertAdjacentHTML\b/'];
        yield 'document.write' => ['/\bdocument\.write/'];
        yield 'DOMParser / fragments' => ['/\b(DOMParser|createContextualFragment)\b/'];
        // Inline styles: blocked by CSP style-src without 'unsafe-inline'.
        yield '.style' => ['/\.style\b/'];
        yield 'cssText' => ['/\bcssText\b/'];
        yield 'style attribute' => ['/setAttribute\s*\(\s*[\'"`]style/i'];
        // Inline handlers: addEventListener only.
        yield 'on* property' => ['/\.on[a-z]+\s*=(?!=)/'];
        yield 'on* attribute' => ['/setAttribute\s*\(\s*[\'"`]on/i'];
        // Nothing loaded from anywhere.
        yield 'absolute URL' => ['#https?://#i'];
        yield 'protocol-relative URL' => ['#[\'"`]//#'];
        yield 'network' => ['/\b(fetch|XMLHttpRequest|WebSocket|EventSource|sendBeacon|importScripts)\b/'];
        yield 'dynamic import' => ['/\bimport\s*\(/'];
        yield 'element with a src' => ['/createElement\s*\(\s*[\'"`](script|iframe|img|link|style)/i'];
        // No globals: everything lives inside the IIFE.
        yield 'global assignment' => ['/\b(window|globalThis|self)\.[A-Za-z_$][\w$]*\s*=(?!=)/'];
    }

    #[DataProvider('forbidden')]
    public function testItDoesNotContain(string $pattern): void
    {
        self::assertDoesNotMatchRegularExpression($pattern, self::source());
    }

    public function testItStaysWithinTheSizeBudget(): void
    {
        self::assertLessThan(self::SIZE_BUDGET, \strlen(self::source()), 'yuuu-ui.js is over its 3 KiB budget.');
    }

    /** One IIFE in strict mode: no global names, no module syntax. */
    public function testItIsAStrictIife(): void
    {
        $code = trim((string) preg_replace('#^/\*.*?\*/#s', '', self::source()));

        self::assertStringStartsWith('(function () {', $code);
        self::assertStringEndsWith('}());', $code);
        self::assertStringContainsString("'use strict';", $code);
        self::assertDoesNotMatchRegularExpression('/^\s*(import|export)\b/m', $code);
    }

    /**
     * The progressive-enhancement contract: without showModal() the
     * script does nothing at all, so links navigate and forms submit.
     * The check has to come before any listener is attached.
     */
    public function testItBailsOutBeforeListeningWhenDialogsAreMissing(): void
    {
        $source = self::source();
        $check = strpos($source, 'prototype.showModal');
        $listen = strpos($source, 'addEventListener');

        self::assertNotFalse($check);
        self::assertNotFalse($listen);
        self::assertLessThan($listen, $check);
    }

    /** Every `data-` attribute the script reacts to is public API: documented. */
    public function testEveryDataAttributeIsDocumentedInTheReadme(): void
    {
        $readme = (string) file_get_contents(\dirname(__DIR__, 3).'/README.md');
        preg_match_all('/\bdata-[a-z][a-z-]*/', self::source(), $attributes);

        self::assertNotSame([], $attributes[0]);
        foreach (array_unique($attributes[0]) as $attribute) {
            self::assertMatchesRegularExpression('/`'.preg_quote($attribute, '/').'[`=]/', $readme, $attribute.' is not documented.');
        }
    }

    private static function source(): string
    {
        $js = file_get_contents(YuuuUiBundle::scriptPath());

        if (false === $js) {
            throw new \RuntimeException('Cannot read the script.');
        }

        return $js;
    }
}

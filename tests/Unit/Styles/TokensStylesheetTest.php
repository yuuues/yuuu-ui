<?php

declare(strict_types=1);

namespace Yuuues\YuuuUi\Tests\Unit\Styles;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Yuuues\YuuuUi\Tests\Support\Stylesheet;
use Yuuues\YuuuUi\Tests\Support\TokensStylesheet;

/**
 * `tokens.css` is generated from section 1 of `yuuu-base.css` and
 * committed. These tests keep the copy from drifting, and prove that
 * what the contrast and hygiene tests check on the base is exactly what
 * a JavaScript app importing `tokens.css` gets.
 */
final class TokensStylesheetTest extends TestCase
{
    public function testTheCommittedFileIsTheGeneratedOne(): void
    {
        self::assertSame(
            TokensStylesheet::render(Stylesheet::source()),
            Stylesheet::source(TokensStylesheet::path()),
            'public/css/tokens.css is out of date: run bin/build-tokens and commit the result.',
        );
    }

    /** @return iterable<string, array{string}> */
    public static function modes(): iterable
    {
        yield 'light' => ['light'];
        yield 'dark' => ['dark'];
    }

    /** The colours StylesheetContrastTest checks are the ones shipped here. */
    #[DataProvider('modes')]
    public function testTheColoursAreTheContrastCheckedOnes(string $mode): void
    {
        $colors = Stylesheet::colors($mode, TokensStylesheet::path());

        self::assertGreaterThan(20, \count($colors));
        self::assertSame(Stylesheet::colors($mode), $colors);
    }

    public function testEveryTokenOfTheBaseIsHere(): void
    {
        self::assertSame(Stylesheet::declaredTokens(), Stylesheet::declaredTokens(TokensStylesheet::path()));
        self::assertSame(Stylesheet::darkTokens(), Stylesheet::darkTokens(TokensStylesheet::path()));
    }

    /**
     * Only custom properties and `color-scheme`, on `:root`, in light and
     * in dark: importing the file styles no element of the app.
     */
    public function testItDeclaresTokensAndNothingElse(): void
    {
        $code = Stylesheet::code(TokensStylesheet::path());

        preg_match_all('/([^{}]+)\{/', $code, $openings);
        $selectors = array_map(static fn (string $selector): string => (string) preg_replace('/\s+/', ' ', trim($selector)), $openings[1]);
        self::assertSame([':root', '@media (prefers-color-scheme: dark)', ':root'], $selectors);

        preg_match_all('/([a-z-]+)\s*:[^;{}]*;/i', $code, $declarations);
        $properties = array_unique(array_filter($declarations[1], static fn (string $property): bool => !str_starts_with($property, '--')));
        self::assertSame(['color-scheme'], array_values($properties));
    }

    public function testItLoadsNothing(): void
    {
        $code = Stylesheet::code(TokensStylesheet::path());

        self::assertDoesNotMatchRegularExpression('/@import\b|@font-face\b|url\(/i', $code);
    }
}

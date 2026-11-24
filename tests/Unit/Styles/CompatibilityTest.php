<?php

declare(strict_types=1);

namespace Yuuues\YuuuUi\Tests\Unit\Styles;

use PHPUnit\Framework\TestCase;
use Yuuues\YuuuUi\Tests\Support\Stylesheet;

/**
 * The compatibility gate: nothing the 0.4.0 stylesheet declared may change.
 *
 * Apps in production load `yuuu-base.css` as it is. Every rule of 0.4.0
 * must still be there, in the same `@media` context, with every
 * declaration identical. New rules and new declarations are allowed.
 *
 * A rule that has to change on purpose is named in CHANGED with its
 * reason; nothing else may differ.
 */
final class CompatibilityTest extends TestCase
{
    private const string FIXTURE = __DIR__.'/../../Fixtures/yuuu-base-0.4.0.css';

    private const string DARK = '@media (prefers-color-scheme: dark)';

    /**
     * Rules allowed to differ from 0.4.0, as "context > selector" => reason.
     * The context is the enclosing at-rule chain joined with " > "; the
     * key is "selector" alone when the rule is top level.
     *
     * @var array<string, string>
     */
    private const array CHANGED = [];

    public function testEveryRuleOf040IsUnchanged(): void
    {
        $current = self::rules(Stylesheet::code());
        /** @var array<string, string> $changed */
        $changed = self::CHANGED;
        $failures = [];

        foreach (self::rules(Stylesheet::code(self::FIXTURE)) as $key => $declarations) {
            if (isset($changed[$key])) {
                continue;
            }

            if (!isset($current[$key])) {
                $failures[] = $key.' is missing';
                continue;
            }

            foreach ($declarations as $property => $value) {
                if (($current[$key][$property] ?? null) !== $value) {
                    $failures[] = \sprintf('%s / %s: 0.4.0 has "%s", now "%s"', $key, $property, $value, $current[$key][$property] ?? '(absent)');
                }
            }
        }

        self::assertSame([], $failures, 'A rule of 0.4.0 changed.');
    }

    public function testNoTokenOf040ChangedItsValue(): void
    {
        $current = self::rules(Stylesheet::code());
        $checked = 0;
        $failures = [];

        foreach (self::rules(Stylesheet::code(self::FIXTURE)) as $key => $declarations) {
            if (':root' !== $key && self::DARK.' > :root' !== $key) {
                continue;
            }

            foreach ($declarations as $property => $value) {
                if (!str_starts_with($property, '--yuuu-')) {
                    continue;
                }

                ++$checked;
                if (($current[$key][$property] ?? null) !== $value) {
                    $failures[] = \sprintf('%s / %s: 0.4.0 has "%s", now "%s"', $key, $property, $value, $current[$key][$property] ?? '(absent)');
                }
            }
        }

        self::assertGreaterThan(0, $checked);
        self::assertSame([], $failures, 'A token of 0.4.0 changed its value.');
    }

    /**
     * Rules and declarations of a stylesheet without comments, as
     * "context > selector" => [property => value], whitespace normalised.
     * Rules repeated in the same context are merged, the last declaration
     * of a property winning, as in the cascade.
     *
     * @return array<string, array<string, string>>
     */
    private static function rules(string $code): array
    {
        $rules = [];
        $contexts = [];
        $offset = 0;
        $length = \strlen($code);

        while ($offset < $length) {
            $open = strpos($code, '{', $offset);
            $close = strpos($code, '}', $offset);

            if (false !== $close && (false === $open || $close < $open)) {
                array_pop($contexts);
                $offset = $close + 1;
                continue;
            }

            if (false === $open) {
                break;
            }

            $prelude = self::collapse(substr($code, $offset, $open - $offset));

            if (1 === preg_match('/^@(media|supports|layer|container|keyframes)\b/', $prelude)) {
                $contexts[] = $prelude;
                $offset = $open + 1;
                continue;
            }

            $end = strpos($code, '}', $open);
            if (false === $end) {
                throw new \RuntimeException('Unbalanced braces after: '.$prelude);
            }

            $key = implode(' > ', [...$contexts, self::collapse((string) preg_replace('/\s*,\s*/', ', ', $prelude))]);
            $rules[$key] = [...($rules[$key] ?? []), ...self::declarations(substr($code, $open + 1, $end - $open - 1))];
            $offset = $end + 1;
        }

        return $rules;
    }

    /** @return array<string, string> */
    private static function declarations(string $body): array
    {
        preg_match_all('/(--[a-z0-9-]+|[a-z-]+)\s*:((?:[^;()"\']|\([^)]*\)|"[^"]*"|\'[^\']*\')*)/i', $body, $pairs, \PREG_SET_ORDER);

        $declarations = [];
        foreach ($pairs as $pair) {
            $declarations[$pair[1]] = self::collapse($pair[2]);
        }

        return $declarations;
    }

    private static function collapse(string $text): string
    {
        return trim((string) preg_replace('/\s+/', ' ', $text));
    }
}

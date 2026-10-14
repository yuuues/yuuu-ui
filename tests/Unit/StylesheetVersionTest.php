<?php

declare(strict_types=1);

namespace Yuuues\YuuuUi\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Yuuues\YuuuUi\StylesheetVersion;
use Yuuues\YuuuUi\YuuuUiBundle;

final class StylesheetVersionTest extends TestCase
{
    private string $file;

    protected function setUp(): void
    {
        $this->file = sys_get_temp_dir().'/yuuu-ui-version-'.bin2hex(random_bytes(6)).'.css';
    }

    protected function tearDown(): void
    {
        if (is_file($this->file)) {
            unlink($this->file);
        }
    }

    public function testItIsAShortHexFingerprint(): void
    {
        file_put_contents($this->file, 'body { color: red; }');

        self::assertMatchesRegularExpression('/^[0-9a-f]{10}$/', StylesheetVersion::of($this->file));
    }

    public function testTheSameContentGivesTheSameFingerprint(): void
    {
        file_put_contents($this->file, 'body { color: red; }');
        $first = StylesheetVersion::of($this->file);

        // Same bytes, new mtime: a deploy that rewrites the file without
        // changing it must not bust every browser cache.
        touch($this->file, time() + 60);
        clearstatcache();

        self::assertSame($first, StylesheetVersion::of($this->file));
    }

    public function testItChangesWhenTheContentChanges(): void
    {
        file_put_contents($this->file, 'body { color: red; }');
        $before = StylesheetVersion::of($this->file);

        file_put_contents($this->file, 'body { color: blue; }');

        self::assertNotSame($before, StylesheetVersion::of($this->file));
    }

    public function testAMissingFileFailsLoudly(): void
    {
        $this->expectException(\LogicException::class);

        StylesheetVersion::of($this->file);
    }

    public function testThePackagedStylesheetExists(): void
    {
        self::assertFileExists(YuuuUiBundle::stylesheetPath());
        self::assertSame(
            substr((string) hash_file('xxh3', YuuuUiBundle::stylesheetPath()), 0, 10),
            StylesheetVersion::of(YuuuUiBundle::stylesheetPath()),
        );
    }
}

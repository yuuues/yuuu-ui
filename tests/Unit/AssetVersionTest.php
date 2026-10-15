<?php

declare(strict_types=1);

namespace Yuuues\YuuuUi\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Yuuues\YuuuUi\AssetVersion;
use Yuuues\YuuuUi\StylesheetVersion;
use Yuuues\YuuuUi\YuuuUiBundle;

final class AssetVersionTest extends TestCase
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

        self::assertMatchesRegularExpression('/^[0-9a-f]{10}$/', AssetVersion::of($this->file));
    }

    public function testTheSameContentGivesTheSameFingerprint(): void
    {
        file_put_contents($this->file, 'body { color: red; }');
        $first = AssetVersion::of($this->file);

        // Same bytes, new mtime: a deploy that rewrites the file without
        // changing it must not bust every browser cache.
        touch($this->file, time() + 60);
        clearstatcache();

        self::assertSame($first, AssetVersion::of($this->file));
    }

    public function testItChangesWhenTheContentChanges(): void
    {
        file_put_contents($this->file, 'body { color: red; }');
        $before = AssetVersion::of($this->file);

        file_put_contents($this->file, 'body { color: blue; }');

        self::assertNotSame($before, AssetVersion::of($this->file));
    }

    public function testAMissingFileFailsLoudly(): void
    {
        $this->expectException(\LogicException::class);

        AssetVersion::of($this->file);
    }

    /** The 0.2 name keeps working, with the same result. */
    public function testTheStylesheetVersionAliasGivesTheSameFingerprint(): void
    {
        file_put_contents($this->file, 'body { color: red; }');

        self::assertSame(AssetVersion::of($this->file), StylesheetVersion::of($this->file));
        self::assertSame(AssetVersion::LENGTH, StylesheetVersion::LENGTH);
    }

    /** @return iterable<string, array{string}> */
    public static function packagedAssets(): iterable
    {
        yield 'stylesheet' => [YuuuUiBundle::stylesheetPath()];
        yield 'script' => [YuuuUiBundle::scriptPath()];
    }

    #[DataProvider('packagedAssets')]
    public function testThePackagedAssetExists(string $path): void
    {
        self::assertFileExists($path);
        self::assertSame(
            substr((string) hash_file('xxh3', $path), 0, 10),
            AssetVersion::of($path),
        );
    }
}

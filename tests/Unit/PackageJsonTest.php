<?php

declare(strict_types=1);

namespace Yuuues\YuuuUi\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * `package.json` lets a JavaScript app install the CSS from a git tag,
 * with no registry: `npm i github:yuuues/yuuu-ui#vX.Y.Z`. What can be
 * checked without Node lives here and runs in bin/verify; the real
 * install and a Vite single-file build run in bin/verify-package.
 */
final class PackageJsonTest extends TestCase
{
    /**
     * npm runs these when it installs from git or packs. The package must
     * work without any of them: the CSS is committed, and the owner's
     * machines run npm with `ignore-scripts=true`.
     */
    private const array LIFECYCLE_SCRIPTS = ['preinstall', 'install', 'postinstall', 'prepare', 'preprepare', 'postprepare', 'prepack', 'postpack', 'prepublish'];

    public function testItIsTheScopedPackageWithTheBundleLicence(): void
    {
        $package = self::json('package.json');

        self::assertSame('@yuuues/yuuu-ui', $package['name']);
        self::assertSame(self::json('composer.json')['license'], $package['license']);
        self::assertMatchesRegularExpression('/^\d+\.\d+\.\d+$/', $package['version']);
    }

    public function testEveryExportPointsToAShippedFile(): void
    {
        $package = self::json('package.json');
        $files = $package['files'];

        self::assertSame(['./tokens.css', './base.css', './package.json'], array_keys($package['exports']));
        foreach ($package['exports'] as $subpath => $target) {
            self::assertIsString($target, $subpath);
            self::assertStringStartsWith('./', $target);
            self::assertFileExists(self::root().'/'.substr($target, 2), $subpath);
            if ('./package.json' !== $target) {
                self::assertContains(substr($target, 2), $files, $subpath.' is exported but not in "files".');
            }
        }

        foreach ($files as $file) {
            self::assertFileExists(self::root().'/'.$file);
        }
    }

    /** Bundlers must never tree-shake a bare `import '...css'` away. */
    public function testCssIsASideEffect(): void
    {
        self::assertContains('*.css', self::json('package.json')['sideEffects']);
    }

    public function testNothingRunsOrDownloadsOnInstall(): void
    {
        $package = self::json('package.json');

        self::assertSame([], array_intersect(array_keys($package['scripts'] ?? []), self::LIFECYCLE_SCRIPTS));
        foreach (['dependencies', 'peerDependencies', 'optionalDependencies', 'bundleDependencies'] as $key) {
            self::assertArrayNotHasKey($key, $package);
        }
    }

    /** One version for both ecosystems, and a README that installs it. */
    public function testTheVersionIsTheSameEverywhere(): void
    {
        $version = self::json('package.json')['version'];
        [$major, $minor] = explode('.', $version);

        self::assertSame($major.'.'.$minor.'.x-dev', self::json('composer.json')['extra']['branch-alias']['dev-main']);
        self::assertStringContainsString('npm i github:yuuues/yuuu-ui#v'.$version, (string) file_get_contents(self::root().'/README.md'));
    }

    private static function root(): string
    {
        return \dirname(__DIR__, 2);
    }

    /** @return array<string, mixed> */
    private static function json(string $file): array
    {
        $decoded = json_decode((string) file_get_contents(self::root().'/'.$file), true, flags: \JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded);

        return $decoded;
    }
}

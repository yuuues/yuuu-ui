// Checks for bin/verify-package. No dependency beyond the fixture's.
//
//   node check.mjs pack <npm pack --json output>   what npm ships
//   node check.mjs build                           the installed package and the two single-file builds
//
// Exits non-zero with every failure listed.

import { readFileSync, readdirSync, realpathSync, statSync } from 'node:fs';
import { join, relative, sep } from 'node:path';
import { fileURLToPath } from 'node:url';

const SHIPPED = ['LICENSE', 'README.md', 'package.json', 'public/css/tokens.css', 'public/css/yuuu-base.css'];

const failures = [];
const check = (ok, message) => {
    if (!ok) failures.push(message);
};

function sameList(actual, expected, what) {
    const a = [...actual].sort();
    const e = [...expected].sort();
    check(JSON.stringify(a) === JSON.stringify(e), `${what}: expected ${JSON.stringify(e)}, got ${JSON.stringify(a)}`);
}

function walk(dir) {
    return readdirSync(dir).flatMap((name) => {
        const path = join(dir, name);
        return statSync(path).isDirectory() ? walk(path) : [path];
    });
}

function pack(file) {
    const [result] = JSON.parse(readFileSync(file, 'utf8'));
    sameList(result.files.map((f) => f.path), SHIPPED, 'npm pack');
    check(result.name === '@yuuues/yuuu-ui', `npm pack: name ${result.name}`);
}

async function build() {
    const { transform } = await import('lightningcss');

    // Node's resolver honours "exports": the two public subpaths resolve,
    // a path into the package does not.
    // realpath: pnpm links the package from its store.
    const installed = realpathSync(fileURLToPath(new URL('./node_modules/@yuuues/yuuu-ui/', import.meta.url)));
    for (const [subpath, file] of [['tokens.css', 'public/css/tokens.css'], ['base.css', 'public/css/yuuu-base.css']]) {
        const resolved = fileURLToPath(import.meta.resolve(`@yuuues/yuuu-ui/${subpath}`));
        check(resolved === join(installed, file), `exports: ${subpath} resolved to ${resolved}`);

        // A strict parse: any syntax error throws.
        try {
            const { warnings } = transform({ filename: file, code: readFileSync(resolved), errorRecovery: false });
            check(warnings.length === 0, `${subpath}: ${JSON.stringify(warnings)}`);
        } catch (error) {
            failures.push(`${subpath} does not parse: ${error.message}`);
        }
    }
    let encapsulated = false;
    try {
        import.meta.resolve('@yuuues/yuuu-ui/public/css/tokens.css');
    } catch {
        encapsulated = true;
    }
    check(encapsulated, 'exports: a path outside "exports" resolved');

    sameList(walk(installed).map((p) => relative(installed, p).split(sep).join('/')), SHIPPED, 'installed package');

    // The single-file builds: one HTML file each, everything inlined,
    // nothing referenced, so nothing can be fetched.
    for (const entry of ['tokens', 'base']) {
        const dist = fileURLToPath(new URL(`./dist/${entry}/`, import.meta.url));
        sameList(readdirSync(dist), [`${entry}.html`], `dist/${entry}`);
        const html = readFileSync(join(dist, `${entry}.html`), 'utf8');

        check(!/\b(?:src|href)\s*=/i.test(html), `${entry}: references a file (src= or href=)`);
        check(!/<link\b/i.test(html), `${entry}: has a <link>`);
        check(!/@import\b|url\(/i.test(html), `${entry}: CSS that loads something`);
        check(!/\bfetch\s*\(|XMLHttpRequest|\bimport\s*\(/.test(html), `${entry}: script that loads something`);
        check(/<style\b/i.test(html), `${entry}: no inlined <style>`);
        check(/--yuuu-color-bg:\s*#f5f6f8/.test(html), `${entry}: light tokens not inlined`);
        check(/prefers-color-scheme:\s*dark/.test(html) && /--yuuu-color-bg:\s*#0f1115/.test(html), `${entry}: dark tokens not inlined`);

        // The app's override comes after the default, so it wins.
        const primaries = [...html.matchAll(/--yuuu-color-primary:\s*(#[0-9a-f]+)/gi)].map((m) => m[1].toLowerCase());
        check(primaries.at(-1) === '#0b5cad', `${entry}: the app's brand override does not come last (${primaries})`);

        if (entry === 'tokens') {
            check(!/\.btn\b/.test(html), 'tokens: carries base classes');
        } else {
            check(/\.btn--primary\b/.test(html), 'base: the classes are missing');
        }
    }
}

const [mode, arg] = process.argv.slice(2);
if (mode === 'pack') {
    pack(arg);
} else if (mode === 'build') {
    await build();
} else {
    failures.push(`usage: node check.mjs pack <file> | build (got ${mode})`);
}

if (failures.length > 0) {
    console.error(failures.map((f) => `FAIL ${f}`).join('\n'));
    process.exit(1);
}
console.log(`OK ${mode}`);

// Checks for bin/verify-package. No dependency beyond the fixture's.
//
//   node check.mjs pack <npm pack --json output>   what npm ships
//   node check.mjs build                           the installed package and the three single-file builds
//
// Exits non-zero with every failure listed.

import { readFileSync, readdirSync, realpathSync, statSync } from 'node:fs';
import { join, relative, sep } from 'node:path';
import { fileURLToPath } from 'node:url';

const SHIPPED = ['LICENSE', 'README.md', 'package.json', 'public/css/tailwind.css', 'public/css/tokens.css', 'public/css/yuuu-base.css'];

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

// The Tailwind build: the utilities of the page are generated from the
// --yuuu-* tokens, Tailwind's own palette is gone, and the base classes sit
// in the components layer (declared before utilities, so utilities win).
function utilities(html) {
    check(html.includes('var(--yuuu-color-surface)'), 'utilities: bg-surface does not use var(--yuuu-color-surface)');
    check(html.includes('var(--yuuu-color-text-muted)'), 'utilities: text-muted does not use var(--yuuu-color-text-muted)');
    check(html.includes('var(--yuuu-radius-2)'), 'utilities: rounded-lg does not use var(--yuuu-radius-2)');
    check(html.includes('var(--yuuu-shadow)'), 'utilities: shadow-card does not use var(--yuuu-shadow)');
    check(html.includes('var(--yuuu-color-border)'), 'utilities: border-border does not use var(--yuuu-color-border)');
    // The page uses bg-red-500 on purpose: with the default palette reset,
    // Tailwind must emit no rule for it.
    check(!/\.bg-red-500/.test(html), 'utilities: bg-red-500 exists, the default palette is not reset');
    check(!/--color-red-500|oklch\(63\.7%/.test(html), 'utilities: a default palette value leaked');

    // The build flattens the layer order statement away, but the layer
    // blocks keep their order: components before utilities, so utilities win.
    const at = (name) => html.search(new RegExp(`@layer\\s+${name}\\s*\\{`));
    check(at('components') !== -1 && at('components') < at('utilities'), 'utilities: @layer components does not come before @layer utilities');
    check(layerBlock(html, 'components').includes('.btn'), 'utilities: .btn is not inside @layer components');
    check(!layerBlock(html, 'utilities').includes('.btn'), 'utilities: .btn leaked into @layer utilities');
}

// The text of every `@layer <name> { ... }` block, braces balanced.
function layerBlock(css, name) {
    let out = '';
    const start = new RegExp(`@layer\\s+${name}\\s*\\{`, 'g');
    for (let m; (m = start.exec(css)); ) {
        let depth = 1;
        let i = start.lastIndex;
        while (i < css.length && depth > 0) {
            depth += css[i] === '{' ? 1 : css[i] === '}' ? -1 : 0;
            i++;
        }
        out += css.slice(start.lastIndex, i);
    }
    return out;
}

async function build() {
    const { transform } = await import('lightningcss');

    // Node's resolver honours "exports": the two public subpaths resolve,
    // a path into the package does not.
    // realpath: pnpm links the package from its store.
    const installed = realpathSync(fileURLToPath(new URL('./node_modules/@yuuues/yuuu-ui/', import.meta.url)));
    // tailwind.css is not here: it uses Tailwind's at-rules (@theme,
    // @custom-variant), which a plain CSS parser rejects. The utilities
    // build below proves it.
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
    check(
        fileURLToPath(import.meta.resolve('@yuuues/yuuu-ui/tailwind.css')) === join(installed, 'public/css/tailwind.css'),
        'exports: tailwind.css did not resolve to the shipped file',
    );

    sameList(walk(installed).map((p) => relative(installed, p).split(sep).join('/')), SHIPPED, 'installed package');

    // The single-file builds: one HTML file each, everything inlined,
    // nothing referenced, so nothing can be fetched.
    for (const entry of ['tokens', 'base', 'utilities']) {
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

        if (entry !== 'utilities') {
            // The app's override comes after the default, so it wins.
            const primaries = [...html.matchAll(/--yuuu-color-primary:\s*(#[0-9a-f]+)/gi)].map((m) => m[1].toLowerCase());
            check(primaries.at(-1) === '#0b5cad', `${entry}: the app's brand override does not come last (${primaries})`);
        }

        if (entry === 'tokens') {
            check(!/\.btn\b/.test(html), 'tokens: carries base classes');
        } else if (entry === 'base') {
            check(/\.btn--primary\b/.test(html), 'base: the classes are missing');
        } else {
            utilities(html);
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

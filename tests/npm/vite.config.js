import { defineConfig } from 'vite';
import { viteSingleFile } from 'vite-plugin-singlefile';

// One page per build: vite-plugin-singlefile inlines everything into a
// single HTML file and needs a single input. ENTRY is "tokens" or "base".
const entry = process.env.ENTRY ?? 'tokens';

export default defineConfig({
    plugins: [viteSingleFile()],
    logLevel: 'warn',
    build: {
        outDir: `dist/${entry}`,
        emptyOutDir: true,
        // Vite's modulepreload polyfill carries a fetch(): useless in a
        // single file (there is nothing to preload) and noise for anyone
        // auditing that the page makes no request.
        modulePreload: { polyfill: false },
        rollupOptions: { input: `${entry}.html` },
    },
});

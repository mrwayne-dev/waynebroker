import tailwindcss from '@tailwindcss/vite';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { defineConfig, type Plugin } from 'vite';

const jsRoot = fileURLToPath(new URL('../resources/js', import.meta.url));

/**
 * Resolves the `@/*` import alias for Ladle's Vite instance.
 *
 * Why this exists rather than a plain `resolve.alias` entry:
 *
 *   1. Ladle rebuilds `resolve.alias` from scratch for its own msw/axe stubs
 *      and discards whatever the user config had (vite-base.js:69-93), so an
 *      alias declared here never survives.
 *   2. Ladle otherwise injects `vite-tsconfig-paths` itself, and that plugin
 *      mis-resolves this project's mapping. tsconfig.json declares
 *      `paths: { "@/*": ["./resources/js/*"] }` with no `baseUrl`, and the
 *      injected plugin resolved `@/lib/utils` to `/resources/js/lib/utils` —
 *      absolute from the filesystem root. Adding `baseUrl` is not the fix:
 *      TypeScript has deprecated it and `tsc` errors on its presence.
 *
 * Ladle skips its own injection when a user plugin is named
 * `vite:tsconfig-paths` (get-user-vite-config.js:90-93), so this takes that
 * name deliberately. It resolves through `this.resolve` so Vite still applies
 * its own extension resolution (.tsx/.ts) to the rewritten path.
 */
function gothamPathAlias(): Plugin {
    return {
        name: 'vite:tsconfig-paths',
        enforce: 'pre',
        async resolveId(source, importer) {
            if (!source.startsWith('@/')) {
                return null;
            }

            const resolved = await this.resolve(
                path.join(jsRoot, source.slice(2)),
                importer,
                { skipSelf: true },
            );

            return resolved ?? null;
        },
    };
}

/**
 * Merged into Ladle's own Vite config.
 *
 * Tailwind is registered here as well as in the app's vite.config.ts: Ladle
 * runs a separate Vite instance, so without this plugin the
 * `@import 'tailwindcss'` at the top of app.css is served verbatim and every
 * story renders unstyled.
 */
export default defineConfig({
    plugins: [gothamPathAlias(), tailwindcss()],
});

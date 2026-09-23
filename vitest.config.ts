import react from '@vitejs/plugin-react';
import { fileURLToPath } from 'node:url';
import { defineConfig } from 'vitest/config';

/**
 * Component tests.
 *
 * Vitest is not a direct dependency: vite-plus 0.3.0 already ships 4.1.11 and
 * pins it through @vitest/browser, so installing vitest@latest alongside it is
 * an unresolvable peer conflict. `npm test` runs `vp test run`, which forwards
 * to that bundled copy and reads this file.
 *
 * A separate config rather than a `test` block in vite.config.ts: the app's
 * config is a vite-plus defineConfig whose type does not expose `test`, and
 * loading the Laravel/Wayfinder plugin stack for a jsdom unit test buys
 * nothing.
 */
export default defineConfig({
    plugins: [react()],
    resolve: {
        alias: {
            '@': fileURLToPath(new URL('./resources/js', import.meta.url)),
        },
    },
    test: {
        environment: 'jsdom',
        globals: true,
        setupFiles: ['./tests/js/setup.ts'],
        // PHP tests live in tests/Unit and tests/Feature and are Pest's;
        // this suite owns tests/js only.
        include: ['tests/js/**/*.test.{ts,tsx}'],
    },
});

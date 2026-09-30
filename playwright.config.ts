import { defineConfig, devices } from '@playwright/test';

/**
 * End-to-end tests.
 *
 * Runs against `php artisan serve` on 8000, which is the local serving model
 * for Phase 0 (Docker Compose was dropped from the gate). The server is started
 * by Playwright unless one is already listening, so `npx playwright test` works
 * from a cold shell and also alongside a dev server you already have up.
 *
 * Assets must be built: the Blade shell resolves @vite through the manifest in
 * public/build, so run `npm run build` before this suite (CI does both in
 * order). A missing manifest fails the page render, not the assertion, which
 * would be a confusing way to learn the build was skipped.
 */
export default defineConfig({
    testDir: './tests/e2e',
    fullyParallel: true,
    forbidOnly: !!process.env.CI,
    retries: process.env.CI ? 1 : 0,
    reporter: process.env.CI ? 'github' : 'list',

    use: {
        baseURL: 'http://localhost:8000',
        trace: 'on-first-retry',
    },

    // Chromium only. A palette assertion needs one honest browser, not three;
    // cross-browser coverage is a Phase 6 concern if it is one at all.
    projects: [
        {
            name: 'chromium',
            use: { ...devices['Desktop Chrome'] },
        },
    ],

    webServer: {
        command: 'php artisan serve --port=8000',
        url: 'http://localhost:8000',
        reuseExistingServer: !process.env.CI,
        timeout: 60_000,
    },
});

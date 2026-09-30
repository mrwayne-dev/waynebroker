import { expect, test } from '@playwright/test';

/**
 * Phase 0 gate: the hero renders in the Gotham palette.
 *
 * The colour assertion is the point of running a real browser here. jsdom does
 * not resolve custom properties, so a unit test cannot tell you that
 * --surface-void survived the chain
 *   body → bg-background → --background → --surface-void → --color-surface-void
 * and actually reached the paint. If someone reorders the cascade or drops
 * gotham.css from app.css, this is the test that fails.
 */
const SURFACE_VOID = 'rgb(11, 18, 32)'; // #0B1220, plan Section 15.1

test('the landing page presents Gotham Investments on the void surface', async ({
    page,
}) => {
    await page.goto('/');

    await expect(
        page.getByRole('heading', { name: 'Gotham Investments', level: 1 }),
    ).toBeVisible();

    await expect(page).toHaveTitle(/Gotham Investments/);

    const bodyBackground = await page
        .locator('body')
        .evaluate((el) => getComputedStyle(el).backgroundColor);

    expect(bodyBackground).toBe(SURFACE_VOID);
});

test('the hero offers exactly one primary action, into sign-in', async ({
    page,
}) => {
    await page.goto('/');

    const cta = page.getByRole('link', { name: 'Sign in' });

    await expect(cta).toBeVisible();
    await expect(cta).toHaveAttribute('href', '/login');

    // Brass is the only primary in the system (Section 15.1), so a second
    // primary-styled action on this page means the hero has drifted.
    await expect(page.locator('a[data-slot="button"]')).toHaveCount(1);
});

test('the URL brand appears in the footer', async ({ page }) => {
    await page.goto('/');

    await expect(
        page.getByText('waynebroker.mgbah.dev', { exact: true }),
    ).toBeVisible();
});

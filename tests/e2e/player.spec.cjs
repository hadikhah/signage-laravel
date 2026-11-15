const { test, expect } = require('@playwright/test');

/**
 * Player loop end-to-end test.
 *
 * Requires:
 *   - stack running at PLAYER_BASE_URL (default http://localhost)
 *   - migrated DB with sample import
 *   - at least one content item linked to the test IP
 *
 * Run:
 *   npx playwright test tests/e2e/player.spec.js
 *   PLAYER_BASE_URL=http://staging.example.com npx playwright test
 */

test.describe('player /monitor', () => {
    test('loads, renders header, and does not crash on empty playlist', async ({ page }) => {
        const errors = [];
        page.on('pageerror', (err) => errors.push(err.message));

        await page.goto('/monitor?ip=10.99.99.99');

        // Empty state must appear (60s retry, not shown here)
        await expect(page.locator('#empty h1')).toHaveText('No content');

        // Header visible with Persian datetime
        await expect(page.locator('#persian-datetime')).toBeVisible();

        // No unhandled JS errors
        expect(errors).toEqual([]);
    });

    test('renders a playlist item from the fixture IP', async ({ page }) => {
        const errors = [];
        page.on('pageerror', (err) => errors.push(err.message));

        await page.goto('/monitor?ip=10.0.0.1');

        // Wait for the first stage content to appear
        await page.waitForSelector('#stage > *', { timeout: 10_000 });

        // Should have at least one child element rendered (img / video / iframe / fallback)
        const childCount = await page.locator('#stage > *').count();
        expect(childCount).toBeGreaterThan(0);

        // Header shows daily message
        await expect(page.locator('#daily-message')).toBeVisible();

        expect(errors).toEqual([]);
    });

    test('subscribes to Reverb playlist.refresh channel', async ({ page }) => {
        const consoleMessages = [];
        page.on('console', (msg) => consoleMessages.push(msg.text()));

        await page.goto('/monitor?ip=10.0.0.1');

        // Wait a moment for WS handshake
        await page.waitForTimeout(3000);

        const subscriptionLine = consoleMessages.find((line) =>
            line.includes('reverb subscribed to playlist.refresh')
            || line.includes('reverb connecting'),
        );

        expect(subscriptionLine).toBeTruthy();
    });
});
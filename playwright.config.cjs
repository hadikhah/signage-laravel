const { defineConfig, devices } = require('@playwright/test');

module.exports = defineConfig({
    testDir: './tests/e2e',
    timeout: 30_000,
    fullyParallel: false,
    reporter: [['list']],
    use: {
        // Docker service name — reachable from inside the `app` container.
        // Override from outside with PLAYER_BASE_URL=http://localhost (host runs).
        baseURL: process.env.PLAYER_BASE_URL || 'http://nginx',
        launchOptions: {
            args: ['--no-sandbox', '--disable-dev-shm-usage'],
        },
        video: 'on',                 // was: 'retain-on-failure'
        trace: 'on',                 // full timeline replay, worth it too
        screenshot: 'only-on-failure',
    },
    projects: [
        { name: 'chromium', use: { ...devices['Desktop Chrome'] } },
    ],
});
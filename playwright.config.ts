import { defineConfig, devices } from '@playwright/test';

export default defineConfig({
    testDir: 'tests/e2e',
    retries: 0,
    timeout: 30_000,
    use: {
        baseURL: 'http://localhost:3000',
        trace: 'on-first-retry',
        screenshot: 'only-on-failure',
        video: 'off',
    },
    outputDir: 'tests/.artifacts/playwright',
    webServer: {
        command: 'bin\\cake.bat server -p 3000',
        url: 'http://localhost:3000',
        reuseExistingServer: true,
        timeout: 60_000,
    },
    projects: [
        {
            name: 'chromium',
            use: { ...devices['Desktop Chrome'] },
        },
    ],
});

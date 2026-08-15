import { defineConfig, devices } from '@playwright/test';

export default defineConfig({
  testDir: 'tests/e2e',
  retries: 0,
  timeout: 120_000,
  workers: 1,
  fullyParallel: false,
  use: {
    baseURL: process.env.RAIL_APP_BASE_URL || 'http://localhost/rail_app_tc6_release',
    trace: 'retain-on-failure',
    screenshot: 'only-on-failure',
    video: 'off',
  },
  outputDir: 'tests/.artifacts/playwright-tc6',
  projects: [{ name: 'chromium', use: { ...devices['Desktop Chrome'] } }],
});

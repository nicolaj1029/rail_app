import { expect, test, type Locator, type Page } from '@playwright/test';

test.describe.configure({ mode: 'serial' });

const configuredBase = process.env.RAIL_APP_BASE_URL?.replace(/\/+$/, '');
const appUrl = (path: string) => configuredBase ? `${configuredBase}${path}` : path;

async function selectAirport(page: Page, input: Locator, query: string, code: string) {
  await input.fill(query);
  const controls = await input.getAttribute('aria-controls');
  expect(controls).toBeTruthy();
  const option = page.locator(`#${controls} button[role="option"]`, { hasText: code }).first();
  await expect(option).toBeVisible();
  await option.click();
}

async function assertTc6Shell(page: Page, brand: string) {
  await expect(page.locator('.tc6-shell')).toBeVisible();
  await expect(page.locator('.tc6-sidebar')).toContainText(brand);
  await expect(page.locator('.tc6-right-panel')).toBeVisible();
  await expect(page.locator('.flow-layout')).toHaveCount(0);
}

test('AIR French fresh session keeps TC6 through reservation, flight match and incident', async ({ page }) => {
  const pageErrors: string[] = [];
  let localAutocompleteCalls = 0;
  let browserProviderCalls = 0;
  page.on('pageerror', error => pageErrors.push(error.message));
  page.on('request', request => {
    const url = request.url().toLowerCase();
    if (url.includes('/api/transport-nodes/search')) localAutocompleteCalls += 1;
    if (url.includes('aerodatabox') || url.includes('rapidapi')) browserProviderCalls += 1;
  });

  await page.goto(appUrl('/fly-ny?lang=fr'));
  await page.locator('a[href*="/flow/air/completed?tc6=1"]').first().click();
  await expect(page).toHaveURL(/\/flow\/entitlements/);
  await assertTc6Shell(page, 'AirClaim');
  await expect(page.locator('.tc6-sidebar .tc6-step')).toHaveCount(7);
  await expect(page.locator('html')).toHaveAttribute('lang', 'fr');
  await expect.poll(() => pageErrors).toEqual([]);

  const departure = page.locator('input[name="dep_station"]').first();
  const arrival = page.locator('input[name="arr_station"]').first();
  await departure.fill('Bruxelles');
  const departureOptions = page.locator(`#${await departure.getAttribute('aria-controls')} button[role="option"]`);
  await expect(departureOptions.filter({ hasText: 'BRU' }).first()).toBeVisible();
  await expect(departureOptions.filter({ hasText: 'CRL' }).first()).toBeVisible();
  await departureOptions.filter({ hasText: 'BRU' }).first().click();
  await selectAirport(page, arrival, 'London', 'LHR');
  await expect(page.locator('input[name="dep_station_lookup_code"]')).toHaveValue('BRU');
  await expect(page.locator('input[name="arr_station_lookup_code"]')).toHaveValue('LHR');
  await page.locator('input[name="dep_date"]').first().fill('2026-08-12');
  await page.locator('.tc6-action-bar button[type="submit"][name="continue"]').click();

  await expect(page).toHaveURL(/\/flow\/air-reservation-contract/);
  await assertTc6Shell(page, 'AirClaim');
  await page.locator('button[type="submit"]').first().click();
  await expect(page).toHaveURL(/\/flow\/air-flight-select/);
  await assertTc6Shell(page, 'AirClaim');

  const flight = page.locator('input[name="selected_flight_key"]').first();
  await expect(flight).toBeVisible();
  await flight.check();
  await page.locator('button[type="submit"]').first().click();
  await expect(page).toHaveURL(/\/flow\/incident/);
  await assertTc6Shell(page, 'AirClaim');
  await expect(page.locator('#airIncidentForm, #incidentStepForm').first()).toBeVisible();

  expect(localAutocompleteCalls).toBeGreaterThan(0);
  expect(browserProviderCalls).toBe(0);
  expect(pageErrors).toEqual([]);
});

for (const product of [
  { route: '/tog-ny', entry: '/flow/rail/completed?tc6=1', brand: 'TrainClaim', mode: 'rail' },
  { route: '/faerge-ny', entry: '/flow/ferry/completed?tc6=1', brand: 'FerryClaim', mode: 'ferry' },
]) {
  test(`${product.mode.toUpperCase()} fresh session renders only TC6 and survives navigation`, async ({ page }) => {
    const pageErrors: string[] = [];
    page.on('pageerror', error => pageErrors.push(error.message));
    await page.goto(appUrl(product.route));
    await page.locator(`a[href*="${product.entry}"]`).first().click();
    await expect(page).toHaveURL(/\/flow\/entitlements/);
    await assertTc6Shell(page, product.brand);
    await expect(page.locator('body')).toHaveClass(new RegExp(`flow-mode-${product.mode}`));
    await page.reload();
    await assertTc6Shell(page, product.brand);
    expect(pageErrors).toEqual([]);
  });
}

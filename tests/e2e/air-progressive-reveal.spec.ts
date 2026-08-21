import { expect, test, type Page } from '@playwright/test';

test.describe.configure({ mode: 'serial' });

const configuredBase = process.env.RAIL_APP_BASE_URL?.replace(/\/+$/, '');
const appUrl = (path: string) => configuredBase ? `${configuredBase}${path}` : path;

const group = (page: Page, name: string) =>
  page.locator(`[data-progressive-group="${name}"]`).first();

async function chooseAirport(page: Page, fieldName: string, query: string, code: string) {
  const input = page.locator(`input[name="${fieldName}"]`).first();
  await input.fill(query);
  const controls = await input.getAttribute('aria-controls');
  expect(controls).toBeTruthy();
  const option = page.locator(`#${controls} button[role="option"]`, { hasText: code }).first();
  await expect(option).toBeVisible();
  await option.click();
}

async function openAirStep1(page: Page, language = 'fr') {
  await page.goto(appUrl(`/fly-ny${language ? `?lang=${language}` : ''}`));
  await page.locator('a[href*="/flow/air/completed?tc6=1"]').first().click();
  await expect(page).toHaveURL(/\/flow\/entitlements/);
  await expect(page.locator('form[data-air-progressive-form="entitlements"]'))
    .toHaveAttribute('data-air-progressive-bound', 'true');
}

async function reachIncident(page: Page) {
  await openAirStep1(page, 'fr');
  await chooseAirport(page, 'dep_station', 'Bruxelles', 'BRU');
  await chooseAirport(page, 'arr_station', 'London', 'LHR');
  await page.locator('select[name="air_route_type"]').selectOption('direct');
  await page.locator('input[name="dep_date"]').fill('2026-08-12');
  await page.locator('input[name="passenger_count"]').fill('1');
  await group(page, 'actions').locator('button[type="submit"]').click();

  await expect(page).toHaveURL(/\/flow\/air-reservation-contract/);
  await expect(page.locator('form[data-air-progressive-form="reservation-contract"]'))
    .toHaveAttribute('data-air-progressive-bound', 'true');
  await group(page, 'actions').locator('button[type="submit"]').click();

  await expect(page).toHaveURL(/\/flow\/air-flight-select/);
  const flight = page.locator('input[name="selected_flight_key"]').first();
  await expect(flight).toBeVisible();
  await flight.check();
  await page.locator('button[type="submit"]').first().click();
  await expect(page).toHaveURL(/\/flow\/incident/);
}

test('AIR Step 1 reveals canonical questions progressively and clears only connection data', async ({ page }) => {
  const pageErrors: string[] = [];
  let providerCalls = 0;
  page.on('pageerror', (error) => pageErrors.push(error.message));
  page.on('request', (request) => {
    const url = request.url().toLowerCase();
    if (url.includes('aerodatabox') || url.includes('rapidapi')) providerCalls += 1;
  });

  await openAirStep1(page);
  await expect(page.locator('.fe-header')).toHaveCount(0);
  await expect(page.getByText('Flyflowet starter ticketless', { exact: true })).toBeHidden();
  await expect(page.locator('#ticketlessCard > .section-title')).toBeHidden();
  await expect(group(page, 'departure')).toBeVisible();
  await expect(group(page, 'arrival')).toBeHidden();
  await expect(group(page, 'route-type')).toBeHidden();
  await expect(group(page, 'actions')).toBeHidden();

  await page.locator('input[name="dep_station"]').fill('Bruxelles');
  await expect(group(page, 'arrival')).toBeHidden();
  await chooseAirport(page, 'dep_station', 'Bruxelles', 'BRU');
  await expect(group(page, 'arrival')).toBeVisible();
  await chooseAirport(page, 'arr_station', 'London', 'LHR');
  await expect(group(page, 'route-type')).toBeVisible();
  await expect(group(page, 'travel-date')).toBeHidden();

  const revealLatencyMs = await page.evaluate(async () => {
    const form = document.querySelector('form[data-air-progressive-form="entitlements"]')!;
    const select = form.querySelector('select[name="air_route_type"]') as HTMLSelectElement;
    return await new Promise<number>((resolve) => {
      const started = performance.now();
      form.addEventListener('air:progressive-updated', () => resolve(performance.now() - started), { once: true });
      select.value = 'direct';
      select.dispatchEvent(new Event('change', { bubbles: true }));
    });
  });
  expect(revealLatencyMs).toBeLessThan(50);
  await expect(page.locator('[data-progressive-group="travel-date"]:visible')).not.toHaveCount(0);
  await page.locator('input[name="dep_date"]').fill('2026-08-12');
  await expect(group(page, 'passengers')).toBeVisible();
  await expect(group(page, 'actions')).toBeHidden();
  await page.locator('input[name="passenger_count"]').fill('2');
  await expect(group(page, 'actions')).toBeVisible();

  const arrival = page.locator('input[name="arr_station"]');
  await page.locator('input[name="dep_station"]').fill('CPH');
  await expect(arrival).toBeVisible();
  await expect(arrival).toHaveValue('London Heathrow Airport');
  await expect(page.locator('input[name="arr_station_lookup_code"]')).toHaveValue('LHR');
  await chooseAirport(page, 'dep_station', 'CPH', 'CPH');

  await page.locator('select[name="air_route_type"]').selectOption('connecting');
  await expect(group(page, 'connection-details')).toBeVisible();
  await page.locator('textarea[name="air_stopover_airports"]').fill('AMS');
  await page.locator('select[name="air_connection_type"]').selectOption('protected_connection');
  await page.locator('select[name="air_route_type"]').selectOption('direct');
  await expect(group(page, 'connection-details')).toBeHidden();
  await expect(page.locator('textarea[name="air_stopover_airports"]')).toHaveValue('');
  await expect(page.locator('select[name="air_connection_type"]')).toHaveValue('');
  await expect(arrival).toHaveValue('London Heathrow Airport');

  expect(providerCalls).toBe(0);
  expect(pageErrors).toEqual([]);
  console.log(`AIR_PROGRESSIVE_REVEAL_MS ${revealLatencyMs.toFixed(2)}`);
});

test('AIR incident and remedies reveal progressively, clear stale values and remain usable without JS', async ({ page, browser }) => {
  test.setTimeout(90_000);
  const pageErrors: string[] = [];
  const progressiveConsoleErrors: string[] = [];
  page.on('pageerror', (error) => pageErrors.push(error.message));
  page.on('console', (message) => {
    if (message.type() === 'error' && /air.progressive|updateAirExpenseRowButtons/i.test(message.text())) {
      progressiveConsoleErrors.push(message.text());
    }
  });
  await reachIncident(page);

  await expect(page.locator('form[data-air-progressive-form="incident"]'))
    .toHaveAttribute('data-air-progressive-bound', 'true');
  await expect(group(page, 'incident-type')).toBeVisible();
  await expect(group(page, 'cancellation-notice')).toBeHidden();
  await expect(group(page, 'delay-expected')).toBeHidden();

  await page.locator('input[name="incident_main"][value="cancellation"]').check();
  await expect(group(page, 'cancellation-notice')).toBeVisible();
  await page.locator('input[name="cancellation_notice_band"][value="under_7_days"]').check();
  await expect(group(page, 'cancellation-reason')).toBeVisible();
  await page.locator('input[name="air_cancellation_reason_informed"][value="yes"]').check();
  await page.locator('select[name="air_cancellation_reason_given"]').selectOption({ index: 1 });
  await expect(group(page, 'cancellation-reroute')).toBeVisible();
  await page.locator('input[name="reroute_offered"][value="yes"]').check();

  await page.locator('input[name="incident_main"][value="delay"]').check();
  await expect(group(page, 'cancellation-notice')).toBeHidden();
  await expect(page.locator('input[name="cancellation_notice_band"]:checked')).toHaveCount(0);
  await expect(page.locator('input[name="air_cancellation_reason_informed"]:checked')).toHaveCount(0);
  await expect(page.locator('select[name="air_cancellation_reason_given"]')).toHaveValue('');
  await expect(page.locator('input[name="reroute_offered"]:checked')).toHaveCount(0);
  await expect(group(page, 'delay-expected')).toBeVisible();

  await page.locator('input[name="air_expected_delay_bucket"][value="five_plus"]').check();
  await page.locator('input[name="air_actual_arrival_delay_bucket"][value="three_to_four"]').check();
  await page.locator('input[name="pmr_user"][value="no"]').check();
  await expect(group(page, 'actions')).toBeVisible();
  expect(pageErrors).toEqual([]);

  await group(page, 'actions').locator('button[type="submit"]').click();
  await expect(page).toHaveURL(/\/flow\/remedies/);
  await expect(page.locator('.tc6-remedies-wrap .tc6-chip')).toContainText(/5\s*\/\s*7/);
  await expect(page.locator('.tc6-remedies-wrap h1').first())
    .toContainText(/Remboursement ou reacheminement|Refund eller ombooking/);
  await expect(page.locator('body')).not.toContainText('Warning (2)');
  await expect(page.locator('form[data-air-progressive-form="remedies"]'))
    .toHaveAttribute('data-air-progressive-bound', 'true');
  await expect(page.locator('#advToggle')).toHaveCount(0);
  await expect(page.locator('#airLiveEstimate')).toHaveCount(1);
  await expect(page.locator('#remediesSubmitBtn')).toHaveCount(0);
  await expect(page.getByText(/Google Maps/i)).toHaveCount(0);
  await page.locator('input[name="remedyChoice"][value="refund_return"]').check();
  await expect(page.locator('#returnExpensePast')).toBeVisible();
  await expect(page.locator('[data-progressive-group="refund-scope"]')).toBeVisible();
  await expect(page.locator('[data-progressive-group="refund-route"]')).toBeHidden();
  await expect(group(page, 'return-expense')).toBeHidden();
  await expect(page.locator('[data-progressive-group="actions"]')).toBeHidden();
  await page.locator('select[name="air_refund_scope"]').selectOption('full_ticket');
  await expect(page.locator('[data-progressive-group="refund-route"]')).toBeVisible();
  await page.locator('input[name="a18_from_station_other"]').fill('Brussels Airport');
  await page.keyboard.press('Escape');
  await page.locator('input[name="a18_return_to_station_other"]').fill('Brussels Airport');
  await page.keyboard.press('Escape');
  await expect(group(page, 'return-expense')).toBeVisible();
  await page.locator('input[name="return_to_origin_expense"][value="yes"]').check({ force: true });
  await expect(group(page, 'return-expense-details')).toBeVisible();
  await page.locator('select[name="air_return_expense_items[0][type]"]').selectOption({ index: 1 });

  await page.locator('input[name="remedyChoice"][value="no_refund_continue"]').check();
  await expect(group(page, 'refund-scope')).toBeHidden();
  await expect(group(page, 'refund-route')).toBeHidden();
  await expect(group(page, 'return-expense')).toBeHidden();
  await expect(page.locator('select[name="air_refund_scope"]')).toHaveValue('');
  await expect(page.locator('input[name="a18_from_station_other"]')).toHaveValue('');
  await expect(page.locator('input[name="a18_return_to_station_other"]')).toHaveValue('');
  await expect(page.locator('input[name="return_to_origin_expense"]:checked')).toHaveCount(0);
  await expect(page.locator('select[name="air_return_expense_items[0][type]"]')).toHaveValue('');

  await page.locator('input[name="remedyChoice"][value="refund_return"]').check();
  await expect(group(page, 'refund-scope')).toBeVisible();
  await expect(group(page, 'refund-route')).toBeHidden();
  await page.locator('select[name="air_refund_scope"]').selectOption('full_ticket');
  await page.locator('input[name="a18_from_station_other"]').fill('Brussels Airport');
  await page.keyboard.press('Escape');
  await page.locator('input[name="a18_return_to_station_other"]').fill('Brussels Airport');
  await page.keyboard.press('Escape');
  await page.locator('input[name="return_to_origin_expense"][value="no"]').check({ force: true });
  await expect(page.locator('.tc6-action-bar button[type="submit"]')).toBeVisible();

  await page.setViewportSize({ width: 390, height: 844 });
  await page.locator('.tc6-action-bar button[type="submit"]').scrollIntoViewIfNeeded();
  await expect(page.locator('.tc6-action-bar button[type="submit"]')).toBeInViewport();
  expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth + 1)).toBe(true);

  const noJsContext = await browser.newContext({
    javaScriptEnabled: false,
    viewport: { width: 390, height: 844 },
    storageState: await page.context().storageState(),
  });
  const noJsPage = await noJsContext.newPage();
  try {
    await noJsPage.goto(appUrl('/flow/remedies?tc6=1&lang=fr'));
    await expect(noJsPage.locator('form[data-air-progressive-form="remedies"]')).toBeVisible();
    await expect(noJsPage.locator('[data-progressive-group="remedy-choice"]')).toBeVisible();
    await expect(noJsPage.locator('[data-progressive-group="refund-scope"]')).toBeVisible();
    await expect(noJsPage.locator('[data-progressive-group="refund-route"]')).toBeVisible();
    await expect(noJsPage.locator('[data-progressive-group="return-expense"]').first()).toBeVisible();
    await expect(noJsPage.locator('[data-progressive-group="return-expense-details"]')).toBeVisible();
    await expect(noJsPage.locator('.tc6-action-bar button[type="submit"]')).toBeVisible();
  } finally {
    await noJsContext.close();
  }

  await page.waitForTimeout(250);
  expect(pageErrors).toEqual([]);
  expect(progressiveConsoleErrors).toEqual([]);
  await page.goBack();
  await expect(page).toHaveURL(/\/flow\/incident/);
  await expect(page.locator('input[name="incident_main"][value="delay"]')).toBeChecked();
  await expect(page.locator('input[name="air_expected_delay_bucket"][value="five_plus"]')).toBeChecked();
  await expect(group(page, 'delay-expected')).toBeVisible();
  await expect(group(page, 'actions')).toBeVisible();
});

test('AIR Step 1 fails open when JavaScript is disabled', async ({ browser }) => {
  const context = await browser.newContext({ javaScriptEnabled: false, viewport: { width: 390, height: 844 } });
  const page = await context.newPage();
  try {
    await page.goto(appUrl('/fly-ny?lang=fr'));
    await page.locator('a[href*="/flow/air/completed?tc6=1"]').first().click();
    const root = page.locator('form[data-air-progressive-form="entitlements"]');
    await expect(root).toBeVisible();
    await expect(root).not.toHaveClass(/air-progressive-ready/);
    for (const name of ['departure', 'arrival', 'route-type', 'connection-details', 'travel-date', 'passengers', 'actions']) {
      await expect(page.locator(`[data-progressive-group="${name}"]:visible`)).not.toHaveCount(0);
    }
  } finally {
    await context.close();
  }
});

test('AIR progressive initialization reveals a server-error group and its predecessors', async ({ page }) => {
  await page.addInitScript(() => {
    const observer = new MutationObserver(() => {
      const target = document.querySelector('[data-progressive-group="passengers"]');
      if (!target || target.querySelector('.error-message')) return;
      const error = document.createElement('div');
      error.className = 'error-message';
      error.textContent = 'Server validation error';
      target.appendChild(error);
      observer.disconnect();
    });
    observer.observe(document, { childList: true, subtree: true });
  });

  await openAirStep1(page, 'fr');
  await expect(group(page, 'passengers')).toBeVisible();
  await expect(group(page, 'passengers').locator('.error-message')).toBeVisible();
  for (const name of ['departure', 'arrival', 'route-type']) {
    await expect(group(page, name)).toBeVisible();
  }
  await expect(page.locator('[data-progressive-group="travel-date"]:visible')).not.toHaveCount(0);
});

test('RAIL and FERRY never activate AIR progressive forms', async ({ page }) => {
  const pageErrors: string[] = [];
  page.on('pageerror', (error) => pageErrors.push(error.message));
  for (const [landing, entry] of [
    ['/tog-ny', '/flow/rail/completed?tc6=1'],
    ['/faerge-ny', '/flow/ferry/completed?tc6=1'],
  ]) {
    await page.goto(appUrl(landing));
    await page.locator(`a[href*="${entry}"]`).first().click();
    await expect(page.locator('[data-air-progressive-form]')).toHaveCount(0);
    await expect(page.locator('[data-airport-preselector]')).toHaveCount(0);
  }
  expect(pageErrors).toEqual([]);
});

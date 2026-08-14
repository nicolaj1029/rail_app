import { expect, test, type Locator, type Page } from "@playwright/test";

const configuredBase = process.env.RAIL_APP_BASE_URL?.replace(/\/+$/, "");
const appUrl = (path: string) => configuredBase ? `${configuredBase}${path}` : path;

async function chooseAirport(page: Page, field: Locator, query: string, code: string) {
  await field.fill(query);
  const option = page.locator('body > .node-suggest.portal button[role="option"]', { hasText: code }).first();
  await expect(option).toBeVisible();
  await option.click();
}

test("Fly ny completed journey selects a live verified AeroDataBox flight", async ({ page }) => {
  test.skip(process.env.AIR_LIVE_E2E !== "1", "Set AIR_LIVE_E2E=1 for the billable live-provider canary.");
  test.setTimeout(120_000);

  await page.goto(appUrl("/fly-ny"));
  const completed = page.locator('a[href*="/flow/air/completed?tc6=1"]').first();
  await expect(completed).toBeVisible();
  await completed.click();
  await expect(page).toHaveURL(/\/flow\/entitlements(?:\?tc6=1)?/);

  const departure = page.locator('input[name="dep_station"]').first();
  const arrival = page.locator('input[name="arr_station"]').first();
  await chooseAirport(page, departure, "CPH", "CPH");
  await chooseAirport(page, arrival, "LHR", "LHR");
  await page.locator('select[name="air_route_type"]').first().selectOption("direct");
  await page.locator('input[name="dep_date"]').first().fill("2026-08-12");
  await page.locator('input[name="passenger_count"]').first().fill("1");
  await page.locator('button[type="submit"][name="continue"]').click();

  await expect(page).toHaveURL(/\/flow\/(?:air-reservation-contract|air-flight-select)/);
  if (page.url().includes("air-reservation-contract")) {
    await page.locator('button[type="submit"]').click();
  }
  await expect(page).toHaveURL(/\/flow\/air-flight-select/);

  const flightSearch = page.locator('input[name="manual_flight_number"]');
  const searchButton = page.locator('[data-flight-search-button]');
  const usesSearchFilter = await searchButton.count() > 0;
  if (usesSearchFilter) {
    await flightSearch.fill("BA823");
    await searchButton.click();
  }
  const candidateSelector = usesSearchFilter ? '[data-flight-option]' : '.flight-option';
  const ba823 = page.locator(candidateSelector, { hasText: "BA823" }).first();
  await expect(ba823).toBeVisible();
  await expect(ba823).toContainText("aerodatabox");
  await ba823.click();
  await page.locator('button[type="submit"]').click();

  await expect(page).toHaveURL(/\/flow\/incident/);
  await expect(page.locator('#airIncidentForm, #incidentStepForm').first()).toBeVisible();
});

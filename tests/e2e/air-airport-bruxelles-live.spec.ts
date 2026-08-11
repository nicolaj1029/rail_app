import { expect, test } from "@playwright/test";

test("live Step 1 resolves Bruxelles locally for departure and arrival", async ({ page }) => {
  let airportRequests = 0;
  let externalProviderRequests = 0;
  const airportSources: string[] = [];

  page.on("request", (request) => {
    const url = request.url().toLowerCase();
    if (url.includes("/api/transport-nodes/search")) airportRequests += 1;
    if (url.includes("aerodatabox") || url.includes("rapidapi")) externalProviderRequests += 1;
  });
  page.on("response", async (response) => {
    if (!response.url().includes("/api/transport-nodes/search")) return;
    const headers = await response.allHeaders();
    airportSources.push(headers["x-airport-search-source"] ?? "");
  });

  const configuredBase = process.env.RAIL_APP_BASE_URL?.replace(/\/+$/, "");
  await page.goto(configuredBase ? `${configuredBase}/flow/air/ongoing` : "/flow/air/ongoing");
  const departure = page.locator('input[name="dep_station"]').first();
  await expect(departure).toBeVisible();
  await departure.evaluate((element: HTMLInputElement) => { element.value = ""; });
  await page.evaluate(() => {
    const timing = { lastInputAt: 0, renderedAt: 0 };
    (window as typeof window & { __bruxellesTiming?: typeof timing }).__bruxellesTiming = timing;
    document.querySelector('input[name="dep_station"]')?.addEventListener("input", () => {
      timing.lastInputAt = performance.now();
    });
    new MutationObserver(() => {
      if (!timing.renderedAt && document.querySelector('body > .node-suggest.portal button[role="option"]')) {
        timing.renderedAt = performance.now();
      }
    }).observe(document.body, { childList: true, subtree: true });
  });

  await departure.pressSequentially("Bruxelles", { delay: 35 });
  const options = page.locator('body > .node-suggest.portal button[role="option"]');
  await expect(options.nth(0)).toContainText("Brussels Airport");
  await expect(options.nth(1)).toContainText("Brussels South Charleroi Airport");
  const renderMs = await page.evaluate(() => {
    const timing = (window as typeof window & { __bruxellesTiming?: { lastInputAt: number; renderedAt: number } }).__bruxellesTiming;
    return (timing?.renderedAt ?? 0) - (timing?.lastInputAt ?? 0);
  });
  console.log(`[air-autocomplete-live] Bruxelles last keystroke to options: ${renderMs} ms`);
  expect(renderMs).toBeLessThan(3000);

  await departure.press("ArrowDown");
  await departure.press("Enter");
  await expect(departure).toHaveValue("Brussels Airport");
  await expect(page.locator('input[name="dep_station_lookup_code"]').first()).toHaveValue("BRU");

  const arrival = page.locator('input[name="arr_station"]').first();
  await arrival.evaluate((element: HTMLInputElement) => { element.value = ""; });
  await arrival.fill("Bruxelles");
  await expect(options.nth(1)).toContainText("Brussels South Charleroi Airport");
  await options.nth(1).click();
  await expect(arrival).toHaveValue("Brussels South Charleroi Airport");
  await expect(page.locator('input[name="arr_station_lookup_code"]').first()).toHaveValue("CRL");

  expect(airportRequests).toBe(2);
  expect(externalProviderRequests).toBe(0);
  await expect.poll(() => airportSources.length).toBe(2);
  expect(airportSources).toEqual(["local", "local"]);
});

import { expect, test, type Locator, type Page } from "@playwright/test";

const configuredBase = process.env.RAIL_APP_BASE_URL?.replace(/\/+$/, "");
const appUrl = (path: string) => configuredBase ? `${configuredBase}${path}` : path;

async function suggestionBox(page: Page, input: Locator): Promise<Locator> {
  const id = await input.getAttribute("aria-controls");
  expect(id).toBeTruthy();
  return page.locator(`#${id}`);
}

async function chooseWithMouse(page: Page, input: Locator, query: string, code: string) {
  await input.fill(query);
  const box = await suggestionBox(page, input);
  await expect(box).toBeVisible();
  const option = box.locator('button[role="option"]', { hasText: code }).first();
  await expect(option).toBeVisible();
  await option.click();
}

test("AIR Step 1 visibly selects canonical departure and arrival airports", async ({ page }) => {
  test.setTimeout(120_000);
  let localAirportRequests = 0;
  let externalProviderRequests = 0;
  page.on("request", (request) => {
    const url = request.url().toLowerCase();
    if (url.includes("/api/transport-nodes/search")) localAirportRequests += 1;
    if (url.includes("aerodatabox") || url.includes("rapidapi")) externalProviderRequests += 1;
  });

  await page.goto(appUrl("/fly-ny"));
  await page.locator('a[href*="/flow/air/completed?tc6=1"]').first().click();
  await expect(page).toHaveURL(/\/flow\/entitlements(?:\?tc6=1)?/);

  const departure = page.locator('input[name="dep_station"]').first();
  const arrival = page.locator('input[name="arr_station"]').first();
  await expect(departure).toHaveAttribute("data-airport-preselector-bound", "true");
  await expect(arrival).toHaveAttribute("data-airport-preselector-bound", "true");
  await expect(departure).toHaveAttribute("role", "combobox");
  await expect(arrival).toHaveAttribute("role", "combobox");

  await chooseWithMouse(page, departure, "Bruxelles", "BRU");
  await expect(departure).toHaveValue("Brussels Airport");
  await expect(page.locator('input[name="dep_station_lookup_id"]')).not.toHaveValue("");
  await expect(page.locator('input[name="dep_station_lookup_code"]')).toHaveValue("BRU");
  await expect(page.locator('input[name="dep_station_lookup_mode"]')).toHaveValue("air");
  await expect(page.locator('input[name="dep_station_lookup_node_type"]')).toHaveValue("airport");

  await departure.fill("CPH");
  await expect(page.locator('input[name="dep_station_lookup_id"]')).toHaveValue("");
  await expect(page.locator('input[name="dep_station_lookup_code"]')).toHaveValue("");
  const departureBox = await suggestionBox(page, departure);
  await expect(departureBox.locator('button[role="option"]').first()).toContainText("CPH");
  await departure.press("ArrowDown");
  await departure.press("ArrowUp");
  await departure.press("ArrowDown");
  await departure.press("Enter");
  await expect(page.locator('input[name="dep_station_lookup_code"]')).toHaveValue("CPH");

  await arrival.fill("Paris");
  const arrivalBox = await suggestionBox(page, arrival);
  await expect(arrivalBox).toBeVisible();
  await arrival.press("Escape");
  await expect(arrivalBox).toBeHidden();
  await arrival.fill("Paris");
  await expect(arrivalBox).toBeVisible();
  await arrival.press("Tab");
  await expect(arrivalBox).toBeHidden();
  await chooseWithMouse(page, arrival, "London", "LHR");
  await expect(arrival).toHaveValue("London Heathrow Airport");
  await expect(page.locator('input[name="arr_station_lookup_id"]')).not.toHaveValue("");
  await expect(page.locator('input[name="arr_station_lookup_code"]')).toHaveValue("LHR");
  await expect(page.locator('input[name="arr_station_lookup_mode"]')).toHaveValue("air");
  await expect(page.locator('input[name="arr_station_lookup_node_type"]')).toHaveValue("airport");

  await departure.fill("asdfxyz");
  await expect(page.locator('input[name="dep_station_lookup_id"]')).toHaveValue("");
  await page.locator('input[name="dep_date"]').fill("2026-08-12");
  await page.locator('input[name="passenger_count"]').fill("1");
  await page.locator('.tc6-action-bar button[type="submit"][name="continue"]').click();
  await expect(page).toHaveURL(/\/flow\/entitlements/);
  await expect.poll(() => departure.evaluate((input: HTMLInputElement) => input.validationMessage))
    .toContain("gyldig afgangslufthavn fra listen");
  await expect(page.locator('input[name="arr_station_lookup_code"]')).toHaveValue("LHR");

  await chooseWithMouse(page, page.locator('input[name="dep_station"]').first(), "Bruxelles", "BRU");
  await page.locator('.tc6-action-bar button[type="submit"][name="continue"]').click();
  await expect(page).toHaveURL(/\/flow\/air-reservation-contract/);

  expect(localAirportRequests).toBeGreaterThan(0);
  expect(externalProviderRequests).toBe(0);
});

test("AIR Step 1 airport option supports touch selection", async ({ browser }) => {
  const context = await browser.newContext({
    hasTouch: true,
    isMobile: true,
    viewport: { width: 390, height: 844 },
  });
  const page = await context.newPage();
  try {
    await page.goto(appUrl("/fly-ny"));
    await page.locator('a[href*="/flow/air/completed?tc6=1"]').first().tap();
    const departure = page.locator('input[name="dep_station"]').first();
    await departure.fill("CPH");
    const box = await suggestionBox(page, departure);
    const option = box.locator('button[role="option"]', { hasText: "CPH" }).first();
    await expect(option).toBeVisible();
    await option.tap();
    await expect(page.locator('input[name="dep_station_lookup_code"]')).toHaveValue("CPH");
    await expect(departure).toHaveValue(/Copenhagen.*Airport/);
  } finally {
    await context.close();
  }
});

test("AIR Step 1 search matrix is visible in both airport fields", async ({ page }) => {
  test.setTimeout(180_000);
  const matrix: Array<[string, string[]]> = [
    ["Bruxelles", ["BRU", "CRL"]],
    ["Brussel", ["BRU", "CRL"]],
    ["Brussels", ["BRU", "CRL"]],
    ["BRU", ["BRU"]],
    ["CRL", ["CRL"]],
    ["Stockholm", ["ARN", "BMA", "NYO", "VST"]],
    ["ARN", ["ARN"]],
    ["Copenhagen", ["CPH"]],
    ["CPH", ["CPH"]],
    ["London", ["LHR"]],
    ["LHR", ["LHR"]],
    ["Paris", ["CDG"]],
    ["CDG", ["CDG"]],
  ];
  const requestStarted = new WeakMap<object, number>();
  const httpMetrics = new Map<string, { httpMs: number; backend: string; source: string }>();
  page.on("request", (request) => {
    if (request.url().includes("/api/transport-nodes/search")) requestStarted.set(request, performance.now());
  });
  page.on("response", (response) => {
    const request = response.request();
    const started = requestStarted.get(request);
    if (started === undefined || !response.url().includes("/api/transport-nodes/search")) return;
    const query = new URL(response.url()).searchParams.get("q") || "";
    httpMetrics.set(query, {
      httpMs: Math.round((performance.now() - started) * 10) / 10,
      backend: response.headers()["server-timing"] || "",
      source: response.headers()["x-airport-search-source"] || "",
    });
  });

  await page.goto(appUrl("/fly-ny"));
  await page.locator('a[href*="/flow/air/completed?tc6=1"]').first().click();
  const visibleMetrics: Record<string, number> = {};
  for (const fieldName of ["dep_station", "arr_station"]) {
    const input = page.locator(`input[name="${fieldName}"]`).first();
    const box = await suggestionBox(page, input);
    for (const [query, expectedCodes] of matrix) {
      const measureVisible = fieldName === "dep_station" && ["Bruxelles", "Stockholm"].includes(query);
      if (measureVisible) {
        await input.evaluate((element: HTMLInputElement) => {
          element.addEventListener("input", () => {
            (window as typeof window & { __airStep1LastInputAt?: number }).__airStep1LastInputAt = window.performance.now();
          }, { once: true });
        });
      }
      await input.fill(query);
      if (measureVisible) {
        const boxId = await box.getAttribute("id");
        await page.waitForFunction(({ id, code }) => {
          const suggestionBox = document.getElementById(id || "");
          return Array.from(suggestionBox?.querySelectorAll('button[role="option"]') || [])
            .some((option) => option.textContent?.includes(code));
        }, { id: boxId, code: expectedCodes[0] }, { polling: "raf" });
        visibleMetrics[query] = await page.evaluate(() => {
          const started = (window as typeof window & { __airStep1LastInputAt?: number }).__airStep1LastInputAt || 0;
          return Math.round((window.performance.now() - started) * 10) / 10;
        });
      }
      for (const code of expectedCodes) {
        await expect(box.locator('button[role="option"]', { hasText: code }).first()).toBeVisible();
      }
    }
  }

  for (const query of ["Bruxelles", "Stockholm"]) {
    expect(httpMetrics.get(query)?.source).toBe("local");
  }
  console.log(`AIR_STEP1_PERF ${JSON.stringify({ visibleMetrics, httpMetrics: Object.fromEntries(httpMetrics) })}`);
});

test("French TC6 AIR Step 1 preserves scripts and shows Bruxelles choices", async ({ page }) => {
  const pageErrors: string[] = [];
  page.on("pageerror", (error) => pageErrors.push(error.message));

  await page.goto(appUrl("/fly-ny?lang=fr"));
  await page.locator('a[href*="/flow/air/completed?tc6=1"]').first().click();
  await page.goto(appUrl("/flow/entitlements?tc6=1&lang=fr"));

  const departure = page.locator('input[name="dep_station"][data-airport-preselector="departure"]').first();
  await expect(departure).toBeVisible();
  await expect(departure).toHaveAttribute("data-airport-preselector-bound", "true");

  await departure.fill("Bruxelles");
  const box = await suggestionBox(page, departure);
  await expect(box.locator('button[role="option"]', { hasText: "BRU" }).first()).toBeVisible();
  await expect(box.locator('button[role="option"]', { hasText: "CRL" }).first()).toBeVisible();
  expect(pageErrors).toEqual([]);
});

import { expect, test } from "@playwright/test";

const stockholmNodes = [
  {
    id: "air-arn",
    mode: "air",
    name: "Stockholm-Arlanda Airport",
    code: "ARN",
    iata_code: "ARN",
    icao_code: "ESSA",
    country: "SE",
    in_eu: true,
    node_type: "airport",
    source: "local",
  },
  {
    id: "air-bma",
    mode: "air",
    name: "Stockholm-Bromma Airport",
    code: "BMA",
    country: "SE",
    in_eu: true,
    node_type: "airport",
    source: "local",
  },
];

const configuredBase = process.env.RAIL_APP_BASE_URL?.replace(/\/+$/, "");
const airFlowUrl = configuredBase ? `${configuredBase}/flow/air/ongoing` : "/flow/air/ongoing";

test.describe("AIR airport autocomplete", () => {
  test("debounces normal typing, renders quickly, caches, and supports the keyboard", async ({ page }) => {
    let airportRequests = 0;
    await page.route("**/api/transport-nodes/search**", async (route) => {
      airportRequests += 1;
      await new Promise((resolve) => setTimeout(resolve, 20));
      await route.fulfill({ status: 200, contentType: "application/json", body: JSON.stringify({ data: { nodes: stockholmNodes } }) });
    });

    await page.goto(airFlowUrl);
    const input = page.locator('input[name="dep_station"]').first();
    await expect(input).toBeVisible();
    await input.evaluate((element: HTMLInputElement) => { element.value = ""; });
    await page.evaluate(() => {
      const timing = { lastInputAt: 0, renderedAt: 0 };
      (window as typeof window & { __airAutocompleteTiming?: typeof timing }).__airAutocompleteTiming = timing;
      document.querySelector('input[name="dep_station"]')?.addEventListener("input", () => {
        timing.lastInputAt = performance.now();
      });
      new MutationObserver(() => {
        if (!timing.renderedAt && document.querySelector('body > .node-suggest.portal button[role="option"]')) {
          timing.renderedAt = performance.now();
        }
      }).observe(document.body, { childList: true, subtree: true });
    });

    await input.pressSequentially("Stockholm", { delay: 35 });
    const options = page.locator('body > .node-suggest.portal button[role="option"]');
    await expect(options.first()).toContainText("Stockholm-Arlanda Airport");
    const renderMs = await page.evaluate(() => {
      const timing = (window as typeof window & { __airAutocompleteTiming?: { lastInputAt: number; renderedAt: number } }).__airAutocompleteTiming;
      return (timing?.renderedAt ?? 0) - (timing?.lastInputAt ?? 0);
    });
    console.log(`[air-autocomplete] last keystroke to rendered options: ${renderMs} ms`);
    expect(renderMs).toBeLessThan(200);
    expect(airportRequests).toBe(1);

    await input.fill("");
    await input.fill("Stockholm");
    await expect(options.first()).toContainText("Stockholm-Arlanda Airport");
    expect(airportRequests).toBe(1);

    await input.press("ArrowDown");
    await expect(options.first()).toHaveAttribute("aria-selected", "true");
    await input.press("Enter");
    await expect(input).toHaveValue("Stockholm-Arlanda Airport");
    await expect(page.locator('input[name="dep_station_lookup_id"]').first()).toHaveValue("air-arn");
  });

  test("aborts stale work and only renders the latest query", async ({ page }) => {
    const requestedQueries: string[] = [];
    await page.route("**/api/transport-nodes/search**", async (route) => {
      const query = new URL(route.request().url()).searchParams.get("q") ?? "";
      requestedQueries.push(query);
      const stale = query === "St";
      await new Promise((resolve) => setTimeout(resolve, stale ? 350 : 20));
      const nodes = stale
        ? [{ id: "air-stale", mode: "air", name: "Stale Airport", code: "OLD", country: "SE", node_type: "airport", source: "local" }]
        : stockholmNodes;
      await route.fulfill({ status: 200, contentType: "application/json", body: JSON.stringify({ data: { nodes } }) });
    });

    await page.goto(airFlowUrl);
    const input = page.locator('input[name="dep_station"]').first();
    await input.evaluate((element: HTMLInputElement) => { element.value = ""; });
    await input.fill("St");
    await page.waitForTimeout(180);
    await input.fill("Stockholm");

    const options = page.locator('body > .node-suggest.portal button[role="option"]');
    await expect(options.first()).toContainText("Stockholm-Arlanda Airport");
    await page.waitForTimeout(400);
    await expect(options).toHaveCount(2);
    await expect(options.filter({ hasText: "Stale Airport" })).toHaveCount(0);
    expect(requestedQueries).toEqual(["St", "Stockholm"]);
  });
});

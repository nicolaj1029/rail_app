import { mockJourneys } from "../config/mock-data.mjs";
import { compareJourneyMatch, normalizeRailDeparture } from "../lib/normalizer.mjs";
import { searchMajorStations } from "../lib/station-catalog.mjs";
import { canonicalizeStationName } from "../lib/station-aliases.mjs";

export async function searchJourneys(criteria = {}) {
  const items = mockJourneys
    .filter((item) => compareJourneyMatch(item, criteria))
    .map((item) => normalizeRailDeparture(item));
  return { items, source: "mock" };
}

export async function searchStations(query = "", limit = 8) {
  const catalogItems = searchMajorStations(query, limit);
  if (catalogItems.length > 0) {
    return { items: catalogItems };
  }

  const canonical = canonicalizeStationName(query);
  const stations = new Map();

  for (const item of mockJourneys) {
    for (const pair of [
      [item.origin_station_name, item.origin_station_code],
      [item.destination_station_name, item.destination_station_code]
    ]) {
      const name = canonicalizeStationName(String(pair[0] || ""));
      const code = String(pair[1] || "");
      if (!name) continue;
      stations.set(`${name}::${code}`, {
        id: code || name,
        name,
        source: "mock",
        confidence: 0.5
      });
    }
  }

  const items = [...stations.values()]
    .filter((item) => {
      if (!canonical) return true;
      return item.name === canonical || item.name.toLowerCase().includes(String(query).toLowerCase());
    })
    .slice(0, limit);

  return { items };
}

export async function getDepartures(criteria = {}) {
  const station = canonicalizeStationName(String(criteria.station || ""));
  const date = String(criteria.date || "").trim();
  const items = mockJourneys
    .filter((item) => {
      if (station && canonicalizeStationName(String(item.origin_station_name || "")) !== station) return false;
      if (date && String(item.planned_departure_at || "").slice(0, 10) !== date) return false;
      return true;
    })
    .map((item) => normalizeRailDeparture(item));

  return { items, source: "mock" };
}

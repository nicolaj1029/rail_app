import { majorStations } from "../config/major-stations.mjs";
import { stationKey } from "./station-aliases.mjs";

function allNames(station) {
  return [station.canonicalName, ...(station.aliases || [])].filter(Boolean);
}

export function findMajorStation(query = "") {
  const needle = stationKey(query);
  if (!needle) return null;
  for (const station of majorStations) {
    for (const name of allNames(station)) {
      if (stationKey(name) === needle) {
        return station;
      }
    }
  }
  return null;
}

export function searchMajorStations(query = "", limit = 8) {
  const needle = stationKey(query);
  if (!needle) return [];

  const scored = [];
  for (const station of majorStations) {
    let score = 0;
    for (const name of allNames(station)) {
      const key = stationKey(name);
      if (key === needle) {
        score = Math.max(score, 100);
      } else if (key.startsWith(needle) || needle.startsWith(key)) {
        score = Math.max(score, 80);
      } else if (key.includes(needle) || needle.includes(key)) {
        score = Math.max(score, 60);
      }
    }
    if (score === 0) continue;
    scored.push({
      id: station.transportRestId || station.canonicalName,
      name: station.canonicalName,
      type: "station",
      source: "catalog",
      confidence: score >= 100 ? 0.96 : score >= 80 ? 0.88 : 0.76,
      country: station.country || null,
      _score: score
    });
  }

  scored.sort((a, b) => b._score - a._score || a.name.localeCompare(b.name));
  return scored.slice(0, limit).map(({ _score, ...item }) => item);
}

export function preferredTransportQueries(query = "") {
  const station = findMajorStation(query);
  if (!station) return [];
  const options = [station.transportRestQuery, station.canonicalName, ...(station.aliases || [])].filter(Boolean);
  const seen = new Set();
  return options.filter((value) => {
    const key = stationKey(value);
    if (!key || seen.has(key)) return false;
    seen.add(key);
    return true;
  });
}

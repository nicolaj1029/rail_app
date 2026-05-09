import crypto from "node:crypto";
import { findMajorStation } from "../lib/station-catalog.mjs";
import { canonicalizeStationName, stationKey } from "../lib/station-aliases.mjs";
import { normalizeRailDeparture } from "../lib/normalizer.mjs";

const defaultLegDuration = 180;
const fallbackTransferMinutes = 22;

const legDurations = new Map([
  ["Kobenhavn H|Odense", 85],
  ["Odense|Aarhus H", 95],
  ["Aarhus H|Aalborg", 80],
  ["Malmo C|Kobenhavn H", 40],
  ["Goteborg C|Malmo C", 155],
  ["Stockholm C|Goteborg C", 200],
  ["Oslo S|Goteborg C", 220],
  ["Hamburg Hbf|Flensburg", 110],
  ["Hamburg Hbf|Berlin Hbf", 115],
  ["Hamburg Hbf|Koln Hbf", 250],
  ["Hamburg Hbf|Frankfurt(Main)Hbf", 240],
  ["Berlin Hbf|Praha hl.n.", 260],
  ["Berlin Hbf|Warszawa Centralna", 335],
  ["Berlin Hbf|Hamburg Hbf", 115],
  ["Frankfurt(Main)Hbf|Koln Hbf", 70],
  ["Frankfurt(Main)Hbf|Munchen Hbf", 195],
  ["Frankfurt(Main)Hbf|Zurich HB", 245],
  ["Frankfurt(Main)Hbf|Paris Gare du Nord", 235],
  ["Frankfurt(Main)Hbf|Bruxelles Midi", 200],
  ["Koln Hbf|Amsterdam Centraal", 170],
  ["Koln Hbf|Bruxelles Midi", 115],
  ["Koln Hbf|Paris Gare du Nord", 205],
  ["Koln Hbf|Frankfurt(Main)Hbf", 70],
  ["Munchen Hbf|Wien Hbf", 240],
  ["Munchen Hbf|Zurich HB", 235],
  ["Munchen Hbf|Milano Centrale", 430],
  ["Munchen Hbf|Budapest-Keleti", 410],
  ["Munchen Hbf|Praha hl.n.", 330],
  ["Zurich HB|Milano Centrale", 220],
  ["Zurich HB|Geneve", 180],
  ["Paris Gare du Nord|Bruxelles Midi", 95],
  ["Paris Gare du Nord|Lyon Part-Dieu", 120],
  ["Lyon Part-Dieu|Marseille Saint-Charles", 100],
  ["Lyon Part-Dieu|Milano Centrale", 300],
  ["Lyon Part-Dieu|Barcelona Sants", 300],
  ["Milano Centrale|Roma Termini", 190],
  ["Roma Termini|Napoli Centrale", 75],
  ["Praha hl.n.|Wien Hbf", 250],
  ["Praha hl.n.|Brno hl.n.", 150],
  ["Brno hl.n.|Wien Hbf", 105],
  ["Wien Hbf|Budapest-Keleti", 160],
  ["Wien Hbf|Bratislava hl.st.", 65],
  ["Bratislava hl.st.|Budapest-Keleti", 150],
  ["Bratislava hl.st.|Praha hl.n.", 240],
  ["Budapest-Keleti|Budapest-Kelenfold", 20],
  ["Budapest-Keleti|Ljubljana", 470],
  ["Ljubljana|Zagreb Glavni kolodvor", 140],
  ["Zagreb Glavni kolodvor|Beograd Centar", 360],
  ["Bucuresti Nord|Sofia Central Station", 540],
  ["Warszawa Centralna|Krakow Glowny", 155],
  ["Warszawa Centralna|Vilnius", 480],
  ["Vilnius|Riga", 260],
  ["Riga|Tallinn", 255],
  ["Luxembourg|Bruxelles Midi", 190],
  ["Barcelona Sants|Madrid Puerta de Atocha", 165],
  ["Barcelona Sants|Lyon Part-Dieu", 300],
  ["Madrid Puerta de Atocha|Valencia Joaquin Sorolla", 115],
  ["Madrid Puerta de Atocha|Sevilla Santa Justa", 165],
  ["Lisboa Oriente|Madrid Puerta de Atocha", 600],
  ["Lisboa Oriente|Porto Campanha", 175]
]);

const countryPrimaryHubs = new Map([
  ["DK", ["Kobenhavn H", "Odense", "Aarhus H", "Aalborg"]],
  ["DE", ["Hamburg Hbf", "Berlin Hbf", "Koln Hbf", "Frankfurt(Main)Hbf", "Munchen Hbf"]],
  ["NL", ["Amsterdam Centraal", "Rotterdam Centraal"]],
  ["BE", ["Bruxelles Midi"]],
  ["FR", ["Paris Gare du Nord", "Paris Gare de Lyon", "Lyon Part-Dieu", "Marseille Saint-Charles"]],
  ["AT", ["Wien Hbf", "Salzburg Hbf"]],
  ["CH", ["Zurich HB", "Geneve"]],
  ["IT", ["Milano Centrale", "Roma Termini", "Napoli Centrale", "Venezia Mestre", "Torino Porta Susa"]],
  ["CZ", ["Praha hl.n.", "Brno hl.n."]],
  ["PL", ["Warszawa Centralna", "Krakow Glowny"]],
  ["HU", ["Budapest-Keleti", "Budapest-Kelenfold"]],
  ["SK", ["Bratislava hl.st."]],
  ["SI", ["Ljubljana"]],
  ["HR", ["Zagreb Glavni kolodvor"]],
  ["RO", ["Bucuresti Nord"]],
  ["BG", ["Sofia Central Station"]],
  ["RS", ["Beograd Centar"]],
  ["LT", ["Vilnius"]],
  ["LV", ["Riga"]],
  ["EE", ["Tallinn"]],
  ["LU", ["Luxembourg"]],
  ["SE", ["Stockholm C", "Goteborg C", "Malmo C"]],
  ["NO", ["Oslo S"]],
  ["ES", ["Barcelona Sants", "Madrid Puerta de Atocha", "Valencia Joaquin Sorolla", "Sevilla Santa Justa"]],
  ["PT", ["Lisboa Oriente", "Porto Campanha"]]
]);

const hubGraph = new Map();

function link(a, b) {
  if (!hubGraph.has(a)) hubGraph.set(a, new Set());
  if (!hubGraph.has(b)) hubGraph.set(b, new Set());
  hubGraph.get(a).add(b);
  hubGraph.get(b).add(a);
}

[
  ["Kobenhavn H", "Odense"],
  ["Odense", "Aarhus H"],
  ["Aarhus H", "Aalborg"],
  ["Kobenhavn H", "Malmo C"],
  ["Malmo C", "Goteborg C"],
  ["Goteborg C", "Stockholm C"],
  ["Goteborg C", "Oslo S"],
  ["Kobenhavn H", "Hamburg Hbf"],
  ["Hamburg Hbf", "Berlin Hbf"],
  ["Hamburg Hbf", "Koln Hbf"],
  ["Hamburg Hbf", "Frankfurt(Main)Hbf"],
  ["Hamburg Hbf", "Flensburg"],
  ["Berlin Hbf", "Praha hl.n."],
  ["Berlin Hbf", "Warszawa Centralna"],
  ["Koln Hbf", "Amsterdam Centraal"],
  ["Koln Hbf", "Rotterdam Centraal"],
  ["Koln Hbf", "Bruxelles Midi"],
  ["Koln Hbf", "Paris Gare du Nord"],
  ["Koln Hbf", "Frankfurt(Main)Hbf"],
  ["Frankfurt(Main)Hbf", "Paris Gare du Nord"],
  ["Frankfurt(Main)Hbf", "Bruxelles Midi"],
  ["Frankfurt(Main)Hbf", "Munchen Hbf"],
  ["Frankfurt(Main)Hbf", "Zurich HB"],
  ["Munchen Hbf", "Wien Hbf"],
  ["Munchen Hbf", "Zurich HB"],
  ["Munchen Hbf", "Milano Centrale"],
  ["Munchen Hbf", "Budapest-Keleti"],
  ["Munchen Hbf", "Praha hl.n."],
  ["Praha hl.n.", "Brno hl.n."],
  ["Praha hl.n.", "Wien Hbf"],
  ["Praha hl.n.", "Bratislava hl.st."],
  ["Wien Hbf", "Bratislava hl.st."],
  ["Wien Hbf", "Budapest-Keleti"],
  ["Bratislava hl.st.", "Budapest-Keleti"],
  ["Budapest-Keleti", "Budapest-Kelenfold"],
  ["Budapest-Keleti", "Ljubljana"],
  ["Ljubljana", "Zagreb Glavni kolodvor"],
  ["Zagreb Glavni kolodvor", "Beograd Centar"],
  ["Warszawa Centralna", "Krakow Glowny"],
  ["Warszawa Centralna", "Vilnius"],
  ["Vilnius", "Riga"],
  ["Riga", "Tallinn"],
  ["Zurich HB", "Geneve"],
  ["Zurich HB", "Milano Centrale"],
  ["Paris Gare du Nord", "Paris Gare de Lyon"],
  ["Paris Gare de Lyon", "Lyon Part-Dieu"],
  ["Lyon Part-Dieu", "Marseille Saint-Charles"],
  ["Lyon Part-Dieu", "Milano Centrale"],
  ["Lyon Part-Dieu", "Barcelona Sants"],
  ["Milano Centrale", "Roma Termini"],
  ["Roma Termini", "Napoli Centrale"],
  ["Barcelona Sants", "Madrid Puerta de Atocha"],
  ["Madrid Puerta de Atocha", "Valencia Joaquin Sorolla"],
  ["Madrid Puerta de Atocha", "Sevilla Santa Justa"],
  ["Lisboa Oriente", "Madrid Puerta de Atocha"],
  ["Lisboa Oriente", "Porto Campanha"],
  ["Bruxelles Midi", "Luxembourg"]
].forEach(([a, b]) => link(a, b));

function stationFromQuery(query = "") {
  const direct = findMajorStation(query);
  if (direct) return direct;

  const key = stationKey(canonicalizeStationName(query));
  if (key.includes("paris")) return findMajorStation("Paris Gare du Nord");
  if (key.includes("brussels")) return findMajorStation("Bruxelles Midi");
  if (key.includes("cologne")) return findMajorStation("Koln Hbf");
  if (key.includes("gothenburg")) return findMajorStation("Goteborg C");
  if (key.includes("malmo")) return findMajorStation("Malmo C");
  if (key.includes("budapest")) return findMajorStation("Budapest-Keleti");
  if (key.includes("vienna")) return findMajorStation("Wien Hbf");
  if (key.includes("munich")) return findMajorStation("Munchen Hbf");
  if (key.includes("milan")) return findMajorStation("Milano Centrale");
  if (key.includes("rome")) return findMajorStation("Roma Termini");
  if (key.includes("warsaw")) return findMajorStation("Warszawa Centralna");
  if (key.includes("prague")) return findMajorStation("Praha hl.n.");
  if (key.includes("lisbon")) return findMajorStation("Lisboa Oriente");
  if (key.includes("porto")) return findMajorStation("Porto Campanha");
  return null;
}

function stationLabel(station, rawQuery) {
  return station?.canonicalName || canonicalizeStationName(rawQuery || "") || String(rawQuery || "").trim();
}

function durationBetween(a, b) {
  const direct = legDurations.get(`${a}|${b}`);
  if (Number.isFinite(direct)) return direct;
  const reverse = legDurations.get(`${b}|${a}`);
  if (Number.isFinite(reverse)) return reverse;
  return defaultLegDuration;
}

function addUnique(target, name) {
  if (!name) return;
  if (target[target.length - 1] === name) return;
  target.push(name);
}

function uniquePath(path) {
  const out = [];
  for (const item of path) addUnique(out, item);
  return out;
}

function hubCandidates(station) {
  if (!station?.country) return [];
  return countryPrimaryHubs.get(station.country) || [];
}

function shortestHubPath(start, target) {
  if (!start || !target) return [];
  if (start === target) return [start];
  const queue = [[start]];
  const seen = new Set([start]);

  while (queue.length > 0) {
    const path = queue.shift();
    const current = path[path.length - 1];
    const neighbors = hubGraph.get(current) || new Set();
    for (const next of neighbors) {
      if (seen.has(next)) continue;
      const nextPath = [...path, next];
      if (next === target) return nextPath;
      seen.add(next);
      queue.push(nextPath);
    }
  }

  return [];
}

function firstReachableHub(station, preferredTargetHubs = []) {
  const options = hubCandidates(station);
  if (!options.length) return null;
  if (!preferredTargetHubs.length) return options[0];

  let best = null;
  let bestLength = Number.POSITIVE_INFINITY;
  for (const originHub of options) {
    for (const targetHub of preferredTargetHubs) {
      const path = shortestHubPath(originHub, targetHub);
      if (!path.length) continue;
      if (path.length < bestLength) {
        best = originHub;
        bestLength = path.length;
      }
    }
  }
  return best || options[0];
}

function buildHubRoute(fromStation, toStation) {
  const fromName = fromStation?.canonicalName || "";
  const toName = toStation?.canonicalName || "";
  if (!fromName || !toName || fromName === toName) return [];

  const destinationHubs = hubCandidates(toStation);
  const originHub = firstReachableHub(fromStation, destinationHubs);
  const destinationHub = firstReachableHub(toStation, originHub ? [originHub] : []);
  const hubPath = originHub && destinationHub ? shortestHubPath(originHub, destinationHub) : [];

  const path = [];
  addUnique(path, fromName);
  if (originHub && originHub !== fromName) addUnique(path, originHub);
  for (const hub of hubPath) addUnique(path, hub);
  if (destinationHub && destinationHub !== toName) addUnique(path, destinationHub);
  addUnique(path, toName);
  return uniquePath(path);
}

function buildVariants(fromStation, toStation) {
  const fromName = fromStation?.canonicalName || "";
  const toName = toStation?.canonicalName || "";
  if (!fromName || !toName || fromName === toName) return [];

  const variants = [];

  if (fromStation?.country === "DK" && toStation?.country === "DK") {
    if (fromName === "Kobenhavn H" && toName === "Aalborg") variants.push(["Kobenhavn H", "Odense", "Aarhus H", "Aalborg"]);
    else if (fromName === "Kobenhavn H" && toName === "Aarhus H") variants.push(["Kobenhavn H", "Odense", "Aarhus H"]);
  }

  const generic = buildHubRoute(fromStation, toStation);
  if (generic.length) variants.push(generic);

  const altDestinationHubs = hubCandidates(toStation).filter((hub) => hub !== generic[generic.length - 2]);
  if (altDestinationHubs.length) {
    const originHub = firstReachableHub(fromStation, altDestinationHubs);
    if (originHub) {
      for (const targetHub of altDestinationHubs.slice(0, 2)) {
        const altHubPath = shortestHubPath(originHub, targetHub);
        if (!altHubPath.length) continue;
        const alt = [];
        addUnique(alt, fromName);
        if (originHub !== fromName) addUnique(alt, originHub);
        for (const hub of altHubPath) addUnique(alt, hub);
        if (targetHub !== toName) addUnique(alt, targetHub);
        addUnique(alt, toName);
        variants.push(uniquePath(alt));
      }
    }
  }

  variants.push([fromName, toName]);

  const unique = [];
  const seen = new Set();
  for (const path of variants.map(uniquePath)) {
    if (path.length < 2) continue;
    const key = path.join("|");
    if (seen.has(key)) continue;
    seen.add(key);
    unique.push(path);
  }
  return unique.slice(0, 3);
}

function isoWithOffset(date, hour, minute = 0) {
  const hh = String(hour).padStart(2, "0");
  const mm = String(minute).padStart(2, "0");
  return `${date}T${hh}:${mm}:00+02:00`;
}

function addMinutes(isoString, minutes) {
  const date = new Date(isoString);
  return new Date(date.getTime() + minutes * 60000).toISOString();
}

function callingPointsFromPath(path, departureIso) {
  const points = [];
  let cursor = departureIso;
  for (let i = 0; i < path.length; i += 1) {
    const station = path[i];
    if (i === 0) {
      points.push({
        station_name: station,
        station_code: null,
        planned_arrival_at: null,
        estimated_arrival_at: null,
        actual_arrival_at: null,
        planned_departure_at: new Date(cursor).toISOString(),
        estimated_departure_at: new Date(cursor).toISOString(),
        actual_departure_at: null,
        cancelled: false,
        platform: null
      });
      continue;
    }
    const prev = path[i - 1];
    cursor = addMinutes(cursor, durationBetween(prev, station));
    const arrival = new Date(cursor).toISOString();
    const departure = i === path.length - 1 ? null : new Date(addMinutes(cursor, fallbackTransferMinutes)).toISOString();
    points.push({
      station_name: station,
      station_code: null,
      planned_arrival_at: arrival,
      estimated_arrival_at: arrival,
      actual_arrival_at: null,
      planned_departure_at: departure,
      estimated_departure_at: departure,
      actual_departure_at: null,
      cancelled: false,
      platform: null
    });
    if (departure) cursor = addMinutes(cursor, fallbackTransferMinutes);
  }
  return points;
}

function buildDeparture(path, criteria, index) {
  const date = String(criteria.date || "").trim();
  const time = String(criteria.time || "08:00").trim() || "08:00";
  const [hourRaw, minuteRaw] = time.split(":");
  const departureIso = isoWithOffset(date, Number.parseInt(hourRaw || "8", 10) || 8, Number.parseInt(minuteRaw || "0", 10) || 0);
  const points = callingPointsFromPath(path, departureIso);
  const first = points[0];
  const last = points[points.length - 1];
  const transferCount = Math.max(0, path.length - 2);
  const via = path.slice(1, -1);
  const product = path.length >= 4 ? "EC" : path.length === 3 ? "IC" : "Train";
  const serviceName = path.length >= 4 ? "International rail corridor" : "Plausibel togrejse";
  const operatorHint = String(criteria.operator_hint || "").trim();

  return normalizeRailDeparture({
    id: `ai-${crypto.createHash("md5").update(JSON.stringify([path, criteria, index])).digest("hex").slice(0, 12)}`,
    source: "ai_fallback",
    confidence: transferCount > 0 ? 0.24 : 0.3,
    train_number: null,
    service_name: serviceName,
    line_name: transferCount > 0 ? `via ${via.join(" · ")}` : "Direkte plausibel forbindelse",
    product,
    operator_code: null,
    operator_name: operatorHint || null,
    infrastructure_manager: null,
    origin_station_name: path[0],
    origin_station_code: null,
    destination_station_name: path[path.length - 1],
    destination_station_code: null,
    planned_departure_at: first?.planned_departure_at || departureIso,
    estimated_departure_at: first?.planned_departure_at || departureIso,
    actual_departure_at: null,
    planned_arrival_at: last?.planned_arrival_at || null,
    estimated_arrival_at: last?.planned_arrival_at || null,
    actual_arrival_at: null,
    departure_delay_minutes: null,
    arrival_delay_minutes: null,
    status: "unknown",
    platform_planned: null,
    platform_actual: null,
    cancelled_section_from: null,
    cancelled_section_to: null,
    calling_points: points,
    disruption_reason_public: "AI fallback: plausibel forbindelse genereret fra et EU-wide hub-net. Kræver brugerbekræftelse.",
    disruption_reason_code: "AI_FALLBACK",
    remarks: [
      "AI fallback route only",
      "Not legal truth",
      transferCount > 0 ? `Via ${via.join(", ")}` : "Direct plausible route"
    ],
    raw: {
      provider_hint: "ai_fallback",
      transfer_count: transferCount,
      transfer_station_names: via,
      rail_leg_count: path.length - 1,
      leg_count: path.length - 1,
      has_connections: transferCount > 0,
      generated_path: path,
      generated_from_country: stationFromQuery(criteria.from_station)?.country || null,
      generated_to_country: stationFromQuery(criteria.to_station)?.country || null
    }
  });
}

export async function searchJourneys(criteria = {}) {
  const fromStation = stationFromQuery(String(criteria.from_station || ""));
  const toStation = stationFromQuery(String(criteria.to_station || ""));
  const fromName = stationLabel(fromStation, criteria.from_station);
  const toName = stationLabel(toStation, criteria.to_station);
  const date = String(criteria.date || "").trim();
  if (!fromName || !toName || !date) {
    return { items: [], source: "ai_fallback", error: "Missing AI fallback journey criteria" };
  }

  const variants = buildVariants(
    fromStation ? { ...fromStation, canonicalName: fromName } : { canonicalName: fromName, country: null },
    toStation ? { ...toStation, canonicalName: toName } : { canonicalName: toName, country: null }
  );
  const items = variants.map((path, index) => buildDeparture(path, criteria, index));
  return { items, source: "ai_fallback" };
}

export async function searchStations() {
  return { items: [], source: "ai_fallback" };
}

export async function getDepartures() {
  return { items: [], source: "ai_fallback" };
}

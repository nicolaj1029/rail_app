import { env } from "../config/env.mjs";
import { fetchJson, withQuery } from "../lib/http.mjs";
import { findMajorStation, preferredTransportQueries, searchMajorStations } from "../lib/station-catalog.mjs";
import { canonicalizeStationName, expandStationQueries, stationMatches } from "../lib/station-aliases.mjs";
import { normalizeRailDeparture } from "../lib/normalizer.mjs";

const railProducts = new Set([
  "national",
  "nationalexpress",
  "regional",
  "regionalexpress",
  "suburban",
  "express",
  "train",
  "ice",
  "ic",
  "ec",
  "re",
  "rb",
  "s"
]);

function pickTime(source, keys) {
  for (const key of keys) {
    const value = source?.[key];
    if (value) return String(value);
  }
  return null;
}

function delayMinutes(planned, estimated) {
  if (!planned || !estimated) return null;
  const plannedDate = new Date(planned);
  const estimatedDate = new Date(estimated);
  if (Number.isNaN(plannedDate.getTime()) || Number.isNaN(estimatedDate.getTime())) return null;
  return Math.round((estimatedDate.getTime() - plannedDate.getTime()) / 60000);
}

function isRailLeg(leg) {
  const line = leg?.line || {};
  const product = String(line.product || line.mode || "").trim().toLowerCase();
  return railProducts.has(product);
}

async function requestJson(path, query) {
  const url = withQuery(`${env.transportRestBaseUrl}${path}`, query);
  const response = await fetchJson(url, { timeoutMs: env.timeoutMs, retries: env.retries });
  return response.ok ? response.json : null;
}

async function resolveLocationId(query) {
  const catalogMatch = findMajorStation(query);
  if (catalogMatch?.transportRestId) {
    return { id: catalogMatch.transportRestId, name: catalogMatch.canonicalName };
  }

  const candidates = [...preferredTransportQueries(query), ...expandStationQueries(query)];

  for (const candidate of candidates) {
    const payload = await requestJson("/locations", {
      query: candidate,
      results: 6,
      addresses: false,
      poi: false
    });
    const items = Array.isArray(payload) ? payload : Array.isArray(payload?.items) ? payload.items : [];
    let fallback = "";
    for (const item of items) {
      if (!item || typeof item !== "object") continue;
      const type = String(item.type || "station").toLowerCase();
      const id = String(item.id || "").trim();
      if (!id || !["station", "stop"].includes(type)) continue;
      if (!fallback) fallback = id;
      if (stationMatches(String(item.name || ""), candidate)) {
        return { id, name: canonicalizeStationName(String(item.name || "")) };
      }
    }
    if (fallback) {
      return { id: fallback, name: canonicalizeStationName(candidate) };
    }
  }

  return { id: "", name: canonicalizeStationName(query) };
}

function mapCallingPoints(stopovers = []) {
  return stopovers
    .filter((stopover) => stopover && typeof stopover === "object")
    .map((stopover) => ({
      station_name: canonicalizeStationName(String(stopover.stop?.name || "")),
      station_code: String(stopover.stop?.id || "") || null,
      planned_arrival_at: pickTime(stopover, ["plannedArrival"]),
      estimated_arrival_at: pickTime(stopover, ["arrival"]),
      actual_arrival_at: pickTime(stopover, ["arrival"]),
      planned_departure_at: pickTime(stopover, ["plannedDeparture"]),
      estimated_departure_at: pickTime(stopover, ["departure"]),
      actual_departure_at: pickTime(stopover, ["departure"]),
      cancelled: Boolean(stopover.cancelled),
      platform: String(stopover.platform || stopover.plannedPlatform || "").trim() || null
    }));
}

function appendCallingPoint(target, point) {
  if (!point || !point.station_name) return;
  const last = target[target.length - 1];
  if (last && last.station_name === point.station_name && last.planned_departure_at === point.planned_departure_at && last.planned_arrival_at === point.planned_arrival_at) {
    return;
  }
  target.push(point);
}

function mapJourneyCallingPoints(railLegs = []) {
  const points = [];
  railLegs.forEach((leg, index) => {
    const originName = canonicalizeStationName(String(leg.origin?.name || ""));
    const destinationName = canonicalizeStationName(String(leg.destination?.name || ""));
    const originPoint = {
      station_name: originName,
      station_code: String(leg.origin?.id || "") || null,
      planned_arrival_at: null,
      estimated_arrival_at: null,
      actual_arrival_at: null,
      planned_departure_at: pickTime(leg, ["plannedDeparture", "departure", "plannedWhen"]),
      estimated_departure_at: pickTime(leg, ["departure", "when"]),
      actual_departure_at: pickTime(leg, ["departure", "when"]),
      cancelled: Boolean(leg.cancelled),
      platform: String(leg.platform || leg.plannedPlatform || "").trim() || null
    };
    const destinationPoint = {
      station_name: destinationName,
      station_code: String(leg.destination?.id || "") || null,
      planned_arrival_at: pickTime(leg, ["plannedArrival", "arrival", "plannedWhen"]),
      estimated_arrival_at: pickTime(leg, ["arrival", "when"]),
      actual_arrival_at: pickTime(leg, ["arrival", "when"]),
      planned_departure_at: null,
      estimated_departure_at: null,
      actual_departure_at: null,
      cancelled: Boolean(leg.cancelled),
      platform: null
    };

    if (index === 0) {
      appendCallingPoint(points, originPoint);
    }
    mapCallingPoints(leg.stopovers || []).forEach((point) => appendCallingPoint(points, point));
    appendCallingPoint(points, destinationPoint);
  });
  return points;
}

function extractTransferStationNames(railLegs = []) {
  const names = [];
  for (let index = 0; index < railLegs.length - 1; index += 1) {
    const currentDestination = canonicalizeStationName(String(railLegs[index]?.destination?.name || ""));
    const nextOrigin = canonicalizeStationName(String(railLegs[index + 1]?.origin?.name || ""));
    const candidate = nextOrigin || currentDestination;
    if (!candidate) continue;
    if (names[names.length - 1] === candidate) continue;
    names.push(candidate);
  }
  return names;
}

function mapJourney(journey, criteria) {
  const legs = Array.isArray(journey?.legs) ? journey.legs.filter((leg) => leg && typeof leg === "object") : [];
  if (!legs.length) return null;

  const railLegs = legs.filter(isRailLeg);
  if (!railLegs.length) return null;

  const first = railLegs[0];
  const last = railLegs[railLegs.length - 1];
  const cancelledCount = railLegs.filter((leg) => Boolean(leg.cancelled)).length;
  const allCancelled = cancelledCount === railLegs.length;
  const someCancelled = cancelledCount > 0;
  const hasReplacement = legs.some((leg) => !isRailLeg(leg) && !leg.walking);

  const plannedDep = pickTime(first, ["plannedDeparture", "departure", "plannedWhen"]);
  const estimatedDep = pickTime(first, ["departure", "when"]);
  const plannedArr = pickTime(last, ["plannedArrival", "arrival", "plannedWhen"]);
  const estimatedArr = pickTime(last, ["arrival", "when"]);

  const departureDelay = delayMinutes(plannedDep, estimatedDep);
  const arrivalDelay = delayMinutes(plannedArr, estimatedArr);

  let status = "planned";
  if (allCancelled) status = "cancelled";
  else if (someCancelled) status = "partially_cancelled";
  else if (hasReplacement) status = "replacement_transport";
  else if ((arrivalDelay ?? 0) > 0 || (departureDelay ?? 0) > 0) status = "delayed";

  const line = first.line || {};
  const operator = line.operator || {};
  const remarks = []
    .concat(Array.isArray(journey.remarks) ? journey.remarks : [])
    .map((remark) => String(remark?.text || remark?.summary || "").trim())
    .filter(Boolean);

  const journeyCallingPoints = mapJourneyCallingPoints(railLegs);
  const transferStationNames = extractTransferStationNames(railLegs);

  return normalizeRailDeparture({
    id: String(journey.id || journey.tripId || ""),
    source: "hafas",
    confidence: 0.78,
    train_number: String(line.name || line.fahrtNr || "").trim() || null,
    service_name: String(line.productName || line.mode || "Train").trim() || null,
    line_name: String(line.name || line.id || "").trim() || null,
    product: String(line.product || line.mode || "").trim() || null,
    operator_code: String(operator.id || "").trim() || null,
    operator_name: String(operator.name || criteria.operator_hint || "").trim() || null,
    infrastructure_manager: null,
    origin_station_name: String(first.origin?.name || criteria.from_station || "").trim() || null,
    origin_station_code: String(first.origin?.id || "").trim() || null,
    destination_station_name: String(last.destination?.name || criteria.to_station || "").trim() || null,
    destination_station_code: String(last.destination?.id || "").trim() || null,
    planned_departure_at: plannedDep,
    estimated_departure_at: estimatedDep,
    actual_departure_at: estimatedDep,
    planned_arrival_at: plannedArr,
    estimated_arrival_at: estimatedArr,
    actual_arrival_at: estimatedArr,
    departure_delay_minutes: departureDelay,
    arrival_delay_minutes: arrivalDelay,
    status,
    platform_planned: String(first.plannedPlatform || "").trim() || null,
    platform_actual: String(first.platform || "").trim() || null,
    cancelled_section_from: someCancelled ? String(first.origin?.name || "").trim() || null : null,
    cancelled_section_to: someCancelled ? String(last.destination?.name || "").trim() || null : null,
    calling_points: journeyCallingPoints,
    disruption_reason_public: remarks[0] || null,
    disruption_reason_code: null,
    remarks,
    raw: {
      provider_hint: "transport_rest",
      journey_id: String(journey.id || ""),
      trip_id: String(first.tripId || ""),
      line_id: String(line.id || ""),
      from_id: String(first.origin?.id || ""),
      to_id: String(last.destination?.id || ""),
      leg_count: legs.length,
      rail_leg_count: railLegs.length,
      transfer_count: Math.max(0, railLegs.length - 1),
      transfer_station_names: transferStationNames,
      has_connections: railLegs.length > 1,
      has_replacement: hasReplacement
    }
  });
}

export async function searchJourneys(criteria = {}) {
  const from = await resolveLocationId(String(criteria.from_station || ""));
  const to = await resolveLocationId(String(criteria.to_station || ""));
  const date = String(criteria.date || "").trim();
  const time = String(criteria.time || "").trim() || "00:00";
  if (!from.id || !to.id || !date) {
    return { items: [], source: "hafas", error: "Missing journey criteria" };
  }

  const departure = `${date}T${time}:00`;
  const payload = await requestJson("/journeys", {
    from: from.id,
    to: to.id,
    departure,
    results: 6,
    stopovers: true,
    remarks: true,
    routingMode: "HYBRID",
    language: "da"
  });

  const journeys = Array.isArray(payload?.journeys) ? payload.journeys : [];
  const items = journeys.map((journey) => mapJourney(journey, criteria)).filter(Boolean);
  return { items, source: "hafas" };
}

export async function searchStations(query = "", limit = 8) {
  if (!query.trim()) return { items: [] };
  const items = searchMajorStations(query, limit);
  const seen = new Set();
  for (const item of items) {
    seen.add(`${item.id}::${item.name}`);
  }
  for (const candidate of expandStationQueries(query)) {
    const payload = await requestJson("/locations", {
      query: candidate,
      results: limit,
      addresses: false,
      poi: false
    });
    const rawItems = Array.isArray(payload) ? payload : Array.isArray(payload?.items) ? payload.items : [];
    for (const item of rawItems) {
      if (!item || typeof item !== "object") continue;
      const id = String(item.id || "").trim() || null;
      const name = canonicalizeStationName(String(item.name || "").trim());
      if (!id || !name) continue;
      const key = `${id}::${name}`;
      if (seen.has(key)) continue;
      seen.add(key);
      items.push({
        id,
        name,
        type: String(item.type || "station"),
        source: "hafas",
        confidence: stationMatches(String(item.name || ""), candidate) ? 0.85 : 0.7
      });
      if (items.length >= limit) {
        return { items };
      }
    }
  }
  return { items };
}

export async function getDepartures(criteria = {}) {
  const station = await resolveLocationId(String(criteria.station || ""));
  const date = String(criteria.date || "").trim();
  const time = String(criteria.time || "").trim() || "00:00";
  if (!station.id || !date) {
    return { items: [], source: "hafas", error: "Missing departure criteria" };
  }

  const when = `${date}T${time}:00`;
  const payload = await requestJson(`/stops/${encodeURIComponent(station.id)}/departures`, {
    when,
    duration: 180,
    results: Number.parseInt(String(criteria.limit || 6), 10) || 6,
    remarks: true,
    language: "da"
  });

  const departures = Array.isArray(payload?.departures) ? payload.departures : [];
  const items = departures
    .map((departure) => {
      const line = departure?.line || {};
      if (!isRailLeg({ line })) return null;
      return normalizeRailDeparture({
        id: String(departure.tripId || departure.id || ""),
        source: "hafas",
        confidence: 0.72,
        train_number: String(line.name || line.fahrtNr || "").trim() || null,
        service_name: String(line.productName || line.mode || "Train").trim() || null,
        line_name: String(line.name || line.id || "").trim() || null,
        product: String(line.product || line.mode || "").trim() || null,
        operator_code: String(line.operator?.id || "").trim() || null,
        operator_name: String(line.operator?.name || "").trim() || null,
        origin_station_name: station.name,
        origin_station_code: station.id,
        destination_station_name: String(departure.direction || "").trim() || null,
        destination_station_code: null,
        planned_departure_at: pickTime(departure, ["plannedWhen", "when"]),
        estimated_departure_at: pickTime(departure, ["when"]),
        actual_departure_at: pickTime(departure, ["when"]),
        planned_arrival_at: null,
        estimated_arrival_at: null,
        actual_arrival_at: null,
        departure_delay_minutes: delayMinutes(pickTime(departure, ["plannedWhen"]), pickTime(departure, ["when"])),
        arrival_delay_minutes: null,
        status: departure.cancelled ? "cancelled" : "planned",
        platform_planned: String(departure.plannedPlatform || "").trim() || null,
        platform_actual: String(departure.platform || "").trim() || null,
        cancelled_section_from: null,
        cancelled_section_to: null,
        calling_points: [],
        disruption_reason_public: null,
        disruption_reason_code: null,
        remarks: [],
        raw: {
          provider_hint: "transport_rest",
          trip_id: String(departure.tripId || "")
        }
      });
    })
    .filter(Boolean);

  return { items, source: "hafas" };
}

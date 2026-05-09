import crypto from "node:crypto";
import { canonicalizeStationName } from "./station-aliases.mjs";

const normalizeString = (value) => {
  const stringValue = String(value ?? "").trim();
  return stringValue === "" ? null : stringValue;
};

const normalizeInt = (value) => {
  if (value === null || value === undefined || value === "") return null;
  const parsed = Number.parseInt(String(value), 10);
  return Number.isFinite(parsed) ? parsed : null;
};

const normalizeFloat = (value, fallback = 0.5) => {
  const parsed = Number.parseFloat(String(value ?? ""));
  if (!Number.isFinite(parsed)) return fallback;
  return Math.min(1, Math.max(0, parsed));
};

const normalizeDateTime = (value) => {
  const raw = normalizeString(value);
  if (!raw) return null;
  const date = new Date(raw);
  return Number.isNaN(date.getTime()) ? null : date.toISOString();
};

const normalizeStatus = (value) => {
  const raw = String(value ?? "").trim().toLowerCase().replace(/[\s-]+/g, "_");
  const allowed = new Set([
    "planned",
    "departed",
    "arrived",
    "delayed",
    "cancelled",
    "partially_cancelled",
    "diverted",
    "replacement_transport",
    "unknown"
  ]);
  if (raw === "canceled") return "cancelled";
  return allowed.has(raw) ? raw : "unknown";
};

export function normalizeRailDeparture(item = {}) {
  const normalized = {
    id: normalizeString(item.id) || "",
    source: normalizeString(item.source)?.toLowerCase() || "mock",
    confidence: normalizeFloat(item.confidence, 0.5),
    train_number: normalizeString(item.train_number),
    service_name: normalizeString(item.service_name),
    line_name: normalizeString(item.line_name),
    product: normalizeString(item.product),
    operator_code: normalizeString(item.operator_code),
    operator_name: normalizeString(item.operator_name),
    infrastructure_manager: normalizeString(item.infrastructure_manager),
    origin_station_name: canonicalizeStationName(normalizeString(item.origin_station_name) || ""),
    origin_station_code: normalizeString(item.origin_station_code),
    destination_station_name: canonicalizeStationName(normalizeString(item.destination_station_name) || ""),
    destination_station_code: normalizeString(item.destination_station_code),
    planned_departure_at: normalizeDateTime(item.planned_departure_at),
    estimated_departure_at: normalizeDateTime(item.estimated_departure_at),
    actual_departure_at: normalizeDateTime(item.actual_departure_at),
    planned_arrival_at: normalizeDateTime(item.planned_arrival_at),
    estimated_arrival_at: normalizeDateTime(item.estimated_arrival_at),
    actual_arrival_at: normalizeDateTime(item.actual_arrival_at),
    departure_delay_minutes: normalizeInt(item.departure_delay_minutes),
    arrival_delay_minutes: normalizeInt(item.arrival_delay_minutes),
    status: normalizeStatus(item.status),
    platform_planned: normalizeString(item.platform_planned),
    platform_actual: normalizeString(item.platform_actual),
    cancelled_section_from: normalizeString(item.cancelled_section_from),
    cancelled_section_to: normalizeString(item.cancelled_section_to),
    calling_points: Array.isArray(item.calling_points) ? item.calling_points : [],
    disruption_reason_public: normalizeString(item.disruption_reason_public),
    disruption_reason_code: normalizeString(item.disruption_reason_code),
    remarks: Array.isArray(item.remarks) ? item.remarks.filter(Boolean).map(String) : [],
    raw: item.raw && typeof item.raw === "object" ? item.raw : {}
  };

  if (!normalized.id) {
    normalized.id = crypto.randomUUID();
  }

  return normalized;
}

export function compareJourneyMatch(item = {}, criteria = {}) {
  const fromStation = canonicalizeStationName(String(criteria.from_station || ""));
  const toStation = canonicalizeStationName(String(criteria.to_station || ""));
  const itemFrom = canonicalizeStationName(String(item.origin_station_name || ""));
  const itemTo = canonicalizeStationName(String(item.destination_station_name || ""));
  const date = String(criteria.date || "").trim();
  const departureDate = String(item.planned_departure_at || "").slice(0, 10);

  if (fromStation && itemFrom && fromStation !== itemFrom) return false;
  if (toStation && itemTo && toStation !== itemTo) return false;
  if (date && departureDate && date !== departureDate) return false;
  return true;
}

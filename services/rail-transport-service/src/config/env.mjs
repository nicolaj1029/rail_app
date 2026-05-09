const parseBoolean = (value, fallback = false) => {
  if (value === undefined || value === null || value === "") return fallback;
  return ["1", "true", "yes", "on"].includes(String(value).trim().toLowerCase());
};

const parseInteger = (value, fallback) => {
  const parsed = Number.parseInt(String(value ?? ""), 10);
  return Number.isFinite(parsed) ? parsed : fallback;
};

export const env = {
  port: parseInteger(process.env.PORT, 7071),
  defaultProvider: String(process.env.RAIL_TRANSPORT_DEFAULT_PROVIDER || "transport_rest").trim(),
  useLive: parseBoolean(process.env.RAIL_TRANSPORT_USE_LIVE, true),
  aiFallbackEnabled: parseBoolean(process.env.RAIL_TRANSPORT_AI_FALLBACK_ENABLED, true),
  transportRestBaseUrl: String(process.env.RAIL_TRANSPORT_TRANSPORT_REST_BASE || "https://v6.db.transport.rest").trim().replace(/\/+$/, ""),
  timeoutMs: Math.max(1000, parseInteger(process.env.RAIL_TRANSPORT_TIMEOUT_MS, 3500)),
  retries: Math.max(0, parseInteger(process.env.RAIL_TRANSPORT_RETRIES, 0)),
  diagnostics: parseBoolean(process.env.RAIL_TRANSPORT_DIAGNOSTICS, true),
  rneTisEnabled: parseBoolean(process.env.RAIL_TRANSPORT_RNE_TIS_ENABLED, false),
  rneTisBaseUrl: String(process.env.RAIL_TRANSPORT_RNE_TIS_BASE_URL || "").trim(),
  rneTisApiKey: String(process.env.RAIL_TRANSPORT_RNE_TIS_API_KEY || "").trim()
};

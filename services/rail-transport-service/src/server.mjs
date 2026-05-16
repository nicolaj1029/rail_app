import { createServer } from "node:http";
import { env } from "./config/env.mjs";
import { createDiagnostics, pushError, pushProviderDiagnostic, pushWarning } from "./lib/diagnostics.mjs";
import * as mockProvider from "./providers/mock-provider.mjs";
import * as transportRestProvider from "./providers/transport-rest-provider.mjs";
import * as aiFallbackProvider from "./providers/ai-fallback-provider.mjs";
import * as rneTisProvider from "./providers/rne-tis-provider.mjs";

function json(response, statusCode, payload) {
  response.writeHead(statusCode, {
    "Content-Type": "application/json; charset=utf-8",
    "Access-Control-Allow-Origin": "*",
    "Access-Control-Allow-Methods": "GET, POST, OPTIONS",
    "Access-Control-Allow-Headers": "Content-Type"
  });
  response.end(JSON.stringify(payload));
}

function notFound(response) {
  json(response, 404, { error: "Not found" });
}

function providerChain() {
  const chain = [];
  if (env.useLive) chain.push({ name: "transport_rest", provider: transportRestProvider });
  chain.push({ name: "mock", provider: mockProvider });
  if (env.aiFallbackEnabled) chain.push({ name: "ai_fallback", provider: aiFallbackProvider });
  if (env.rneTisEnabled) chain.push({ name: "rne_tis", provider: rneTisProvider });
  return chain;
}

async function queryProviders(method, args) {
  const diagnostics = createDiagnostics();
  for (const entry of providerChain()) {
    const start = Date.now();
    try {
      const result = await entry.provider[method](...args);
      const items = Array.isArray(result?.items) ? result.items : [];
      pushProviderDiagnostic(diagnostics, {
        provider: entry.name,
        ok: true,
        itemCount: items.length,
        elapsedMs: Date.now() - start,
        error: result?.error || null
      });
      if (result?.error) pushWarning(diagnostics, `${entry.name}: ${result.error}`);
      if (items.length > 0) {
        return { items, source: result?.source || entry.name, diagnostics };
      }
    } catch (error) {
      pushProviderDiagnostic(diagnostics, {
        provider: entry.name,
        ok: false,
        itemCount: 0,
        elapsedMs: Date.now() - start,
        error: error instanceof Error ? error.message : "Unknown provider error"
      });
      pushError(diagnostics, `${entry.name}: ${error instanceof Error ? error.message : "Unknown provider error"}`);
    }
  }
  return { items: [], source: "none", diagnostics };
}

async function handleHealth(response) {
  const diagnostics = createDiagnostics();
  const health = {
    ok: true,
    service: "rail-transport-service",
    version: "0.1.0",
    provider_order: providerChain().map((entry) => entry.name),
    config: {
      use_live: env.useLive,
      ai_fallback_enabled: env.aiFallbackEnabled,
      default_provider: env.defaultProvider,
      transport_rest_base_url: env.transportRestBaseUrl,
      timeout_ms: env.timeoutMs,
      retries: env.retries,
      rne_tis_enabled: env.rneTisEnabled
    },
    diagnostics
  };

  json(response, 200, health);
}

async function handleHealthProbe(response) {
  const diagnostics = createDiagnostics();
  const health = {
    ok: true,
    service: "rail-transport-service",
    version: "0.1.0",
    provider_order: providerChain().map((entry) => entry.name),
    config: {
      use_live: env.useLive,
      ai_fallback_enabled: env.aiFallbackEnabled,
      default_provider: env.defaultProvider,
      transport_rest_base_url: env.transportRestBaseUrl,
      timeout_ms: env.timeoutMs,
      retries: env.retries,
      rne_tis_enabled: env.rneTisEnabled
    },
    diagnostics
  };

  if (env.useLive) {
    const start = Date.now();
    try {
      const probe = await transportRestProvider.searchStations("Hamburg Hbf", 1);
      pushProviderDiagnostic(diagnostics, {
        provider: "transport_rest",
        ok: Array.isArray(probe.items),
        itemCount: Array.isArray(probe.items) ? probe.items.length : 0,
        elapsedMs: Date.now() - start,
        error: null
      });
    } catch (error) {
      pushProviderDiagnostic(diagnostics, {
        provider: "transport_rest",
        ok: false,
        itemCount: 0,
        elapsedMs: Date.now() - start,
        error: error instanceof Error ? error.message : "Unknown health probe error"
      });
      health.ok = false;
    }
  }

  json(response, health.ok ? 200 : 503, health);
}

async function handleStations(requestUrl, response) {
  const query = requestUrl.searchParams.get("q") || "";
  const limit = Number.parseInt(requestUrl.searchParams.get("limit") || "8", 10) || 8;
  const result = await queryProviders("searchStations", [query, limit]);
  json(response, 200, result);
}

async function handleJourneys(requestUrl, response) {
  const criteria = {
    from_station: requestUrl.searchParams.get("from_station") || "",
    from_station_id: requestUrl.searchParams.get("from_station_id") || "",
    to_station: requestUrl.searchParams.get("to_station") || "",
    to_station_id: requestUrl.searchParams.get("to_station_id") || "",
    date: requestUrl.searchParams.get("date") || "",
    time: requestUrl.searchParams.get("time") || "",
    operator_hint: requestUrl.searchParams.get("operator_hint") || "",
    train_number_hint: requestUrl.searchParams.get("train_number_hint") || "",
    locale: requestUrl.searchParams.get("locale") || "da-DK"
  };
  const result = await queryProviders("searchJourneys", [criteria]);
  json(response, 200, result);
}

async function handleDepartures(requestUrl, response) {
  const criteria = {
    station: requestUrl.searchParams.get("station") || "",
    date: requestUrl.searchParams.get("date") || "",
    time: requestUrl.searchParams.get("time") || "",
    limit: requestUrl.searchParams.get("limit") || "6",
    locale: requestUrl.searchParams.get("locale") || "da-DK"
  };
  const result = await queryProviders("getDepartures", [criteria]);
  json(response, 200, result);
}

function isLoopbackAddress(remoteAddress) {
  const normalized = String(remoteAddress || "").trim().toLowerCase();
  return ["127.0.0.1", "::1", "::ffff:127.0.0.1"].includes(normalized);
}

let server;

async function handleShutdown(request, response) {
  if (!isLoopbackAddress(request.socket?.remoteAddress)) {
    return json(response, 403, { ok: false, error: "Forbidden" });
  }

  json(response, 202, {
    ok: true,
    service: "rail-transport-service",
    message: "Shutdown accepted"
  });

  setTimeout(() => {
    server.close(() => {
      process.exit(0);
    });
    setTimeout(() => process.exit(0), 500).unref();
  }, 50).unref();
}

server = createServer(async (request, response) => {
  if (!request.url) return notFound(response);
  if (request.method === "OPTIONS") return json(response, 204, {});

  const requestUrl = new URL(request.url, `http://127.0.0.1:${env.port}`);
  if (request.method === "POST" && requestUrl.pathname === "/shutdown") {
    return handleShutdown(request, response);
  }
  if (request.method !== "GET") return json(response, 405, { error: "Method not allowed" });

  if (requestUrl.pathname === "/") {
    return json(response, 200, {
      service: "rail-transport-service",
      version: "0.1.0",
      ok: true,
      endpoints: ["/health", "/stations/search", "/journeys", "/departures", "/shutdown"]
    });
  }
  if (requestUrl.pathname === "/health") {
    if (requestUrl.searchParams.get("probe") === "1") {
      return handleHealthProbe(response);
    }
    return handleHealth(response);
  }
  if (requestUrl.pathname === "/stations/search") return handleStations(requestUrl, response);
  if (requestUrl.pathname === "/journeys") return handleJourneys(requestUrl, response);
  if (requestUrl.pathname === "/departures") return handleDepartures(requestUrl, response);

  return notFound(response);
});

server.listen(env.port, () => {
  console.log(`rail-transport-service listening on http://127.0.0.1:${env.port}`);
});

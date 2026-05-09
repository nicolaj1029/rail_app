import { env } from "../config/env.mjs";

const sleep = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

export async function fetchJson(url, options = {}) {
  const timeoutMs = options.timeoutMs ?? env.timeoutMs;
  const retries = options.retries ?? env.retries;
  const headers = {
    Accept: "application/json",
    "Accept-Language": "da,en;q=0.8",
    "User-Agent": "rail-transport-service/0.1",
    ...(options.headers || {})
  };

  let lastError = null;

  for (let attempt = 0; attempt <= retries; attempt += 1) {
    const controller = new AbortController();
    const timer = setTimeout(() => controller.abort(), timeoutMs);

    try {
      const response = await fetch(url, {
        method: options.method || "GET",
        headers,
        body: options.body,
        signal: controller.signal
      });

      const text = await response.text();
      let json = null;
      try {
        json = text ? JSON.parse(text) : null;
      } catch {
        json = null;
      }

      clearTimeout(timer);

      if (!response.ok) {
        lastError = new Error(`HTTP ${response.status} from ${url}`);
        if (attempt < retries) {
          await sleep(150 * (attempt + 1));
          continue;
        }
        return { ok: false, status: response.status, json, text, error: lastError.message };
      }

      return { ok: true, status: response.status, json, text, error: null };
    } catch (error) {
      clearTimeout(timer);
      lastError = error;
      if (attempt < retries) {
        await sleep(150 * (attempt + 1));
        continue;
      }
    }
  }

  return {
    ok: false,
    status: 0,
    json: null,
    text: "",
    error: lastError instanceof Error ? lastError.message : "Unknown fetch error"
  };
}

export function withQuery(baseUrl, query = {}) {
  const url = new URL(baseUrl);
  for (const [key, value] of Object.entries(query)) {
    if (value === undefined || value === null || value === "") continue;
    url.searchParams.set(key, String(value));
  }
  return url.toString();
}

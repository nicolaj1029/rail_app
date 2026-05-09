import { env } from "../config/env.mjs";

export async function searchJourneys() {
  if (!env.rneTisEnabled || !env.rneTisBaseUrl) {
    return { items: [], source: "rne_tis", error: "RNE/TIS not configured" };
  }
  return { items: [], source: "rne_tis", error: "RNE/TIS stub only" };
}

export async function searchStations() {
  return { items: [], source: "rne_tis", error: "RNE/TIS stub only" };
}

export async function getDepartures() {
  return { items: [], source: "rne_tis", error: "RNE/TIS stub only" };
}

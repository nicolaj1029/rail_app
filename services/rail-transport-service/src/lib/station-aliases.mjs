import { stationAliases } from "../config/station-aliases.mjs";

export function compareKey(value = "") {
  return String(value)
    .replace(/Æ/g, "AE")
    .replace(/æ/g, "ae")
    .replace(/Ø/g, "OE")
    .replace(/ø/g, "oe")
    .replace(/Å/g, "AA")
    .replace(/å/g, "aa")
    .replace(/Ä/g, "A")
    .replace(/ä/g, "a")
    .replace(/Ö/g, "O")
    .replace(/ö/g, "o")
    .replace(/Ü/g, "U")
    .replace(/ü/g, "u")
    .replace(/ß/g, "ss")
    .normalize("NFKD")
    .replace(/[\u0300-\u036f]/g, "")
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, " ")
    .trim();
}

export function stationKey(value = "") {
  return compareKey(value)
    .replace(/ hovedbanegard| hovedbanegaard/g, " h")
    .replace(/ central station| centralstation/g, " c");
}

export function canonicalizeStationName(input = "") {
  const key = stationKey(input);
  for (const aliases of Object.values(stationAliases)) {
    for (const variant of aliases) {
      if (stationKey(variant) === key) {
        return aliases[0];
      }
    }
  }
  return input;
}

export function stationMatches(candidate = "", query = "") {
  return stationKey(canonicalizeStationName(candidate)) === stationKey(canonicalizeStationName(query));
}

export function expandStationQueries(query = "") {
  const canonical = canonicalizeStationName(query);
  const stripped = String(query).replace(/\s*\/.*$/u, "").trim();
  const options = [query, canonical, stripped].filter(Boolean);
  for (const aliases of Object.values(stationAliases)) {
    if (aliases.length === 0) continue;
    if (stationKey(aliases[0]) !== stationKey(canonical)) continue;
    options.push(...aliases);
    break;
  }
  const seen = new Set();
  return options.filter((item) => {
    const key = stationKey(item);
    if (seen.has(key)) return false;
    seen.add(key);
    return true;
  });
}

export function compareOperator(a = "", b = "") {
  return compareKey(a) === compareKey(b);
}

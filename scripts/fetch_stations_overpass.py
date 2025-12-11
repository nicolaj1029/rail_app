"""
Fetch all railway stations/halts for EU27 countries from Overpass API and
emit a flattened JSON file suitable for config/data/stations_coords.json.

Usage:
    python scripts/fetch_stations_overpass.py > stations_coords.json

Notes:
- Uses Overpass public endpoint; be gentle (timeout + sleep between calls).
- Keeps only basic fields: country, osm_id, name, type, lat, lon, source.
- Requires Python 3.8+ and `requests` installed.
"""

import json
import sys
import time
from typing import Dict, List

import requests

MAX_RETRIES = 5
RETRY_BASE_SLEEP = 5  # seconds

OVERPASS_URL = "https://overpass-api.de/api/interpreter"

# EU27 ISO codes
EU27 = [
    "AT",
    "BE",
    "BG",
    "HR",
    "CY",
    "CZ",
    "DK",
    "EE",
    "FI",
    "FR",
    "DE",
    "GR",
    "HU",
    "IE",
    "IT",
    "LV",
    "LT",
    "LU",
    "MT",
    "NL",
    "PL",
    "PT",
    "RO",
    "SK",
    "SI",
    "ES",
    "SE",
]


def build_query(iso: str) -> str:
    """Build Overpass QL for one country."""
    return f"""
    [out:json][timeout:900];
    area["ISO3166-1"="{iso}"][admin_level=2]->.target;
    (
      node["railway"~"station|halt"](area.target);
      way["railway"~"station|halt"](area.target);
      relation["railway"~"station|halt"](area.target);
    );
    out center;
    """


def fetch_country(iso: str) -> List[Dict]:
    """Fetch stations for a single country."""
    q = build_query(iso)
    last_err = None
    for attempt in range(1, MAX_RETRIES + 1):
        try:
            resp = requests.post(OVERPASS_URL, data={"data": q})
            resp.raise_for_status()
            data = resp.json()
            break
        except requests.HTTPError as e:
            last_err = e
            if resp.status_code == 429 and attempt < MAX_RETRIES:
                sleep_s = RETRY_BASE_SLEEP * attempt
                sys.stderr.write(
                    f"Rate limited on {iso}, retry {attempt}/{MAX_RETRIES} in {sleep_s}s...\n"
                )
                sys.stderr.flush()
                time.sleep(sleep_s)
                continue
            raise
        except Exception as e:
            last_err = e
            if attempt < MAX_RETRIES:
                sleep_s = RETRY_BASE_SLEEP * attempt
                sys.stderr.write(
                    f"Error on {iso}, retry {attempt}/{MAX_RETRIES} in {sleep_s}s ({e})...\n"
                )
                sys.stderr.flush()
                time.sleep(sleep_s)
                continue
            raise last_err

    elements = data.get("elements", [])
    results = []
    for el in elements:
        tags = el.get("tags", {}) or {}
        name = tags.get("name")
        if not name:
            continue
        kind = tags.get("railway", "")
        # Coordinates
        lat = el.get("lat")
        lon = el.get("lon")
        if lat is None or lon is None:
            center = el.get("center") or {}
            lat = center.get("lat")
            lon = center.get("lon")
        if lat is None or lon is None:
            continue
        results.append(
            {
                "country": iso,
                "osm_id": el.get("id"),
                "type": kind,
                "name": name,
                "lat": float(lat),
                "lon": float(lon),
                "source": "osm_overpass",
            }
        )
    return results


def main() -> None:
    all_rows: List[Dict] = []
    for idx, iso in enumerate(EU27, 1):
        sys.stderr.write(f"[{idx}/{len(EU27)}] Fetching {iso}...\n")
        sys.stderr.flush()
        rows = fetch_country(iso)
        all_rows.extend(rows)
        # Be polite to Overpass
        time.sleep(3)
    json.dump(all_rows, sys.stdout, ensure_ascii=False, indent=2)


if __name__ == "__main__":
    main()

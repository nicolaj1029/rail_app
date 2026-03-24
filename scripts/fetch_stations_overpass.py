"""
Fetch all railway stations/halts for EU27 countries from Overpass API and
emit a flattened JSON file suitable for config/data/nodes/stations_coords.json.

Usage:
    python scripts/fetch_stations_overpass.py > config/data/nodes/stations_coords.json

Notes:
- Uses Overpass public endpoint; be gentle (timeout + sleep between calls).
- Keeps only basic fields: country, osm_id, name, type, lat, lon, source.
- Requires Python 3.8+ and `requests` installed.
"""

import json
import sys
import time
import argparse
import os
from typing import Dict, List

import requests

MAX_RETRIES = 5
RETRY_BASE_SLEEP = 5  # seconds

OVERPASS_URLS = [
    # Rotate between public instances to reduce 5xx/timeout issues.
    "https://overpass-api.de/api/interpreter",
    "https://overpass.kumi.systems/api/interpreter",
    "https://overpass.openstreetmap.ru/api/interpreter",
]

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
      node["railway"="station"](area.target);
      node["railway"="halt"](area.target);
      way["railway"="station"](area.target);
      way["railway"="halt"](area.target);
      relation["railway"="station"](area.target);
      relation["railway"="halt"](area.target);
    );
    out center;
    """


def fetch_country(iso: str, urls: List[str]) -> List[Dict]:
    """Fetch stations for a single country."""
    q = build_query(iso)
    last_err = None
    for attempt in range(1, MAX_RETRIES + 1):
        try:
            url = urls[(attempt - 1) % len(urls)]
            # Set an explicit timeout; Overpass can hang under load without one.
            resp = requests.post(url, data={"data": q}, timeout=(30, 1200))
            resp.raise_for_status()
            data = resp.json()
            break
        except requests.HTTPError as e:
            last_err = e
            status = getattr(resp, "status_code", None)
            if status in (429, 502, 503, 504) and attempt < MAX_RETRIES:
                sleep_s = RETRY_BASE_SLEEP * attempt
                sys.stderr.write(
                    f"Overpass {status} on {iso}, retry {attempt}/{MAX_RETRIES} in {sleep_s}s...\n"
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
    ap = argparse.ArgumentParser(description="Fetch EU27 railway stations/halts via Overpass and write JSON.")
    ap.add_argument("--out", default="-", help="Output path (UTF-8). Use '-' for stdout (default).")
    ap.add_argument("--countries", default="", help="Comma-separated ISO2 list (default: EU27).")
    ap.add_argument("--flush-every", type=int, default=1, help="When --out is a file, rewrite output every N countries (default: 1). Use 0 to write only at end.")
    ap.add_argument("--url", default="", help="Overpass endpoint URL. If set, disables rotation between public instances.")
    ap.add_argument("--resume", action="store_true", help="If --out exists, resume by skipping countries already present in the output.")
    args = ap.parse_args()

    # Make stdout UTF-8 even on Windows when redirected.
    try:
        sys.stdout.reconfigure(encoding="utf-8", errors="strict")
    except Exception:
        pass

    countries = EU27
    if args.countries:
        countries = [c.strip().upper() for c in args.countries.split(",") if c.strip()]
        if not countries:
            countries = EU27
    urls = OVERPASS_URLS
    if args.url:
        urls = [args.url]

    all_rows: List[Dict] = []
    done_countries = set()
    if args.resume and args.out and args.out != "-" and os.path.exists(args.out):
        try:
            with open(args.out, "r", encoding="utf-8") as f:
                existing = json.load(f)
            if isinstance(existing, list):
                all_rows = [r for r in existing if isinstance(r, dict)]
                done_countries = {str(r.get("country", "")).upper() for r in all_rows if r.get("country")}
                done_countries.discard("")
                sys.stderr.write(f"Resuming from {args.out}: {len(all_rows)} rows, {len(done_countries)} countries.\n")
                sys.stderr.flush()
        except Exception as e:
            sys.stderr.write(f"Resume failed (will start fresh): {e}\n")
            sys.stderr.flush()
            all_rows = []
            done_countries = set()
    for idx, iso in enumerate(countries, 1):
        if iso in done_countries:
            sys.stderr.write(f"[{idx}/{len(countries)}] Skipping {iso} (already present)\n")
            sys.stderr.flush()
            continue
        sys.stderr.write(f"[{idx}/{len(countries)}] Fetching {iso}...\n")
        sys.stderr.flush()
        rows = fetch_country(iso, urls)
        all_rows.extend(rows)

        # If writing to file, periodically flush to avoid losing progress on long runs.
        if args.out and args.out != "-" and args.flush_every and (idx % args.flush_every == 0):
            payload = json.dumps(all_rows, ensure_ascii=False, indent=2)
            out_path = args.out
            tmp_path = out_path + ".tmp"
            with open(tmp_path, "w", encoding="utf-8", newline="\n") as f:
                f.write(payload)
            try:
                os.replace(tmp_path, out_path)
            except Exception:
                try:
                    os.remove(out_path)
                except Exception:
                    pass
                os.rename(tmp_path, out_path)

        # Be polite to Overpass
        time.sleep(3)

    payload = json.dumps(all_rows, ensure_ascii=False, indent=2)
    if args.out and args.out != "-":
        out_path = args.out
        tmp_path = out_path + ".tmp"
        with open(tmp_path, "w", encoding="utf-8", newline="\n") as f:
            f.write(payload)
        # Atomic-ish replace on Windows
        try:
            os.replace(tmp_path, out_path)
        except Exception:
            # Best effort fallback
            try:
                os.remove(out_path)
            except Exception:
                pass
            os.rename(tmp_path, out_path)
    else:
        sys.stdout.write(payload)


if __name__ == "__main__":
    main()

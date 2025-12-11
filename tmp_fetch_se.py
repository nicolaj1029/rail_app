import requests, json, time
iso='SE'
q='''[out:json][timeout:900];area["ISO3166-1"="%s"][admin_level=2]->.c;(node["railway"~"station|halt"](area.c);way["railway"~"station|halt"](area.c);relation["railway"~"station|halt"](area.c););out center;''' % iso
endpoints = ['https://lz4.overpass-api.de/api/interpreter','https://overpass-api.de/api/interpreter']
resp=None
for url in endpoints:
    try:
        resp = requests.post(url, data={'data': q}, timeout=120)
        resp.raise_for_status()
        break
    except Exception as e:
        print('endpoint failed', url, e)
        resp=None
        time.sleep(2)
if resp is None:
    raise SystemExit('all endpoints failed')
els = resp.json().get('elements', [])
rows = []
for el in els:
    tags = el.get('tags') or {}
    name = tags.get('name')
    if not name:
        continue
    lat = el.get('lat'); lon = el.get('lon')
    if lat is None or lon is None:
        c = el.get('center') or {}
        lat = c.get('lat'); lon = c.get('lon')
    if lat is None or lon is None:
        continue
    rows.append({
        'country': 'SE',
        'osm_id': el.get('id'),
        'type': tags.get('railway',''),
        'name': name,
        'lat': float(lat),
        'lon': float(lon),
        'source': 'osm_overpass'
    })
print('rows', len(rows))
with open('config/data/stations_coords.json', 'w', encoding='utf-8') as f:
    json.dump(rows, f, ensure_ascii=False, indent=2)

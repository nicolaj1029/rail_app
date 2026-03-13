# Transport node import

Dette projekt bruger `config/data/transport_nodes.json` som lookup-lag for multimodal autocomplete i `TRIN 2` for:

- `ferry`
- `bus`
- `air`

Rail beholder sit separate stationsdatasæt.

## Kommando

```powershell
php bin/cake.php transport_nodes_import --mode ferry --source data\ports.csv --profile ferry_unlocode
```

## Understøttede formater

- `json` — array af objekter
- `csv` — header-row + data rows

## Import-profiler

Der ligger foruddefinerede profiler i `config/data/transport_node_import_profiles/`:

- `air_ourairports`
- `ferry_unlocode`
- `bus_osm`

Profilerne udfylder standardfelter for de mest realistiske kilder. CLI-flag kan stadig overstyre profilværdier.

Profiler kan også sætte simple filtre, fx:

- `require_code`
- `filter_col`
- `filter_allow`

## Minimumsfelter

Importeren forsøger at normalisere disse felter:

- `name`
- `country`
- `code`
- `lat`
- `lon`
- `node_type`
- `city`
- `parent_name`
- `aliases`
- `in_eu`

Hvis nogle felter mangler, bruger importeren:

- mode-specifik `node_type` fallback
- `country -> in_eu` afledning for kendte EU-lande
- automatisk `id`-generering

## Eksempler

### Airports / OurAirports-style CSV

```powershell
php bin/cake.php transport_nodes_import `
  --mode air `
  --source data\airports.csv `
  --profile air_ourairports
```

`air_ourairports` filtrerer automatisk til `medium_airport` og `large_airport` og kræver en brugbar IATA-kode.

### Ports / UN LOCODE-derived CSV

```powershell
php bin/cake.php transport_nodes_import `
  --mode ferry `
  --source data\ports.csv `
  --profile ferry_unlocode
```

### Bus terminals / OSM or GTFS-derived JSON

```powershell
php bin/cake.php transport_nodes_import `
  --mode bus `
  --source data\bus_terminals.json `
  --profile bus_osm
```

## Skabeloner

Eksempelskabeloner ligger i `docs/transport_node_sources/`:

- `airports_ourairports_template.csv`
- `ports_unlocode_template.csv`
- `bus_nodes_osm_template.json`

De er kun skabeloner. De skal erstattes af rigtige eksportfiler før import.

## Realistisk source-strategi

### Ferry
- baseline: `UN/LOCODE`
- enrichment: `OSM ferry_terminal`

### Air
- baseline: `OurAirports`
- carrier-EU-status holdes separat i operator-laget

### Bus
- baseline geografi: `OSM`
- service metadata: `GTFS / NeTEx / National Access Points`

## Bemærkning

Denne importer er bevidst generisk og lokal-first. Den downloader ikke selv data. Formålet er at kunne:

1. normalisere forskellige kilder til én fælles fil
2. udskifte seed-data gradvist
3. holde autocomplete og scope-afledning stabilt

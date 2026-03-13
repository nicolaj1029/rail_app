# Transport node import

Dette projekt bruger `config/data/transport_nodes.json` som lookup-lag for multimodal autocomplete i `TRIN 2` for:

- `ferry`
- `bus`
- `air`

Rail beholder sit separate stationsdatasæt.

## Kommando

```powershell
php bin/cake.php transport_nodes_import --mode ferry --source data\unlocode_ports.json --format json --replace --source-label unlocode
```

## Understøttede formater

- `json` — array af objekter
- `csv` — header-row + data rows

## Minimumsfelter

Importer forsøger at normalisere disse felter:

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
  --format csv `
  --replace `
  --source-label ourairports `
  --name-col name `
  --country-col iso_country `
  --code-col iata_code `
  --lat-col latitude_deg `
  --lon-col longitude_deg `
  --city-col municipality `
  --default-node-type airport
```

### Ports / UN LOCODE-derived CSV

```powershell
php bin/cake.php transport_nodes_import `
  --mode ferry `
  --source data\ports.csv `
  --format csv `
  --replace `
  --source-label unlocode `
  --name-col name `
  --country-col country `
  --code-col locode `
  --lat-col lat `
  --lon-col lon `
  --city-col city `
  --node-type-col node_type `
  --aliases-col aliases
```

### Bus terminals / OSM or GTFS-derived JSON

```powershell
php bin/cake.php transport_nodes_import `
  --mode bus `
  --source data\bus_terminals.json `
  --format json `
  --replace `
  --source-label osm `
  --name-col name `
  --country-col country `
  --code-col code `
  --lat-col lat `
  --lon-col lon `
  --node-type-col node_type `
  --city-col city `
  --parent-col parent_name
```

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

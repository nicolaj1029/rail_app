# rail-transport-service

Dedicated Node.js microservice for rail transport data.

Purpose:
- keep all legal logic in CakePHP
- isolate flaky rail data provider behavior from PHP
- normalize provider output into a shared DTO
- support provider failover, retries, aliases, diagnostics, and future GTFS-RT

## Endpoints

- `GET /health`
- `GET /stations/search?q=...&limit=8`
- `GET /journeys?from_station=...&to_station=...&date=YYYY-MM-DD&time=HH:mm`
- `GET /departures?station=...&date=YYYY-MM-DD&time=HH:mm`

## Environment

- `PORT=7071`
- `RAIL_TRANSPORT_DEFAULT_PROVIDER=transport_rest`
- `RAIL_TRANSPORT_USE_LIVE=true`
- `RAIL_TRANSPORT_AI_FALLBACK_ENABLED=true`
- `RAIL_TRANSPORT_TRANSPORT_REST_BASE=https://v6.db.transport.rest`
- `RAIL_TRANSPORT_TIMEOUT_MS=8000`
- `RAIL_TRANSPORT_RETRIES=1`
- `RAIL_TRANSPORT_RNE_TIS_ENABLED=false`

## Notes

- This scaffold currently uses a robust HTTP adapter to `transport.rest`.
- When live and exact mock data fail, the service can generate a low-confidence `ai_fallback` route skeleton based on known European rail corridors. This is UX-only and must be user-confirmed later in CakePHP.
- It is structured so `hafas-client` / `db-vendo-client` can replace the live provider without changing the CakePHP DTO contract.
- The microservice is transport-data only. No legal entitlement logic belongs here.

## Run

```bash
cd services/rail-transport-service
node src/server.mjs
```

Example health check:

```bash
curl "http://127.0.0.1:7071/health"
```

CakePHP integration:

- `RAIL_TRANSPORT_SERVICE_ENABLED=true`
- `RAIL_TRANSPORT_SERVICE_BASE_URL=http://127.0.0.1:7071`

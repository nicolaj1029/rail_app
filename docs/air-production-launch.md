# AIR production launch runbook

## Gap matrix

| Area | RAIL standard found | AIR before hardening | AIR launch implementation |
|---|---|---|---|
| Provider outcomes | Explicit success/zero/timeout/rate-limit/unavailable/invalid categories | Empty array for every failure | Redacted provider attempts and distinct no-data/failure statuses |
| Time bounds | Configurable provider timeouts and fail-closed fallback | Fixed 8-10 second calls per provider | 2 second connect, 5 second provider, 8 second overall lookup budget |
| Cache | Dedicated planned/realtime caches and measured hit/miss paths | General cache with one implicit TTL | Dedicated AIR cache with active/future/historical/no-data TTLs |
| Identity | Canonical station/operator IDs | Display flight string and carrier names | Marketing/operating numbers and carrier IATA/ICAO identities |
| Time | Normalized planned/realtime timestamps | Naive local timestamps | Local, airport timezone and UTC fields; UTC-only delay arithmetic |
| Failure safety | Fail-closed, provider attempts, user-safe messages | Provider exceptions swallowed as no result | Classified 4xx/429/5xx/network/timeout/schema failures; no retry storm |
| Observability | Request/provider timing and cache state | None | Correlation ID, provider/cache/status and internal/provider/total latency |
| Health | App/runtime health separated | No AIR health route | `GET /api/air/health`, with no billable provider call |

## Required production environment

Set `USE_LIVE_APIS=true` and configure at least one provider key in the DreamHost
environment. Prefer AeroDataBox; Aviationstack is the secondary adapter. RapidAPI
authentication uses the server-side `X-RapidAPI-Key` and `X-RapidAPI-Host`
headers. The adapter makes two half-day FIDS requests because that endpoint has a
12-hour range limit. The calls are paced by `AIR_PROVIDER_MIN_REQUEST_INTERVAL_MS`
to respect RapidAPI request-rate limits, without retries. For a historical search
where a flight number is known, the adapter uses the single-flight endpoint
instead, preserving completed arrival status and actual times with one provider
request. Future and active searches retain FIDS because its codeshare rows carry
the fuller marketing/operating relationship. The adapter does not request
flight-plan data. Never place key values
in Git, shell history, smoke URLs or support output.

Configure `HostRouting.adminHosts`, `HostRouting.publicHosts` and preview/basic-auth
hosts in the production-only CakePHP configuration. Hostnames are data, not
application logic. Keep application admin authentication enabled and provide at
least one `ADMIN_JURIST_PASS` or `ADMIN_OPERATOR_PASS` value of 12 or more
characters. DreamHost HTTP authentication or an IP policy is optional
defense-in-depth, not a replacement for application route protection.

Optional defaults are documented in `.env.example`. AIR introduces no database
migration, cron, queue worker, asset build, or new Composer dependency.

## Preflight

```bash
cd "$APP_DIR"
php -v
composer validate --no-check-publish
php bin/cake.php routes | grep -E 'api/air/(health|flights/search)'
php vendor/bin/phpunit tests/TestCase/Service/AirFlightNormalizerTest.php tests/TestCase/Service/AirFlightProviderFailureTest.php tests/TestCase/Service/FlightSearchServiceTest.php tests/TestCase/Controller/Api/AirFlightsControllerTest.php
```

`/api/air/health` must return `application=ok`, `live_apis_enabled=true`,
`live_provider_configured=true`, `live_provider_ready=true`, and
`cache_configured=true`, and must not display a key. A configured provider is not
reported ready until a successful live lookup has produced a safe outcome.

## Deploy

The repository does not record the DreamHost checkout path or production branch.
Set both explicitly after confirming them in the DreamHost panel/current checkout.

```bash
export APP_DIR='/absolute/path/to/the/current/rail_app/checkout'
export DEPLOY_BRANCH='feature/season-step10-unlock'
cd "$APP_DIR"
test "$(git branch --show-current)" = "$DEPLOY_BRANCH"
test -z "$(git status --porcelain)"
export PREVIOUS_SHA="$(git rev-parse HEAD)"
printf '%s\n' "$PREVIOUS_SHA" > "$APP_DIR/../rail_app.previous-sha"
git fetch --prune origin
git pull --ff-only origin "$DEPLOY_BRANCH"
export DEPLOYED_SHA="$(git rev-parse HEAD)"
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
php bin/cake.php cache clear air_flights
```

Do not run migrations solely for this AIR release. Review the repository's overall
migration status separately because unrelated pending migrations exist in the
working tree.

## Smoke

Set `BASE_URL` to the public AIR host, without a trailing slash.

```bash
export BASE_URL='https://air.example.com'
curl --fail --silent --show-error "$BASE_URL/api/air/health"
curl --fail --silent --show-error "$BASE_URL/fly-ny"
curl --fail --silent --show-error "$BASE_URL/faerge-ny"
curl --fail --silent --show-error "$BASE_URL/tog-ny"
```

Use one provider-confirmed future flight and one recent completed flight for the
two lookup requests. Confirm `success=true`, `operational_data_verified=true`,
UTC timestamps, freshness, status and actual-vs-estimated delay basis. Where a
codeshare is naturally returned, verify marketing and operating identities
separately. Repeat exactly one identical lookup to prove a cache hit and no second
provider request. Do not repeat live calls for performance testing; run the local
fixture/cache benchmark.

## Rollback

AIR has no schema change, so code rollback is independent of the database:

```bash
cd "$APP_DIR"
export PREVIOUS_SHA="$(cat "$APP_DIR/../rail_app.previous-sha")"
test -n "$PREVIOUS_SHA"
git switch --detach "$PREVIOUS_SHA"
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
php bin/cake.php cache clear air_flights
curl --fail --silent --show-error "$BASE_URL/api/air/health"
curl --fail --silent --show-error "$BASE_URL/tog-ny"
curl --fail --silent --show-error "$BASE_URL/faerge-ny"
```

Preserve `PREVIOUS_SHA` until all post-deploy AIR/RAIL/FERRY smokes pass. Returning
production to a branch after incident resolution is a separate controlled deploy.

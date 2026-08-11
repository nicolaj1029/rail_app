param(
    [Parameter(Mandatory = $true)]
    [ValidatePattern('^https?://')]
    [string]$BaseUrl,
    [int]$ColdSamples = 30,
    [int]$WarmSamples = 50
)

$ErrorActionPreference = 'Stop'

function Get-Stats([double[]]$Values) {
    $sorted = @($Values | Sort-Object)
    $count = $sorted.Count
    if ($count -eq 0) {
        return $null
    }

    return [ordered]@{
        n = $count
        min_ms = [Math]::Round($sorted[0], 3)
        p50_ms = [Math]::Round($sorted[[Math]::Ceiling($count * 0.50) - 1], 3)
        p95_ms = [Math]::Round($sorted[[Math]::Ceiling($count * 0.95) - 1], 3)
        max_ms = [Math]::Round($sorted[-1], 3)
    }
}

function Measure-AirRequests([string[]]$Uris) {
    $wall = @()
    $appTotal = @()
    $internal = @()
    $provider = @()
    $statuses = @()

    foreach ($uri in $Uris) {
        $timer = [Diagnostics.Stopwatch]::StartNew()
        $response = Invoke-WebRequest -UseBasicParsing -Uri $uri -TimeoutSec 15
        $timer.Stop()
        $body = $response.Content | ConvertFrom-Json
        if ($response.StatusCode -ne 200 -or -not $body.lookup_status) {
            throw "AIR benchmark request failed: HTTP $($response.StatusCode)"
        }
        $wall += $timer.Elapsed.TotalMilliseconds
        $appTotal += [double]$body.timing.total_ms
        $internal += [double]$body.timing.internal_ms
        $provider += [double]$body.timing.provider_ms
        $statuses += [string]$body.lookup_status
    }

    return [ordered]@{
        wall = Get-Stats $wall
        app_total = Get-Stats $appTotal
        internal = Get-Stats $internal
        provider = Get-Stats $provider
        statuses = @($statuses | Group-Object | ForEach-Object {
            [ordered]@{status = $_.Name; count = $_.Count}
        })
    }
}

$runId = [DateTimeOffset]::UtcNow.ToUnixTimeSeconds() % 1000
$endpoint = $BaseUrl.TrimEnd('/') + '/api/air/flights/search'
$query = '?departure=CPH&arrival=LHR&date=2026-09-01&depTime=08%3A00&arrTime=09%3A00&flightNumber='
$coldUris = for ($i = 0; $i -lt $ColdSamples; $i++) {
    $endpoint + $query + ('SK{0}' -f (1000 + (($runId + $i) % 8000)))
}
$warmUri = $endpoint + $query + ('SK{0}' -f (9000 + ($runId % 900)))
$null = Invoke-WebRequest -UseBasicParsing -Uri $warmUri -TimeoutSec 15
$warmUris = for ($i = 0; $i -lt $WarmSamples; $i++) { $warmUri }

[ordered]@{
    measured_at = (Get-Date).ToUniversalTime().ToString('o')
    base_url = $BaseUrl
    cold_cache_miss = Measure-AirRequests $coldUris
    warm_cache_hit = Measure-AirRequests $warmUris
} | ConvertTo-Json -Depth 8

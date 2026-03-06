#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * Check that season/period pass policy URLs are reachable (best-effort).
 *
 * Usage:
 *   php bin/check_season_policy_links.php
 *   php bin/check_season_policy_links.php --json
 *   php bin/check_season_policy_links.php --only=DE
 *   php bin/check_season_policy_links.php --only=Deutsche Bahn
 *
 * Notes:
 * - This does NOT "legally verify" any rule text; it only checks link health.
 * - Some sites block automated requests (403). Treat those as "manual check in browser".
 */

$root = dirname(__DIR__);
$matrixPath = $root . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'season_policy_matrix.json';

$asJson = in_array('--json', $argv, true);
$insecure = in_array('--insecure', $argv, true);
$only = null;
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--only=')) {
        $only = trim(substr($arg, 7));
    }
}

if (!is_file($matrixPath)) {
    fwrite(STDERR, "Missing policy matrix: $matrixPath\n");
    exit(2);
}
$raw = file_get_contents($matrixPath);
if ($raw === false) {
    fwrite(STDERR, "Cannot read: $matrixPath\n");
    exit(3);
}
$json = json_decode($raw, true);
if (!is_array($json) || !is_array($json['policies'] ?? null)) {
    fwrite(STDERR, "Invalid season policy matrix JSON structure.\n");
    exit(4);
}

/** @return array{ok:bool,http_code:int,effective_url:string,error:string,content_type:string,checked_at:string,method:string,tls_verified:bool} */
$checkUrl = static function (string $url) use ($insecure): array {
    $url = trim($url);
    $checkedAt = gmdate('c');
    if ($url === '') {
        return [
            'ok' => false,
            'http_code' => 0,
            'effective_url' => '',
            'error' => 'empty_url',
            'content_type' => '',
            'checked_at' => $checkedAt,
            'method' => 'none',
            'tls_verified' => false,
        ];
    }

    $run = static function (string $url, bool $head, bool $tlsVerify): array {
        $ch = curl_init($url);
        if ($ch === false) {
            return ['err' => 'curl_init_failed', 'code' => 0, 'eff' => $url, 'ct' => ''];
        }
        curl_setopt_array($ch, [
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 8,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => false,
            CURLOPT_NOBODY => $head,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_USERAGENT => 'rail_app/season_policy_link_check (+https://localhost/rail_app)',
            CURLOPT_SSL_VERIFYPEER => $tlsVerify,
            CURLOPT_SSL_VERIFYHOST => $tlsVerify ? 2 : 0,
        ]);
        if (!$head) {
            curl_setopt($ch, CURLOPT_RANGE, '0-2048');
        }

        $data = curl_exec($ch);
        $err = '';
        if ($data === false) {
            $err = (string)curl_error($ch);
        }
        $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $eff = (string)curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
        $ct = (string)curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        curl_close($ch);

        return ['err' => $err, 'code' => $code, 'eff' => $eff ?: $url, 'ct' => $ct];
    };

    // Try HEAD first (fast), then GET if HEAD is blocked or inconclusive.
    $tlsVerify = !$insecure;
    $r = $run($url, true, $tlsVerify);
    $method = 'HEAD';
    $code = (int)$r['code'];
    $err = (string)$r['err'];
    $eff = (string)$r['eff'];
    $ct = (string)$r['ct'];

    // If TLS verification fails due to missing CA bundle, retry insecure (and mark tls_verified=false)
    $tlsVerified = $tlsVerify;
    if ($tlsVerify && $code === 0 && $err !== '' && preg_match('/SSL certificate problem/i', $err)) {
        $tlsVerify = false;
        $tlsVerified = false;
        $r = $run($url, true, $tlsVerify);
        $method = 'HEAD';
        $code = (int)$r['code'];
        $err = (string)$r['err'];
        $eff = (string)$r['eff'];
        $ct = (string)$r['ct'];
    }

    $needGet = false;
    if ($code === 0 || in_array($code, [403, 405, 406, 429], true)) {
        $needGet = true;
    }
    if ($needGet) {
        $r2 = $run($url, false, $tlsVerify);
        $method = 'GET';
        $code = (int)$r2['code'];
        $err = (string)$r2['err'];
        $eff = (string)$r2['eff'];
        $ct = (string)$r2['ct'];
    }

    $ok = ($code >= 200 && $code < 400);
    return [
        'ok' => $ok,
        'http_code' => $code,
        'effective_url' => $eff,
        'error' => $err,
        'content_type' => $ct,
        'checked_at' => $checkedAt,
        'method' => $method,
        'tls_verified' => $tlsVerified,
    ];
};

$results = [];
foreach ((array)$json['policies'] as $pRaw) {
    if (!is_array($pRaw)) {
        continue;
    }
    $op = trim((string)($pRaw['operator'] ?? ''));
    $cc = strtoupper(trim((string)($pRaw['country'] ?? '')));
    if ($op === '' && $cc === '') {
        continue;
    }

    if ($only !== null && $only !== '') {
        $onlyUpper = strtoupper($only);
        if ($cc !== $onlyUpper && stripos($op, $only) === false) {
            continue;
        }
    }

    $src = trim((string)($pRaw['source_url'] ?? ''));
    $ch = (array)($pRaw['claim_channel'] ?? []);
    $claim = trim((string)($ch['value'] ?? ''));

    $row = [
        'country' => $cc,
        'operator' => $op,
        'coverage_status' => (string)($pRaw['coverage_status'] ?? ''),
        'verified' => !empty($pRaw['verified']),
        'last_verified' => (string)($pRaw['last_verified'] ?? ''),
        'checks' => [],
    ];

    if ($src !== '') {
        $row['checks']['source_url'] = $checkUrl($src);
        $row['checks']['source_url']['input_url'] = $src;
    }
    if ($claim !== '') {
        $row['checks']['claim_url'] = $checkUrl($claim);
        $row['checks']['claim_url']['input_url'] = $claim;
    }

    $results[] = $row;
}

usort($results, static function ($a, $b): int {
    $aC = (string)($a['country'] ?? '');
    $bC = (string)($b['country'] ?? '');
    if ($aC !== $bC) {
        return $aC <=> $bC;
    }
    return strnatcasecmp((string)($a['operator'] ?? ''), (string)($b['operator'] ?? ''));
});

if ($asJson) {
    $out = json_encode(
        ['checked_at' => gmdate('c'), 'count' => count($results), 'results' => $results],
        JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
    if (!is_string($out)) {
        fwrite(STDERR, "Failed to encode JSON.\n");
        exit(5);
    }
    fwrite(STDOUT, $out . "\n");
    exit(0);
}

$ok = 0;
$bad = 0;
$manual = 0;

fwrite(STDOUT, "Season policy link check\n");
fwrite(STDOUT, "Matrix: $matrixPath\n\n");
if ($insecure) {
    fwrite(STDOUT, "NOTE: --insecure enabled (TLS cert verification disabled)\n\n");
}

foreach ($results as $row) {
    $cc = (string)$row['country'];
    $op = (string)$row['operator'];
    $checks = (array)($row['checks'] ?? []);
    if (empty($checks)) {
        continue;
    }
    fwrite(STDOUT, "$cc | $op\n");
    foreach ($checks as $label => $c) {
        $c = (array)$c;
        $code = (int)($c['http_code'] ?? 0);
        $method = (string)($c['method'] ?? '');
        $in = (string)($c['input_url'] ?? '');
        $eff = (string)($c['effective_url'] ?? '');
        $err = trim((string)($c['error'] ?? ''));
        $isOk = !empty($c['ok']);

        if ($isOk) {
            $ok++;
        } else {
            $bad++;
        }
        if (!$isOk && $code === 403) {
            $manual++;
        }

        $suffix = $eff !== '' && $eff !== $in ? " -> $eff" : '';
        $errStr = $err !== '' ? " err=\"$err\"" : '';
        fwrite(STDOUT, "  - $label [$method $code] $in$suffix$errStr\n");
    }
}

fwrite(STDOUT, "\nSummary: ok=$ok bad=$bad");
if ($manual > 0) {
    fwrite(STDOUT, " (manual_check_suggested=$manual)");
}
fwrite(STDOUT, "\n");

<?php
declare(strict_types=1);

namespace App\Service;

use DateTimeImmutable;
use DateTimeZone;
use Throwable;

/**
 * Converts provider-specific rows into the stable AIR lookup contract.
 */
final class AirFlightNormalizer
{
    /** Construct the provider-neutral normalizer. */
    public function __construct(private ?TransportOperatorRegistry $operatorRegistry = null)
    {
        $this->operatorRegistry ??= new TransportOperatorRegistry();
    }

    /**
     * @param array<string,mixed> $item
     * @param array<string,mixed> $context
     * @return array<string,mixed>|null
     */
    public function normalize(array $item, string $expectedFrom, string $expectedTo, array $context = []): ?array
    {
        $departureIata = $this->airportCode($item['departure_airport_iata'] ?? null, 3);
        $arrivalIata = $this->airportCode($item['arrival_airport_iata'] ?? null, 3);
        if (
            $departureIata === null
            || $arrivalIata === null
            || $departureIata !== $expectedFrom
            || $arrivalIata !== $expectedTo
        ) {
            return null;
        }

        $marketingNumber = $this->normalizeFlightNumber(
            (string)($item['marketing_flight_number'] ?? $item['flight_number'] ?? ''),
        );
        $operatingNumber = $this->normalizeFlightNumber((string)($item['operating_flight_number'] ?? ''));
        $requestedNumber = $this->normalizeFlightNumber((string)($context['flightNumber'] ?? ''));
        $codeshares = $this->normalizeFlightNumbers((array)($item['codeshare_numbers'] ?? []));
        if ($marketingNumber !== '') {
            $codeshares[] = $marketingNumber;
        }
        if ($operatingNumber !== '') {
            $codeshares[] = $operatingNumber;
        }
        $codeshares = array_values(array_unique($codeshares));

        if ($requestedNumber !== '' && $this->numbersIdentifySameService($requestedNumber, [$marketingNumber])) {
            // Keep the passenger-visible identity while preserving the operating identity separately.
            $marketingNumber = $requestedNumber;
        }
        $displayNumber = $marketingNumber !== '' ? $marketingNumber : $operatingNumber;

        $marketing = $this->carrierIdentity($item, 'marketing', $displayNumber);
        $operating = $this->carrierIdentity($item, 'operating', $operatingNumber);
        if ($operating['name'] === '' && $operating['iata'] === '' && $operating['icao'] === '') {
            $operating = $marketing;
        }

        $times = [];
        $timeFields = [
            'scheduled_departure', 'estimated_departure', 'actual_departure',
            'scheduled_arrival', 'estimated_arrival', 'actual_arrival',
        ];
        foreach ($timeFields as $field) {
            $times[$field] = $this->normalizeTimeSet($item, $field);
        }

        if ($times['scheduled_departure']['local'] === null && $times['scheduled_departure']['utc'] === null) {
            return null;
        }

        $departureDelay = $this->differenceMinutes(
            $times['scheduled_departure']['utc'],
            $times['actual_departure']['utc'],
        );
        $estimatedDepartureDelay = $this->differenceMinutes(
            $times['scheduled_departure']['utc'],
            $times['estimated_departure']['utc'],
        );
        $arrivalDelay = $this->differenceMinutes($times['scheduled_arrival']['utc'], $times['actual_arrival']['utc']);
        $estimatedArrivalDelay = $this->differenceMinutes(
            $times['scheduled_arrival']['utc'],
            $times['estimated_arrival']['utc'],
        );

        $cancelled = $this->nullableBool($item['cancelled'] ?? null);
        $diverted = $this->nullableBool($item['diverted'] ?? null);
        $status = $this->normalizeStatus(
            (string)($item['status'] ?? ''),
            $cancelled,
            $diverted,
            $times,
            $arrivalDelay,
            $estimatedArrivalDelay,
        );
        if ($status === 'cancelled') {
            $cancelled = true;
        }
        if ($status === 'diverted') {
            $diverted = true;
        }

        $retrievedAt = $this->normalizeUtc((string)($item['retrieved_at'] ?? '')) ?? gmdate('Y-m-d\TH:i:s\Z');
        $source = strtolower(trim((string)($item['source'] ?? 'unknown')));
        $providerFlightId = $this->bounded((string)($item['provider_flight_id'] ?? ''), 190);
        $identityKey = $this->identityKey(
            $displayNumber,
            $operatingNumber,
            $departureIata,
            $arrivalIata,
            $times['scheduled_departure']['utc'] ?? $times['scheduled_departure']['local'],
        );
        $departureDelayBasis = $departureDelay !== null
            ? 'actual'
            : ($estimatedDepartureDelay !== null ? 'estimated' : null);
        $arrivalDelayBasis = $arrivalDelay !== null
            ? 'actual'
            : ($estimatedArrivalDelay !== null ? 'estimated' : null);

        return [
            'flight_key' => trim((string)($item['flight_key'] ?? '')) !== ''
                ? (string)$item['flight_key']
                : hash('sha256', $identityKey . '|' . $source),
            'flight_identity_key' => $identityKey,
            'flight_number' => $displayNumber,
            'marketing_flight_number' => $marketingNumber,
            'operating_flight_number' => $operatingNumber,
            'marketing_carrier_name' => $marketing['name'],
            'marketing_carrier_iata' => $marketing['iata'],
            'marketing_carrier_icao' => $marketing['icao'],
            'operating_carrier_name' => $operating['name'],
            'operating_carrier_iata' => $operating['iata'],
            'operating_carrier_icao' => $operating['icao'],
            'carrier_name' => $marketing['name'] !== '' ? $marketing['name'] : $operating['name'],
            'departure_airport_iata' => $departureIata,
            'departure_airport_icao' => $this->airportCode($item['departure_airport_icao'] ?? null, 4),
            'arrival_airport_iata' => $arrivalIata,
            'arrival_airport_icao' => $this->airportCode($item['arrival_airport_icao'] ?? null, 4),
            'actual_arrival_airport_iata' => $this->airportCode($item['actual_arrival_airport_iata'] ?? null, 3),
            'actual_arrival_airport_icao' => $this->airportCode($item['actual_arrival_airport_icao'] ?? null, 4),
            'departure_timezone' => $times['scheduled_departure']['timezone'],
            'arrival_timezone' => $times['scheduled_arrival']['timezone'],
            'scheduled_departure_local' => $times['scheduled_departure']['local'],
            'scheduled_departure_utc' => $times['scheduled_departure']['utc'],
            'estimated_departure_local' => $times['estimated_departure']['local'],
            'estimated_departure_utc' => $times['estimated_departure']['utc'],
            'actual_departure_local' => $times['actual_departure']['local'],
            'actual_departure_utc' => $times['actual_departure']['utc'],
            'scheduled_arrival_local' => $times['scheduled_arrival']['local'],
            'scheduled_arrival_utc' => $times['scheduled_arrival']['utc'],
            'estimated_arrival_local' => $times['estimated_arrival']['local'],
            'estimated_arrival_utc' => $times['estimated_arrival']['utc'],
            'actual_arrival_local' => $times['actual_arrival']['local'],
            'actual_arrival_utc' => $times['actual_arrival']['utc'],
            'departure_delay_minutes' => $departureDelay,
            'estimated_departure_delay_minutes' => $estimatedDepartureDelay,
            'departure_delay_basis' => $departureDelayBasis,
            'arrival_delay_minutes' => $arrivalDelay,
            'estimated_arrival_delay_minutes' => $estimatedArrivalDelay,
            'arrival_delay_basis' => $arrivalDelayBasis,
            'status' => $status,
            'provider_status' => $this->bounded((string)($item['status'] ?? ''), 80),
            'cancelled' => $cancelled,
            'diverted' => $diverted,
            'source' => $source,
            'provider' => $source,
            'provider_flight_id' => $providerFlightId !== '' ? $providerFlightId : null,
            'retrieved_at' => $retrievedAt,
            'codeshare_numbers' => $codeshares,
            'codeshare_role' => strtolower(trim((string)($item['codeshare_role'] ?? 'unknown'))),
            'time_provenance' => is_array($item['time_provenance'] ?? null) ? $item['time_provenance'] : [],
            'operational_data_verified' => !in_array(
                $source,
                ['ticketless_seed', 'manual_fallback_seed', 'manual'],
                true,
            ),
        ];
    }

    /** Normalize the passenger-visible airline designator and service number. */
    public function normalizeFlightNumber(string $value): string
    {
        $value = strtoupper(trim($value));
        $value = preg_replace('/[\s\-]+/', '', $value) ?? $value;

        return preg_match('/^[A-Z0-9]{2,3}\d{1,5}[A-Z]?$/', $value) ? $value : '';
    }

    /** @param array<int,string> $values */
    private function normalizeFlightNumbers(array $values): array
    {
        $out = [];
        foreach ($values as $value) {
            $normalized = $this->normalizeFlightNumber((string)$value);
            if ($normalized !== '') {
                $out[] = $normalized;
            }
        }

        return $out;
    }

    /**
     * @param array<int,string> $candidates
     */
    private function numbersIdentifySameService(string $requested, array $candidates): bool
    {
        $requestedKey = $this->canonicalFlightNumber($requested);
        foreach ($candidates as $candidate) {
            if (
                $candidate === $requested
                || ($requestedKey !== '' && $requestedKey === $this->canonicalFlightNumber($candidate))
            ) {
                return true;
            }
        }

        return false;
    }

    /** Resolve IATA/ICAO variants to a stable internal operator/service key. */
    private function canonicalFlightNumber(string $number): string
    {
        $parts = $this->splitFlightNumber($number);
        if ($parts === null) {
            return $number;
        }
        $carrier = $this->operatorRegistry?->findByCode('air', $parts['carrier']);
        $operatorKey = strtolower(trim((string)($carrier['operator_key'] ?? '')));

        return ($operatorKey !== '' ? $operatorKey : $parts['carrier']) . ':' . ltrim($parts['number'], '0');
    }

    /** @return array{name:string,iata:string,icao:string} */
    private function carrierIdentity(array $item, string $role, string $flightNumber): array
    {
        $name = trim((string)($item[$role . '_carrier_name'] ?? ''));
        $iata = strtoupper(trim((string)($item[$role . '_carrier_iata'] ?? '')));
        $icao = strtoupper(trim((string)($item[$role . '_carrier_icao'] ?? '')));
        $lookup = $iata !== '' ? $iata : ($icao !== '' ? $icao : ($name !== '' ? $name : $flightNumber));
        $match = $lookup !== '' ? $this->operatorRegistry?->findByIdentity('air', $lookup) : null;
        $parts = $this->splitFlightNumber($flightNumber);
        if (!is_array($match) && $parts !== null) {
            $match = $this->operatorRegistry?->findByCode('air', $parts['carrier']);
        }
        $codes = is_array($match['codes'] ?? null) ? $match['codes'] : [];

        return [
            'name' => $name !== '' ? $name : trim((string)($match['name'] ?? '')),
            'iata' => preg_match('/^[A-Z0-9]{2}$/', $iata) ? $iata : strtoupper(trim((string)($codes['iata'] ?? ''))),
            'icao' => preg_match('/^[A-Z]{3}$/', $icao) ? $icao : strtoupper(trim((string)($codes['icao'] ?? ''))),
        ];
    }

    /** @return array{local:?string,utc:?string,timezone:?string} */
    private function normalizeTimeSet(array $item, string $field): array
    {
        $localRaw = trim((string)($item[$field . '_local'] ?? ''));
        $utcRaw = trim((string)($item[$field . '_utc'] ?? ''));
        $movement = str_contains($field, 'departure') ? 'departure' : 'arrival';
        $timezone = trim((string)(
            $item[$field . '_timezone']
            ?? $item[$movement . '_timezone']
            ?? ''
        ));
        $local = $this->normalizeLocal($localRaw);
        $utc = $this->normalizeUtc($utcRaw);
        if ($utc === null && $localRaw !== '') {
            $utc = $this->utcFromLocal($localRaw, $timezone);
        }

        return ['local' => $local, 'utc' => $utc, 'timezone' => $timezone !== '' ? $timezone : null];
    }

    /** Normalize an airport-local wall-clock timestamp without changing its date. */
    private function normalizeLocal(string $value): ?string
    {
        if ($value === '') {
            return null;
        }
        if (!preg_match('/^(\d{4}-\d{2}-\d{2})[T ](\d{2}:\d{2})(?::(\d{2}))?/', $value, $m)) {
            return null;
        }

        return $m[1] . 'T' . $m[2] . ':' . ($m[3] ?? '00');
    }

    /** Normalize an offset-aware timestamp to UTC. */
    private function normalizeUtc(string $value): ?string
    {
        if ($value === '') {
            return null;
        }
        try {
            return (new DateTimeImmutable($value))->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z');
        } catch (Throwable) {
            return null;
        }
    }

    /** Convert a local timestamp only when an offset or airport timezone is known. */
    private function utcFromLocal(string $value, string $timezone): ?string
    {
        try {
            if (preg_match('/(?:Z|[+\-]\d{2}:?\d{2})$/', $value)) {
                return (new DateTimeImmutable($value))->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z');
            }
            if ($timezone !== '') {
                return (new DateTimeImmutable($value, new DateTimeZone($timezone)))
                    ->setTimezone(new DateTimeZone('UTC'))
                    ->format('Y-m-d\TH:i:s\Z');
            }
        } catch (Throwable) {
        }

        return null;
    }

    /** Calculate a delay from UTC timestamps; naive local values are never compared. */
    private function differenceMinutes(?string $scheduledUtc, ?string $observedUtc): ?int
    {
        if ($scheduledUtc === null || $observedUtc === null) {
            return null;
        }
        try {
            $scheduled = new DateTimeImmutable($scheduledUtc);
            $observed = new DateTimeImmutable($observedUtc);
        } catch (Throwable) {
            return null;
        }

        return (int)round(($observed->getTimestamp() - $scheduled->getTimestamp()) / 60);
    }

    /** @param array<string,array{local:?string,utc:?string,timezone:?string}> $times */
    private function normalizeStatus(
        string $raw,
        ?bool $cancelled,
        ?bool $diverted,
        array $times,
        ?int $arrivalDelay,
        ?int $estimatedArrivalDelay,
    ): string {
        $value = strtolower(trim($raw));
        if ($cancelled === true || str_contains($value, 'cancel') || str_contains($value, 'annul')) {
            return 'cancelled';
        }
        if ($diverted === true || str_contains($value, 'divert') || str_contains($value, 'return')) {
            return 'diverted';
        }
        if ($times['actual_arrival']['utc'] !== null || preg_match('/arriv|landed|completed/', $value)) {
            return 'arrived';
        }
        if ($times['actual_departure']['utc'] !== null || preg_match('/departed|airborne|enroute|en route/', $value)) {
            return 'departed';
        }
        if (str_contains($value, 'active')) {
            return 'active';
        }
        if (
            str_contains($value, 'delay')
            || ($arrivalDelay !== null && $arrivalDelay > 0)
            || ($estimatedArrivalDelay !== null && $estimatedArrivalDelay > 0)
        ) {
            return 'delayed';
        }
        if (preg_match('/scheduled|expected|boarding/', $value)) {
            return 'scheduled';
        }

        return 'unknown';
    }

    /** Build a deterministic operational identity without dropping the marketing number. */
    private function identityKey(
        string $marketingNumber,
        string $operatingNumber,
        string $from,
        string $to,
        ?string $departure,
    ): string {
        $number = $operatingNumber !== '' ? $operatingNumber : $marketingNumber;

        return implode('|', [$this->canonicalFlightNumber($number), $from, $to, (string)$departure]);
    }

    /** @return array{carrier:string,number:string}|null */
    private function splitFlightNumber(string $number): ?array
    {
        if (preg_match('/^([A-Z]{3})(\d{1,5}[A-Z]?)$/', $number, $matches)) {
            return ['carrier' => $matches[1], 'number' => $matches[2]];
        }
        if (preg_match('/^([A-Z0-9]{2})(\d{1,5}[A-Z]?)$/', $number, $matches)) {
            return ['carrier' => $matches[1], 'number' => $matches[2]];
        }

        return null;
    }

    /** Validate an IATA or ICAO airport code. */
    private function airportCode(mixed $value, int $length): ?string
    {
        $value = strtoupper(trim((string)$value));

        return preg_match('/^[A-Z]{' . $length . '}$/', $value) ? $value : null;
    }

    /** Preserve unknown provider booleans as null. */
    private function nullableBool(mixed $value): ?bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if ($value === null || $value === '') {
            return null;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    }

    /** Bound provider text retained in the normalized contract. */
    private function bounded(string $value, int $length): string
    {
        return mb_substr(trim($value), 0, $length);
    }
}

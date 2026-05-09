<?php
declare(strict_types=1);

namespace App\Service;

final class SeededFerryDepartureListProvider implements FerryDepartureListProviderInterface
{
    public function searchByRouteAndDate(string $departureCode, string $arrivalCode, string $date, array $context = []): array
    {
        $date = trim($date);
        if ($date === '') {
            return [];
        }

        $departureLabel = trim((string)($context['departureLabel'] ?? ''));
        $arrivalLabel = trim((string)($context['arrivalLabel'] ?? ''));
        $operator = trim((string)($context['operator'] ?? ''));
        $departureTime = $this->normalizeTime((string)($context['depTime'] ?? ''));
        $arrivalTime = $this->normalizeTime((string)($context['arrTime'] ?? ''));
        $vesselName = trim((string)($context['vesselName'] ?? ''));

        if ($departureLabel === '' && $arrivalLabel === '' && $operator === '' && $departureTime === '' && $vesselName === '') {
            return [];
        }

        $departureCode = strtoupper(trim($departureCode));
        $arrivalCode = strtoupper(trim($arrivalCode));
        $resolvedStatus = trim((string)($context['status'] ?? 'scheduled'));

        return [[
            'departure_key' => implode('|', [
                $date,
                $departureCode !== '' ? $departureCode : $departureLabel,
                $arrivalCode !== '' ? $arrivalCode : $arrivalLabel,
                $departureTime !== '' ? $departureTime : '00:00',
                $operator !== '' ? $operator : 'seed',
            ]),
            'operator_name' => $operator,
            'vessel_name' => $vesselName,
            'departure_port_code' => $departureCode,
            'arrival_port_code' => $arrivalCode,
            'departure_port_name' => $departureLabel,
            'arrival_port_name' => $arrivalLabel,
            'scheduled_departure_local' => $this->buildDateTime($date, $departureTime),
            'scheduled_arrival_local' => $this->buildDateTime($date, $arrivalTime),
            'estimated_arrival_local' => $this->buildDateTime($date, $arrivalTime),
            'status' => $resolvedStatus !== '' ? $resolvedStatus : null,
            'source' => 'ticketless_seed',
        ]];
    }

    private function normalizeTime(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }
        if (preg_match('/^(\d{1,2}):(\d{2})/', $value, $m)) {
            return sprintf('%02d:%02d', (int)$m[1], (int)$m[2]);
        }

        return '';
    }

    private function buildDateTime(string $date, string $time): ?string
    {
        if ($date === '') {
            return null;
        }
        if ($time === '') {
            return $date . 'T00:00:00';
        }

        return $date . 'T' . $time . ':00';
    }
}

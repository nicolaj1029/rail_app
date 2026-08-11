<?php
declare(strict_types=1);

namespace App\Service\Air;

final class AirExpenseReviewBandKeyMapper
{
    /**
     * @param array<int,string> $selectedExistingKeys
     * @param array<string,mixed> $context
     * @return array<int,array<string,string>>
     */
    public function map(string $scope, array $selectedExistingKeys, array $context): array
    {
        $form = (array)($context['form'] ?? []);
        $scope = strtolower(trim($scope));
        $seen = [];
        $mapped = [];

        foreach ($selectedExistingKeys as $existingKey) {
            $existingKey = trim((string)$existingKey);
            if ($existingKey === '') {
                continue;
            }

            if ($scope === 'air_assistance_scope') {
                foreach ($this->mapAssistanceKey($existingKey, $form) as $item) {
                    $dedupe = $item['existing_key'] . '|' . $item['internal_category'];
                    if (!isset($seen[$dedupe])) {
                        $seen[$dedupe] = true;
                        $mapped[] = $item;
                    }
                }
                continue;
            }

            if (in_array($scope, ['air_reroute_scope', 'air_refund_scope'], true)) {
                foreach ($this->mapTransportKey($scope, $existingKey, $form, $context) as $item) {
                    $dedupe = $item['existing_key'] . '|' . $item['internal_category'];
                    if (!isset($seen[$dedupe])) {
                        $seen[$dedupe] = true;
                        $mapped[] = $item;
                    }
                }
            }
        }

        return $mapped;
    }

    /**
     * @param array<string,mixed> $form
     * @return array<int,array<string,string>>
     */
    private function mapAssistanceKey(string $existingKey, array $form): array
    {
        $value = strtolower(trim((string)($form[$existingKey] ?? '')));
        if (in_array($existingKey, ['meal_offered', 'air_meals_offered', 'air_refreshments_offered'], true)) {
            if (!in_array($value, ['yes', 'no'], true)) {
                return [];
            }

            return [[
                'existing_key' => $existingKey,
                'internal_category' => 'assistance_meals',
            ]];
        }

        if (in_array($existingKey, ['hotel_offered', 'air_hotel_offered'], true)) {
            if (!in_array($value, ['yes', 'no'], true)) {
                return [];
            }

            return [[
                'existing_key' => $existingKey,
                'internal_category' => 'assistance_hotel',
            ]];
        }

        if (in_array($existingKey, ['assistance_hotel_transport_included', 'air_hotel_transport_included'], true)) {
            if (!in_array($value, ['yes', 'no'], true)) {
                return [];
            }

            return [[
                'existing_key' => $existingKey,
                'internal_category' => 'assistance_hotel_airport_transfer',
            ]];
        }

        if (in_array($existingKey, ['air_communication_offered', 'communication_offered'], true)) {
            if (!in_array($value, ['yes', 'no'], true)) {
                return [];
            }

            return [[
                'existing_key' => $existingKey,
                'internal_category' => 'assistance_communication',
            ]];
        }

        return [];
    }

    /**
     * @param array<string,mixed> $form
     * @param array<string,mixed> $context
     * @return array<int,array<string,string>>
     */
    private function mapTransportKey(string $scope, string $existingKey, array $form, array $context): array
    {
        $mapped = [];
        $prefix = $scope === 'air_reroute_scope' ? 'reroute_' : 'refund_return_';

        $typeKeys = $scope === 'air_reroute_scope'
            ? ['air_reroute_expense_type', 'air_reroute_expense_items.type', 'air_alternative_airport_transfer_needed']
            : ['air_return_expense_type', 'air_return_expense_items.type'];
        if (!in_array($existingKey, $typeKeys, true)) {
            return [];
        }

        $types = [];
        if ($existingKey === 'air_alternative_airport_transfer_needed') {
            if ((string)($form['air_alternative_airport_transfer_needed'] ?? '') === 'yes') {
                $types[] = [
                    'type' => 'airport_transfer',
                    'description' => trim((string)($form['air_alternative_airport_from'] ?? '') . ' ' . (string)($form['air_alternative_airport_to'] ?? '')),
                ];
            }
        } elseif (str_ends_with($existingKey, '_items.type')) {
            $rows = (array)($form[$scope === 'air_reroute_scope' ? 'air_reroute_expense_items' : 'air_return_expense_items'] ?? []);
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $types[] = [
                    'type' => strtolower(trim((string)($row['type'] ?? ''))),
                    'description' => trim((string)($row['description'] ?? '')),
                ];
            }
        } else {
            $types[] = [
                'type' => strtolower(trim((string)($form[$existingKey] ?? ''))),
                'description' => trim((string)($form[$scope === 'air_reroute_scope' ? 'air_reroute_expense_description' : 'air_return_expense_description'] ?? '')),
            ];
        }

        foreach ($types as $row) {
            $type = (string)($row['type'] ?? '');
            if ($type === '') {
                continue;
            }

            $category = match ($type) {
                'new_ticket', 'expensive_solution' => $scope === 'air_reroute_scope'
                    ? 'reroute_replacement_' . $this->ticketDistanceCategory($context)
                    : 'refund_return_' . $this->ticketDistanceCategory($context),
                'return_to_origin' => $scope === 'air_refund_scope'
                    ? 'refund_return_' . $this->ticketDistanceCategory($context)
                    : '',
                'airport_transfer' => $prefix . 'airport_change_transfer',
                'other_transport' => $prefix . $this->transportDescriptionCategory((string)($row['description'] ?? '')),
                default => '',
            };
            if ($category === '') {
                continue;
            }

            $mapped[] = [
                'existing_key' => $existingKey,
                'internal_category' => $category,
            ];
        }

        return $mapped;
    }

    /**
     * @param array<string,mixed> $context
     */
    private function ticketDistanceCategory(array $context): string
    {
        $form = (array)($context['form'] ?? []);
        $airScope = (array)($context['airScope'] ?? ($context['air_scope'] ?? []));
        $distanceBand = strtolower(trim((string)($form['air_distance_band'] ?? ($airScope['air_distance_band'] ?? ''))));
        if ($distanceBand === 'other_over_3500') {
            return 'air_ticket_longhaul';
        }

        $distanceKm = $form['flight_distance_km'] ?? ($airScope['flight_distance_km'] ?? null);
        if (is_numeric($distanceKm) && (float)$distanceKm > 3500) {
            return 'air_ticket_longhaul';
        }

        return 'air_ticket_shorthaul';
    }

    private function transportDescriptionCategory(string $description): string
    {
        $haystack = strtolower(trim($description));
        if ($haystack === '') {
            return 'city_transfer';
        }
        if (preg_match('/taxi|uber|bolt|lyft|rideshare/', $haystack)) {
            return 'taxi_or_rideshare';
        }
        if (preg_match('/train|rail|bus|coach|metro/', $haystack)) {
            return 'rail_or_bus_transfer';
        }
        if (preg_match('/airport|lufthavn|terminal/', $haystack)) {
            return 'airport_change_transfer';
        }

        return 'city_transfer';
    }
}

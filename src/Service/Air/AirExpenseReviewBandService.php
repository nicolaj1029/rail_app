<?php
declare(strict_types=1);

namespace App\Service\Air;

final class AirExpenseReviewBandService
{
    /** @var array<string,mixed> */
    private array $zonesConfig;
    /** @var array<string,mixed> */
    private array $bandsConfig;
    private AirPassengerExpenseLocationResolver $locationResolver;
    private AirExpenseReviewBandKeyMapper $keyMapper;

    /**
     * @param array<string,mixed>|null $zonesConfig
     * @param array<string,mixed>|null $bandsConfig
     */
    public function __construct(
        ?array $zonesConfig = null,
        ?array $bandsConfig = null,
        ?AirPassengerExpenseLocationResolver $locationResolver = null,
        ?AirExpenseReviewBandKeyMapper $keyMapper = null
    ) {
        $this->zonesConfig = $zonesConfig ?? (array)include CONFIG . 'air' . DS . 'air_airport_cost_zones.php';
        $this->bandsConfig = $bandsConfig ?? (array)include CONFIG . 'air' . DS . 'air_expense_review_bands.php';
        $this->locationResolver = $locationResolver ?? new AirPassengerExpenseLocationResolver();
        $this->keyMapper = $keyMapper ?? new AirExpenseReviewBandKeyMapper();
    }

    /**
     * @param array<string,mixed> $context
     * @return array<string,mixed>
     */
    public function resolveExpenseLocation(array $context): array
    {
        return $this->locationResolver->resolve($context);
    }

    public function resolveAirportCostZone(?string $airportIata): string
    {
        $airportIata = strtoupper(trim((string)$airportIata));
        $default = strtolower(trim((string)($this->zonesConfig['default_zone'] ?? 'mid')));
        $zone = strtolower(trim((string)(($this->zonesConfig['airport_overrides'] ?? [])[$airportIata] ?? '')));

        return in_array($zone, ['low', 'mid', 'high', 'hub', 'very_high'], true) ? $zone : $default;
    }

    /**
     * @param array<string,mixed> $context
     */
    public function resolveScope(array $context): string
    {
        $scope = strtolower(trim((string)($context['scope'] ?? ($context['action'] ?? ''))));

        return match ($scope) {
            'assistance', 'air_assistance_scope' => 'air_assistance_scope',
            'remedies_reroute', 'reroute', 'air_reroute_scope' => 'air_reroute_scope',
            'remedies_refund', 'refund', 'air_refund_scope' => 'air_refund_scope',
            default => 'air_assistance_scope',
        };
    }

    /**
     * @param array<int,string> $selectedExistingKeys
     * @param array<string,mixed> $context
     * @return array<int,array<string,string>>
     */
    public function mapExistingKeysToCategories(string $scope, array $selectedExistingKeys, array $context): array
    {
        return $this->keyMapper->map($scope, $selectedExistingKeys, $context);
    }

    /**
     * @param array<string,mixed> $context
     * @param array<int,string> $selectedExistingKeys
     * @return array<string,mixed>
     */
    public function estimateForScope(array $context, array $selectedExistingKeys): array
    {
        $scope = $this->resolveScope($context);
        $location = $this->resolveExpenseLocation($context);
        $zone = $this->resolveAirportCostZone((string)($location['expense_airport_iata'] ?? ''));
        $mapped = $this->mapExistingKeysToCategories($scope, $selectedExistingKeys, $context);
        $items = [];
        $categorySeen = [];
        $totalMin = 0;
        $totalMax = 0;
        $manualReviewRequired = false;
        $manualReviewReasons = [];

        foreach ($mapped as $row) {
            $category = (string)($row['internal_category'] ?? '');
            if ($category === '' || isset($categorySeen[$category])) {
                continue;
            }
            $band = $this->bandForCategory($scope, $category, $zone);
            if ($band === []) {
                continue;
            }
            $categorySeen[$category] = true;

            $item = [
                'existing_key' => (string)($row['existing_key'] ?? ''),
                'internal_category' => $category,
                'label' => $this->labelForCategory($category),
                'legal_basis' => str_starts_with($scope, 'air_assistance_') ? 'EU261 Art. 9' : 'EU261 Art. 8',
                'min' => (int)($band['min'] ?? 0),
                'max' => (int)($band['max'] ?? 0),
                'currency' => (string)($this->bandsConfig['currency'] ?? 'EUR'),
                'manual_review_above' => (int)($band['manual_review_above'] ?? 0),
                'is_estimate_only' => true,
            ];
            $items[] = $item;
            $totalMin += (int)$item['min'];
            $totalMax += (int)$item['max'];

            $uploaded = $this->uploadedAmountForCategory($scope, $category, $context);
            if ($uploaded !== null && $uploaded > (float)$item['manual_review_above']) {
                $manualReviewRequired = true;
                $manualReviewReasons[] = $this->manualReviewReason($category, $zone, $uploaded, (float)$item['manual_review_above']);
            }
        }

        return [
            'scope' => $scope,
            'expense_airport_iata' => (string)($location['expense_airport_iata'] ?? ''),
            'expense_country_code' => (string)($location['expense_country_code'] ?? ''),
            'expense_airport_label' => (string)($location['expense_airport_label'] ?? ''),
            'airport_cost_zone' => $zone,
            'location_source' => (string)($location['location_source'] ?? 'unresolved'),
            'location_confidence' => (string)($location['location_confidence'] ?? 'low'),
            'fallback_used' => !empty($location['fallback_used']),
            'currency' => (string)($this->bandsConfig['currency'] ?? 'EUR'),
            'items' => $items,
            'total_min' => $totalMin,
            'total_max' => $totalMax,
            'flags' => [
                'is_estimate_only' => true,
                'manual_review_possible' => true,
                'manual_review_required' => $manualReviewRequired,
                'manual_review_reasons' => $manualReviewReasons,
            ],
            'ui_note' => 'Belobene er vejledende review-niveauer og ikke faste juridiske caps. Endelig vurdering sker ud fra dokumentation, nodvendighed og rimelighed.',
        ];
    }

    /**
     * @param array<string,mixed> $context
     * @return array<string,mixed>
     */
    public function availableBandsForScope(array $context): array
    {
        $scope = $this->resolveScope($context);
        $keys = match ($scope) {
            'air_assistance_scope' => ['meal_offered', 'hotel_offered', 'assistance_hotel_transport_included', 'communication_offered'],
            'air_reroute_scope' => ['air_reroute_expense_items.type', 'air_alternative_airport_transfer_needed'],
            'air_refund_scope' => ['air_return_expense_items.type', 'air_return_expense_type'],
            default => [],
        };

        if ($scope === 'air_reroute_scope') {
            $form = (array)($context['form'] ?? []);
            if (empty($form['air_reroute_expense_items']) && empty($form['air_reroute_expense_type'])) {
                $context['form']['air_reroute_expense_items'] = [
                    ['type' => 'new_ticket', 'description' => ''],
                    ['type' => 'airport_transfer', 'description' => ''],
                    ['type' => 'other_transport', 'description' => 'taxi'],
                    ['type' => 'other_transport', 'description' => 'train'],
                    ['type' => 'other_transport', 'description' => 'city transfer'],
                ];
            }
        } elseif ($scope === 'air_refund_scope') {
            $form = (array)($context['form'] ?? []);
            if (empty($form['air_return_expense_items']) && empty($form['air_return_expense_type'])) {
                $context['form']['air_return_expense_items'] = [
                    ['type' => 'return_to_origin', 'description' => ''],
                    ['type' => 'airport_transfer', 'description' => ''],
                    ['type' => 'other_transport', 'description' => 'taxi'],
                    ['type' => 'other_transport', 'description' => 'train'],
                    ['type' => 'other_transport', 'description' => 'city transfer'],
                ];
            }
        } elseif ($scope === 'air_assistance_scope') {
            $context['form'] = array_merge((array)($context['form'] ?? []), [
                'meal_offered' => 'no',
                'hotel_offered' => 'no',
                'assistance_hotel_transport_included' => 'no',
                'communication_offered' => 'no',
            ]);
        }

        return $this->estimateForScope($context, $keys);
    }

    /**
     * @return array<string,mixed>
     */
    private function bandForCategory(string $scope, string $category, string $zone): array
    {
        if ($scope === 'air_assistance_scope') {
            $map = [
                'assistance_meals' => 'meals',
                'assistance_hotel' => 'hotel',
                'assistance_hotel_airport_transfer' => 'hotel_airport_transfer',
                'assistance_communication' => 'communication',
            ];

            return (array)(($this->bandsConfig['assistance_bands_by_airport_zone'] ?? [])[$zone][$map[$category] ?? ''] ?? []);
        }

        $transportMap = [
            'reroute_city_transfer' => 'city_transfer',
            'reroute_airport_change_transfer' => 'airport_change_transfer',
            'reroute_rail_or_bus_transfer' => 'rail_or_bus_transfer',
            'reroute_taxi_or_rideshare' => 'taxi_or_rideshare',
            'refund_return_city_transfer' => 'city_transfer',
            'refund_return_airport_change_transfer' => 'airport_change_transfer',
            'refund_return_rail_or_bus_transfer' => 'rail_or_bus_transfer',
            'refund_return_taxi_or_rideshare' => 'taxi_or_rideshare',
        ];
        if (isset($transportMap[$category])) {
            return (array)(($this->bandsConfig['transport_bands_by_airport_zone'] ?? [])[$zone][$transportMap[$category]] ?? []);
        }

        $ticketBandKey = str_ends_with($category, 'longhaul') ? 'longhaul' : 'regional_or_shorthaul';
        if (str_contains($category, 'air_ticket_')) {
            return (array)(($this->bandsConfig['flight_ticket_bands'] ?? [])[$ticketBandKey] ?? []);
        }

        return [];
    }

    private function labelForCategory(string $category): string
    {
        return match ($category) {
            'assistance_meals' => 'Maltider / forfriskninger',
            'assistance_hotel' => 'Hotel / indkvartering',
            'assistance_hotel_airport_transfer' => 'Transport mellem lufthavn og hotel',
            'assistance_communication' => 'Kommunikation',
            'reroute_replacement_air_ticket_shorthaul' => 'Ny flybillet (regional / short-haul)',
            'reroute_replacement_air_ticket_longhaul' => 'Ny flybillet (long-haul)',
            'reroute_city_transfer' => 'Bynaer transfer ved ombooking',
            'reroute_airport_change_transfer' => 'Transfer til/fra alternativ lufthavn',
            'reroute_rail_or_bus_transfer' => 'Tog / bus ved ombooking',
            'reroute_taxi_or_rideshare' => 'Taxi / rideshare ved ombooking',
            'refund_return_air_ticket_shorthaul' => 'Returflyvning / ny billet tilbage',
            'refund_return_air_ticket_longhaul' => 'Returflyvning / ny long-haul billet tilbage',
            'refund_return_city_transfer' => 'Bynaer transfer tilbage til udgangspunkt',
            'refund_return_airport_change_transfer' => 'Lufthavnsskift / transfer tilbage',
            'refund_return_rail_or_bus_transfer' => 'Tog / bus tilbage til udgangspunkt',
            'refund_return_taxi_or_rideshare' => 'Taxi / rideshare tilbage til udgangspunkt',
            default => $category,
        };
    }

    /**
     * @param array<string,mixed> $context
     */
    private function uploadedAmountForCategory(string $scope, string $category, array $context): ?float
    {
        $form = (array)($context['form'] ?? []);

        if ($scope === 'air_assistance_scope') {
            return match ($category) {
                'assistance_meals' => $this->sumList($form['meal_self_paid_amount_items'] ?? [$form['meal_self_paid_amount'] ?? null]),
                'assistance_hotel' => $this->sumList($form['hotel_self_paid_amount_items'] ?? [$form['hotel_self_paid_amount'] ?? null]),
                'assistance_hotel_airport_transfer' => $this->sumList([$form['hotel_transport_self_paid_amount'] ?? null]),
                default => null,
            };
        }

        $rows = (array)($form[$scope === 'air_reroute_scope' ? 'air_reroute_expense_items' : 'air_return_expense_items'] ?? []);
        $total = 0.0;
        $matched = false;
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $type = strtolower(trim((string)($row['type'] ?? '')));
            $description = strtolower(trim((string)($row['description'] ?? '')));
            $amount = $this->toFloat($row['amount'] ?? null);
            if ($amount === null) {
                continue;
            }
            if ($this->rowMatchesCategory($category, $type, $description, $scope)) {
                $matched = true;
                $total += $amount;
            }
        }

        return $matched ? $total : null;
    }

    private function rowMatchesCategory(string $category, string $type, string $description, string $scope): bool
    {
        $prefix = $scope === 'air_reroute_scope' ? 'reroute_' : 'refund_return_';
        return match (true) {
            str_ends_with($category, 'airport_change_transfer') => $type === 'airport_transfer' || str_contains($description, 'airport'),
            str_ends_with($category, 'taxi_or_rideshare') => $type === 'other_transport' && preg_match('/taxi|uber|bolt|lyft|rideshare/', $description) === 1,
            str_ends_with($category, 'rail_or_bus_transfer') => $type === 'other_transport' && preg_match('/train|rail|bus|coach|metro/', $description) === 1,
            str_ends_with($category, 'city_transfer') => $type === 'other_transport' && !preg_match('/taxi|uber|bolt|lyft|rideshare|train|rail|bus|coach|metro|airport/', $description),
            $category === $prefix . 'replacement_air_ticket_shorthaul' || $category === $prefix . 'replacement_air_ticket_longhaul' => in_array($type, ['new_ticket', 'expensive_solution', 'return_to_origin'], true),
            default => false,
        };
    }

    /**
     * @param array<int,mixed>|mixed $values
     */
    private function sumList(mixed $values): ?float
    {
        $rows = is_array($values) ? $values : [$values];
        $sum = 0.0;
        $matched = false;
        foreach ($rows as $value) {
            $float = $this->toFloat($value);
            if ($float === null) {
                continue;
            }
            $matched = true;
            $sum += $float;
        }

        return $matched ? $sum : null;
    }

    private function toFloat(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return (float)$value;
        }

        $normalized = str_replace(',', '.', preg_replace('/[^0-9,.-]/', '', (string)$value) ?? '');
        return is_numeric($normalized) ? (float)$normalized : null;
    }

    private function manualReviewReason(string $category, string $zone, float $amount, float $threshold): string
    {
        $label = $this->labelForCategory($category);
        return sprintf('%s exceeds %s airport review band (%.2f > %.2f)', $label, $zone, $amount, $threshold);
    }
}

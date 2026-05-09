<?php
declare(strict_types=1);

namespace App\Service;

use Cake\Core\Configure;

final class TransportCapsResolver
{
    /**
     * @param array<string,mixed> $airScope
     * @param array<string,mixed> $context
     * @return array<string,mixed>
     */
    public function resolveAir(array $airScope = [], array $context = []): array
    {
        $config = $this->readAirConfig();
        $engine = (array)($config['engine'] ?? []);
        $jurisdiction = $this->resolveAirJurisdiction($airScope, $context);
        $legal = (array)($config['jurisdictions'][$jurisdiction] ?? []);
        $uiMode = $jurisdiction === 'EU261' ? 'no_fixed_caps' : 'show_engine_caps';

        return [
            'jurisdiction' => $jurisdiction,
            'engine' => $engine,
            'legal' => $legal,
            'ui_mode' => $uiMode,
            'tooltips' => [
                'care' => $this->buildCareTooltip($jurisdiction, $engine, $legal),
                'reroute' => $this->buildRerouteTooltip($jurisdiction, $engine, $legal),
            ],
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function readAirConfig(): array
    {
        $config = (array)Configure::read('TransportCaps.air', []);
        if ($config !== []) {
            return $config;
        }

        $fallback = CONFIG . 'transport_caps.php';
        if (!is_file($fallback)) {
            return [];
        }

        /** @var array<string,mixed> $raw */
        $raw = include $fallback;

        return (array)($raw['TransportCaps']['air'] ?? []);
    }

    /**
     * @param array<string,mixed> $airScope
     * @param array<string,mixed> $context
     */
    private function resolveAirJurisdiction(array $airScope, array $context): string
    {
        $candidates = [
            $context['jurisdiction'] ?? null,
            $context['air_legal_jurisdiction'] ?? null,
            $airScope['jurisdiction'] ?? null,
            $airScope['legal_regime'] ?? null,
            $airScope['scope_regime'] ?? null,
        ];
        foreach ($candidates as $candidate) {
            $normalized = $this->normalizeJurisdiction($candidate);
            if ($normalized !== null) {
                return $normalized;
            }
        }

        if (!empty($airScope['regulation_applies'])) {
            return 'EU261';
        }

        return 'MONTREAL';
    }

    private function normalizeJurisdiction(mixed $value): ?string
    {
        $normalized = strtoupper(trim((string)$value));
        return match ($normalized) {
            'EU261', 'EU', 'EEA', 'UK' => 'EU261',
            'CA', 'CANADA', 'APPR' => 'CA',
            'US', 'USA', 'DOT' => 'US',
            'MONTREAL', 'MC99', 'INTL', 'GLOBAL' => 'MONTREAL',
            default => null,
        };
    }

    /**
     * @param array<string,mixed> $engine
     * @param array<string,mixed> $legal
     */
    private function buildCareTooltip(string $jurisdiction, array $engine, array $legal): string
    {
        $meals = (array)($engine['meals'] ?? []);
        $hotel = (array)($engine['hotel'] ?? []);
        $hotelTransport = (array)($engine['hotel_transport'] ?? []);
        $engineLine = sprintf(
            'Intern review: maaltider op til EUR %s/dag, hotel op til EUR %s/nat, hoteltransport op til EUR %s samlet.',
            (string)($meals['per_day_eur'] ?? '?'),
            (string)($hotel['per_night_eur'] ?? '?'),
            (string)($hotelTransport['total_eur'] ?? '?')
        );

        return match ($jurisdiction) {
            'EU261' => 'Juridisk: maaltider, hotel og transport skal gives gratis, uden faste pengebeloebslofter. Kun rimelige og noedvendige udgifter. '
                . $engineLine,
            'CA' => 'Juridisk: food, drink, hotel og transport skal gives ved disruption, men uden faste money-caps. '
                . $engineLine,
            'US' => 'Juridisk: care afhænger typisk af airline policy. Ingen generel foederal ret til hotel eller maaltider ved delays/cancellations. '
                . $engineLine,
            default => 'Juridisk: Montreal giver ikke et fast live care-loft. Reelle og dokumenterede tab vurderes konkret. '
                . $engineLine,
        };
    }

    /**
     * @param array<string,mixed> $engine
     * @param array<string,mixed> $legal
     */
    private function buildRerouteTooltip(string $jurisdiction, array $engine, array $legal): string
    {
        $transfer = (array)($engine['transfer'] ?? []);
        $selfReroute = (array)($engine['self_reroute'] ?? []);
        $engineLine = sprintf(
            'Intern review: transfer op til EUR %s bynaert eller EUR %s mellem lufthavne. Selvbetalt reroute typisk op til EUR %s regionalt eller EUR %s long-haul.',
            (string)($transfer['urban_eur'] ?? '?'),
            (string)($transfer['inter_airport_eur'] ?? '?'),
            (string)($selfReroute['short_medium_haul_eur'] ?? '?'),
            (string)($selfReroute['long_haul_eur'] ?? '?')
        );

        return match ($jurisdiction) {
            'EU261' => 'Juridisk: airline skal tilbyde refund eller comparable reroute ved earliest opportunity. Transfer mellem lufthavne skal daekkes fuldt. '
                . $engineLine,
            'CA' => 'Juridisk: reroute og disruption-compensation afhænger af carrier size og situation. Care er rimelig, ikke cap-baseret. '
                . $engineLine,
            'US' => 'Juridisk: reroute, hotel og anden disruption coverage afhænger ofte af airline commitments. Denied boarding har egne DOT-beloeb. '
                . $engineLine,
            default => 'Juridisk: Montreal giver ikke et fast reroute-loft. Dokumenterede meromkostninger vurderes konkret. '
                . $engineLine,
        };
    }
}

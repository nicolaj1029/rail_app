<?php
declare(strict_types=1);

namespace App\Service;

use Cake\Core\Configure;
use Cake\Utility\Hash;

/**
 * PriceHintsService
 *
 * Beregner realistiske prisintervaller for udgifter i Art. 18(3)/Art. 20.
 * Læser basisintervaller fra config/price_hints/*.json og skalerer efter land,
 * stationstype og togtype. Valuta hentes via fxFetcher-callback (eksternt API)
 * med statisk fallback hvis callback ikke giver en rate.
 */
class PriceHintsService
{
    private array $priceProfiles;
    private array $stationProfiles;
    private array $baseRanges;

    public function __construct()
    {
        $this->priceProfiles = $this->loadJson(CONFIG . 'price_hints' . DS . 'price_profiles.json');
        $this->stationProfiles = $this->loadJson(CONFIG . 'price_hints' . DS . 'station_profiles.json');
        $this->baseRanges = $this->loadJson(CONFIG . 'price_hints' . DS . 'base_price_ranges_eur.json');
    }

    /**
     * @param array $ctx [
     *   countryCode: string (ISO),
     *   stationType: RURAL|REGIONAL|METRO,
     *   trainType: REGIONAL|INTERCITY|HIGHSPEED,
     *   arrivalLocalHour: int 0-23,
     *   currencyOverride?: string (ISO)
     * ]
     * @param callable|null $fxFetcher fn(string $currency): float|null  // henter kurs mod EUR
     * @return array {
     *   meals:{min,max,currency}|null,
     *   hotelPerNight:{min,max,currency}|null,
     *   taxi:{min,max,currency}|null,
     *   altTransport:{min,max,currency}|null,
     *   upgradeFirstClass:{min,max,currency}|null
     * }
     */
    public function build(array $ctx, ?callable $fxFetcher = null): array
    {
        $countryCode = strtoupper((string)Hash::get($ctx, 'countryCode', 'DE'));
        $stationType = strtoupper((string)Hash::get($ctx, 'stationType', 'REGIONAL'));
        $trainType = strtoupper((string)Hash::get($ctx, 'trainType', 'INTERCITY'));
        $arrivalHour = (int)Hash::get($ctx, 'arrivalLocalHour', 12);
        $currencyOverride = strtoupper((string)Hash::get($ctx, 'currencyOverride', ''));

        $countryProfile = $this->priceProfiles['countries'][$countryCode] ?? ['priceLevel' => 'MID', 'currency' => $this->priceProfiles['defaultCurrency'] ?? 'EUR'];
        $priceLevel = strtoupper((string)($countryProfile['priceLevel'] ?? 'MID'));
        $currency = $currencyOverride !== '' ? $currencyOverride : (string)($countryProfile['currency'] ?? 'EUR');
        $stationProfile = $this->stationProfiles['stationTypes'][$stationType] ?? $this->stationProfiles['stationTypes']['REGIONAL'];

        $timeIsNight = ($arrivalHour >= 20 || $arrivalHour < 6);

        $fx = function (string $cur) use ($fxFetcher): float {
            $cur = strtoupper(trim($cur));
            if ($fxFetcher) {
                $r = $fxFetcher($cur);
                if (is_numeric($r) && $r > 0) {
                    return (float)$r;
                }
            }
            // Fallback statiske kurser (EUR-baseret)
            $fallback = ['EUR'=>1.0,'DKK'=>7.45,'SEK'=>11.0,'BGN'=>1.96,'CZK'=>25.0,'HUF'=>385.0,'PLN'=>4.35,'RON'=>4.95,'CHF'=>0.95,'GBP'=>0.85];
            return $fallback[$cur] ?? 1.0;
        };

        $convertRange = function (array $rangeEur) use ($currency, $fx): array {
            $rate = $fx($currency);
            $rounding = ['DKK'=>5,'SEK'=>5,'NOK'=>5,'PLN'=>5,'CZK'=>10,'HUF'=>100,'CHF'=>1,'GBP'=>1];
            $step = $rounding[$currency] ?? 1;
            $roundFn = function (float $v) use ($rate, $step): float {
                $val = $v * $rate;
                return round($val / $step) * $step;
            };
            return [
                'min' => $roundFn((float)$rangeEur['min']),
                'max' => $roundFn((float)$rangeEur['max']),
                'currency' => $currency,
            ];
        };

        $pickRange = fn(string $key): ?array => $this->baseRanges[$key][$priceLevel] ?? null;

        $mealsBase = $pickRange('meals');
        $hotelBase = $pickRange('hotelPerNight');
        $taxiBase = $pickRange('taxiMediumKm');
        $altBase = match ($trainType) {
            'REGIONAL' => $pickRange('altTransportRegional'),
            'HIGHSPEED' => $pickRange('altTransportHighspeed'),
            default => $pickRange('altTransportIntercity'),
        };
        $upgradeBase = $pickRange('upgradeFirstClass');

        $scale = fn(?array $range, float $mult): ?array =>
            $range ? ['min' => $range['min'] * $mult, 'max' => $range['max'] * $mult] : null;

        $mealsAdj = $scale($mealsBase, (float)$stationProfile['mealMultiplier']);
        $hotelAdj = $timeIsNight ? $scale($hotelBase, (float)$stationProfile['hotelMultiplier']) : null;
        $taxiAdj = $scale($taxiBase, (float)$stationProfile['taxiMultiplier']);

        return [
            'meals' => $mealsAdj ? $convertRange($mealsAdj) : null,
            'hotelPerNight' => $hotelAdj ? $convertRange($hotelAdj) : null,
            'taxi' => $taxiAdj ? $convertRange($taxiAdj) : null,
            'altTransport' => $altBase ? $convertRange($altBase) : null,
            'upgradeFirstClass' => $upgradeBase ? $convertRange($upgradeBase) : null,
        ];
    }

    private function loadJson(string $path): array
    {
        if (!is_file($path)) {
            return [];
        }
        $raw = (string)@file_get_contents($path);
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }
}

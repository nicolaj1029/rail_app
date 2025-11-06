<?php
declare(strict_types=1);

namespace App\Service;

/**
 * Lightweight fare/pricing detector for Art. 9 Q3 section.
 * Mirrors the heuristics proposed for TS clients, implemented in PHP.
 */
class PricingDetectionService
{
    public const PTN_SUPER_SPAR = '/\b(super\s*sparpreis|supersparpreis)(\s*europa)?\b/i';
    public const PTN_SPAR = '/\b(sparpreis)(?!\s*finder)\b/i';
    public const PTN_FLEX = '/\bflexpreis\b/i';

    public const PTN_ANY_TRAIN = '/\b(any\s*train|vilk[åa]rlig(?:e)?\s*afgang|beliebige[ns]?\s*zug|freie\s*zugwahl)\b/i';
    public const PTN_SPECIFIC_TRAIN = '/\b(ICE|IC|EC|RJ|RJX|RE|EN|NJ|TGV)\s*\d{1,4}\b/i';
    public const PTN_RESERVATION = '/\breservier|\bres\.-?nr|\bseat\s*reserv|\bplads/i';

    public const PTN_ABO = '/\b(abo(nnement)?|periodekort|season\s*pass|bahncard\s*100)\b/i';

    /** Detect purchase type. Returns [value, source, confidence] or null. */
    public function detectPurchaseType(string $text): ?array
    {
        if (preg_match(self::PTN_ABO, $text)) {
            return ['value' => 'Abonnement/Periodekort', 'source' => 'auto', 'confidence' => 0.9];
        }
        if (preg_match(self::PTN_FLEX, $text)) {
            return ['value' => 'Flex', 'source' => 'auto', 'confidence' => 0.95];
        }
        if (preg_match(self::PTN_SUPER_SPAR, $text)) {
            return ['value' => 'Standard/Non-flex', 'source' => 'auto', 'confidence' => 0.95];
        }
        if (preg_match(self::PTN_SPAR, $text)) {
            return ['value' => 'Semi-flex', 'source' => 'auto', 'confidence' => 0.9];
        }
        if (preg_match(self::PTN_ANY_TRAIN, $text) && !preg_match(self::PTN_SPECIFIC_TRAIN, $text)) {
            return ['value' => 'Flex', 'source' => 'auto', 'confidence' => 0.7];
        }
        return null;
    }

    /** Detect train binding. Returns [value, source, confidence] or null. */
    public function detectTrainBinding(string $text): ?array
    {
        if (preg_match(self::PTN_FLEX, $text) || preg_match(self::PTN_ANY_TRAIN, $text)) {
            return ['value' => 'Vilkårlig afgang samme dag', 'source' => 'auto', 'confidence' => 0.9];
        }
        if (preg_match(self::PTN_SPECIFIC_TRAIN, $text) || preg_match(self::PTN_RESERVATION, $text)) {
            return ['value' => 'Kun specifikt tog', 'source' => 'auto', 'confidence' => 0.9];
        }
        return null;
    }

    /** Weak heuristic: multiple price levels likely shown. */
    public function suggestMultiPriceShown(string $text): ?array
    {
        if (preg_match(self::PTN_SUPER_SPAR, $text) || preg_match(self::PTN_SPAR, $text) || preg_match(self::PTN_FLEX, $text)) {
            return ['value' => 'Ja', 'source' => 'auto', 'confidence' => 0.6];
        }
        return null;
    }

    /** Weak heuristic: cheapest highlighted. */
    public function suggestCheapestHighlighted(string $text): ?array
    {
        if (preg_match(self::PTN_SUPER_SPAR, $text) || preg_match(self::PTN_SPAR, $text)) {
            return ['value' => 'Ja', 'source' => 'auto', 'confidence' => 0.55];
        }
        return null;
    }
}

?>
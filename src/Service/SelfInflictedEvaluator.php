<?php
declare(strict_types=1);

namespace App\Service;

class SelfInflictedEvaluator
{
    /**
     * Evaluate CIV self-inflicted conditions based on TRIN 5 inputs.
     * @param array<string,mixed> $form
     * @return array{selfInflicted:bool,reasons:array<int,string>}
     */
    public function evaluate(array $form): array
    {
        $hasValidTicket = (($form['hasValidTicket'] ?? 'yes') === 'yes');
        $safetyMisconduct = (($form['safetyMisconduct'] ?? 'no') === 'yes');
        $forbiddenItemsOrAnimals = (($form['forbiddenItemsOrAnimals'] ?? 'no') === 'yes');
        $customsOk = (($form['customsRulesBreached'] ?? 'yes') === 'yes');

        $reasons = [];
        if (!$hasValidTicket) { $reasons[] = 'no_valid_ticket'; }
        if ($safetyMisconduct) { $reasons[] = 'unsafe_behavior'; }
        if ($forbiddenItemsOrAnimals) { $reasons[] = 'prohibited_items'; }
        if (!$customsOk) { $reasons[] = 'admin_noncompliance'; }

        $selfInflicted = !$hasValidTicket || $safetyMisconduct || $forbiddenItemsOrAnimals || !$customsOk;
        return [ 'selfInflicted' => $selfInflicted, 'reasons' => $reasons ];
    }
}

?>

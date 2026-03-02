<?php
declare(strict_types=1);

namespace App\Service;

/**
 * Computes flow-stepper navigation state from session flags.
 *
 * This is intentionally "light": it does not try to infer completeness from every field,
 * only from explicit per-step done flags set by FlowController on successful POST.
 */
final class FlowStepsService
{
    /**
     * Canonical 10-step split-flow order.
     *
     * - `action` must match FlowController action name.
     * - `doneFlag` is written to `flow.flags` as string "1" when the step is completed.
     * - `prereqFlags` must all be "1" for the step to be editable (POST). For GET we allow
     *   a read-only preview via `?preview=1`.
     */
    public const STEPS = [
        ['id' => 'start',       'num' => 1,  'title' => 'Start & Rejsestatus',                            'action' => 'start',       'doneFlag' => 'step1_done',  'prereqFlags' => []],
        ['id' => 'entitlements','num' => 2,  'title' => 'Billet / Ticketless + Grunddata',               'action' => 'entitlements','doneFlag' => 'step2_done',  'prereqFlags' => ['step1_done']],
        ['id' => 'journey',     'num' => 3,  'title' => 'Rejseplan + Kontrakt (Art. 12) + PMR/Cykel',    'action' => 'journey',     'doneFlag' => 'step3_done',  'prereqFlags' => ['step2_done']],
        ['id' => 'station',     'num' => 4,  'title' => 'Strandet paa station? (Art. 20(3))',            'action' => 'station',     'doneFlag' => 'step4_done',  'prereqFlags' => ['step3_done']],
        ['id' => 'incident',    'num' => 5,  'title' => 'Haendelse + Gating (Art. 18/20 + national)',    'action' => 'incident',    'doneFlag' => 'step5_done',  'prereqFlags' => ['step4_done']],
        // Gate flags are written by TRIN 5 (incident) to prevent users from editing downstream steps
        // when Art. 18/20 is not active. Locked steps can still be previewed via ?preview=1.
        ['id' => 'choices',     'num' => 6,  'title' => 'Strandet paa sporet? + Hvor endte du (Art. 20(2)(c))', 'action' => 'choices', 'doneFlag' => 'step6_done',  'prereqFlags' => ['step5_done', 'gate_art20_2c']],
        ['id' => 'remedies',    'num' => 7,  'title' => 'Refusion / Omlaegning (Art. 18)',               'action' => 'remedies',    'doneFlag' => 'step7_done',  'prereqFlags' => ['step6_done', 'gate_art18']],
        ['id' => 'assistance',  'num' => 8,  'title' => 'Udgifter: Mad, Hotel (Art. 20)',                'action' => 'assistance',  'doneFlag' => 'step8_done',  'prereqFlags' => ['step7_done', 'gate_art20']],
        ['id' => 'downgrade',   'num' => 9,  'title' => 'Nedgradering: Klasse/Reservation (Annex II)',    'action' => 'downgrade',   'doneFlag' => 'step9_done',  'prereqFlags' => ['step8_done']],
        ['id' => 'compensation','num' => 10, 'title' => 'Beregning & Resultat (Art. 19)',                'action' => 'compensation','doneFlag' => 'step10_done', 'prereqFlags' => ['step9_done']],
        ['id' => 'applicant',   'num' => 11, 'title' => 'Ansoeger & Udbetaling',                          'action' => 'applicant',   'doneFlag' => 'step11_done', 'prereqFlags' => ['step10_done']],
        ['id' => 'consent',     'num' => 12, 'title' => 'Samtykke & Ekstra info',                         'action' => 'consent',     'doneFlag' => 'step12_done', 'prereqFlags' => ['step11_done']],
    ];

    /**
     * @param array<string,mixed> $flags
     * @return array<int, array<string,mixed>>
     */
    public function buildSteps(array $flags, string $currentAction): array
    {
        $out = [];
        foreach (self::STEPS as $s) {
            $done = ((string)($flags[$s['doneFlag']] ?? '')) === '1';
            $isCurrent = $s['action'] === $currentAction;
            $missing = [];
            foreach ($s['prereqFlags'] as $pf) {
                if (((string)($flags[$pf] ?? '')) !== '1') {
                    $missing[] = $pf;
                }
            }
            $unlocked = empty($missing);

            $state = 'needs';
            if (!$unlocked) {
                $state = 'locked';
            } elseif ($done) {
                $state = 'completed';
            }
            if ($isCurrent) {
                $state = $done ? 'current_done' : 'current';
            }

            $out[] = $s + [
                'done' => $done,
                'unlocked' => $unlocked,
                'missing' => $missing,
                'state' => $state,
            ];
        }
        return $out;
    }

    /**
     * @return array<string, array<string,mixed>>
     */
    public function flagsToStepMeta(): array
    {
        $map = [];
        foreach (self::STEPS as $s) {
            $map[$s['doneFlag']] = $s;
        }
        return $map;
    }
}

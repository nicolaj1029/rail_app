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
        ['id' => 'start',       'num' => 1,  'title' => 'Start',                               'action' => 'start',       'doneFlag' => 'step1_done',  'prereqFlags' => []],
        ['id' => 'entitlements','num' => 2,  'title' => 'Billet & Grundlag',                   'action' => 'entitlements','doneFlag' => 'step2_done',  'prereqFlags' => ['step1_done']],
        ['id' => 'journey',     'num' => 3,  'title' => 'Rejseplan (Art. 12/9 input)',         'action' => 'journey',     'doneFlag' => 'step3_done',  'prereqFlags' => ['step2_done']],
        ['id' => 'station',     'num' => 4,  'title' => 'Transport Fra Station (Art. 20(3))',  'action' => 'station',     'doneFlag' => 'step4_done',  'prereqFlags' => ['step3_done']],
        ['id' => 'incident',    'num' => 5,  'title' => 'Haendelse + Gating (Art. 18/20)',     'action' => 'incident',    'doneFlag' => 'step5_done',  'prereqFlags' => ['step4_done']],
        ['id' => 'choices',     'num' => 6,  'title' => 'Dine Valg (Art. 20(2)(c) / 18)',      'action' => 'choices',     'doneFlag' => 'step6_done',  'prereqFlags' => ['step5_done']],
        ['id' => 'remedies',    'num' => 7,  'title' => 'Omlaegning / Refusion (Art. 18)',     'action' => 'remedies',    'doneFlag' => 'step7_done',  'prereqFlags' => ['step6_done']],
        ['id' => 'assistance',  'num' => 8,  'title' => 'Mad, Hotel (Art. 20)',                'action' => 'assistance',  'doneFlag' => 'step8_done',  'prereqFlags' => ['step7_done']],
        ['id' => 'downgrade',   'num' => 9,  'title' => 'Nedgradering (Annex II)',             'action' => 'downgrade',   'doneFlag' => 'step9_done',  'prereqFlags' => ['step8_done']],
        ['id' => 'compensation','num' => 10, 'title' => 'Kompensation (Art. 19) / Resultat',   'action' => 'compensation','doneFlag' => 'step10_done', 'prereqFlags' => ['step9_done']],
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


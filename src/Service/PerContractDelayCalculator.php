<?php
declare(strict_types=1);

namespace App\Service;

final class PerContractDelayCalculator
{
    /**
     * Compute planned vs actual final arrival for a contract and delay in minutes.
     * @param array $contract One contract from PerContractSplitter
     * @return array{
     *   plannedArrival: string|null,
     *   actualArrival: string|null,
     *   delayMinutes: int|null,
     *   status: 'OK'|'MISSING_ACTUAL'|'MISSING_PLANNED'
     * }
     */
    public function endToEndDelay(array $contract): array
    {
        $segments = (array)($contract['segments'] ?? []);
        usort($segments, function($a,$b){
            return strcmp((string)($a['depPlanned'] ?? ''), (string)($b['depPlanned'] ?? ''));
        });

        $plannedArrival = null;
        $actualArrival  = null;

        foreach ($segments as $seg) {
            $pa = $seg['arrPlanned'] ?? null;
            if ($pa && ($plannedArrival === null || strcmp((string)$pa, (string)$plannedArrival) > 0)) {
                $plannedArrival = (string)$pa;
            }
            $aa = $seg['arrActual'] ?? null;
            if ($aa && ($actualArrival === null || strcmp((string)$aa, (string)$actualArrival) > 0)) {
                $actualArrival = (string)$aa;
            }
        }

        if (!$plannedArrival) {
            return ['plannedArrival'=>null,'actualArrival'=>$actualArrival,'delayMinutes'=>null,'status'=>'MISSING_PLANNED'];
        }
        if (!$actualArrival) {
            return ['plannedArrival'=>$plannedArrival,'actualArrival'=>null,'delayMinutes'=>null,'status'=>'MISSING_ACTUAL'];
        }

        $delayMinutes = $this->minutesBetween($plannedArrival, $actualArrival);
        return [
            'plannedArrival' => $plannedArrival,
            'actualArrival'  => $actualArrival,
            'delayMinutes'   => max(0, $delayMinutes),
            'status'         => 'OK',
        ];
    }

    private function minutesBetween(string $from, string $to): int
    {
        $a = new \DateTimeImmutable($from);
        $b = new \DateTimeImmutable($to);
        $diff = $b->getTimestamp() - $a->getTimestamp();
        return (int) floor($diff / 60);
    }
}

<?php
declare(strict_types=1);

namespace App\Service;

use Cake\Log\Log;

class TicketJoinService
{
    /**
     * Attempt to detect and group uploaded tickets into a shared claim if PNR, date, and passenger context overlap.
     * This is a stateless helper; consumers can persist groupings in session or storage.
     *
     * @param array<int,array<string,mixed>> $tickets Each ticket's OCR + parsed data
     * @return array<int,array<string,mixed>> Grouped tickets with claim root
     */
    public function groupTickets(array $tickets): array
    {
        $groups = [];
        foreach ($tickets as $ticket) {
            $pnr = (string)($ticket['bookingRef'] ?? '');
            $date = (string)($ticket['dep_date'] ?? ($ticket['segments'][0]['dep_date'] ?? ''));
            $key = ($pnr !== '' ? $pnr : 'no_pnr') . '|' . ($date !== '' ? $date : 'no_date');

            if ($pnr === '' || $date === '') {
                Log::warning('[TicketJoin] Could not group ticket – missing PNR or dep_date');
            }

            if (!isset($groups[$key])) {
                $groups[$key] = [
                    'shared' => true,
                    'pnr' => $pnr,
                    'dep_date' => $date,
                    'tickets' => [],
                ];
            }
            $groups[$key]['tickets'][] = $ticket;
        }
        foreach ($groups as &$group) {
            if (count($group['tickets']) === 1) {
                $group['shared'] = false;
            }
        }
        unset($group);
        return array_values($groups);
    }

    /**
     * Lightweight hinting: log a potential link target for subsequent uploads based on PNR + date + passenger snapshot.
     * Returns a descriptor with what we matched on.
     *
     * @param string|null $pnr
     * @param string|null $journeyDate ISO YYYY-MM-DD
     * @param array<int,array<string,mixed>> $passengers
     * @return array<string,mixed>
     */
    public function tryLinkToExistingJourney(?string $pnr, ?string $journeyDate, array $passengers = []): array
    {
        $desc = [
            'matched' => false,
            'pnr' => $pnr,
            'dep_date' => $journeyDate,
            'passenger_count' => count($passengers),
        ];
        if ($pnr && $journeyDate) {
            // In a real implementation, consult storage to find an existing claim by (pnr, date)
            // and mark this upload as attached; here we just return a hint.
            $desc['matched'] = true;
            $desc['hint'] = 'Candidate link by PNR+date';
        }
        Log::info('[TicketJoin] tryLinkToExistingJourney: ' . json_encode($desc));
        return $desc;
    }
}

?>

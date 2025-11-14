<?php
declare(strict_types=1);

namespace App\Service;

/**
 * Split journey into separate transport contracts per ticket/PNR.
 * Adapts to current app data by accepting a normalized flow array produced in controller.
 *
 * Expected normalized shape (built from groupedTickets/_segments_auto):
 * - flow['journey']['segments'][] items with keys:
 *   ticketId (string|null), pnr (string|null), operator (string|null),
 *   depPlanned (YYYY-MM-DDTHH:MM), arrPlanned (YYYY-MM-DDTHH:MM),
 *   arrActual (YYYY-MM-DDTHH:MM|null), currency (string|null), ticketTotal (float|null),
 *   priceShare (float|null)
 */
final class PerContractSplitter
{
    /**
     * @param array $flow Normalized flow structure
     * @return array<int, array{
     *   contractKey: string,
     *   pnr?: string|null,
     *   ticketId?: string|null,
     *   operatorSet: string[],
     *   segments: array<int, array>,
     *   currency: string|null,
     *   ticketTotal?: float|null
     * }>
     */
    public function split(array $flow): array
    {
        $journey = (array)($flow['journey'] ?? []);
        $segments = (array)($journey['segments'] ?? []);
        $contracts = [];

        foreach ($segments as $seg) {
            $ticketId = $seg['ticketId'] ?? null;
            $pnr      = $seg['pnr'] ?? null;

            // Key priority: ticketId > pnr > operator+date
            if (!empty($ticketId)) {
                $key = 'TICKET:' . (string)$ticketId;
            } elseif (!empty($pnr)) {
                $key = 'PNR:' . (string)$pnr;
            } else {
                $op = (string)($seg['operator'] ?? 'UNKNOWN_OP');
                $d  = substr((string)($seg['depPlanned'] ?? ''), 0, 10); // YYYY-MM-DD
                $key = 'FALLBACK:' . $op . ':' . $d;
            }

            if (!isset($contracts[$key])) {
                $contracts[$key] = [
                    'contractKey' => $key,
                    'pnr' => $pnr,
                    'ticketId' => $ticketId,
                    'operatorSet' => [],
                    'segments' => [],
                    'currency' => $seg['currency'] ?? ($journey['currency'] ?? null),
                    'ticketTotal' => $seg['ticketTotal'] ?? null,
                ];
            }

            $contracts[$key]['segments'][] = $seg;
            $op = (string)($seg['operator'] ?? 'UNKNOWN_OP');
            if ($op !== '' && !in_array($op, $contracts[$key]['operatorSet'], true)) {
                $contracts[$key]['operatorSet'][] = $op;
            }
            if (empty($contracts[$key]['ticketTotal']) && !empty($seg['ticketTotal'])) {
                $contracts[$key]['ticketTotal'] = $seg['ticketTotal'];
            }
        }

        return array_values($contracts);
    }
}

<?php
declare(strict_types=1);

namespace App\Service;

final class Art12FlowEvaluator
{
    public const RESP_ART12_3_OPERATOR = 'art12_3_operator';
    public const RESP_ART12_4_RETAILER = 'art12_4_retailer';
    public const RESP_NONE             = 'none';

    /** @return array{stage:string,ticket_scope:'through'|'separate'|null,responsibility:string|null,notes:array<int,string>,hooks_to_collect:array<int,string>} */
    public function decide(array $flow): array
    {
        $meta = (array)($flow['meta'] ?? []);
        $journey = (array)($flow['journey'] ?? []);
        $segments = (array)($journey['segments'] ?? []);
        $notes = [];
        $hooks = [];

        $seller = (string)($meta['journey.seller_type'] ?? ($meta['seller_type'] ?? 'operator'));

        $ticketCount = (int)($meta['ticket_upload_count'] ?? 0);
        if ($ticketCount < 1) {
            return [
                'stage' => 'TRIN_2',
                'ticket_scope' => null,
                'responsibility' => null,
                'notes' => ['Upload billet for at fortsætte.'],
                'hooks_to_collect' => []
            ];
        }

        // TRIN 2
        $shared = (string)($meta['shared_pnr_scope'] ?? 'unknown'); // yes|no|unknown
        if ($shared === 'unknown') {
            $hooks[] = 'same_transaction_all';
            return [
                'stage' => 'TRIN_2',
                'ticket_scope' => null,
                'responsibility' => null,
                'notes' => ['Bekræft om alle billetter er købt i én transaktion.'],
                'hooks_to_collect' => $hooks,
            ];
        }
        if ($shared === 'no') {
            $sameTxn = (string)($meta['same_transaction_all'] ?? 'unknown');
            if ($sameTxn === 'unknown') {
                $hooks[] = 'same_transaction_all';
                return [
                    'stage' => 'TRIN_2',
                    'ticket_scope' => null,
                    'responsibility' => null,
                    'notes' => ['Flere PNR: Er de stadig købt i samme transaktion?'],
                    'hooks_to_collect' => $hooks,
                ];
            }
            if ($sameTxn === 'no') {
                $notes[] = 'TRIN 2: Flere PNR og ikke samme transaktion → særskilte kontrakter.';
                return [
                    'stage' => 'STOP',
                    'ticket_scope' => 'separate',
                    'responsibility' => self::RESP_NONE,
                    'notes' => $notes,
                    'hooks_to_collect' => [],
                ];
            }
            // yes ⇒ continue
        }
        // shared === 'yes' ⇒ continue

        // TRIN 3
        if ($seller === 'retailer') {
            $notes[] = 'TRIN 3: Stk. 4 gælder (køb hos billetudsteder/rejsebureau) → TRIN 4.';
        } else {
            $notes[] = 'TRIN 3: Stk. 3 gælder (køb hos operatør) → TRIN 3.1.';
            // TRIN 3.1
            $ops = [];
            foreach ($segments as $s) {
                $op = (string)($s['operator'] ?? '');
                if ($op !== '') { $ops[$op] = true; }
            }
            $multiOps = count($ops) > 1;
            if (!$multiOps) {
                $notes[] = 'TRIN 3.1: Kun én operatør → gennemgående billet efter stk. 3.';
                return [
                    'stage' => 'STOP',
                    'ticket_scope' => 'through',
                    'responsibility' => self::RESP_ART12_3_OPERATOR,
                    'notes' => $notes,
                    'hooks_to_collect' => [],
                ];
            }
            $notes[] = 'TRIN 3.1: Flere operatører → videre til TRIN 4.';
        }

        // TRIN 4
        $disc = (string)($meta['through_ticket_disclosure_given'] ?? 'unknown');
        if ($disc === 'unknown') {
            return [
                'stage' => 'TRIN_4',
                'ticket_scope' => null,
                'responsibility' => null,
                'notes' => array_merge($notes, ['Angiv om du var tydeligt informeret om gennemgående eller ej før køb.']),
                'hooks_to_collect' => ['through_ticket_disclosure_given'],
            ];
        }

        // TRIN 5
        $sep = (string)($meta['separate_contract_notice'] ?? 'unknown');
        if ($sep === 'unknown') {
            return [
                'stage' => 'TRIN_5',
                'ticket_scope' => null,
                'responsibility' => null,
                'notes' => array_merge($notes, ['Er det angivet på billetterne, at de er særskilte befordringskontrakter?']),
                'hooks_to_collect' => ['separate_contract_notice'],
            ];
        }

        if ($sep === 'no' || ($sep === 'yes' && $disc === 'no')) {
            $resp = ($seller === 'operator') ? self::RESP_ART12_3_OPERATOR : self::RESP_ART12_4_RETAILER;
            $notes[] = 'TRIN 5: Betingelserne for undtagelsen er ikke opfyldt → gennemgående billet.';
            return [
                'stage' => 'STOP',
                'ticket_scope' => 'through',
                'responsibility' => $resp,
                'notes' => $notes,
                'hooks_to_collect' => [],
            ];
        }

        $notes[] = 'TRIN 5: Oplyst før køb + angivet som særskilte → ikke gennemgående.';
        return [
            'stage' => 'STOP',
            'ticket_scope' => 'separate',
            'responsibility' => self::RESP_NONE,
            'notes' => $notes,
            'hooks_to_collect' => [],
        ];
    }
}

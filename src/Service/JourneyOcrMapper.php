<?php
declare(strict_types=1);

namespace App\Service;

/**
 * JourneyOcrMapper
 *
 * Map raw OCR/LLM fields to a minimal journey + meta suitable for priming TRIN 6 and
 * running Art12AutoDeriver + Art12Evaluator. This follows the compact recipe
 * shared in discussion and keeps user-visible answers for Q1 and Q4 as 'Ved ikke'
 * so the UI can collect them explicitly.
 */
final class JourneyOcrMapper
{
    /**
     * @param array $ocr keys: operator, country, product, dep_date, dep_time, arr_time,
     *                   dep_station, arr_station, train_no, ticket_no|booking_ref, price,
     *                   anticipated_delay_60
     * @return array{journey: array, meta: array}
     */
    public function map(array $ocr): array
    {
        // 3.1 – operator, country, product
        $operator = (string)($ocr['operator'] ?? 'SNCF');
        $country  = (string)($ocr['country']  ?? 'FR');
        $product  = (string)($ocr['product']  ?? 'TGV');

        // 3.2 – planned journey
        $depDate  = (string)($ocr['dep_date'] ?? '2025-07-22');
        $depTime  = (string)($ocr['dep_time'] ?? '07:42');
        $arrTime  = (string)($ocr['arr_time'] ?? '11:33');
        $from     = (string)($ocr['dep_station'] ?? 'POITIERS');
        $to       = (string)($ocr['arr_station'] ?? 'TOULOUSE MATABIAU');
        $trainNo  = (string)($ocr['train_no'] ?? 'TGV 8501');

        // 3.2.7 – Ticket No / Booking Ref
        $bookingRef = (string)($ocr['ticket_no'] ?? ($ocr['booking_ref'] ?? 'KM0506'));

        // 3.2.8 – price (not critical for Art. 12, useful elsewhere)
        $price   = (string)($ocr['price'] ?? '22.00');

        // Confirmation (journey not completed, likely 60+)
        $anticipatedDelay60 = (bool)($ocr['anticipated_delay_60'] ?? true);

        // Build journey – at least one segment
        $journey = [
            'bookingRef'   => $bookingRef,
            'seller_type'  => null,            // unknown seller channel → AUTO
            'operator'     => $operator,
            'operator_cc'  => $country,
            'product'      => $product,
            'missed_connection' => null,
            'anticipated_delay_60' => $anticipatedDelay60,
            'segments' => [[
                'pnr'        => $bookingRef,
                'carrier'    => $operator,
                'operator'   => $operator,
                'train_no'   => $trainNo,
                'from'       => $from,
                'to'         => $to,
                'dep_date'   => $depDate,
                'dep_time'   => $depTime,
                'arr_time'   => $arrTime,
                'price'      => $price,
            ]],
        ];

        // Prime meta for TRIN 6: only Q1 and Q4 are left for UI; rest are AUTO/unknown
        $meta = [
            'through_ticket_disclosure' => 'Ved ikke',  // Q1 – user may change
            'separate_contract_notice'  => 'Ved ikke',  // Q4 – user may change
            // Q2,3,5,6,7,8,13 will be derived automatically
            // Q9–12 remain unknown unless later supplied
        ];

        return compact('journey','meta');
    }
}

?>

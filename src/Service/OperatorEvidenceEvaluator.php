<?php
declare(strict_types=1);

namespace App\Service;

class OperatorEvidenceEvaluator
{
    /**
     * Evaluate presence of operator-side evidence for disruption (ticket stamp, QR payload, etc.).
     * @param array<string,mixed> $form
     * @return array{operatorEvidence:string,sources:array<int,string>}
     */
    public function evaluate(array $form): array
    {
        $stamp = (($form['operatorStampedDisruptionProof'] ?? 'no') === 'yes');
        return [
            'operatorEvidence' => $stamp ? 'present' : 'missing',
            'sources' => $stamp ? ['ticket_stamp'] : []
        ];
    }
}

?>

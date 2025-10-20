<?php
declare(strict_types=1);

namespace App\Controller;

use App\Service\EligibilityService;
use App\Service\ExemptionsRepository;
use App\Service\NationalOverridesRepository;

class ClientClaimsController extends AppController
{
    public function start(): void
    {
        // Client-facing simple form to submit a claim
    }

    public function submit(): void
    {
        $this->request->allowMethod(['post']);
        $data = (array)$this->request->getData();

        $claims = $this->fetchTable('Claims');
        $claim = $claims->newEntity([
            'client_name' => (string)($data['name'] ?? ''),
            'client_email' => (string)($data['email'] ?? ''),
            'country' => (string)($data['country'] ?? ''),
            'operator' => (string)($data['operator'] ?? ''),
            'product' => (string)($data['product'] ?? ''),
            'delay_min' => (int)($data['delay_min'] ?? 0),
            'refund_already' => !empty($data['refund_already']),
            'known_delay_before_purchase' => !empty($data['known_delay_before_purchase']),
            'extraordinary' => !empty($data['extraordinary']),
            'self_inflicted' => !empty($data['self_inflicted']),
            'ticket_price' => (float)($data['ticket_price'] ?? 0),
            'currency' => (string)($data['currency'] ?? 'EUR'),
            'assignment_accepted' => !empty($data['assignment_accepted']),
        ]);

        if (empty($claim->assignment_accepted)) {
            $claim->setError('assignment_accepted', ['required' => 'Du skal acceptere overdragelsen for at fortsætte.']);
        }

        if ($claim->getErrors()) {
            $this->set('errors', $claim->getErrors());
            $this->viewBuilder()->setTemplate('start');
            return;
        }

        $service = new EligibilityService(new ExemptionsRepository(), new NationalOverridesRepository());
        $res = $service->computeCompensation([
            'delayMin' => $claim->delay_min,
            'euOnly' => true,
            'refundAlready' => (bool)$claim->refund_already,
            'knownDelayBeforePurchase' => (bool)$claim->known_delay_before_purchase,
            'extraordinary' => (bool)$claim->extraordinary,
            'selfInflicted' => (bool)$claim->self_inflicted,
            'country' => $claim->country,
            'operator' => $claim->operator,
            'product' => $claim->product,
        ]);

        $claim->computed_percent = (int)($res['percent'] ?? 0);
        $claim->computed_source = (string)($res['source'] ?? 'eu');
        $claim->computed_notes = $res['notes'] ?? null;
        $claim->compensation_amount = round(((float)$claim->ticket_price) * $claim->computed_percent / 100, 2);
        $claim->fee_amount = round(((float)$claim->compensation_amount) * $claim->fee_percent / 100, 2);
        $claim->payout_amount = max(0, (float)$claim->compensation_amount - (float)$claim->fee_amount);

    if ($claims->save($claim)) {
            // Handle uploads (ticket, receipts, delay confirmation)
            $files = $this->request->getUploadedFiles();
            $dir = WWW_ROOT . 'files' . DIRECTORY_SEPARATOR . 'cases' . DIRECTORY_SEPARATOR . $claim->case_number;
            if (!is_dir($dir)) {
                mkdir($dir, 0777, true);
            }
            $attachments = $this->fetchTable('ClaimAttachments');
            foreach ([
                'ticket_file' => 'ticket',
                'receipts_file' => 'receipts',
                'delay_confirmation_file' => 'delay_confirmation',
            ] as $field => $type) {
                if (!isset($files[$field])) { continue; }
                $file = $files[$field];
                if ($file->getError() === UPLOAD_ERR_OK && $file->getSize() > 0) {
                    $safe = preg_replace('/[^a-zA-Z0-9._-]/', '_', $file->getClientFilename());
                    $target = $dir . DIRECTORY_SEPARATOR . $type . '_' . $safe;
                    $file->moveTo($target);
                    $attachments->save($attachments->newEntity([
                        'claim_id' => $claim->id,
                        'type' => $type,
                        'path' => 'files/cases/' . $claim->case_number . '/' . basename($target),
                        'original_name' => $file->getClientFilename(),
                        'size' => (int)$file->getSize(),
                    ]));
                }
            }

            // Generate assignment PDF and update record
            try {
                $dir = WWW_ROOT . 'files' . DIRECTORY_SEPARATOR . 'cases' . DIRECTORY_SEPARATOR . $claim->case_number;
                if (!is_dir($dir)) {
                    mkdir($dir, 0777, true);
                }
                $fsPath = $dir . DIRECTORY_SEPARATOR . 'assignment.pdf';
                $webPath = '/files/cases/' . rawurlencode($claim->case_number) . '/assignment.pdf';

                $pdf = new \FPDF('P', 'mm', 'A4');
                $pdf->AddPage();
                $pdf->SetFont('Arial', 'B', 16);
                $pdf->Cell(0, 10, 'Assignment of Claim', 0, 1);
                $pdf->SetFont('Arial', '', 12);
                $pdf->MultiCell(0, 7, 'Case: ' . $claim->case_number);
                $pdf->MultiCell(0, 7, 'Client: ' . $claim->client_name . ' <' . $claim->client_email . '>');
                $pdf->MultiCell(0, 7, 'Operator/Product: ' . ($claim->operator ?? '') . ' / ' . ($claim->product ?? ''));
                $pdf->Ln(4);
                $pdf->MultiCell(0, 7, 'The client hereby assigns all rights to pursue reimbursement/compensation for the journey to the legal representative. Immediate payout provided to client, net of agreed fee.');
                $pdf->Ln(4);
                $pdf->MultiCell(0, 7, 'Computed compensation basis: ' . (int)$claim->computed_percent . '% (' . ($claim->computed_source ?? 'eu') . '), amount ' . number_format((float)$claim->compensation_amount, 2) . ' ' . ($claim->currency ?? 'EUR'));
                $pdf->MultiCell(0, 7, 'Fee: ' . (int)$claim->fee_percent . '% = ' . number_format((float)$claim->fee_amount, 2) . ' ' . ($claim->currency ?? 'EUR'));
                $pdf->MultiCell(0, 7, 'Payout to client: ' . number_format((float)$claim->payout_amount, 2) . ' ' . ($claim->currency ?? 'EUR'));
                $pdf->Ln(10);
                $pdf->MultiCell(0, 7, 'Date: ' . date('Y-m-d H:i'));
                $pdf->Output('F', $fsPath);

                $claim->assignment_pdf = 'files/cases/' . $claim->case_number . '/assignment.pdf';
                $claims->save($claim);
            } catch (\Throwable $e) {
                // Non-fatal: continue without assignment file
            }

            // Instant payout simulation
            try {
                $pay = new \App\Service\PaymentsService();
                $resPay = $pay->payout((float)$claim->payout_amount, (string)$claim->currency, [
                    'name' => (string)$claim->client_name,
                    'email' => (string)$claim->client_email,
                ]);
                if ($resPay['status'] === 'paid') {
                    $claim->payout_status = 'paid';
                    $claim->payout_reference = $resPay['reference'];
                    $claim->paid_at = date('Y-m-d H:i:s');
                    $claims->save($claim);
                }
            } catch (\Throwable $e) {
                // keep as pending if any error
            }

            $this->set('claim', $claim);
            $this->viewBuilder()->setTemplate('submitted');
            return;
        }

        $this->set('saveError', true);
        $this->viewBuilder()->setTemplate('start');
    }
}

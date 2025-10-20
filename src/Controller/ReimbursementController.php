<?php
declare(strict_types=1);

namespace App\Controller;

use setasign\Fpdi\Fpdi;
use setasign\Fpdi\PdfParser\CrossReference\CrossReferenceException;
use FPDF; // autoloaded by setasign/fpdf

class ReimbursementController extends AppController
{
    public function start(): void
    {
        // Render the form
    }

    public function generate(): void
    {
        $this->request->allowMethod(['post', 'get']);
        $data = $this->request->is('post') ? (array)$this->request->getData() : (array)$this->request->getQueryParams();
        if ($this->request->is('get')) {
            // If user navigated directly without any params, send them to the form
            $hasAny = array_filter($data, fn($v) => $v !== null && $v !== '');
            if (empty($hasAny)) {
                $this->redirect(['action' => 'start']);
                return;
            }
        }

        // Minimal PDF summary (not filling the original PDF yet)
    $this->disableAutoRender();
        $pdf = new FPDF('P', 'mm', 'A4');
        $pdf->AddPage();
        $pdf->SetFont('Arial', 'B', 16);
        $pdf->Cell(0, 10, 'Reimbursement Claim Summary', 0, 1);

        $pdf->SetFont('Arial', '', 12);
        foreach ([
            'name' => 'Applicant Name',
            'email' => 'Email',
            'operator' => 'Railway Undertaking',
            'dep_date' => 'Scheduled departure date',
            'dep_station' => 'Departure station',
            'arr_station' => 'Destination station',
            'dep_time' => 'Scheduled departure time',
            'arr_time' => 'Scheduled arrival time',
            'train_no' => 'Train no./category',
            'ticket_no' => 'Ticket number/PNR',
            'price' => 'Ticket price',
            'actual_arrival_date' => 'Actual arrival date',
            'actual_dep_time' => 'Actual departure time',
            'actual_arr_time' => 'Actual arrival time',
            'missed_connection_station' => 'Missed connection station',
        ] as $key => $label) {
            $val = (string)($data[$key] ?? '');
            $pdf->MultiCell(0, 7, sprintf('%s: %s', $label, $val));
        }

        $pdf->Ln(5);
        $pdf->MultiCell(0, 7, 'Reason: ' . implode(', ', array_keys(array_filter([
            'delay' => !empty($data['reason_delay']),
            'cancellation' => !empty($data['reason_cancellation']),
            'missed connection' => !empty($data['reason_missed_conn']),
        ]))));

        // Output inline for now
        $this->response = $this->response->withType('pdf');
        $this->response = $this->response->withStringBody($pdf->Output('S'));
        return;
    }

    public function official(): void
    {
        $this->request->allowMethod(['post', 'get']);
        $data = $this->request->is('post') ? (array)$this->request->getData() : (array)$this->request->getQueryParams();
        if ($this->request->is('get')) {
            $hasAny = array_filter($data, fn($v) => $v !== null && $v !== '');
            if (empty($hasAny)) {
                $this->redirect(['action' => 'start']);
                return;
            }
        }

        // Allow overriding template via query parameter for diagnostics, but only within webroot or webroot/files
        $source = null;
        $forceName = (string)($this->request->getQuery('template') ?? '');
        if ($forceName !== '') {
            $try = [WWW_ROOT . 'files' . DIRECTORY_SEPARATOR . $forceName, WWW_ROOT . $forceName];
            foreach ($try as $p) {
                if (is_file($p)) { $source = $p; break; }
            }
        }
        if ($source === null) {
            $source = $this->findOfficialTemplatePath();
        }
        if ($source === null || !is_file($source)) {
            // Fallback to summary if template missing
            $this->disableAutoRender();
            $pdf = new FPDF('P', 'mm', 'A4');
            $pdf->AddPage();
            $pdf->SetFont('Arial', 'B', 14);
            $pdf->Cell(0, 10, 'Official Form template missing', 0, 1);
            $pdf->SetFont('Arial', '', 12);
            $pdf->MultiCell(0, 7, "Looked in: webroot/files and webroot. Filenames tried include 'reimbursement_form_uncompressed.pdf' and '(reimboursement|reimbursement) form - EN - accessible.pdf' (spaces or %20).\nYou can also force a file with ?template=FILENAME.pdf");
            $this->response = $this->response->withType('pdf')->withStringBody($pdf->Output('S'));
            return;
        }

    $map = $this->loadFieldMap() ?: $this->officialFieldMap();
    $debug = (bool)$this->request->getQuery('debug');
    $dx = (float)($this->request->getQuery('dx') ?? 0);
    $dy = (float)($this->request->getQuery('dy') ?? 0);

    $this->disableAutoRender();
    $fpdi = new Fpdi('P', 'mm', 'A4');
        try {
            $pageCount = $fpdi->setSourceFile($source);
        } catch (CrossReferenceException $e) {
            // Handle compressed xref (unsupported by free parser)
            $this->disableAutoRender();
            $pdf = new FPDF('P', 'mm', 'A4');
            $pdf->AddPage();
            $pdf->SetFont('Arial', 'B', 14);
            $pdf->MultiCell(0, 8, 'Cannot import template: compressed cross-reference (XRef) stream');
            $pdf->SetFont('Arial', '', 12);
            $pdf->Ln(2);
            $pdf->MultiCell(0, 6, "This PDF uses a compression technique that the free FPDI parser can't handle. Options:\n\n1) Provide an uncompressed PDF (save as PDF 1.4 / 'reduced size').\n2) Convert locally using qpdf to disable object streams.\n3) Use the commercial fpdi-pdf-parser add-on.\n\nTried file:\n" . $source);
            $pdf->Ln(2);
            $pdf->SetFont('Courier', '', 10);
            $pdf->MultiCell(0, 5, "qpdf --qdf --object-streams=disable \"in.pdf\" \"out.pdf\"");
            $this->response = $this->response->withType('pdf')->withStringBody($pdf->Output('S'));
            return;
        } catch (\Throwable $e) {
            $this->disableAutoRender();
            $pdf = new FPDF('P', 'mm', 'A4');
            $pdf->AddPage();
            $pdf->SetFont('Arial', 'B', 14);
            $pdf->MultiCell(0, 8, 'Cannot import template');
            $pdf->SetFont('Arial', '', 12);
            $pdf->MultiCell(0, 6, 'Error: ' . $e->getMessage() . "\nFile: " . $source);
            $this->response = $this->response->withType('pdf')->withStringBody($pdf->Output('S'));
            return;
        }
        for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
            $tpl = $fpdi->importPage($pageNo);
            $size = $fpdi->getTemplateSize($tpl);
            $fpdi->AddPage($size['orientation'], [$size['width'], $size['height']]);
            $fpdi->useTemplate($tpl, 0, 0, $size['width'], $size['height']);

            // Optional debug grid overlay to calibrate coordinates
            if ($debug) {
                // Draw grid; offset labels to reflect dx/dy
                $this->drawDebugGrid($fpdi, (float)$size['width'], (float)$size['height']);
                if ($dx != 0 || $dy != 0) {
                    $fpdi->SetTextColor(255, 0, 0);
                    $fpdi->SetFont('Helvetica', '', 7);
                    $fpdi->SetXY(4, 4);
                    $fpdi->Cell(0, 4, sprintf('nudge dx=%.1fmm dy=%.1fmm', $dx, $dy));
                    $fpdi->SetTextColor(0, 0, 0);
                }
            }

            // Write fields for this page
            if (!empty($map[$pageNo])) {
                $fpdi->SetFont('Helvetica', '', 9);
                foreach ($map[$pageNo] as $field => $cfg) {
                    $type = $cfg['type'] ?? 'text';
                    $x = (float)$cfg['x'] + $dx;
                    $y = (float)$cfg['y'] + $dy;
                    $w = isset($cfg['w']) ? (float)$cfg['w'] : 0;
                    if ($type === 'checkbox') {
                        $checked = !empty($data[$field]);
                        if ($checked) {
                            $fpdi->SetDrawColor(0,0,0);
                            $fpdi->SetLineWidth(0.3);
                            // Draw X in ~4mm box
                            $fpdi->Line($x, $y, $x+4, $y+4);
                            $fpdi->Line($x, $y+4, $x+4, $y);
                        }
                        continue;
                    }
                    $srcField = $cfg['source'] ?? $field;
                    $val = (string)($data[$srcField] ?? '');
                    if ($val === '') { continue; }
                    $fpdi->SetXY($x, $y);
                    if (!empty($cfg['multiline'])) {
                        $fpdi->MultiCell($w > 0 ? $w : 100, 4, $val);
                    } else {
                        $fpdi->Cell($w > 0 ? $w : 0, 4, $val, 0, 0);
                    }
                }
            }
        }

        $this->response = $this->response->withType('pdf')->withStringBody($fpdi->Output('S'));
        return;
    }

    /**
     * Minimal coordinate map for key fields on the official form.
     * Coordinates are in mm relative to the top-left of each page.
     * Adjust iteratively by viewing the output.
     * @return array<int,array<string,array{x:float,y:float,w?:float,multiline?:bool}>>
     */
    private function officialFieldMap(): array
    {
        return [
            1 => [
                'name' => ['x' => 30, 'y' => 40, 'w' => 80, 'type' => 'text'],
                'email' => ['x' => 130, 'y' => 40, 'w' => 60, 'type' => 'text'],
                'operator' => ['x' => 30, 'y' => 55, 'w' => 80, 'type' => 'text'],
                'train_no' => ['x' => 130, 'y' => 55, 'w' => 60, 'type' => 'text'],
                'dep_station' => ['x' => 30, 'y' => 70, 'w' => 80, 'type' => 'text'],
                'arr_station' => ['x' => 130, 'y' => 70, 'w' => 60, 'type' => 'text'],
                'dep_date' => ['x' => 30, 'y' => 85, 'w' => 40, 'type' => 'text'],
                'arr_time' => ['x' => 130, 'y' => 85, 'w' => 40, 'type' => 'text'],
                'ticket_no' => ['x' => 30, 'y' => 100, 'w' => 160, 'type' => 'text'],
                'price' => ['x' => 30, 'y' => 115, 'w' => 40, 'type' => 'text'],
                'actual_arrival_date' => ['x' => 30, 'y' => 130, 'w' => 40, 'type' => 'text'],
                'missed_connection_station' => ['x' => 30, 'y' => 145, 'w' => 160, 'multiline' => true, 'type' => 'text'],
                // Section 6: Additional information (arguments/evidence) – wide multiline area
                'additional_info' => ['x' => 30, 'y' => 200, 'w' => 160, 'multiline' => true, 'type' => 'text'],
            ],
        ];
    }

    /**
     * Attempt to locate the official template PDF, handling filenames with spaces or literal %20,
     * and both reimbursement/reimboursement spellings.
     */
    private function findOfficialTemplatePath(): ?string
    {
        $dirs = [
            WWW_ROOT . 'files' . DIRECTORY_SEPARATOR,
            WWW_ROOT,
        ];
        // Prefer locally converted/uncompressed files first
        $candidates = [
            'reimbursement_form_uncompressed.pdf',
            'reimbursement_form_converted.pdf',
            // then the known official names (may be compressed)
            'reimboursement form - EN - accessible.pdf',
            'reimboursement%20form%20-%20EN%20-%20accessible.pdf',
            'reimbursement form - EN - accessible.pdf',
            'reimbursement%20form%20-%20EN%20-%20accessible.pdf',
        ];
        foreach ($dirs as $dir) {
            foreach ($candidates as $file) {
                $p = $dir . $file;
                if (is_file($p)) { return $p; }
            }
        }
        // Fallback glob scan in both locations
        foreach ($dirs as $dir) {
            $patterns = [
                $dir . '*reimbours*form*EN*accessible*.pdf',
                $dir . '*reimburs*form*EN*accessible*.pdf',
            ];
            foreach ($patterns as $glob) {
                $hits = glob($glob) ?: [];
                if (!empty($hits)) { return $hits[0]; }
            }
        }
        return null;
    }

    /**
     * Load a field map from config/pdf/reimbursement_map.json if present.
     * @return array<int,array<string,array<string,mixed>>>|null
     */
    private function loadFieldMap(): ?array
    {
        $path = CONFIG . 'pdf' . DIRECTORY_SEPARATOR . 'reimbursement_map.json';
        if (!is_file($path)) {
            return null;
        }
        $json = (string)file_get_contents($path);
        $data = json_decode($json, true);
        return is_array($data) ? $data : null;
    }

    /**
     * Draw a light grid to calibrate coordinates (in mm).
     */
    private function drawDebugGrid(Fpdi $pdf, float $w, float $h): void
    {
        $pdf->SetDrawColor(200, 200, 200);
        $pdf->SetTextColor(120, 120, 120);
        $pdf->SetFont('Helvetica', '', 6);
        for ($x = 0; $x <= $w; $x += 10) {
            $pdf->Line($x, 0, $x, $h);
            if ($x % 20 === 0) { $pdf->SetXY($x + 1, 2); $pdf->Cell(8, 3, (string)$x, 0, 0); }
        }
        for ($y = 0; $y <= $h; $y += 10) {
            $pdf->Line(0, $y, $w, $y);
            if ($y % 20 === 0) { $pdf->SetXY(2, $y + 1); $pdf->Cell(8, 3, (string)$y, 0, 0); }
        }
    }
}

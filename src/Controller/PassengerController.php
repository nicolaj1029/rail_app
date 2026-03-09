<?php
declare(strict_types=1);

namespace App\Controller;

use App\Service\FlowStepsService;
use App\Service\OperatorCatalog;
use App\Service\SeasonPolicyCatalog;
use Cake\Routing\Router;

class PassengerController extends AppController
{
    public function start(): void
    {
        $snapshot = $this->buildSnapshot();
        $quickLinks = [
            'flowStart' => Router::url('/flow/start', true),
            'flowJourney' => Router::url('/flow/journey', true),
            'flowCompensation' => Router::url('/flow/compensation', true),
            'review' => Router::url('/passenger/review', true),
            'commuter' => Router::url('/passenger/commuter', true),
            'claims' => Router::url('/passenger/claims', true),
        ];

        $this->set(compact('snapshot', 'quickLinks'));
    }

    public function review(): void
    {
        $snapshot = $this->buildSnapshot();
        $this->set(compact('snapshot'));
    }

    public function commuter(): void
    {
        $snapshot = $this->buildSnapshot();
        $coverage = null;

        try {
            $opCat = new OperatorCatalog();
            $seasonCat = new SeasonPolicyCatalog();
            $coverage = $seasonCat->coverageReport($opCat, true);
        } catch (\Throwable) {
            $coverage = null;
        }

        $this->set(compact('snapshot', 'coverage'));
    }

    public function claims(): void
    {
        $snapshot = $this->buildSnapshot();
        $claimLinks = [
            'compensation' => Router::url('/flow/compensation', true),
            'applicant' => Router::url('/flow/applicant', true),
            'consent' => Router::url('/flow/consent', true),
            'reimbursement' => Router::url('/reimbursement', true),
            'shadowCases' => Router::url('/api/shadow/cases', true),
        ];

        $this->set(compact('snapshot', 'claimLinks'));
    }

    /**
     * @return array<string,mixed>
     */
    private function buildSnapshot(): array
    {
        $session = $this->request->getSession();
        $form = (array)$session->read('flow.form') ?: [];
        $flags = (array)$session->read('flow.flags') ?: [];

        $stepsService = new FlowStepsService();
        $currentAction = $this->inferCurrentAction($flags);
        $steps = $stepsService->buildSteps($flags, $currentAction);
        $visibleSteps = array_values(array_filter($steps, static fn(array $step): bool => (bool)($step['visible'] ?? true)));

        $nextStep = null;
        foreach ($visibleSteps as $step) {
            if (($step['unlocked'] ?? false) && !($step['done'] ?? false)) {
                $nextStep = $step;
                break;
            }
        }

        $ticketMode = (string)($form['ticket_upload_mode'] ?? '');
        $mode = ($ticketMode === 'seasonpass' || ((string)($flags['gate_season_pass'] ?? '')) === '1')
            ? 'commuter'
            : 'standard';

        $route = trim(implode(' → ', array_filter([
            (string)($form['dep_station_name'] ?? $form['from_station'] ?? ''),
            (string)($form['arr_station_name'] ?? $form['to_station'] ?? ''),
        ])));

        return [
            'form' => $form,
            'flags' => $flags,
            'mode' => $mode,
            'operator' => (string)($form['operator_name'] ?? $form['operator'] ?? ''),
            'product' => (string)($form['operator_product'] ?? ''),
            'route' => $route,
            'status' => $this->deriveStatus($flags, $mode),
            'steps' => $visibleSteps,
            'nextStep' => $nextStep,
            'completedVisible' => count(array_filter($visibleSteps, static fn(array $step): bool => (bool)($step['done'] ?? false))),
            'visibleTotal' => count($visibleSteps),
        ];
    }

    /**
     * @param array<string,mixed> $flags
     */
    private function inferCurrentAction(array $flags): string
    {
        foreach (FlowStepsService::STEPS as $step) {
            $doneFlag = (string)($step['doneFlag'] ?? '');
            if ($doneFlag === '' || ((string)($flags[$doneFlag] ?? '')) !== '1') {
                return (string)($step['action'] ?? 'start');
            }
        }

        return 'consent';
    }

    /**
     * @param array<string,mixed> $flags
     */
    private function deriveStatus(array $flags, string $mode): string
    {
        if (((string)($flags['step12_done'] ?? '')) === '1') {
            return 'Klar til indsendelse';
        }
        if (((string)($flags['step10_done'] ?? '')) === '1') {
            return 'Resultat klar';
        }
        if (((string)($flags['step5_done'] ?? '')) === '1') {
            return 'Klar til review';
        }
        if (((string)($flags['step2_done'] ?? '')) === '1' && $mode === 'commuter') {
            return 'Pendler-setup aktivt';
        }
        if (((string)($flags['step1_done'] ?? '')) === '1') {
            return 'Sag påbegyndt';
        }

        return 'Ikke startet';
    }
}

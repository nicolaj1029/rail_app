<?php
namespace App\Controller\Admin;

use App\Controller\AppController;
use App\Service\CaseRiskService;
use Cake\Datasource\Exception\RecordNotFoundException;
use Cake\ORM\Table;
use Cake\Utility\Text;

class CasesController extends AppController
{
    protected Table $Cases;

    public function initialize(): void
    {
        parent::initialize();
        $this->Cases = $this->fetchTable('Cases');
    }

    public function index()
    {
        $q = trim((string)$this->request->getQuery('q'));
        $query = $this->Cases->find();
        if ($q !== '') {
            $query->where(['OR' => [
                'ref LIKE' => '%' . $q . '%',
                'passenger_name LIKE' => '%' . $q . '%',
                'operator LIKE' => '%' . $q . '%',
            ]]);
        }
        $query->orderByDesc('Cases.created');
        $cases = $query->limit(200)->all();
        $this->set(compact('cases','q'));
    }

    public function view($id = null)
    {
        try { $case = $this->Cases->get($id); } catch (RecordNotFoundException $e) { throw $e; }
        $this->set(compact('case'));
    }

    public function edit($id = null)
    {
        $case = $this->Cases->get($id);
        if ($this->request->is(['post','put','patch'])) {
            $data = $this->request->getData();
            $case = $this->Cases->patchEntity($case, $data);
            if ($this->Cases->save($case)) {
                $this->Flash->success('Sag opdateret');
                return $this->redirect(['action' => 'view', $case->id]);
            }
            $this->Flash->error('Kunne ikke gemme');
        }
        $this->set(compact('case'));
    }

    public function passenger($id = null)
    {
        $case = $this->Cases->get($id);
        $ref = trim((string)$case->get('ref'));
        if ($ref === '') {
            $this->Flash->error('Sagen har ingen reference til passenger-backend.');
            return $this->redirect(['action' => 'view', $id]);
        }

        return $this->redirect('/passenger/case?ref=' . rawurlencode($ref) . '&admin=1');
    }

    public function airTravelForm($id = null)
    {
        $case = $this->Cases->get($id);
        $snapshot = json_decode((string)$case->get('flow_snapshot'), true);
        if (!is_array($snapshot) || $snapshot === []) {
            $this->Flash->error('Sagen mangler et brugbart flow-snapshot til PDF-generering.');
            return $this->redirect(['action' => 'view', $id]);
        }

        $form = (array)($snapshot['form'] ?? []);
        $transportMode = strtolower(trim((string)($form['transport_mode'] ?? ($form['gating_mode'] ?? ''))));
        if ($transportMode !== 'air') {
            $this->Flash->error('air_travel_form.pdf er kun koblet til air-sager.');
            return $this->redirect(['action' => 'view', $id]);
        }

        $this->request->getSession()->write('flow', $snapshot);

        return $this->redirect('/reimbursement/official?template=Form_air_travel/air_travel_form.pdf');
    }

    public function airStatementForm($id = null)
    {
        $case = $this->Cases->get($id);
        $snapshot = json_decode((string)$case->get('flow_snapshot'), true);
        if (!is_array($snapshot) || $snapshot === []) {
            $this->Flash->error('Sagen mangler et brugbart flow-snapshot til PDF-generering.');
            return $this->redirect(['action' => 'view', $id]);
        }

        $form = (array)($snapshot['form'] ?? []);
        $transportMode = strtolower(trim((string)($form['transport_mode'] ?? ($form['gating_mode'] ?? ''))));
        if ($transportMode !== 'air') {
        $this->Flash->error('staevning-flysag.pdf er kun koblet til air-sager.');
            return $this->redirect(['action' => 'view', $id]);
        }

        $this->request->getSession()->write('flow', $snapshot);

        return $this->redirect('/reimbursement/official?template=Staevning_template_air_DK/staevning-flysag-uncompressed.pdf');
    }

    public function createFromSession()
    {
        $sess = $this->request->getSession()->read('flow') ?: [];
        $form = (array)($sess['form'] ?? []);
        $journey = (array)($sess['journey'] ?? []);
        $compute = (array)($sess['compute'] ?? []);
        $flags = (array)($sess['flags'] ?? []);
        $incident = (array)($sess['incident'] ?? []);
        $meta = (array)($sess['meta'] ?? []);
        $euOnly = (bool)($compute['euOnly'] ?? true);
        $delayWhole = isset($form['delayAtFinalMinutes']) ? (int)$form['delayAtFinalMinutes'] : 0;
        $delayEu = isset($compute['delayMinEU']) ? (int)$compute['delayMinEU'] : null;
        $delay = ($euOnly && $delayEu !== null && $delayEu >= 0) ? $delayEu : $delayWhole;
        $remedy = (string)($form['remedyChoice'] ?? '');
        $expensesTotal = 0.0;
        foreach (['meal_self_paid_amount','hotel_self_paid_amount','blocked_self_paid_amount','alt_self_paid_amount'] as $ek) {
            $v = (string)($form[$ek] ?? '');
            if ($v !== '' && is_numeric($v)) { $expensesTotal += (float)$v; }
        }
        $compBand = (string)($form['compensationBand'] ?? '');
        $compAmount = isset($meta['claim']['compensation_amount']) ? (float)$meta['claim']['compensation_amount'] : null;
        $currency = (string)($journey['ticketPrice']['currency'] ?? ($form['price_currency'] ?? ''));
        $operator = (string)($form['operator'] ?? ($journey['operator']['value'] ?? ''));
        $travelDate = null;
        if (!empty($form['dep_date'])) { $travelDate = $form['dep_date']; }
        $snapshot = json_encode($sess, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        $riskFields = [
            'risk_score' => 0,
            'risk_level' => 'low',
            'risk_flags' => null,
            'fraud_review_required' => false,
            'risk_last_evaluated_at' => date('Y-m-d H:i:s'),
            'risk_summary' => 'Ingen staerke risikosignaler fundet i fase 1.',
            'duplicate_flag' => false,
        ];
        try {
            $riskService = new CaseRiskService();
            $risk = $riskService->evaluate($sess);
            $riskFields = $riskService->toCaseFields($risk);
        } catch (\Throwable) {
            // Risk screening must not block case creation.
        }
        $ref = Text::uuid();
        $case = $this->Cases->newEntity([
            'ref' => $ref,
            'status' => 'open',
            'travel_date' => $travelDate,
            'passenger_name' => (string)($form['passenger_name'] ?? ''),
            'operator' => $operator,
            'country' => (string)($journey['country']['value'] ?? ''),
            'delay_min_eu' => $delay,
            'remedy_choice' => $remedy,
            'art20_expenses_total' => $expensesTotal,
            'comp_band' => $compBand,
            'comp_amount' => $compAmount,
            'currency' => $currency,
            'eu_only' => $euOnly,
            'extraordinary' => (bool)($form['operatorExceptionalCircumstances'] ?? false),
            'attachments_count' => 0,
            'flow_snapshot' => $snapshot,
        ] + $riskFields);
        if ($this->Cases->save($case)) {
            $this->Flash->success('Sag oprettet fra session: ' . $ref);
            return $this->redirect(['action' => 'view', $case->id]);
        }
        $this->Flash->error('Kunne ikke oprette sag.');
        return $this->redirect(['action' => 'index']);
    }
}

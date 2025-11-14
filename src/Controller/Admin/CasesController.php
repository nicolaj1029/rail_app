<?php
namespace App\Controller\Admin;

use App\Controller\AppController;
use Cake\Datasource\Exception\RecordNotFoundException;
use Cake\Utility\Text;

class CasesController extends AppController
{
    public function initialize(): void
    {
        parent::initialize();
        $this->loadModel('Cases');
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
        $query->orderDesc('Cases.created');
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

    public function createFromSession()
    {
        $sess = $this->request->getSession()->read('flow') ?: [];
        $form = (array)($sess['form'] ?? []);
        $journey = (array)($sess['journey'] ?? []);
        $compute = (array)($sess['compute'] ?? []);
        $flags = (array)($sess['flags'] ?? []);
        $incident = (array)($sess['incident'] ?? []);
        $meta = (array)($sess['meta'] ?? []);
        $delay = (int)($compute['delayMinEU'] ?? ($form['delayAtFinalMinutes'] ?? 0));
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
            'eu_only' => (bool)($compute['euOnly'] ?? true),
            'extraordinary' => (bool)($form['operatorExceptionalCircumstances'] ?? false),
            'duplicate_flag' => false,
            'attachments_count' => 0,
            'flow_snapshot' => $snapshot,
        ]);
        if ($this->Cases->save($case)) {
            $this->Flash->success('Sag oprettet fra session: ' . $ref);
            return $this->redirect(['action' => 'view', $case->id]);
        }
        $this->Flash->error('Kunne ikke oprette sag.');
        return $this->redirect(['action' => 'index']);
    }
}

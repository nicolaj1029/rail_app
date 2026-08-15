<?php
declare(strict_types=1);

/**
 * CakePHP(tm) : Rapid Development Framework (https://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 * @link      https://cakephp.org CakePHP(tm) Project
 * @since     0.2.9
 * @license   https://opensource.org/licenses/mit-license.php MIT License
 */
namespace App\Controller;

use Cake\Controller\Controller;
use Cake\Core\Configure;
use Cake\Event\EventInterface;
use Cake\Routing\Router;

/**
 * Application Controller
 *
 * Add your application-wide methods in the class below, your controllers
 * will inherit them.
 *
 * @link https://book.cakephp.org/5/en/controllers.html#the-app-controller
 */
class AppController extends Controller
{
    /**
     * Actions that have an audited TC6 presentation template in this release.
     *
     * @return array<int,string>
     */
    protected function tc6FlowActions(): array
    {
        return [
            'entitlements',
            'airReservationContract',
            'airLegSelect',
            'airFlightSelect',
            'railDepartureSelect',
            'ferryDepartureSelect',
            'railstranding',
            'incident',
            'choices',
            'remedies',
            'assistance',
            'downgrade',
            'compensation',
            'applicant',
            'consent',
        ];
    }

    protected function resolveTc6Mode(): bool
    {
        if ((string)$this->getRequest()->getParam('controller') !== 'Flow') {
            return false;
        }

        $action = (string)$this->getRequest()->getParam('action');
        if (!in_array($action, $this->tc6FlowActions(), true)) {
            return false;
        }

        $session = $this->getRequest()->getSession();
        $query = $this->getRequest()->getQuery('tc6');
        if ($query !== null) {
            $normalized = strtolower(trim((string)$query));
            $enabled = !in_array($normalized, ['', '0', 'false', 'off', 'no'], true);
            $session->write('flow.tc6_mode', $enabled ? '1' : '0');

            return $enabled;
        }

        return (string)$session->read('flow.tc6_mode') === '1';
    }

    protected function resolveTc6Language(): string
    {
        $session = $this->getRequest()->getSession();
        $query = $this->getRequest()->getQuery('lang') ?? $this->getRequest()->getQuery('locale');
        if ($query !== null) {
            $language = strtolower(substr(trim((string)$query), 0, 2));
            if (in_array($language, ['da', 'en', 'fr'], true)) {
                $session->write('flow.ui_language', $language);

                return $language;
            }
        }

        $stored = strtolower((string)$session->read('flow.ui_language'));

        return in_array($stored, ['da', 'en', 'fr'], true) ? $stored : 'da';
    }

    private function configureTc6Presentation(string $action): void
    {
        $session = $this->getRequest()->getSession();
        $form = (array)$session->read('flow.form') ?: [];
        $meta = (array)$session->read('flow.meta') ?: [];
        $flags = (array)$session->read('flow.flags') ?: [];
        $mode = strtolower((string)($form['transport_mode'] ?? ($meta['transport_mode'] ?? 'rail')));
        if (!in_array($mode, ['air', 'rail', 'ferry'], true)) {
            $mode = 'rail';
        }
        $uiLanguage = $this->resolveTc6Language();

        $definitions = [
            'air' => [
                ['actions' => ['entitlements'], 'da' => 'Basisrejse', 'fr' => 'Trajet de base'],
                ['actions' => ['airReservationContract'], 'da' => 'Reservation, kontrakt', 'fr' => 'Reservation, contrat'],
                ['actions' => ['airLegSelect', 'airFlightSelect'], 'da' => 'Fly-match', 'fr' => 'Flight-match'],
                ['actions' => ['incident'], 'da' => 'Hændelse + dine muligheder nu', 'fr' => 'Incident + vos options maintenant'],
                ['actions' => ['choices', 'remedies', 'assistance'], 'da' => 'Valg efter hændelsen', 'fr' => 'Choix après l’incident'],
                ['actions' => ['downgrade'], 'da' => 'Nedgradering (hvis relevant)', 'fr' => 'Déclassement (si pertinent)'],
                ['actions' => ['compensation', 'applicant', 'consent'], 'da' => 'Kontakt & opret sag', 'fr' => 'Contact et création du dossier'],
            ],
            'rail' => [
                ['actions' => ['entitlements'], 'da' => 'Billet og rejsegrundlag', 'fr' => 'Billet et base du trajet'],
                ['actions' => ['railDepartureSelect'], 'da' => 'Vælg afgang og kontrakt', 'fr' => 'Choisissez le départ et le contrat'],
                ['actions' => ['railstranding'], 'da' => 'Stranding og live-status', 'fr' => 'Blocage et statut en direct'],
                ['actions' => ['incident'], 'da' => 'Hændelse + dine muligheder nu', 'fr' => 'Incident + vos options maintenant'],
                ['actions' => ['choices'], 'da' => 'Dine valg', 'fr' => 'Vos choix'],
                ['actions' => ['remedies'], 'da' => 'Refusion / ombooking', 'fr' => 'Remboursement / réacheminement'],
                ['actions' => ['assistance'], 'da' => 'Assistance', 'fr' => 'Assistance'],
                ['actions' => ['downgrade'], 'da' => 'Nedgradering', 'fr' => 'Déclassement'],
                ['actions' => ['compensation'], 'da' => 'Kompensation', 'fr' => 'Indemnisation'],
                ['actions' => ['applicant', 'consent'], 'da' => 'Kontakt & samtykke', 'fr' => 'Contact et consentement'],
            ],
            'ferry' => [
                ['actions' => ['entitlements'], 'da' => 'Sejlads og billet', 'fr' => 'Traversée et billet'],
                ['actions' => ['ferryDepartureSelect'], 'da' => 'Vælg afgang', 'fr' => 'Choisissez le départ'],
                ['actions' => ['incident'], 'da' => 'Hændelse + dine muligheder nu', 'fr' => 'Incident + vos options maintenant'],
                ['actions' => ['choices'], 'da' => 'Dine valg', 'fr' => 'Vos choix'],
                ['actions' => ['remedies'], 'da' => 'Refusion / ombooking', 'fr' => 'Remboursement / réacheminement'],
                ['actions' => ['assistance'], 'da' => 'Assistance', 'fr' => 'Assistance'],
                ['actions' => ['compensation'], 'da' => 'Kompensation', 'fr' => 'Indemnisation'],
                ['actions' => ['applicant', 'consent'], 'da' => 'Kontakt & samtykke', 'fr' => 'Contact et consentement'],
            ],
        ];

        $stepDefinitions = $definitions[$mode];
        $currentStep = 1;
        foreach ($stepDefinitions as $index => $definition) {
            if (in_array($action, $definition['actions'], true)) {
                $currentStep = $index + 1;
                break;
            }
        }

        $query = ['tc6' => 1];
        if ($uiLanguage !== 'da') {
            $query['lang'] = $uiLanguage;
        }
        $steps = [];
        foreach ($stepDefinitions as $index => $definition) {
            $number = $index + 1;
            $targetAction = (string)$definition['actions'][0];
            $steps[$number] = [
                'label' => (string)($definition[$uiLanguage] ?? $definition['da']),
                'url' => $number <= $currentStep
                    ? Router::url(['controller' => 'Flow', 'action' => $targetAction, '?' => $query])
                    : '',
            ];
        }

        $doneSteps = $currentStep > 1 ? range(1, $currentStep - 1) : [];
        $total = count($steps);
        $progressPct = (int)round(($currentStep / max(1, $total)) * 100);
        $progressLabel = $currentStep . ' / ' . $total . ($uiLanguage === 'fr' ? ' étapes' : ' trin');
        $travelState = strtolower((string)($flags['travel_state'] ?? ($meta['entry_travel_state'] ?? 'completed')));
        $travelLabel = match ([$uiLanguage, $travelState]) {
            ['fr', 'ongoing'] => 'Voyage en cours',
            ['fr', 'before_start'] => 'Avant le départ',
            ['fr', 'completed'] => 'Voyage terminé',
            ['da', 'ongoing'] => 'Igangværende rejse',
            ['da', 'before_start'] => 'Før afgang',
            default => 'Afsluttet rejse',
        };
        $stats = [
            [$uiLanguage === 'fr' ? 'Parcours' : 'Flow', $travelLabel, null],
            ['Transport', strtoupper($mode), null],
        ];

        $template = match ($action) {
            'entitlements' => match ($mode) {
                'air' => 'tc6/air_entitlements',
                'ferry' => 'tc6/ferry_entitlements',
                default => 'tc6/entitlements',
            },
            'airReservationContract' => 'tc6/air_reservation_contract',
            'airFlightSelect' => 'tc6/air_flight_select',
            'railDepartureSelect' => 'tc6/rail_departure_select',
            'ferryDepartureSelect' => 'tc6/ferry_departure_select',
            'railstranding' => 'tc6/railstranding',
            'incident' => match ($mode) {
                'air' => 'tc6/air_incident',
                'ferry' => 'tc6/ferry_incident',
                default => 'tc6/incident',
            },
            'choices' => match ($mode) {
                'air' => 'tc6/air_choices',
                'ferry' => 'tc6/ferry_choices',
                default => 'tc6/choices',
            },
            'remedies' => 'tc6/remedies',
            'assistance' => 'tc6/assistance',
            'downgrade' => 'tc6/downgrade',
            'compensation' => 'tc6/compensation',
            'applicant' => 'tc6/applicant',
            'consent' => 'tc6/consent',
            default => null,
        };

        $this->viewBuilder()->setLayout('tc6_flow');
        if ($template !== null) {
            $this->viewBuilder()->setTemplate($template);
        }
        $this->set(compact(
            'steps',
            'doneSteps',
            'currentStep',
            'progressPct',
            'progressLabel',
            'stats',
            'uiLanguage'
        ));
        $this->set('tc6Mode', true);
        $this->set('flowCurrentAction', $action);
    }

    /**
     * Initialization hook method.
     *
     * Use this method to add common initialization code like loading components.
     *
     * e.g. `$this->loadComponent('FormProtection');`
     *
     * @return void
     */
    public function initialize(): void
    {
        parent::initialize();

        $this->loadComponent('Flash');

        /*
         * Enable the following component for recommended CakePHP form protection settings.
         * see https://book.cakephp.org/5/en/controllers/components/form-protection.html
         */
        //$this->loadComponent('FormProtection');
    }

    public function beforeRender(EventInterface $event)
    {
        parent::beforeRender($event);

        // Ensure UTF-8 output consistently. Some environments default to ISO-8859-1 which breaks Danish characters.
        $enc = (string)Configure::read('App.encoding') ?: 'UTF-8';
        if (method_exists($this->response, 'getCharset') && $this->response->getCharset() !== $enc) {
            $this->response = $this->response->withCharset($enc);
        }

        // Provide flow stepper state to all /flow/* pages (multi-page split-flow).
        try {
            $controller = (string)$this->getRequest()->getParam('controller');
            if ($controller !== 'Flow') {
                return;
            }

            $action = (string)$this->getRequest()->getParam('action');
            if ($this->resolveTc6Mode()) {
                $this->configureTc6Presentation($action);

                return;
            }
            $svc = new \App\Service\FlowStepsService();
            $stepActions = array_map(static fn($s) => (string)($s['action'] ?? ''), $svc::STEPS);
            if (!in_array($action, $stepActions, true)) {
                return;
            }
            $flags = (array)$this->getRequest()->getSession()->read('flow.flags') ?: [];
            $form = (array)$this->getRequest()->getSession()->read('flow.form') ?: [];
            $meta = (array)$this->getRequest()->getSession()->read('flow.meta') ?: [];
            $transportMode = strtolower((string)($form['transport_mode'] ?? ($meta['transport_mode'] ?? '')));
            if ($transportMode !== '') {
                $flags['transport_mode'] = $transportMode;
            }
            $entryVariant = strtolower(trim((string)($flags['entry_variant'] ?? ($meta['entry_variant'] ?? ''))));
            if ($entryVariant !== '') {
                $flags['entry_variant'] = $entryVariant;
            }
            $travelState = strtolower(trim((string)($flags['travel_state'] ?? ($form['travel_state'] ?? ($meta['entry_travel_state'] ?? '')))));
            if (in_array($travelState, ['completed', 'ongoing', 'before_start'], true)) {
                $flags['travel_state'] = $travelState;
            }
            $gatingMode = strtolower(trim((string)($flags['gating_mode'] ?? ($form['gating_mode'] ?? ($meta['gating_mode'] ?? '')))));
            if (in_array($gatingMode, ['rail', 'ferry', 'bus', 'air'], true)) {
                $flags['gating_mode'] = $gatingMode;
            }
            $steps = $svc->buildSteps($flags, $action);
            $neighbors = $svc->neighborActions($flags, $action);

            $this->set('flowSteps', $steps);
            $this->set('flowCurrentAction', $action);
            $this->set('flowPrevAction', $neighbors['prev']);
            $this->set('flowNextAction', $neighbors['next']);
        } catch (\Throwable $e) {
            // Stepper is non-critical; ignore errors.
        }
    }
}

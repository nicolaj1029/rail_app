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
use Cake\Event\EventInterface;

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

        // Provide flow stepper state to all /flow/* pages (multi-page split-flow).
        try {
            $controller = (string)$this->getRequest()->getParam('controller');
            if ($controller !== 'Flow') {
                return;
            }

            $action = (string)$this->getRequest()->getParam('action');
            $svc = new \App\Service\FlowStepsService();
            $stepActions = array_map(static fn($s) => (string)($s['action'] ?? ''), $svc::STEPS);
            if (!in_array($action, $stepActions, true)) {
                return;
            }
            $flags = (array)$this->getRequest()->getSession()->read('flow.flags') ?: [];
            $steps = $svc->buildSteps($flags, $action);

            $this->set('flowSteps', $steps);
            $this->set('flowCurrentAction', $action);
        } catch (\Throwable $e) {
            // Stepper is non-critical; ignore errors.
        }
    }
}

<?php
/**
 * Routes configuration.
 *
 * In this file, you set up routes to your controllers and their actions.
 * Routes are very important mechanism that allows you to freely connect
 * different URLs to chosen controllers and their actions (functions).
 *
 * It's loaded within the context of `Application::routes()` method which
 * receives a `RouteBuilder` instance `$routes` as method argument.
 *
 * CakePHP(tm) : Rapid Development Framework (https://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 * @link          https://cakephp.org CakePHP(tm) Project
 * @license       https://opensource.org/licenses/mit-license.php MIT License
 */

use Cake\Routing\Route\DashedRoute;
use Cake\Routing\RouteBuilder;

/*
 * This file is loaded in the context of the `Application` class.
 * So you can use `$this` to reference the application class instance
 * if required.
 */
return function (RouteBuilder $routes): void {
    /*
     * The default class to use for all routes
     *
     * The following route classes are supplied with CakePHP and are appropriate
     * to set as the default:
     *
     * - Route
     * - InflectedRoute
     * - DashedRoute
     *
     * If no call is made to `Router::defaultRouteClass()`, the class used is
     * `Route` (`Cake\Routing\Route\Route`)
     *
     * Note that `Route` does not do any inflections on URLs which will result in
     * inconsistently cased URLs when used with `{plugin}`, `{controller}` and
     * `{action}` markers.
     */
    $routes->setRouteClass(DashedRoute::class);

    $routes->scope('/', function (RouteBuilder $builder): void {
        /*
         * Here, we are connecting '/' (base path) to a controller called 'Pages',
         * its action called 'display', and we pass a param to select the view file
         * to use (in this case, templates/Pages/home.php)...
         */
        $builder->connect('/', ['controller' => 'Pages', 'action' => 'display', 'home']);

        /*
         * ...and connect the rest of 'Pages' controller's URLs.
         */
        $builder->connect('/pages/*', 'Pages::display');

        // Project documents routes
        $builder->connect('/project', ['controller' => 'Project', 'action' => 'index']);
        $builder->connect('/project/links', ['controller' => 'Project', 'action' => 'links']);
        $builder->connect('/project/{slug}', ['controller' => 'Project', 'action' => 'view'])
            ->setPass(['slug']);
        $builder->connect('/project/annotate/{slug}', ['controller' => 'Project', 'action' => 'annotate'])
            ->setPass(['slug']);
        $builder->connect('/project/text/{slug}', ['controller' => 'Project', 'action' => 'text'])
            ->setPass(['slug']);

        // Claims wizard routes
        $builder->connect('/claims', ['controller' => 'Claims', 'action' => 'start']);
        $builder->connect('/claims/compute', ['controller' => 'Claims', 'action' => 'compute']);

    // Reimbursement demo routes
    $builder->connect('/reimbursement', ['controller' => 'Reimbursement', 'action' => 'start']);
    $builder->connect('/reimbursement/generate', ['controller' => 'Reimbursement', 'action' => 'generate']);
    $builder->connect('/reimbursement/official', ['controller' => 'Reimbursement', 'action' => 'official']);

    // Upload flow (client starts here)
    $builder->connect('/upload', ['controller' => 'Upload', 'action' => 'index']);
    $builder->connect('/upload/analyze', ['controller' => 'Upload', 'action' => 'analyze']);

        // Client claims routes
        $builder->connect('/start', ['controller' => 'ClientClaims', 'action' => 'start']);
        $builder->connect('/submit', ['controller' => 'ClientClaims', 'action' => 'submit']);

    // Client wizard (live MVP)
    $builder->connect('/wizard', ['controller' => 'ClientWizard', 'action' => 'start']);
    $builder->connect('/wizard/questions', ['controller' => 'ClientWizard', 'action' => 'questions']);
    $builder->connect('/wizard/expenses', ['controller' => 'ClientWizard', 'action' => 'expenses']);
    $builder->connect('/wizard/summary', ['controller' => 'ClientWizard', 'action' => 'summary']);

    // Streamlined flow wizard
    $builder->connect('/flow', ['controller' => 'Flow', 'action' => 'one']);
    $builder->connect('/flow/one', ['controller' => 'Flow', 'action' => 'one']);
    // Split-step flow (clearer separation)
    $builder->connect('/flow/details', ['controller' => 'Flow', 'action' => 'details']);
    $builder->connect('/flow/screening', ['controller' => 'Flow', 'action' => 'screening']);
    $builder->connect('/flow/choices', ['controller' => 'Flow', 'action' => 'choices']);
    $builder->connect('/flow/extras', ['controller' => 'Flow', 'action' => 'extras']);
    $builder->connect('/flow/applicant', ['controller' => 'Flow', 'action' => 'applicant']);
    $builder->connect('/flow/consent', ['controller' => 'Flow', 'action' => 'consent']);
    // Legacy wizard steps (kept)
    $builder->connect('/flow/start', ['controller' => 'Flow', 'action' => 'start']);
    $builder->connect('/flow/journey', ['controller' => 'Flow', 'action' => 'journey']);
    $builder->connect('/flow/entitlements', ['controller' => 'Flow', 'action' => 'entitlements']);
    $builder->connect('/flow/summary', ['controller' => 'Flow', 'action' => 'summary']);

    // Admin area (non-auth demo)
        $builder->connect('/admin/claims', ['prefix' => 'Admin', 'controller' => 'Claims', 'action' => 'index']);
        $builder->connect('/admin/claims/view/{id}', ['prefix' => 'Admin', 'controller' => 'Claims', 'action' => 'view'])
            ->setPass(['id']);
        $builder->connect('/admin/claims/update-status/{id}', ['prefix' => 'Admin', 'controller' => 'Claims', 'action' => 'updateStatus'])
            ->setPass(['id']);
        $builder->connect('/admin/claims/mark-paid/{id}', ['prefix' => 'Admin', 'controller' => 'Claims', 'action' => 'markPaid'])
            ->setPass(['id']);

        /*
         * Connect catchall routes for all controllers.
         *
         * The `fallbacks` method is a shortcut for
         *
         * ```
         * $builder->connect('/{controller}', ['action' => 'index']);
         * $builder->connect('/{controller}/{action}/*', []);
         * ```
         *
         * It is NOT recommended to use fallback routes after your initial prototyping phase!
         * See https://book.cakephp.org/5/en/development/routing.html#fallbacks-method for more information
         */
        $builder->fallbacks();
    });

    // API prefix (JSON)
    $routes->prefix('Api', function (RouteBuilder $builder): void {
        $builder->setRouteClass(DashedRoute::class);
        // Demo fixtures endpoint
        $builder->connect('/demo/fixtures', ['controller' => 'Demo', 'action' => 'fixtures']);
    $builder->connect('/demo/exemption-fixtures', ['controller' => 'Demo', 'action' => 'exemptionFixtures']);
    $builder->connect('/demo/art12-fixtures', ['controller' => 'Demo', 'action' => 'art12Fixtures']);
    $builder->connect('/demo/scenarios', ['controller' => 'Demo', 'action' => 'scenarios']);
    $builder->connect('/demo/mock-tickets', ['controller' => 'Demo', 'action' => 'mockTickets']);
    $builder->connect('/demo/run-scenarios', ['controller' => 'Demo', 'action' => 'runScenarios']);
    $builder->connect('/demo/generate-mocks', ['controller' => 'Demo', 'action' => 'generateMocks']);
        // Pipeline stubs
        $builder->connect('/ingest/ticket', ['controller' => 'Ingest', 'action' => 'ticket']);
        $builder->connect('/rne/trip', ['controller' => 'Rne', 'action' => 'trip']);
        $builder->connect('/operator/{operatorCode}/trip', ['controller' => 'Operator', 'action' => 'trip'])
            ->setPass(['operatorCode']);
        $builder->connect('/compute/compensation', ['controller' => 'Compute', 'action' => 'compensation']);
        $builder->connect('/compute/exemptions', ['controller' => 'Compute', 'action' => 'exemptions']);
    $builder->connect('/compute/art12', ['controller' => 'Compute', 'action' => 'art12']);
    $builder->connect('/compute/art9', ['controller' => 'Compute', 'action' => 'art9']);
    $builder->connect('/compute/refund', ['controller' => 'Compute', 'action' => 'refund']);
    $builder->connect('/compute/refusion', ['controller' => 'Compute', 'action' => 'refusion']);
    $builder->connect('/compute/claim', ['controller' => 'Compute', 'action' => 'claim']);

    // Unified pipeline (OCR ingest + all evaluators in one)
    $builder->connect('/pipeline/run', ['controller' => 'Pipeline', 'action' => 'run']);

        // Provider stubs for SNCF / DB / DSB / RNE / Open
        $builder->connect('/providers/sncf/booking/validate', ['controller' => 'Providers', 'action' => 'sncfBookingValidate']);
        $builder->connect('/providers/sncf/trains', ['controller' => 'Providers', 'action' => 'sncfTrains']);
        $builder->connect('/providers/sncf/realtime', ['controller' => 'Providers', 'action' => 'sncfRealtime']);

        $builder->connect('/providers/db/lookup', ['controller' => 'Providers', 'action' => 'dbLookup']);
        $builder->connect('/providers/db/trip', ['controller' => 'Providers', 'action' => 'dbTrip']);
        $builder->connect('/providers/db/realtime', ['controller' => 'Providers', 'action' => 'dbRealtime']);

        $builder->connect('/providers/dsb/trip', ['controller' => 'Providers', 'action' => 'dsbTrip']);
        $builder->connect('/providers/dsb/realtime', ['controller' => 'Providers', 'action' => 'dsbRealtime']);

        $builder->connect('/providers/rne/realtime', ['controller' => 'Providers', 'action' => 'rneRealtime']);
        $builder->connect('/providers/open/rt', ['controller' => 'Providers', 'action' => 'openRealtime']);
        $builder->fallbacks();
    });

    /*
     * If you need a different set of middleware or none at all,
     * open new scope and define routes there.
     *
     * ```
     * $routes->scope('/api', function (RouteBuilder $builder): void {
     *     // No $builder->applyMiddleware() here.
     *
     *     // Parse specified extensions from URLs
     *     // $builder->setExtensions(['json', 'xml']);
     *
     *     // Connect API actions here.
     * });
     * ```
     */
};

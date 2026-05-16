<?php

use function Cake\Core\env;

/*
 * Local configuration file to provide any overrides to your app.php configuration.
 * Copy and save this file as app_local.php and make changes as required.
 * Note: It is not recommended to commit files with credentials such as app_local.php
 * into source code version control.
 */
return [
    /*
     * Debug Level:
     *
     * Production Mode:
     * false: No error messages, errors, or warnings shown.
     *
     * Development Mode:
     * true: Errors and warnings shown.
     */
    'debug' => filter_var(env('DEBUG', true), FILTER_VALIDATE_BOOLEAN),

    'SiteAccess' => [
        'enabled' => filter_var(env('SITE_BASIC_AUTH_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
        'username' => env('SITE_BASIC_AUTH_USER', ''),
        'password' => env('SITE_BASIC_AUTH_PASS', ''),
        'realm' => env('SITE_BASIC_AUTH_REALM', 'Preview'),
    ],

    'PublicSite' => [
        'enabled' => filter_var(env('PUBLIC_SITE_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
        'landingPath' => env('PUBLIC_SITE_LANDING_PATH', '/passenger/start'),
        'hideTopNav' => filter_var(env('PUBLIC_SITE_HIDE_TOP_NAV', true), FILTER_VALIDATE_BOOLEAN),
        'hidePassengerNav' => filter_var(env('PUBLIC_SITE_HIDE_PASSENGER_NAV', true), FILTER_VALIDATE_BOOLEAN),
    ],

    'External' => [
        'useLiveApis' => true,
        'dbTransportRestBase' => 'https://v6.db.transport.rest',
        'aeroDataBox' => [
            'apiKey' => '',
            'apiHost' => 'aerodatabox.p.rapidapi.com',
            'baseUrl' => 'https://aerodatabox.p.rapidapi.com',
        ],
        'aviationstack' => [
            'apiKey' => '',
            'baseUrl' => 'https://api.aviationstack.com/v1/flights',
        ],
    ],

    'Rail' => [
        // Shared hosting should normally use direct HAFAS calls, not a local node service.
        'transportServiceEnabled' => false,
        'transportServiceBaseUrl' => 'http://127.0.0.1:7071',
        'hafas' => [
            'enabled' => true,
        ],
    ],

    /*
     * Security and encryption configuration
     *
     * - salt - A random string used in security hashing methods.
     *   The salt value is also used as the encryption key.
     *   You should treat it as extremely sensitive data.
     */
    'Security' => [
        'salt' => env('SECURITY_SALT', '__SALT__'),
    ],

    /*
     * Connection information used by the ORM to connect
     * to your application's datastores.
     *
     * See app.php for more configuration options.
     */
    'Datasources' => [
        'default' => [
            'host' => 'localhost',
            /*
             * CakePHP will use the default DB port based on the driver selected
             * MySQL on MAMP uses port 8889, MAMP users will want to uncomment
             * the following line and set the port accordingly
             */
            //'port' => 'non_standard_port_number',

            'username' => 'my_app',
            'password' => 'secret',

            'database' => 'my_app',
            /*
             * If not using the default 'public' schema with the PostgreSQL driver
             * set it here.
             */
            //'schema' => 'myapp',

            /*
             * You can use a DSN string to set the entire configuration
             */
            'url' => env('DATABASE_URL', null),
        ],

        /*
         * The test connection is used during the test suite.
         */
        'test' => [
            'host' => 'localhost',
            //'port' => 'non_standard_port_number',
            'username' => 'my_app',
            'password' => 'secret',
            'database' => 'test_myapp',
            //'schema' => 'myapp',
            'url' => env('DATABASE_TEST_URL', 'sqlite://127.0.0.1/tmp/tests.sqlite'),
        ],
    ],

    /*
     * Email configuration.
     *
     * Host and credential configuration in case you are using SmtpTransport
     *
     * See app.php for more configuration options.
     */
    'EmailTransport' => [
        'default' => [
            'host' => 'localhost',
            'port' => 25,
            'username' => null,
            'password' => null,
            'client' => null,
            'url' => env('EMAIL_TRANSPORT_DEFAULT_URL', null),
        ],
    ],
];

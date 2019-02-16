<?php
return [
    'doctrine' => [
        'connection' => [
            'orm_default' => [
                'driverClass' => 'Doctrine\DBAL\Driver\PDOMySql\Driver',
                'params'      => [
                    'host'          => '127.0.0.1',
                    'port'          => '3306',
                    'user'          => 'root',
                    'password'      => '',
                    'dbname'        => 'test',
                    'driverOptions' => [
                        1002 => 'SET NAMES utf8',
                    ],
                ],
            ],
        ],
        'configuration' => [
            'orm_default' => [
            ],
        ],
    ],
    'modules' => [
        'Zend\Router',
        'Zend\Mvc\Plugin\FlashMessenger', // for controller tests
        'Zend\I18n', // for controller tests
        'DoctrineModule',
        'DoctrineORMModule',
        'SlmQueue',
        'Vrok\References',
        'Vrok',
        'Vrok\Premium',
    ],
    'module_listener_options' => [
        'config_glob_paths'    => [
            __DIR__.'/TestConfig.php',
            __DIR__.'/TestConfig.local.php',
        ],
        'module_paths' => [
            'module',
            'vendor',
        ],
    ],
    'feature_manager' => [
        'features' => [
            'test' => [
                'service'    => 'VrokPremiumTest\Feature\TestFeature',
                'candidates' => [
                    'Vrok\Entity\User',
                ],
            ],
        ],
    ],
    'service_manager' => [
        'factories' => [
            'VrokPremiumTest\Feature\TestFeature' => function ($sm) {
                return new \VrokPremiumTest\Feature\TestFeature();
            },
        ],
    ],
];

<?php
return [
    'bjyauthorize' => [
        'guards' => [
            'BjyAuthorize\Guard\Controller' => [
                [
                    'controller' => 'Vrok\Premium\Controller\Index',
                    'roles'      => ['admin'],
                ],
                [
                    'controller' => 'Vrok\Premium\Controller\Feature',
                    'roles'      => ['admin'],
                ],
            ],
        ],
    ],
    'doctrine' => [
        /*
         * SQLite fails with weird behaviour, reusing old records that were
         * previously deleted by FeatureManagerTest::setUp()
         */
        /*'connection' => [
            'orm_default' => [
                'driverClass' => 'Doctrine\DBAL\Driver\PDOSqlite\Driver',
                'params'      => [
                    'memory' => true,
                ],
            ],
        ],/**/
        'connection' => [
            'orm_default' => [
                'driverClass' => 'Doctrine\DBAL\Driver\PDOMySql\Driver',
                'params'      => [
                    'host'          => 'mysql',
                    'port'          => '3306',
                    'user'          => 'test',
                    'password'      => 'test',
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
        'Zend\Form',
        'Zend\Mvc\Plugin\FlashMessenger', // for controller tests
        'Zend\I18n', // for controller tests
        'Zend\Mvc\I18n',
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
    'view_manager' => [
        'exception_template' => 'error/index',
        'layout'             => 'error/index',
        'template_map'       => [
            'error/403'   => __DIR__.'/view/error.phtml',
            'error/404'   => __DIR__.'/view/error.phtml',
            'error/index' => __DIR__.'/view/error.phtml',
           ],
        'template_path_stack' => [
            __DIR__.'/view',
        ],
    ],
];

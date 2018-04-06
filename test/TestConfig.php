<?php
return [
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
        'driver' => [
/*            'ref_entities' => [
                'class' => 'Doctrine\ORM\Mapping\Driver\AnnotationDriver',
                'cache' => 'array',
                'paths' => [__DIR__.'/RefHelperTest/Entity'],
            ],
            'orm_default' => [
                'drivers' => [
                    'RefHelperTest\Entity' => 'ref_entities',
                ],
            ],*/
        ],
    ],
    'modules' => [
        'Zend\Router',
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

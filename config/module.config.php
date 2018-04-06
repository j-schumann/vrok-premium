<?php

/**
 * vrok-premium config.
 */
return [
// <editor-fold defaultstate="collapsed" desc="bjyauthorize">
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
// </editor-fold>
// <editor-fold defaultstate="collapsed" desc="doctrine">
    'doctrine' => [
        'driver' => [
            'vrok_premium_entities' => [
                'class' => 'Doctrine\ORM\Mapping\Driver\AnnotationDriver',
                'cache' => 'array',
                'paths' => [__DIR__.'/../src/Entity'],
            ],
            'orm_default' => [
                'drivers' => [
                    'Vrok\Premium\Entity' => 'vrok_premium_entities',
                ],
            ],
        ],
    ],
// </editor-fold>
// <editor-fold defaultstate="collapsed" desc="feature_manager">
    /*'feature_manager' => [
        'features' => [
            'featureName' => [
                'service' => 'Feature\Service\Class',
                'candidates' => [
                    'Vrok\Entity\User',
                ],
            ],
        ],
    ],*/
// </editor-fold>
// <editor-fold defaultstate="collapsed" desc="navigation">
    /*
     * Bjy-Authorize uses resource names like
     * controller/{ControllerServiceName}:{action} when the guards are defined with
     * one or more actions instead of defining the actions as privileges on the controllers
     * When no action is set we must only use controller/{ControllerServiceName} as
     * there is no resource controller/{ControllerServiceName}:index for them.
     */
    'navigation' => [
        'default' => [
            'administration' => [
                'label'    => 'navigation.administration', // default label or none is rendered
                'uri'      => '#', // we need either a route or an URI to avoid fatal error
                'order'    => 1000,
                'pages'    => [
                    [
                        'label'    => 'navigation.premium',
                        'route'    => 'premium',
                        'resource' => 'controller/Vrok\Premium\Controller\Index',
                        'pages'    => [
                            [
                                'label' => 'navigation.premium.feature',
                                'route' => 'premium/feature',
                            ],
                            [
                                'label'   => 'navigation.premium.feature.assign',
                                'route'   => 'premium/feature/assign',
                                'visible' => true,
                            ],
                            [
                                'label'   => 'navigation.premium.feature.remove',
                                'route'   => 'premium/feature/remove',
                                'visible' => false,
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ],
// </editor-fold>
// <editor-fold defaultstate="collapsed" desc="router">
    'router' => [
        'routes' => [
            'premium' => [
                'type'    => 'Literal',
                'options' => [
                    'route'    => '/premium/',
                    'defaults' => [
                        'controller' => 'Vrok\Premium\\Controller\Index',
                        'action'     => 'index',
                    ],
                ],
                'may_terminate' => true,
                'child_routes'  => [
                    'feature' => [
                        'type'    => 'Segment',
                        'options' => [
                            'route'    => 'feature/',
                            'defaults' => [
                                'controller' => 'Vrok\Premium\\Controller\Feature',
                                'action'     => 'index',
                            ],
                        ],
                        'may_terminate' => true,
                        'child_routes'  => [
                            'assign' => [
                                'type'    => 'Segment',
                                'options' => [
                                    'route'    => 'assign/',
                                    'defaults' => [
                                        'action' => 'assign',
                                    ],
                                ],
                            ],
                            'defaults' => [
                                'type'    => 'Segment',
                                'options' => [
                                    'route'       => 'defaults/[:name][/]',
                                    'constraints' => [
                                        'name' => '[a-zA-Z][a-zA-Z0-9_-]+',
                                    ],
                                    'defaults' => [
                                        'action' => 'defaults',
                                    ],
                                ],
                            ],
                            'remove' => [
                                'type'    => 'Segment',
                                'options' => [
                                    'route'    => 'remove/',
                                    'defaults' => [
                                        'action' => 'remove',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ],
// </editor-fold>
// <editor-fold defaultstate="collapsed" desc="service_manager">
    'service_manager' => [
        'factories' => [
            'Vrok\Premium\Service\FeatureManager' => 'Vrok\Premium\Service\FeatureManagerFactory',
        ],
    ],
// </editor-fold>
// <editor-fold defaultstate="collapsed" desc="view_manager">
    'view_manager' => [
        'template_path_stack' => [
            __DIR__.'/../view',
        ],
    ],
// </editor-fold>
];

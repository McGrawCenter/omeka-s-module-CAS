<?php

namespace CAS;

return [
    'controllers' => [
        'factories' => [
            'CAS\Controller\Login' => Service\Controller\LoginControllerFactory::class,
        ],
    ],
    'entity_manager' => [
        'mapping_classes_paths' => [
            dirname(__DIR__) . '/src/Entity',
        ],
        'proxy_paths' => [
            dirname(__DIR__) . '/data/doctrine-proxies',
        ],
    ],
    'form_elements' => [
        'invokables' => [
            'CAS\Form\ConfigForm' => Form\ConfigForm::class,
        ],
    ],
    'router' => [
        'routes' => [
            'cas' => [
                'type' => 'Literal',
                'options' => [
                    'route' => '/cas',
                    'defaults' => [
                        '__NAMESPACE__' => 'CAS\Controller',
                    ],
                ],
                'child_routes' => [
                    'login' => [
                        'type' => 'Literal',
                        'options' => [
                            'route' => '/login',
                            'defaults' => [
                                'controller' => 'Login',
                                'action' => 'login',
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ],
    'view_helpers' => [
        'invokables' => [
            'casLoginUrl' => View\Helper\CasLoginUrl::class,
        ],
    ],
    'view_manager' => [
        'template_path_stack' => [
            dirname(__DIR__) . '/view',
        ],
    ],
];

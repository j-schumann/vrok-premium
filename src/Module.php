<?php

/**
 * @copyright   (c) 2014-18, Vrok
 * @license     MIT License (http://www.opensource.org/licenses/mit-license.php)
 * @author      Jakob Schumann <schumann@vrok.de>
 */

namespace Vrok\Premium;

use Vrok\SlmQueue\JobProviderInterface;
use Zend\ModuleManager\Feature\ConfigProviderInterface;
use Zend\ModuleManager\Feature\ControllerProviderInterface;
use Zend\ModuleManager\Feature\FormElementProviderInterface;

/**
 * Module bootstrapping.
 */
class Module implements
    ConfigProviderInterface,
    ControllerProviderInterface,
    FormElementProviderInterface,
    JobProviderInterface
{
    /**
     * Returns the modules default configuration.
     *
     * @return array
     */
    public function getConfig()
    {
        return include __DIR__.'/../config/module.config.php';
    }

    /**
     * Return additional serviceManager config with closures that should not be
     * in the config files to allow caching of the complete configuration.
     *
     * @return array
     */
    public function getControllerConfig()
    {
        return [
            'factories' => [
                'Vrok\Premium\Controller\Feature' => function ($sm) {
                    $fm = $sm->get(Service\FeatureManager::class);
                    return new Controller\FeatureController($fm, $sm);
                },
                'Vrok\Premium\Controller\Index' => function ($sm) {
                    return new Controller\IndexController($sm);
                },
            ]
        ];
    }

    /**
     * Return additional serviceManager config with closures that should not be in the
     * config files to allow caching of the complete configuration.
     *
     * @return array
     */
    public function getFormElementConfig()
    {
        return [
            'factories' => [
                'Vrok\Premium\Form\FeatureDefaults' => function ($sm) {
                    $form = new Form\FeatureDefaults();
                    $form->setTranslator($sm->get('MvcTranslator'));
                    return $form;
                },
            ],
        ];
    }

    /**
     * Retrieve factories for SlmQueue jobs.
     *
     * @return array
     */
    public function getJobManagerConfig()
    {
        return [
            'factories' => [
                'Vrok\Premium\Job\AssignFeature' => function ($sl) {
                    $fm = $sl->get(Service\FeatureManager::class);
                    $em = $sl->get('EventManager');
                    return new Job\AssignFeature($fm, $em);
                },
                'Vrok\Premium\Job\RemoveFeature' => function ($sl) {
                    $fm = $sl->get(Service\FeatureManager::class);
                    $em = $sl->get('EventManager');
                    return new Job\RemoveFeature($fm, $em);
                },
                'Vrok\Premium\Job\UpdateFeatureDefaultConfig' => function ($sl) {
                    $fm = $sl->get(Service\FeatureManager::class);
                    $em = $sl->get('EventManager');
                    return new Job\UpdateFeatureDefaultConfig($fm, $em);
                },
            ],
        ];
    }
}

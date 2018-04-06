<?php

/**
 * @copyright   (c) 2014-18, Vrok
 * @license     MIT License (http://www.opensource.org/licenses/mit-license.php)
 * @author      Jakob Schumann <schumann@vrok.de>
 */

namespace Vrok\Premium\Service;

use Interop\Container\ContainerInterface;
use Vrok\References\Service\ReferenceHelper;
use Vrok\Service\Meta;
use Zend\ServiceManager\Exception\ServiceNotCreatedException;
use Zend\ServiceManager\Factory\FactoryInterface;

class FeatureManagerFactory implements FactoryInterface
{
    /**
     * Create a new ReferenceHelper.
     *
     * @param  ContainerInterface $container
     * @param  string             $requestedName
     * @param  null|array         $options
     *
     * @return ReferenceHelper
     * @throws ServiceNotFoundException if unable to resolve the service.
     * @throws ServiceNotCreatedException if an exception is raised when
     *     creating a service.
     * @throws ContainerException if any other error occurs
     */
    public function __invoke(
        ContainerInterface $container,
        $requestedName,
        array $options = null
    ) {
        $em = $container->get('Doctrine\ORM\EntityManager');
        if (! $em) {
            throw new ServiceNotCreatedException(
                'Doctrine\ORM\EntityManager could not be found when trying to create a ReferenceHelper'
            );
        }

        $refHelper = $container->get(ReferenceHelper::class);
        $metaService = $container->get(Meta::class);

        $manager = new FeatureManager($em, $metaService, $refHelper, $container);

        $configuration = $container->get('Config');
        if (isset($configuration['feature_manager'])) {
            $manager->setOptions($configuration['feature_manager']);
        }

        return $manager;
    }
}

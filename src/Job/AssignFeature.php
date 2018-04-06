<?php

/**
 * @copyright   (c) 2014-18, Vrok
 * @license     MIT License (http://www.opensource.org/licenses/mit-license.php)
 * @author      Jakob Schumann <schumann@vrok.de>
 */

namespace Vrok\Premium\Job;

use Doctrine\DBAL\Connection;
use Vrok\Premium\Exception\InvalidArgumentException;
use Vrok\Premium\Service\FeatureManager;
use SlmQueue\Job\AbstractJob;
use Zend\EventManager\EventManagerAwareTrait;
use Zend\EventManager\EventManagerInterface;

/**
 * Executed when the admin assignes a single feature to an owner.
 */
class AssignFeature extends AbstractJob
{
    use EventManagerAwareTrait;

    const EVENT_FEATURE_ASSIGNED = 'featureAssigned';

    protected $eventIdentifier = 'Vrok\Premium';

    /**
     * @var FeatureManager
     */
    protected $featureManager = null;

    /**
     * Class constructor - stores the dependencies.
     *
     * @param FeatureManager $fm
     * @param EventManagerInterface $em
     */
    public function __construct(FeatureManager $fm, EventManagerInterface $em)
    {
        $this->featureManager = $fm;
        $this->setEventManager($em);
    }

    /**
     * {@inheritdoc}
     *
     * @throws InvalidArgumentException when the job payload is incomplete
     * @triggers EVENT_FEATURE_ASSIGNED
     */
    public function execute()
    {
        $payload = $this->getContent();
        if (empty($payload['feature']) || ! is_string($payload['feature'])) {
            throw new InvalidArgumentException('Feature name missing or invalid!');
        }
        if (empty($payload['owner']) || ! is_array($payload['owner'])) {
            throw new InvalidArgumentException('Owner reference missing or invalid!');
        }
        if (empty($payload['source']) || ! is_array($payload['source'])) {
            throw new InvalidArgumentException('Source reference missing or invalid!');
        }
        if (! isset($payload['params']) || ! is_array($payload['params'])) {
            throw new InvalidArgumentException('Feature params missing or invalid!');
        }

        $rh = $this->featureManager->getReferenceHelper();
        $owner = $rh->getObject($payload['owner']);
        $source = $rh->getObject($payload['source']);

        $em = $this->featureManager->getEntityManager();
        $oldTI = $em->getConnection()->getTransactionIsolation();
        $em->getConnection()->setTransactionIsolation(Connection::TRANSACTION_SERIALIZABLE);
        $em->getConnection()->beginTransaction(); // suspend auto-commit

        try {
            $assignment = $this->featureManager->assignFeature(
                $payload['feature'],
                $owner,
                $payload['params'],
                $source
            );
            $em->flush();

            $this->featureManager->updateFeatureOwner($payload['feature'], $owner);

            $this->events->trigger(
                self::EVENT_FEATURE_ASSIGNED,
                $assignment
            );

            $em->flush();
            $em->getConnection()->commit();
        } catch (\Throwable $e) {
            $em->getConnection()->rollBack();
            throw $e;
        }

        $em->getConnection()->setTransactionIsolation($oldTI);
    }
}

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
 * Executed when the admin removes a single feature of an owner.
 */
class RemoveFeature extends AbstractJob
{
    use EventManagerAwareTrait;

    const EVENT_FEATURE_REMOVED = 'featureRemoved';

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
     * @triggers EVENT_FEATURE_REMOVED
     */
    public function execute()
    {
        $payload = $this->getContent();
        if (empty($payload['featureId']) || ! is_int($payload['featureId'])) {
            throw new InvalidArgumentException(
                'Feature assignment ID missing or invalid!'
            );
        }

        if (empty($payload['userId']) || ! is_int($payload['userId'])) {
            throw new InvalidArgumentException('Admin ID missing or invalid!');
        }

        $assignment = $this->featureManager->getAssignmentRepository()
                ->find($payload['featureId']);
        /* @var $assignment Entity\FeatureAssignment */
        if (! $assignment) {
            // silently fail, maybe there were two jobs to remove the feature
            return;
        }

        $owner = $this->featureManager->getReferenceHelper()
                ->getReferencedObject($assignment, 'owner');

        $feature  = $assignment->getFeature();
        $params   = $assignment->getParams();
        $source   = $assignment->getReference('source');
        $userId   = $payload['userId'];

        $em = $this->featureManager->getEntityManager();
        $oldTI = $em->getConnection()->getTransactionIsolation();
        $em->getConnection()->setTransactionIsolation(Connection::TRANSACTION_SERIALIZABLE);
        $em->getConnection()->beginTransaction(); // suspend auto-commit

        try {
            $em->remove($assignment);
            $em->flush();

            $this->featureManager->updateFeatureOwner($feature, $owner);

            $this->events->trigger(
                self::EVENT_FEATURE_REMOVED,
                $owner,
                compact('feature', 'params', 'source', 'userId')
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

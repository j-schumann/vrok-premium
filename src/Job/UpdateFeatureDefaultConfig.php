<?php

/**
 * @copyright   (c) 2014-18, Vrok
 * @license     MIT License (http://www.opensource.org/licenses/mit-license.php)
 * @author      Jakob Schumann <schumann@vrok.de>
 */

namespace Vrok\Premium\Job;

use Doctrine\DBAL\Connection;
use Vrok\Premium\Exception\InvalidArgumentException;
use Vrok\Premium\Exception\RuntimeException;
use Vrok\Premium\Service\FeatureManager;
use SlmQueue\Job\AbstractJob;
use Zend\EventManager\EventManagerAwareTrait;
use Zend\EventManager\EventManagerInterface;

/**
 * Executed when the admin changes the default configuration for a feature.
 */
class UpdateFeatureDefaultConfig extends AbstractJob
{
    use EventManagerAwareTrait;

    const EVENT_FEATURE_DEFAULTS_UPDATED = 'featureDefaultConfigUpdated';

    protected $eventIdentifier = 'Vrok\Premium';

    /**
     * @var FeatureManager
     */
    protected $featureManager = null;

    /**
     * @var string
     */
    protected $feature = null;

    /**
     * @var array
     */
    protected $oldConfig = [];

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
     * Checks all potential owners if the new defaults are relevant for them,
     * if yes triggers an update.
     *
     * Does not use a DB transaction for all updates as this could potentially
     * affect thousands of owners which could have potentially multiple records
     * each which are affected, so this job would run quite long with the
     * blocking transaction. Instead each update has its own transaction.
     * The feature strategies should implement updateOwner() in a way it can be
     * repeatedly executed, so this job could simply be restarted after errors.
     *
     * @throws InvalidArgumentException when the job payload is incomplete
     * @throws RuntimeException when the feature is unknown
     * @triggers EVENT_FEATURE_DEFAULTS_UPDATED
     */
    public function execute()
    {
        $payload = $this->getContent();
        if (empty($payload['feature']) || ! is_string($payload['feature'])) {
            throw new InvalidArgumentException('Feature name missing or invalid!');
        }
        $this->feature = $payload['feature'];

        if (empty($payload['oldConfig']) || ! is_array($payload['oldConfig'])) {
            throw new InvalidArgumentException('Old feature config missing or invalid!');
        }
        $this->oldConfig = $payload['oldConfig'];

        if (empty($payload['userId']) || ! is_int($payload['userId'])) {
            throw new InvalidArgumentException('Admin ID missing or invalid!');
        }

        $features = $this->featureManager->getFeatureConfig();
        if (! isset($features[$this->feature])) {
            throw new RuntimeException("Unknown feature {$this->feature}!");
        }

        foreach ($features[$this->feature]['candidates'] as $candidate) {
            $this->updateCandidate($candidate);
        }

        $this->events->trigger(
            self::EVENT_FEATURE_DEFAULTS_UPDATED,
            $this->featureManager,
            ['feature' => $this->feature]
        );
    }

    /**
     * Find all potential owners for the given candidate class and update
     * them if necessary.
     *
     * @param string $candidate
     */
    protected function updateCandidate(string $candidate)
    {
        $em = $this->featureManager->getEntityManager();
        $repo = $em->getRepository($candidate);
        $potentialOwners = $repo->findAll();

        foreach ($potentialOwners as $owner) {
            $this->updateOwner($owner);
        }
    }

    /**
     * Update a single potential owner if necessary.
     *
     * @param object $owner
     */
    protected function updateOwner(/*object*/ $owner)
    {
        $assignment = $this->featureManager->getAssignmentByOwner($owner, $this->feature);
        $strategy = $this->featureManager->getFeatureStrategy($this->feature);
        $default = $this->featureManager->getDefaultConfig($this->feature);
        $defaultRating = $strategy->calculateRating($default);
        $oldRating = $strategy->calculateRating($this->oldConfig);

        // the default is inactive now
        if (! $default['active']) {
            // only a parameter changed, still inactive by default -> nothing to do
            if (! $this->oldConfig['active']) {
                return;
            }

            // default changed to inactive & the owner has no assignment ->
            // set to inactive for him
            if (! $assignment) {
                $this->doUpdate($owner);
                return;
            }

            // feature is now inactive by default but the user has an assignment
            // with a higher rating already -> nothing to do
            if ($oldRating <= $assignment->getRating()) {
                return;
            }

            // status changed to inactive & old defaults were used ->
            // change to his own (lower) assignment
            $this->doUpdate($owner);
            return;
        }

        // it was either inactive before or a value changed -> and the owner
        // has no custom assignment -> update
        if (! $assignment) {
            $this->doUpdate($owner);
            return;
        }

        // it was active before and the old rating was higher than his assignment
        // -> either the new rating is still above is custom rating or the
        // custom assignment is active now -> update
        if ($this->oldConfig['active'] && $assignment->getRating() < $oldRating) {
            $this->doUpdate($owner);
            return;
        }

        // the user already has an assigment higher than the default -> nothing to do
        if ($assignment->getRating() >= $defaultRating) {
            return;
        }

        // the default is active & rated higher than his assignment -> update
        $this->doUpdate($owner);
    }

    /**
     * Encapsulate a single owner update in a transaction for safety.
     *
     * @param object $owner
     * @throws \Throwable
     */
    public function doUpdate(/*object*/ $owner)
    {
        $em = $this->featureManager->getEntityManager();
        $oldTI = $em->getConnection()->getTransactionIsolation();
        $em->getConnection()->setTransactionIsolation(Connection::TRANSACTION_SERIALIZABLE);
        $em->getConnection()->beginTransaction(); // suspend auto-commit

        try {
            $this->featureManager->updateFeatureOwner($this->feature, $owner);
            $em->flush();
            $em->getConnection()->commit();
        } catch (\Throwable $e) {
            $em->getConnection()->rollBack();
            throw $e;
        }

        $em->getConnection()->setTransactionIsolation($oldTI);
    }
}

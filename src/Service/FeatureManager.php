<?php

/**
 * @copyright   (c) 2014-18, Vrok
 * @license     MIT License (http://www.opensource.org/licenses/mit-license.php)
 * @author      Jakob Schumann <schumann@vrok.de>
 */

namespace Vrok\Premium\Service;

use Doctrine\ORM\EntityManagerInterface;
use Vrok\Doctrine\EntityRepository;
use Vrok\Premium\Entity\AssignmentFilter;
use Vrok\Premium\Entity\FeatureAssignment;
use Vrok\Premium\Exception\DomainException;
use Vrok\Premium\Exception\InvalidArgumentException;
use Vrok\Premium\Feature\FeatureInterface;
use Vrok\References\Service\ReferenceHelper;
use Vrok\Service\Meta;
use Zend\ServiceManager\ServiceLocatorInterface;

/**
 * Handle feature assignment creation & loading, parameter checks etc.
 */
class FeatureManager
{
    /**
     * @var EntityManagerInterface
     */
    protected $entityManager = null;

    /**
     * @var Meta
     */
    protected $metaService = null;

    /**
     * @var ReferenceHelper
     */
    protected $refHelper = null;

    /**
     * Hash of registered features with their service names and candidate classes.
     *
     * @var array
     */
    protected $featureConfig = [];

    /**
     * @var ServiceLocatorInterface
     */
    protected $serviceManager = null;

    /**
     * Cache of loaded feature strategies.
     *
     * @var array
     */
    protected $features = [];

    /**
     * Class constructor - stores the dependencies.
     *
     * @param EntityManagerInterface $em
     * @param Meta $meta
     * @param ReferenceHelper $refHelper
     * @param ServiceLocatorInterface $sm
     */
    public function __construct(
        EntityManagerInterface $em,
        Meta $meta,
        ReferenceHelper $refHelper,
        ServiceLocatorInterface $sm
    ) {
        $this->entityManager = $em;
        $this->metaService = $meta;
        $this->refHelper = $refHelper;
        $this->serviceManager = $sm;
    }

    /**
     * Returns true if the given owner entity is a valid candidate for the given
     * feature, else false.
     *
     * @param string $feature
     * @param object $owner
     * @return bool
     * @throws DomainException when the feature is unknown
     */
    public function isValidCandidate(string $feature, object $owner) : bool
    {
        if (! isset($this->featureConfig[$feature])) {
            throw new DomainException("Unknown feature $feature!");
        }

        $ownerClass = get_class($owner);
        $classes = array_merge([$ownerClass], class_parents($ownerClass));
        $matches = array_intersect($classes, $this->featureConfig[$feature]['candidates']);
        return count($matches) > 0;
    }

    /**
     * Creates a new FeatureAssignment with the given data, returns the persisted
     * entity. Does not flush the entityManager and does not update the owner!
     *
     * @param string $feature
     * @param object $owner
     * @param array $config
     * @param object $source
     * @return FeatureAssignment
     * @throws DomainException when the owner is no valid candidate
     */
    public function assignFeature(
        string $feature,
        object $owner,
        array $config,
        object $source
    ) : FeatureAssignment {
        if (! $this->isValidCandidate($feature, $owner)) {
            throw new DomainException(get_class($owner).
                ' is no valid candidate class for feature '.$feature);
        }

        $assignment = new FeatureAssignment();
        $assignment->setFeature($feature);
        $assignment->setParams($config);

        $strategy = $this->getFeatureStrategy($feature);
        $assignment->setRating($strategy->calculateRating($config));

        $this->refHelper->setReferencedObject($assignment, 'owner', $owner);
        $this->refHelper->setReferencedObject($assignment, 'source', $source);

        $this->entityManager->persist($assignment);
        return $assignment;
    }

    /**
     * Update the owner and its depending records according to the current
     * config of the given feature.
     *
     * @param string $feature
     * @param object $owner
     */
    public function updateFeatureOwner(string $feature, object $owner)
    {
        $params = $this->getParameters($feature, $owner);
        $strategy = $this->getFeatureStrategy($feature);
        $strategy->updateOwner($owner, $params);
    }

    /**
     * Retrieve the feature implementation that holds the basic configuration,
     * parameter definition and can update the owner (and his related entities)
     * if necessary.
     *
     * @param string $name
     * @return FeatureInterface
     * @throws DomainException when the feature or service is unknown or invalid
     */
    public function getFeatureStrategy(string $name) : FeatureInterface
    {
        if (isset($this->features[$name])) {
            return $this->features[$name];
        }

        if (! isset($this->featureConfig[$name])) {
            throw new DomainException("Unknown feature $name!");
        }

        if (! $this->serviceManager->has($this->featureConfig[$name]['service'])) {
            throw new DomainException('Feature service '.
                    $this->featureConfig[$name]['service'].' is unknown!');
        }

        $strategy = $this->serviceManager->get($this->featureConfig[$name]['service']);
        if (! $strategy || ! $strategy instanceof FeatureInterface) {
            throw new DomainException('Feature service '.
                $this->featureConfig[$name]['service']
                .' could not be fetched or does not implement the FeatureInterface!');
        }

        $this->features[$name] = $strategy;
        return $strategy;
    }

    /**
     * Returns the default config for this feature. Stored in the system meta.
     * Used for all owners that don't have that feature active (by admin
     * assignment / subscription).
     *
     * @return array
     * @throwns DomainException when the feature is unknown
     */
    public function getDefaultConfig(string $featureName) : array
    {
        if (! isset($this->featureConfig[$featureName])) {
            throw new DomainException("Unknown feature $featureName!");
        }

        $config = $this->metaService->getValue($this->getMetaKey($featureName));
        if (! $config) {
            $strategy = $this->getFeatureStrategy($featureName);
            $config = $strategy->getDefaultConfig();

            // a feature is never activated by just being added to the config
            $config['active'] = false;
        }

        return $config;
    }

    /**
     * Sets the config to use for all owners that don't have this feature
     * assigned by an admin or subscription.
     * Attention: Does not flush the entityManager!
     *
     * @param string $featureName
     * @param array $config
     * @throws DomainException when the feature is unknown
     * @throws InvalidArgumentException when the "active" key is missing
     */
    public function setDefaultConfig(string $featureName, array $config)
    {
        if (! isset($this->featureConfig[$featureName])) {
            throw new DomainException("Unknown feature $featureName!");
        }

        if (! isset($config['active']) || ! is_bool($config['active'])) {
            throw new InvalidArgumentException(
                "Deafult feature config must contain the 'active' key with bool value!"
            );
        }
        $this->metaService->setValue($this->getMetaKey($featureName), $config);
    }

    /**
     * Returns the value of the parameter given by name for the given feature
     * (and owner).
     *
     * @param string feature
     * @param string $param
     * @param object $owner
     * @return mixed
     * @throws DomainException when the parameter is not defined
     */
    public function getParameter(
        string $feature,
        string $param,
        object $owner = null
    ) {
        $params = $this->getParameters($feature, $owner);
        if (! isset($params[$param])) {
            throw new DomainException(
                "Feature $feature has no parameter named $param!"
            );
        }

        return $params[$param];
    }

    /**
     * Returns the current parameters of the given feature.
     * Returns the default feature config if no owner is given or his individual
     * ranking is lower than the default ranking.
     *
     * @param string $feature
     * @param object $owner
     * @return array
     */
    public function getParameters(string $feature, object $owner = null) : array
    {
        $default = $this->getDefaultConfig($feature);

        // if no owner is given we asked for the default setting
        if (! $owner) {
            return $default;
        }

        $assignment = $this->getAssignmentByOwner($owner, $feature);

        // no assignment -> use default value for this owner
        if (! $assignment) {
            return $default;
        }

        $assigned = $assignment->getParams();
        // when it's assigned it's automatically active
        $assigned['active'] = true;

        // he has the feature assigned and it's not active by default
        // -> use the assigned value
        if (! $default['active']) {
            return $assigned;
        }

        $strategy = $this->getFeatureStrategy($feature);
        $defaultRating = $strategy->calculateRating($default);

        // the default rating is higher than his assigned rating, e.g. if the
        // default was adjusted after the feature was assigned -> use default
        if ($defaultRating > $assignment->getRating()) {
            return $default;
        }

        return $assigned;
    }

    /**
     * Returns true if the feature has any configurable parameters, else false.
     * Does not include 'active' option for the default configuration.
     *
     * @param string $feature
     * @return bool
     */
    public function featureHasParameters($feature)
    {
        // account for 'active' here
        return count($this->getDefaultConfig($feature)) > 1;
    }

    /**
     * Returns true if the feature has a parameter of the given name.
     *
     * @param string $feature
     * @param string $param
     *
     * @return bool
     */
    public function featureHasParameter(string $feature, string $param) : bool
    {
        $config = $this->getDefaultConfig($feature);
        return isset($config[$param]);
    }

    /**
     * Returns true if the feature is active for the given owner (or active for
     * everyone if no owner was given), else false.
     *
     * @param string $feature
     * @param object $owner
     * @return bool
     */
    public function featureIsActive(string $feature, object $owner = null) : bool
    {
        // If an owner is given and he has the feature assigned it is active for him.
        if ($owner && $this->getAssignmentByOwner($owner, $feature)) {
            return true;
        }

        // if no owner is given we asked for the default setting (e.g. guest users),
        // if we are here and an owner is given he doesn't have the feature assigned
        // -> use the default config too.
        $config = $this->getDefaultConfig($feature);
        return (bool)$config['active'];
    }

    /**
     * Retrieve the assignment for the given owner and the given feature with
     * the highest ranking. Returns null if none found.
     *
     * @param object $owner
     * @param string $feature
     * @return array
     */
    public function getAssignmentByOwner(object $owner, string $feature) : ?FeatureAssignment
    {
        $filterValues = $this->refHelper->getEntityFilterData(
            FeatureAssignment::class,
            'owner',
            $owner
        );

        $filter = $this->getAssignmentFilter();
        $filter->byReference($filterValues)
               ->byFeature($feature)
               ->orderByField('rating', 'DESC')
               ->limit(1);

        return $filter->getQuery()->getOneOrNullResult();
    }

    /**
     * Retrieve all feature assignments for the givcen owner.
     * If a feature name is given only assignments for this feature are returned.
     *
     * @param object $owner
     * @param string $feature
     * @return array
     */
    public function getAssignmentsByOwner(object $owner, string $feature = null) : array
    {
        $filterValues = $this->refHelper->getEntityFilterData(
            FeatureAssignment::class,
            'owner',
            $owner
        );

        $filter = $this->getAssignmentFilter();
        $filter->byReference($filterValues);
        if ($feature) {
            $filter->byFeature($feature);
        }

        return $filter->getResult();
    }

    /**
     * Retrieve a new filter instance.
     *
     * @param string $alias
     * @return AssignmentFilter
     */
    public function getAssignmentFilter(string $alias = 'f') : AssignmentFilter
    {
        $qb = $this->getAssignmentRepository()->createQueryBuilder($alias);
        return new AssignmentFilter($qb);
    }

    /**
     * Retrieve the feature assignment repository instance.
     *
     * @return EntityRepository
     */
    public function getAssignmentRepository() : EntityRepository
    {
        return $this->entityManager->getRepository(FeatureAssignment::class);
    }

    /**
     * Registers the feature given by name with the given service and its
     * candidate classes (= allowed owners).
     *
     * @param string $feature
     * @param string $service
     * @param array $classes
     */
    public function addFeature(string $feature, string $service, array $classes)
    {
        $this->featureConfig[$feature] = [
            'service'    => $service,
            'candidates' => $classes,
        ];
    }

    /**
     * Registers multiple features at once.
     *
     * @param array $features [feature => [service => class, candidates => [class1, class2]]]
     * @throws InvalidArgumentException when the given config is not valid
     */
    public function addFeatures(array $features)
    {
        foreach ($features as $name => $config) {
            if (! is_string($name)) {
                throw new InvalidArgumentException(
                    "Feature array must be indexed with feature names!"
                );
            }
            if (! is_array($config)
                || ! isset($config['service'])
                || ! isset($config['candidates'])
            ) {
                throw new InvalidArgumentException(
                    "Config must be an array with 'service' and 'candidates'!"
                );
            }

            $this->addFeature($name, $config['service'], $config['candidates']);
        }
    }

    /**
     * Sets all config options at once.
     *
     * @param array $options
     */
    public function setOptions(array $options)
    {
        $this->featureConfig = [];
        if (isset($options['features'])) {
            $this->addFeatures($options['features']);
        }
    }

    /**
     * Retrieve the complete feature configuration.
     *
     * @return array
     */
    public function getFeatureConfig() : array
    {
        return $this->featureConfig;
    }

    /**
     * Returns true if a feature with the given name is configured, else false.
     *
     * @param string $feature
     * @return bool
     */
    public function featureExists(string $feature) : bool
    {
        return isset($this->featureConfig[$feature]);
    }

    /**
     * Retrieve the used EntityManager.
     *
     * @return EntityManagerInterface
     */
    public function getEntityManager() : EntityManagerInterface
    {
        return $this->entityManager;
    }

    /**
     * Retrieve the used ReferenceHelper.
     *
     * @return ReferenceHelper
     */
    public function getReferenceHelper() : ReferenceHelper
    {
        return $this->refHelper;
    }

    /**
     * Returns the meta key to use for storing the features default config
     * in the system meta.
     *
     * @param string $featureName
     * @return string
     */
    protected function getMetaKey(string $featureName) : string
    {
        return 'featureDefaults'.ucfirst($featureName);
    }
}

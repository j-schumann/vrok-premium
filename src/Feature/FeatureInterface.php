<?php

/**
 * @copyright   (c) 2018, Vrok
 * @license     MIT License (http://www.opensource.org/licenses/mit-license.php)
 * @author      Jakob Schumann <schumann@vrok.de>
 */

namespace Vrok\Premium\Feature;

/**
 * Interface for all features to provide a constant API for all owner types.
 */
interface FeatureInterface
{
    /**
     * Returns the default config for this feature. Stored in the system meta.
     * Used for all owners that don't have that feature active (by admin
     * assignment / subscription).
     *
     * @return array
     */
    public function getDefaultConfig() : array;

    /**
     * Returns a Zend\Form element definition according to the parameters definition.
     *
     * @param string $name
     * @return array
     * @throws DomainException  when the parameter is not defined
     */
    public function getParameterFormElement(string $name) : array;

    /**
     * Returns a Zend\InputFilter input definition corresponding to the given parameter.
     *
     * @param string $name
     * @return array
     * @throws DomainException  when the parameter is not defined
     */
    public function getParameterInputFilter(string $name) : array;

    /**
     * Calculates the features rating for the given parameters.
     * Formula can differ for each feature and may depend on the value of a single
     * parameter or invert it for reversed sorting:
     * lower parameter, e.g. limit -> higher rating
     * Used to compare different configurations.
     *
     * @param array $params
     * @return int
     */
    public function calculateRating(array $params) : int;

    /**
     * Update the owner properties (or depending records) according to the given
     * feature config (either from the highest ranking assigned or the default
     * config). The $params array contains the additional "active" key.
     *
     * This should not unnecessarily flush the entityManager or use transactions,
     * this is done by the featureManager / the queue jobs, combining multiple
     * updates for subscription changes or including changes from triggered
     * events in the transaction.
     *
     * @param object $owner
     * @param array $params
     */
    public function updateOwner(object $owner, array $params);
}

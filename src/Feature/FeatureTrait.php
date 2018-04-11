<?php

/**
 * @copyright   (c) 2018, Vrok
 * @license     MIT License (http://www.opensource.org/licenses/mit-license.php)
 * @author      Jakob Schumann <schumann@vrok.de>
 */

namespace Vrok\Premium\Feature;

/**
 * Implements basic functionality for features.
 */
trait FeatureTrait
{
    public function getDefaultConfig() : array
    {
        return $this->defaultConfig;
    }

    /**
     * Simple feature without parameters does not provide form elements.
     *
     * @param string $name
     * @return array
     */
    public function getParameterFormElement(string $name): array
    {
        return [];
    }

    /**
     * Simple feature without parameters does not provide input filters.
     *
     * @param string $name
     * @return array
     */
    public function getParameterInputFilter(string $name): array
    {
        return [];
    }

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
    public function calculateRating(array $params) : int
    {
        // features that are simply active or they aren't don't need a rating
        return 0;
    }

    public function updateOwner(object $owner, array $params)
    {
        // simple features don't need to update any entity, e.g. if they only
        // give access to functionality
    }
}

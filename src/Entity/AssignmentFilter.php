<?php

/**
 * @copyright   (c) 2014-16, Vrok
 * @license     MIT License (http://www.opensource.org/licenses/mit-license.php)
 * @author      Jakob Schumann <schumann@vrok.de>
 */

namespace Vrok\Premium\Entity;

use Vrok\Doctrine\AbstractFilter;
use Vrok\Doctrine\Traits\ReferenceFilterTrait;

/**
 * Implements functions to query for feature assignments by often used filters
 * to avoid code duplication.
 */
class AssignmentFilter extends AbstractFilter
{
    use ReferenceFilterTrait;

    /**
     * Retrieve only meta entries for the given meta name.
     *
     * @param string $name
     *
     * @return self
     */
    public function byFeature($name)
    {
        $this->qb->andWhere($this->alias.'.feature = :feature')
           ->setParameter('feature', $name);

        return $this;
    }
}

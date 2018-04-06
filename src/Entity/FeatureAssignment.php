<?php

/**
 * @copyright   (c) 2017, Vrok
 * @license     MIT License (http://www.opensource.org/licenses/mit-license.php)
 * @author      Jakob Schumann <schumann@vrok.de>
 */

namespace Vrok\Premium\Entity;

use Doctrine\ORM\Mapping as ORM;
use Vrok\Doctrine\Traits\AutoincrementId;
use Vrok\Doctrine\Traits\CreationDate;
use Vrok\References\Entity\HasReferenceInterface;
use Vrok\References\Entity\HasReferenceTrait;

/**
 * Represents a feature assigned to an entity. The feature has its parameters
 * and calculated ranking, the owner is referenced as well as the source (who
 * assigned this feature, an admin manually or a subscription automatically).
 *
 * @ORM\Entity(repositoryClass="Vrok\Doctrine\EntityRepository")
 * @ORM\Table(name="premium_features", indexes={
 *     @ORM\Index(name="pf_feature_idx", columns={"feature"}),
 *     @ORM\Index(name="pf_owner_idx", columns={"ownerClass", "ownerIdentifiers"}),
 *     @ORM\Index(name="pf_source_idx", columns={"sourceClass", "sourceIdentifiers"})
 * })
 */
class FeatureAssignment implements HasReferenceInterface
{
    use AutoincrementId;
    use CreationDate;
    use HasReferenceTrait;

    protected $references = [
        'owner'  => true,
        'source' => true,
    ];

    /**
     * @var string
     * @ORM\Column(type="string", length=255, nullable=false)
     */
    protected $ownerClass;

    /**
     * @var string
     * @ORM\Column(type="string", length=255, nullable=false)
     */
    protected $ownerIdentifiers;

    /**
     * @var string
     * @ORM\Column(type="string", length=255, nullable=false)
     */
    protected $sourceClass;

    /**
     * @var string
     * @ORM\Column(type="string", length=255, nullable=false)
     */
    protected $sourceIdentifiers;

// <editor-fold defaultstate="collapsed" desc="feature">
    /**
     * @var string
     * @ORM\Column(type="string", length=70)
     */
    protected $feature;

    /**
     * Returns the feature name.
     *
     * @return string
     */
    public function getFeature() : string
    {
        return $this->feature;
    }

    /**
     * Sets the users feature.
     *
     * @param string $feature
     */
    public function setFeature(string $feature)
    {
        $this->feature = $feature;
    }
// </editor-fold>
// <editor-fold defaultstate="collapsed" desc="params">
    /**
     * Holds the parameters used for the feature
     *
     * @var mixed
     * @ORM\Column(type="json_array", length=65535, nullable=true)
     */
    protected $params = [];

    /**
     * Returns the feature params
     *
     * @return array
     */
    public function getParams() : array
    {
        return (array)$this->params;
    }

    /**
     * Sets the feature parameters.
     *
     * @param array $params
     */
    public function setParams(array $params)
    {
        $this->params = $params;
    }
// </editor-fold>
// <editor-fold defaultstate="collapsed" desc="rating">
    /**
     * @var int
     * @ORM\Column(type="integer", options={"default" = 0})
     */
    protected $rating = 0;

    /**
     * Returns the rating corresponding to the parameters.
     *
     * @return int
     */
    public function getRating() : int
    {
        return $this->rating;
    }

    /**
     * Sets the rating corresponding to the parameters.
     *
     * @param int $value
     */
    public function setRating(int $value)
    {
        $this->rating = $value;
    }
// </editor-fold>
}

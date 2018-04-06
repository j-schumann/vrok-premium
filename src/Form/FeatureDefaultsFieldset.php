<?php

/**
 * @copyright   (c) 2014-18, Vrok
 * @license     MIT License (http://www.opensource.org/licenses/mit-license.php)
 * @author      Jakob Schumann <schumann@vrok.de>
 */

namespace Vrok\Premium\Form;

use Vrok\Form\Fieldset;
use Zend\InputFilter\InputFilterProviderInterface;

/**
 * Form to change feature defaults
 */
class FeatureDefaultsFieldset extends Fieldset implements InputFilterProviderInterface
{
    /**
     * {@inheritDoc}
     */
    public function init()
    {
        $this->setName('defaults');

        $this->add([
            'type'    => 'Zend\Form\Element\Checkbox',
            'name'    => 'active',
            'options' => [
                'label'           => 'form.feature.active.label',
                'unchecked_value' => 0,
                'checked_value'   => 1,
            ],
        ]);
    }

    /**
     * {@inheritDoc}
     */
    public function getInputFilterSpecification()
    {
        $spec = [
            'active' => [
                'name'        => 'active',
                'required'    => false,
                'allow_empty' => true,
                'filters' => [
                     ['name' => 'Zend\Filter\Boolean']
                ]
            ],
        ];

        return $spec;
    }
}

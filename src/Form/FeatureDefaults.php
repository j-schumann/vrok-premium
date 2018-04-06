<?php

/**
 * @copyright   (c) 2014-18, Vrok
 * @license     MIT License (http://www.opensource.org/licenses/mit-license.php)
 * @author      Jakob Schumann <schumann@vrok.de>
 */

namespace Vrok\Premium\Form;

use Vrok\Form\Form;

/**
 * Allows admins to change the default configuration of a feature.
 */
class FeatureDefaults extends Form
{
    /**
     * {@inheritDoc}
     */
    public function init()
    {
        // csrf first so error message appears above the form
        $this->addCsrfElement('csrfFeatureDefaults');

        $this->add([
            'type'    => 'Vrok\Premium\Form\FeatureDefaultsFieldset',
            'options' => [
                'use_as_base_fieldset' => true,
            ],
        ]);

        $this->add([
            'name'       => 'submit',
            'attributes' => [
                'type'  => 'submit',
                'value' => 'form.submit',
            ],
        ]);
    }
}

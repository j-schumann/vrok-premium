<?php

namespace VrokPremiumTest\Feature;

use Vrok\Premium\Feature\FeatureInterface;
use Vrok\Premium\Feature\FeatureTrait;

class TestFeature implements FeatureInterface
{
    use FeatureTrait;

    protected $defaultConfig = [
        'param' => 1,
    ];

    public function calculateRating(array $params): int
    {
        return $params['param'];
    }

    public function updateOwner(object $owner, array $params)
    {
        // this allows us to simulate an error when assigning a feature
        if ($params['param'] == 66) {
            throw new \RuntimeException('Evil things happen!');
        }

        // this allows us to simulate an error when removing an assignment
        if ($owner->getDisplayName() == 'throw') {
            throw new \RuntimeException('As you wish!');
        }

        if (!$params['active']) {
            $owner->setDisplayName('inactive');
            return;
        }

        // change the display name so we have a value to check if the update
        // was triggered
        $owner->setDisplayName($params['param']);
    }
}

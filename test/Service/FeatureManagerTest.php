<?php

/**
 * @copyright   (c) 2018, Vrok
 * @license     MIT License (http://www.opensource.org/licenses/mit-license.php)
 * @author      Jakob Schumann <schumann@vrok.de>
 */

namespace VrokPremiumTest\Service;

use PHPUnit\Framework\TestCase;
use Vrok\Entity\User;
use Vrok\Premium\Exception\DomainException;
use Vrok\Premium\Exception\InvalidArgumentException;
use Vrok\Premium\Entity;
use Vrok\Premium\Service\FeatureManager;
use VrokPremiumTest\Bootstrap;
use VrokPremiumTest\Feature\TestFeature;

class FeatureManagerTest extends TestCase
{
    /**
     * @var FeatureManager
     */
    protected $featureManager = null;

    /**
     * @var User
     */
    protected $user = null;

    protected function setUp()
    {
        $serviceManager  = Bootstrap::getServiceManager();
        $this->featureManager = $serviceManager->get(FeatureManager::class);
        $em = $this->featureManager->getEntityManager();

        $this->user = $em->getRepository(User::class)->findOneBy([
            'username' => 'test',
        ]);
        if (! $this->user) {
            $this->user = new User();
            $this->user->setUsername('test');
            $this->user->setEmail('test@domain.tld');
            $this->user->setPassword('test');
            $em->persist($this->user);
            $em->flush();
        }

        $q = $em->createQuery('DELETE FROM Vrok\Premium\Entity\FeatureAssignment fa');
        $q->execute();

        $ms = $serviceManager->get('Vrok\Service\Meta');
        /* @var $ms \Vrok\Service\Meta */
        $ms->setValue('featureDefaultsTest', null);
    }

    public function testGetFeatureConfig()
    {
        $config = $this->featureManager->getFeatureConfig();
        $this->assertEquals([
            'test' => [
                'service'    => 'VrokPremiumTest\Feature\TestFeature',
                'candidates' => [
                    'Vrok\Entity\User',
                ],
            ],
        ], $config);
    }

    public function testSetOptions()
    {
        $options = [
            'features' => [
                'userCommentCount' => [
                    'service'    => 'UserCommentCountFeature',
                    'candidates' => [
                        'Vrok\Entity\User',
                    ],
                ]
            ],
        ];
        $old = $this->featureManager->getFeatureConfig();
        $this->featureManager->setOptions($options);
        $this->assertEquals(
            $options['features'],
            $this->featureManager->getFeatureConfig()
        );
        $this->featureManager->setOptions(['features' => $old]);
    }

    public function testAddFeature()
    {
        $this->featureManager->addFeature('123', 'Service123', ['Candidate']);
        $config = $this->featureManager->getFeatureConfig();
        $this->assertArrayHasKey('123', $config);
        $this->assertEquals(['service' => 'Service123','candidates' => ['Candidate']], $config['123']);
    }

    public function testAddFeaturesChecksName()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Feature array must be indexed with feature names!');
        $this->featureManager->addFeatures([
            123 => ['service' => 'Service123', 'candidates' => ['Candidate']]
        ]);
    }

    public function testAddFeaturesChecksService()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Config must be an array with 'service' and 'candidates'!");
        $this->featureManager->addFeatures([
            '123n' => ['service' => null, 'candidates' => ['Candidate']]
        ]);
    }

    public function testAddFeaturesChecksCandidates()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Config must be an array with 'service' and 'candidates'!");
        $this->featureManager->addFeatures([
            '123n' => ['service' => 'null', 'candidates' => null]
        ]);
    }

    public function testAddFeatures()
    {
        $this->featureManager->addFeatures([
            'abc' => [
                'service'    => 'def',
                'candidates' => ['ghi'],
            ],
        ]);
        $config = $this->featureManager->getFeatureConfig();
        $this->assertArrayHasKey('abc', $config);
        $this->assertEquals(['service' => 'def','candidates' => ['ghi']], $config['abc']);
    }

    public function testGetStrategy()
    {
        $strategy = $this->featureManager->getFeatureStrategy('test');
        $this->assertInstanceOf(TestFeature::class, $strategy);
    }

    public function testGetUnknownStrategy()
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Unknown feature');
        $this->featureManager->getFeatureStrategy('unknownFeature');
    }

    public function testGetUnknownStrategyService()
    {
        $this->featureManager->addFeatures([
            'unknown' => [
                'service'    => 'Feature\Unknown',
                'candidates' => [],
            ],
        ]);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Feature service Feature\Unknown is unknown!');
        $this->featureManager->getFeatureStrategy('unknown');
    }

    public function testGetInvalidStrategyService()
    {
        $this->featureManager->addFeatures([
            'invalid' => [
                'service'    => 'Vrok\Service\UserManager',
                'candidates' => [],
            ],
        ]);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage(
            'could not be fetched or does not implement the FeatureInterface!'
        );
        $this->featureManager->getFeatureStrategy('invalid');
    }

    public function testGetAssignmentRepository()
    {
        $this->assertInstanceOf(
            'Vrok\Doctrine\EntityRepository',
            $this->featureManager->getAssignmentRepository()
        );
    }

    public function testGetAssignmentFilter()
    {
        $this->assertInstanceOf(
            Entity\AssignmentFilter::class,
            $this->featureManager->getAssignmentFilter()
        );
    }

    public function testFeatureHasParameters()
    {
        $this->assertTrue($this->featureManager->featureHasParameters('test'));
    }

    public function testFeatureHasParameter()
    {
        $this->assertTrue($this->featureManager->featureHasParameter('test', 'param'));
    }

    public function testFeatureDoesNotHaveParameter()
    {
        $this->assertFalse($this->featureManager->featureHasParameter('test', 'param2'));
    }

    public function testFeatureIsInactiveByDefault()
    {
        $this->assertFalse($this->featureManager->featureIsActive('test'));
    }

    public function testGetDefaultConfig()
    {
        $config = $this->featureManager->getDefaultConfig('test');
        $this->assertEquals(['param' => 1, 'active' => false], $config);
        $this->assertEquals(1, $this->featureManager->getParameter('test', 'param'));
    }

    public function testGetDefaultConfigWithUnknownFeature()
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage("Unknown feature unknownfeature!");
        $this->featureManager->getDefaultConfig('unknownfeature');
    }

    public function testSetDefaultConfig()
    {
        $new = ['param' => 3, 'active' => true];
        $this->featureManager->setDefaultConfig('test', $new);

        $config = $this->featureManager->getDefaultConfig('test');
        $this->assertEquals($new, $config);
    }

    public function testSetDefaultConfigChecksFeature()
    {
        $new = ['param' => 3, 'active' => false];
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage("Unknown feature unknownfeature!");
        $this->featureManager->setDefaultConfig('unknownfeature', $new);
    }

    public function testSetDefaultConfigChecksActiveKey()
    {
        $new = ['param' => 3];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            "Deafult feature config must contain the 'active' key with bool value!"
        );
        $this->featureManager->setDefaultConfig('test', $new);
    }

    public function testFeatureCanBeActivatedWithDefault()
    {
        $new = ['param' => 3, 'active' => true];
        $this->featureManager->setDefaultConfig('test', $new);
        $this->assertTrue($this->featureManager->featureIsActive('test'));
    }

    public function testIsValidCandidate()
    {
        $this->assertTrue($this->featureManager->isValidCandidate('test', $this->user));
    }

    public function testInvalidCandidate()
    {
        $fa = new Entity\FeatureAssignment();
        $this->assertfalse($this->featureManager->isValidCandidate('test', $fa));
    }

    public function testIsValidCandidateWithUnknownFeature()
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Unknown feature');
        $this->featureManager->isValidCandidate('unknownFeature', $this->user);
    }

    public function testAssignFeature()
    {
        $feature = $this->featureManager->assignFeature(
            'test',
            $this->user,
            ['param' => 10],
            $this->user
        );

        $this->assertInstanceOf(Entity\FeatureAssignment::class, $feature);
        $this->assertEquals(['param' => 10], $feature->getParams());
        $this->assertEquals(
            ['class' => User::class, 'identifiers' => ['id' => $this->user->getId()]],
            $feature->getReference('owner')
        );
        $this->assertEquals(
            ['class' => User::class, 'identifiers' => ['id' => $this->user->getId()]],
            $feature->getReference('source')
        );
    }

    public function testAssignFeatureChecksCandidate()
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('is no valid candidate class');
        $this->featureManager->assignFeature(
            'test',
            $this->featureManager,
            ['param' => 10],
            $this->user
        );
    }

    public function testGetAssignmentByOwner()
    {
        $this->featureManager->assignFeature(
            'test',
            $this->user,
            ['param' => 20],
            $this->user
        );
        $this->featureManager->assignFeature(
            'test',
            $this->user,
            ['param' => 21],
            $this->user
        );

        // persist the new features
        $em = $this->featureManager->getEntityManager();
        $em->flush();

        $assigned = $this->featureManager->getAssignmentByOwner($this->user, 'test');
        $this->assertInstanceOf(Entity\FeatureAssignment::class, $assigned);
        $this->assertEquals(['param' => 21], $assigned->getParams());
    }

    public function testGetAssignmentsByOwner()
    {
        $this->featureManager->assignFeature(
            'test',
            $this->user,
            ['param' => 30],
            $this->user
        );
        $feature2 = $this->featureManager->assignFeature(
            'test',
            $this->user,
            ['param' => 31],
            $this->user
        );
        $feature2->setFeature('fake');

        // persist the new features
        $em = $this->featureManager->getEntityManager();
        $em->flush();

        $all = $this->featureManager->getAssignmentsByOwner($this->user);
        $this->assertInternalType('array', $all);
        $this->assertCount(2, $all);
        $this->assertInstanceOf(Entity\FeatureAssignment::class, $all[0]);
        $this->assertInstanceOf(Entity\FeatureAssignment::class, $all[1]);
    }

    public function testGetAssignmentsByOwnerAndFeature()
    {
        $this->featureManager->assignFeature(
            'test',
            $this->user,
            ['param' => 40],
            $this->user
        );
        $feature2 = $this->featureManager->assignFeature(
            'test',
            $this->user,
            ['param' => 41],
            $this->user
        );
        $feature2->setFeature('fake');
        $em = $this->featureManager->getEntityManager();
        $em->flush();

        $test = $this->featureManager->getAssignmentsByOwner($this->user, 'test');
        $this->assertInternalType('array', $test);
        $this->assertCount(1, $test);

        $testFeature = $test[0];
        $this->assertInstanceOf(Entity\FeatureAssignment::class, $testFeature);
        $this->assertEquals(['param' => 40], $testFeature->getParams());
    }

    public function testGetParametersWithoutOwner()
    {
        $this->featureManager->setDefaultConfig('test', ['param' => 50, 'active' => true]);

        $config = $this->featureManager->getParameters('test');
        $this->assertEquals(['param' => 50, 'active' => true], $config);
    }

    public function testGetParametersWithoutAssignment()
    {
        $this->featureManager->setDefaultConfig('test', ['param' => 50, 'active' => true]);
        $config = $this->featureManager->getParameters('test', $this->user);
        $this->assertEquals(['param' => 50, 'active' => true], $config);
    }

    public function testGetParametersWithOwner()
    {
        $this->featureManager->assignFeature(
            'test',
            $this->user,
            ['param' => 60],
            $this->user
        );
        $em = $this->featureManager->getEntityManager();
        $em->flush();

        $config = $this->featureManager->getParameters('test', $this->user);
        $this->assertEquals(['param' => 60, 'active' => true], $config);
    }

    public function testGetParametersChecksDefaultRating()
    {
        $this->featureManager->setDefaultConfig('test', ['param' => 50, 'active' => true]);
        $this->featureManager->assignFeature(
            'test',
            $this->user,
            ['param' => 49],
            $this->user
        );
        $em = $this->featureManager->getEntityManager();
        $em->flush();

        $config = $this->featureManager->getParameters('test', $this->user);
        $this->assertEquals(['param' => 50, 'active' => true], $config);
    }

    public function testGetParametersChecksWithInactiveDefault()
    {
        $this->featureManager->assignFeature(
            'test',
            $this->user,
            ['param' => 49],
            $this->user
        );
        $em = $this->featureManager->getEntityManager();
        $em->flush();
        $this->featureManager->setDefaultConfig('test', ['param' => 50, 'active' => false]);

        $config = $this->featureManager->getParameters('test', $this->user);
        $this->assertEquals(['param' => 49, 'active' => true], $config);
    }

    public function testGetParameterWithoutOwner()
    {
        $this->featureManager->setDefaultConfig('test', ['param' => 50, 'active' => true]);
        $this->assertEquals(50, $this->featureManager->getParameter('test', 'param'));
    }

    public function testGetParameter()
    {
        $this->featureManager->assignFeature(
            'test',
            $this->user,
            ['param' => 70],
            $this->user
        );
        $em = $this->featureManager->getEntityManager();
        $em->flush();

        $p = $this->featureManager->getParameter('test', 'param', $this->user);
        $this->assertEquals(70, $p);
    }

    public function testGetParameterWithUnknownParam()
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Feature test has no parameter named invalid!');
        $this->featureManager->getParameter('test', 'invalid', $this->user);
    }
}

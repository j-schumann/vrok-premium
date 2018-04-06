<?php

/**
 * @copyright   (c) 2018, Vrok
 * @license     MIT License (http://www.opensource.org/licenses/mit-license.php)
 * @author      Jakob Schumann <schumann@vrok.de>
 */

namespace VrokPremiumTest\Job;

use PHPUnit\Framework\TestCase;
use SlmQueue\Job\JobInterface;
use SlmQueue\Job\JobPluginManager;
use Vrok\Entity\User;
use Vrok\Premium\Exception\InvalidArgumentException;
use Vrok\Premium\Job\UpdateFeatureDefaultConfig;
use Vrok\Premium\Service\FeatureManager;
use VrokPremiumTest\Bootstrap;

class UpdateFeatureDefaultConfigTest extends TestCase
{
    /**
     * @var FeatureManager
     */
    protected $featureManager = null;

    /**
     * @var User[]
     */
    protected $users = [];

    /**
     * @return JobInterface
     */
    protected function getJob()
    {
        $serviceManager = Bootstrap::getServiceManager();
        $jm = $serviceManager->get(JobPluginManager::class);
        return $jm->get(UpdateFeatureDefaultConfig::class);
    }

    protected function setUp()
    {
        $serviceManager  = Bootstrap::getServiceManager();
        $this->featureManager = $serviceManager->get(FeatureManager::class);

        $ms = $serviceManager->get('Vrok\Service\Meta');
        /* @var $ms \Vrok\Service\Meta */
        $ms->setValue('featureDefaultsTest', null);

        $em = $serviceManager->get('Doctrine\ORM\EntityManager');
        /* @var $em \Doctrine\ORM\EntityManagerInterface */

        $q = $em->createQuery('DELETE FROM Vrok\Premium\Entity\FeatureAssignment fa');
        $q->execute();
        $q2 = $em->createQuery('DELETE FROM Vrok\Entity\User u');
        $q2->execute();

        $this->users['low'] = new User();
        $this->users['low']->setUsername('low');
        $this->users['low']->setEmail('low@domain.tld');
        $this->users['low']->setPassword('test');
        $em->persist($this->users['low']);
        $this->users['high'] = new User();
        $this->users['high']->setUsername('high');
        $this->users['high']->setEmail('high@domain.tld');
        $this->users['high']->setPassword('test');
        $em->persist($this->users['high']);
        $this->users['none'] = new User();
        $this->users['none']->setUsername('none');
        $this->users['none']->setEmail('none@domain.tld');
        $this->users['none']->setPassword('test');
        $em->persist($this->users['none']);
        $em->flush();

        $this->featureManager->assignFeature(
            'test',
            $this->users['low'],
            ['param' => 5],
            $this->users['low']
        );
        $this->featureManager->assignFeature(
            'test',
            $this->users['high'],
            ['param' => 10],
            $this->users['high']
        );
        $em->flush();

        $this->updateUsers();
    }

    protected function updateUsers()
    {
        $this->featureManager->updateFeatureOwner('test', $this->users['none']);
        $this->featureManager->updateFeatureOwner('test', $this->users['low']);
        $this->featureManager->updateFeatureOwner('test', $this->users['high']);
        $this->featureManager->getEntityManager()->flush();
    }

    public function testInstantiateJob()
    {
        $job = $this->getJob();
        $this->assertInstanceOf(UpdateFeatureDefaultConfig::class, $job);
    }

    public function testJobChecksFeatureName()
    {
        $job = $this->getJob();
        $job->setContent(['featureName' => null, 'userId' => 1, 'oldConfig' => []]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Feature name missing or invalid!');
        $job->execute();
    }

    public function testJobChecksUser()
    {
        $job = $this->getJob();
        $job->setContent(['feature' => 'test', 'userId' => null, 'oldConfig' => ['active' => 1]]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Admin ID missing or invalid!');
        $job->execute();
    }

    public function testJobChecksOldConfig()
    {
        $job = $this->getJob();
        $job->setContent(['feature' => 'test', 'userId' => 1, 'oldConfig' => []]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Old feature config missing or invalid!');
        $job->execute();
    }

    public function testInactiveToInactive()
    {
        $old = ['param'  => 2, 'active' => false];
        $this->featureManager->setDefaultConfig('test', $old);
        $this->updateUsers();

        $this->assertEquals('inactive', $this->users['none']->getDisplayName());
        $this->assertEquals('5', $this->users['low']->getDisplayName());
        $this->assertEquals('10', $this->users['high']->getDisplayName());

        $new = ['param'  => 15, 'active' => false];
        $this->featureManager->setDefaultConfig('test', $new);

        $job = $this->getJob();
        $job->setContent([
            'feature'   => 'test',
            'userId'    => 1,
            'oldConfig' => $old,
        ]);
        $job->execute();

        $this->assertEquals('inactive', $this->users['none']->getDisplayName());
        $this->assertEquals('5', $this->users['low']->getDisplayName());
        $this->assertEquals('10', $this->users['high']->getDisplayName());
    }

    public function testActiveToInactive()
    {
        $old = ['param'  => 7, 'active' => true];
        $this->featureManager->setDefaultConfig('test', $old);
        $this->updateUsers();

        $this->assertEquals('7', $this->users['none']->getDisplayName());
        $this->assertEquals('7', $this->users['low']->getDisplayName());
        $this->assertEquals('10', $this->users['high']->getDisplayName());

        $new = ['param'  => 15, 'active' => false];
        $this->featureManager->setDefaultConfig('test', $new);

        $job = $this->getJob();
        $job->setContent([
            'feature'   => 'test',
            'userId'    => 1,
            'oldConfig' => $old,
        ]);
        $job->execute();

        $this->assertEquals('inactive', $this->users['none']->getDisplayName());
        $this->assertEquals('5', $this->users['low']->getDisplayName());
        $this->assertEquals('10', $this->users['high']->getDisplayName());
    }

    public function testActiveToActive()
    {
        $old = ['param'  => 7, 'active' => true];
        $this->featureManager->setDefaultConfig('test', $old);
        $this->updateUsers();

        $this->assertEquals('7', $this->users['none']->getDisplayName());
        $this->assertEquals('7', $this->users['low']->getDisplayName());
        $this->assertEquals('10', $this->users['high']->getDisplayName());

        $new = ['param'  => 4, 'active' => true];
        $this->featureManager->setDefaultConfig('test', $new);

        $job = $this->getJob();
        $job->setContent([
            'feature'   => 'test',
            'userId'    => 1,
            'oldConfig' => $old,
        ]);
        $job->execute();

        $this->assertEquals('4', $this->users['none']->getDisplayName());
        $this->assertEquals('5', $this->users['low']->getDisplayName());
        $this->assertEquals('10', $this->users['high']->getDisplayName());
    }

    public function testJobTriggersEvent()
    {
        $old = ['param'  => 7, 'active' => true];
        $job = $this->getJob();
        $job->setContent([
            'feature'   => 'test',
            'userId'    => 1,
            'oldConfig' => $old,
        ]);

        $serviceManager  = Bootstrap::getServiceManager();
        $em = $serviceManager->get('EventManager');
        /* @var $em \Zend\EventManager\EventManagerInterface */

        $called = false;
        $em->getSharedManager()->attach(
            'Vrok\Premium',
            UpdateFeatureDefaultConfig::EVENT_FEATURE_DEFAULTS_UPDATED,
            function (\Zend\EventManager\EventInterface $e) use (&$called) {
                $this->assertEquals('test', $e->getParam('feature'));
                $called = true;
            }
        );

        $job->execute();
        $this->assertTrue($called);

        $em->getSharedManager()->clearListeners(
            'Vrok\Premium',
            UpdateFeatureDefaultConfig::EVENT_FEATURE_DEFAULTS_UPDATED
        );
    }

    public function testJobUsesTransaction()
    {
        $old = ['param'  => 7, 'active' => true];
        $this->featureManager->setDefaultConfig('test', $old);
        $this->updateUsers();

        $this->assertEquals('7', $this->users['none']->getDisplayName());
        $this->assertEquals('7', $this->users['low']->getDisplayName());
        $this->assertEquals('10', $this->users['high']->getDisplayName());

        $new = ['param'  => 15, 'active' => true];
        $this->featureManager->setDefaultConfig('test', $new);

        // trigger an exception, "throw" will be persisted by first flush
        $this->users['none']->setDisplayName('throw');

        $job = $this->getJob();
        $job->setContent([
            'feature'   => 'test',
            'userId'    => 1,
            'oldConfig' => $old,
        ]);

        try {
            $job->execute();
        } catch (\RuntimeException $e) {
        }

        // database unchanged?
        $this->featureManager->getEntityManager()->clear();

        $em = $this->featureManager->getEntityManager();

        $none = $em->getRepository(User::class)->find($this->users['none']->getId());
        $low = $em->getRepository(User::class)->find($this->users['low']->getId());
        $high = $em->getRepository(User::class)->find($this->users['high']->getId());

        $this->assertEquals('15', $low->getDisplayName());
        $this->assertEquals('15', $high->getDisplayName());

        // "none" has the highest ID, updated after the others -> threw an
        // exception -> unchanged username
        $this->assertEquals('throw', $none->getDisplayName());
    }
}

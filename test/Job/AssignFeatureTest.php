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
use Vrok\Premium\Entity;
use Vrok\Premium\Job\AssignFeature;
use Vrok\Premium\Service\FeatureManager;
use VrokPremiumTest\Bootstrap;

class AssignFeatureTest extends TestCase
{
    /**
     * @var FeatureManager
     */
    protected $featureManager = null;

    /**
     * @var User
     */
    protected $user = null;

    /**
     * @return JobInterface
     */
    protected function getJob()
    {
        $serviceManager = Bootstrap::getServiceManager();
        $jm = $serviceManager->get(JobPluginManager::class);
        return $jm->get(AssignFeature::class);
    }

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
    }

    public function testInstantiateJob()
    {
        $job = $this->getJob();
        $this->assertInstanceOf(AssignFeature::class, $job);
    }

    public function testJobChecksFeature()
    {
        $job = $this->getJob();
        $job->setContent(['owner' => [], 'source' => [], 'params']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Feature name missing or invalid!');
        $job->execute();
    }

    public function testJobChecksOwner()
    {
        $job = $this->getJob();
        $job->setContent(['feature' => 'test', 'source' => [], 'params']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Owner reference missing or invalid!');
        $job->execute();
    }

    public function testJobChecksSource()
    {
        $job = $this->getJob();
        $job->setContent(['feature' => 'test', 'owner' => ['class'], 'params']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Source reference missing or invalid!');
        $job->execute();
    }

    public function testJobChecksParams()
    {
        $job = $this->getJob();
        $job->setContent(['feature' => 'test', 'owner' => ['class'], 'source' => ['class'], 'params' => null]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Feature params missing or invalid!');
        $job->execute();
    }

    public function testJobCreatesAssignment()
    {
        $ref = $this->featureManager->getReferenceHelper()->getReferenceData($this->user);

        $this->featureManager->setDefaultConfig('test', ['active' => false, 'param' => 1]);
        $this->assertEquals([], $this->featureManager->getAssignmentRepository()->findAll());
        $this->assertEquals(1, $this->featureManager->getParameter(
            'test',
            'param',
            $this->user
        ));

        $job = $this->getJob();
        $job->setContent([
            'feature' => 'test',
            'owner'   => $ref,
            'source'  => $ref,
            'params'  => ['param' => 3],
        ]);
        $job->execute();

        $assignments = $this->featureManager->getAssignmentRepository()->findAll();
        $this->assertEquals(1, count($assignments));
        $this->assertEquals(3, $this->featureManager->getParameter(
            'test',
            'param',
            $this->user
        ));
    }

    public function testJobExecutesUpdate()
    {
        $ref = $this->featureManager->getReferenceHelper()->getReferenceData($this->user);

        $this->user->setDisplayName('test');

        $job = $this->getJob();
        $job->setContent([
            'feature' => 'test',
            'owner'   => $ref,
            'source'  => $ref,
            'params'  => ['param' => 55],
        ]);

        $job->execute();
        $this->assertEquals('55', $this->user->getDisplayName());
    }

    public function testJobTriggersEvent()
    {
        $ref = $this->featureManager->getReferenceHelper()->getReferenceData($this->user);

        $job = $this->getJob();
        $job->setContent([
            'feature' => 'test',
            'owner'   => $ref,
            'source'  => $ref,
            'params'  => ['param' => 2],
        ]);

        $serviceManager  = Bootstrap::getServiceManager();
        $em = $serviceManager->get('EventManager');
        /* @var $em \Zend\EventManager\EventManagerInterface */

        $called = false;
        $em->getSharedManager()->attach(
            'Vrok\Premium',
            AssignFeature::EVENT_FEATURE_ASSIGNED,
            function (\Zend\EventManager\EventInterface $e) use (&$called) {
                $this->assertInstanceOf(Entity\FeatureAssignment::class, $e->getTarget());
                $this->assertEquals(['param' => 2], $e->getTarget()->getParams());
                $called = true;
            }
        );

        $job->execute();
        $this->assertTrue($called);
        $em->getSharedManager()->clearListeners(
            'Vrok\Premium',
            AssignFeature::EVENT_FEATURE_ASSIGNED
        );
    }

    public function testJobUsesTransaction()
    {
        $ref = $this->featureManager->getReferenceHelper()->getReferenceData($this->user);

        $job = $this->getJob();
        $job->setContent([
            'feature' => 'test',
            'owner'   => $ref,
            'source'  => $ref,
            'params'  => ['param' => 66],
        ]);

        try {
            $job->execute();
        } catch (\RuntimeException $e) {
        }

        // database unchanged?
        $this->assertEquals([], $this->featureManager->getAssignmentRepository()->findAll());
    }
}

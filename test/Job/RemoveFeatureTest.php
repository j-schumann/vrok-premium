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
use Vrok\Premium\Job\RemoveFeature;
use Vrok\Premium\Service\FeatureManager;
use VrokPremiumTest\Bootstrap;

class RemoveFeatureTest extends TestCase
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
        return $jm->get(RemoveFeature::class);
    }

    protected function setUp()
    {
        $serviceManager  = Bootstrap::getServiceManager();
        $this->featureManager = $serviceManager->get(FeatureManager::class);

        $em = $serviceManager->get('Doctrine\ORM\EntityManager');
        /* @var $em \Doctrine\ORM\EntityManagerInterface */

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
        $this->assertInstanceOf(RemoveFeature::class, $job);
    }

    public function testJobChecksFeatureId()
    {
        $job = $this->getJob();
        $job->setContent(['featureId' => null, 'userId' => $this->user->getId()]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Feature assignment ID missing or invalid!');
        $job->execute();
    }

    public function testJobChecksUser()
    {
        $job = $this->getJob();
        $job->setContent(['featureId' => 1, 'userId' => null]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Admin ID missing or invalid!');
        $job->execute();
    }

    public function testJobRemovesAssignment()
    {
        $assignment = $this->featureManager->assignFeature(
            'test',
            $this->user,
            ['param' => 3],
            $this->user
        );
        $this->featureManager->getEntityManager()->flush();

        $assignments = $this->featureManager->getAssignmentRepository()->findAll();
        $this->assertEquals(1, count($assignments));
        $this->assertEquals(3, $this->featureManager->getParameter(
            'test',
            'param',
            $this->user
        ));

        $job = $this->getJob();
        $job->setContent([
            'featureId' => $assignment->getId(),
            'userId'    => $this->user->getId(),
        ]);
        $job->execute();

        $this->assertEquals([], $this->featureManager->getAssignmentRepository()->findAll());
        $this->assertEquals(1, $this->featureManager->getParameter(
            'test',
            'param',
            $this->user
        ));
    }

    /**
     * @group ttest
     */
    public function testJobExecutesUpdate()
    {
        $this->featureManager->setDefaultConfig('test', ['param'  => 1, 'active' => true]);
        $assignment = $this->featureManager->assignFeature(
            'test',
            $this->user,
            ['param' => 3],
            $this->user
        );
        $this->user->setDisplayName('test');
        $this->featureManager->getEntityManager()->flush();

        $job = $this->getJob();
        $job->setContent([
            'featureId' => $assignment->getId(),
            'userId'    => $this->user->getId(),
        ]);
        $job->execute();

        $this->assertEquals('1', $this->user->getDisplayName());
    }

    public function testJobTriggersEvent()
    {
        $assignment = $this->featureManager->assignFeature(
            'test',
            $this->user,
            ['param' => 3],
            $this->user
        );
        $this->featureManager->getEntityManager()->flush();
        $this->user->setDisplayName('test');

        $job = $this->getJob();
        $job->setContent([
            'featureId' => $assignment->getId(),
            'userId'    => $this->user->getId(),
        ]);

        $serviceManager  = Bootstrap::getServiceManager();
        $em = $serviceManager->get('EventManager');
        /* @var $em \Zend\EventManager\EventManagerInterface */

        $called = false;
        $em->getSharedManager()->attach(
            'Vrok\Premium',
            RemoveFeature::EVENT_FEATURE_REMOVED,
            function (\Zend\EventManager\EventInterface $e) use (&$called) {
                $this->assertInstanceOf(User::class, $e->getTarget());
                $this->assertEquals('test', $e->getParam('feature'));
                $this->assertEquals(['param' => 3], $e->getParam('params'));
                $this->assertEquals($this->user->getId(), $e->getParam('userId'));
                $this->assertEquals(
                    ['class' => User::class, 'identifiers' => ['id' => $this->user->getId()]],
                    $e->getParam('source')
                );
                $called = true;
            }
        );

        $job->execute();
        $this->assertTrue($called);

        $em->getSharedManager()->clearListeners(
            'Vrok\Premium',
            RemoveFeature::EVENT_FEATURE_REMOVED
        );
    }

    public function testJobUsesTransaction()
    {
        $assignment = $this->featureManager->assignFeature(
            'test',
            $this->user,
            ['param' => 3],
            $this->user
        );
        $this->user->setDisplayName('throw');
        $this->featureManager->getEntityManager()->flush();

        $job = $this->getJob();
        $job->setContent([
            'featureId' => $assignment->getId(),
            'userId'    => $this->user->getId(),
        ]);

        try {
            $job->execute();
        } catch (\RuntimeException $e) {
        }

        // database unchanged?
        $this->featureManager->getEntityManager()->clear();

        $assignments = $this->featureManager->getAssignmentRepository()->findAll();
        $this->assertEquals(1, count($assignments));

        $user = $this->featureManager->getEntityManager()
                ->getRepository(User::class)->find($this->user->getId());
        $this->assertEquals('throw', $user->getDisplayName());

        // reset for other tests
        $user->setDisplayName('test');
        $this->featureManager->getEntityManager()->flush();
    }
}

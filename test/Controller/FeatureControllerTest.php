<?php

/**
 * @copyright   (c) 2018, Vrok
 * @license     MIT License (http://www.opensource.org/licenses/mit-license.php)
 * @author      Jakob Schumann <schumann@vrok.de>
 */

namespace VrokPremiumTest\Controller;

use Vrok\Entity\User;
use Vrok\Premium\Exception\DomainException;
use Vrok\Premium\Exception\InvalidArgumentException;
use Vrok\Premium\Entity;
use Vrok\Premium\Service\FeatureManager;
use VrokPremiumTest\Bootstrap;
use VrokPremiumTest\Feature\TestFeature;
use Zend\Test\PHPUnit\Controller\AbstractHttpControllerTestCase;

class FeatureControllerTest extends AbstractHttpControllerTestCase
{
    public function setUp()
    {
        $serviceManager  = Bootstrap::getServiceManager();
        //$this->application = Bootstrap::$application;
        //\Doctrine\Common\Util\Debug::dump($serviceManager->get('ApplicationConfig'), 4);
        //exit;
        $this->setApplicationConfig(
            $serviceManager->get('ApplicationConfig')
        );

        $mockBjy = $this->getMockBuilder("BjyAuthorize\Provider\Identity\AuthenticationIdentityProvider")
            ->setConstructorArgs([$serviceManager->get('Zend\Authentication\AuthenticationService')])
            ->getMock();

        $mockBjy->expects($this->any())
            ->method('getIdentityRoles')
            ->will($this->returnValue(['admin']));

        $smNew = $this->getApplication()
            ->getServiceManager();
        $smNew->setAllowOverride(true);
        //$smNew->setService('BjyAuthorize\Provider\Identity\AuthenticationIdentityProvider', $mockBjy);
        parent::setUp();

        // @todo Zend\Test\PHPUnit\ControllerAbstractControllerTestCase leert $_SERVER,
        // das wird aber vom SessionContainer benötigt für expiry des FlashMessengers...
        $_SERVER['REQUEST_TIME'] = time();
    }

    /**
     * @group tt
     */
    public function testIndexAction()
    {
        $this->dispatch('/premium/feature/defaults/test');
        $this->assertResponseStatusCode(200);
    }
}

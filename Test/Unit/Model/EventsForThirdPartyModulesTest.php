<?php
/**
 * Bolt magento2 plugin
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 *
 * @category   Bolt
 * @package    Bolt_Boltpay
 * @copyright  Copyright (c) 2017-2020 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Bolt\Boltpay\Test\Unit\Model;

use Bolt\Boltpay\Test\Unit\BoltTestCase;
use Bolt\Boltpay\Model\EventsForThirdPartyModules;
use Magento\TestFramework\Helper\Bootstrap;

/**
 * Class ThirdPartyModuleFactoryTest
 * @package Bolt\Boltpay\Test\Unit\Model
 */
class EventsForThirdPartyModuleTest extends BoltTestCase
{
 
    /**
     * @test
     */
    public function configTest()
    {
        $this->skipTestInUnitTestsFlow();
        $eventsListeners = EventsForThirdPartyModules::eventListeners;
        foreach ($eventsListeners as $eventName => $eventListeners) {
            foreach ($eventListeners["listeners"] as $listener) {
                static::assertArrayHasKey('module', $listener);
                static::assertTrue(is_array($listener["3pclasses"]));
                static::assertTrue(count($listener["3pclasses"])>=1);
                $boltClass = Bootstrap::getObjectManager()->create($listener["boltClass"]);
                static::assertTrue(method_exists($boltClass, $eventName));
            }
        }
    }

    /**
     * @test
     */
    public function dispatchEventTest()
    {
        $this->skipTestInUnitTestsFlow();
        $eventsForThirdPartyModulesMock = Bootstrap::getObjectManager()->get(EventsForThirdPartyModulesMock::class);
        $listenerMock = Bootstrap::getObjectManager()->get(ListenerMock::class);
        
        /*$eventsForThirdPartyModulesMock->dispatchEvent("classDoesNotExist");
        static::assertFalse($listenerMock->methodCalled);
        
        $eventsForThirdPartyModulesMock->dispatchEvent("moduleDoesNotEnabled");
        static::assertFalse($listenerMock->methodCalled);*/
        
        $eventsForThirdPartyModulesMock->dispatchEvent("shouldCall");
        static::assertTrue($listenerMock->methodCalled);
    }
}

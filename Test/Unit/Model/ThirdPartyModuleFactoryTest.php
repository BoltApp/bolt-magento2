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
 * @copyright  Copyright (c) 2017-2021 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Bolt\Boltpay\Test\Unit\Model;

use Bolt\Boltpay\Test\Unit\BoltTestCase;
use Bolt\Boltpay\Helper\Log as LogHelper;
use Bolt\Boltpay\Model\ThirdPartyModuleFactory;
use Bolt\Boltpay\Test\Unit\TestHelper;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use Magento\TestFramework\Helper\Bootstrap;

/**
 * Class ThirdPartyModuleFactoryTest
 * @package Bolt\Boltpay\Test\Unit\Model
 * @coversDefaultClass \Bolt\Boltpay\Model\ThirdPartyModuleFactory
 */
class ThirdPartyModuleFactoryTest extends BoltTestCase
{
    const MODULE_NAME = 'Bolt_Boltpay';
    const CLASS_NAME = 'Bolt\Boltpay\Helper\Cart';

    /**
     * @var ObjectManager
     */
    private $objectManager;

    /**
     * @var LogHelper
     */
    private $logHelper;

    /**
     * @var ThirdPartyModuleFactory
     */
    private $thirdPartyModuleFactory;

    public function setUpInternal()
    {
        if (!class_exists('\Magento\TestFramework\Helper\Bootstrap')) {
            return;
        }
        $this->objectManager = Bootstrap::getObjectManager();
        $this->thirdPartyModuleFactory = $this->objectManager->create(ThirdPartyModuleFactory::class);
    }

    /**
     * @test
     * @covers ::getInstance
     */
    public function getInstance_withModuleIsNotAvailable_returnNull()
    {
        $this->assertNull($this->thirdPartyModuleFactory->getInstance());
    }

    /**
     * @test
     * @covers ::getInstance
     */
    public function getInstance_withModuleIsAvailable_returnObjectManagerObject()
    {
        TestHelper::setInaccessibleProperty($this->thirdPartyModuleFactory, 'moduleName', self::MODULE_NAME);
        TestHelper::setInaccessibleProperty($this->thirdPartyModuleFactory, 'className', self::CLASS_NAME);
        $object = $this->thirdPartyModuleFactory->getInstance();
        self::assertEquals(self::CLASS_NAME, get_class($object));
    }

    /**
     * @test
     * @covers ::isAvailable
     */
    public function isAvailable()
    {
        TestHelper::setInaccessibleProperty($this->thirdPartyModuleFactory, 'moduleName', self::MODULE_NAME);
        $this->assertTrue($this->thirdPartyModuleFactory->isAvailable());
    }

    /**
     * @test
     * @covers ::isExists
     */
    public function isExists_withClass_returnTrue()
    {
        TestHelper::setInaccessibleProperty($this->thirdPartyModuleFactory, 'className', self::CLASS_NAME);
        $this->assertTrue($this->thirdPartyModuleFactory->isExists());
    }

    /**
     * @test
     * @covers ::isExists
     */
    public function isExists_withInterface_returnTrue()
    {
        TestHelper::setInaccessibleProperty($this->thirdPartyModuleFactory, 'className', 'Bolt\Boltpay\Api\CreateOrderInterface');
        $this->assertTrue($this->thirdPartyModuleFactory->isExists());
    }
}

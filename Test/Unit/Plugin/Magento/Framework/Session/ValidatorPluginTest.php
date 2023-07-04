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
 * @copyright  Copyright (c) 2017-2023 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Bolt\Boltpay\Test\Unit\Plugin\Magento\Framework\Session;

use Bolt\Boltpay\Helper\Hook;
use Bolt\Boltpay\Test\Unit\BoltTestCase;
use Bolt\Boltpay\Plugin\Magento\Framework\Session\ValidatorPlugin;
use Bolt\Boltpay\Test\Unit\TestUtils;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\Framework\Session\Validator;
use Magento\Framework\Session\SessionManager;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Class ValidatorPluginTest
 * @package Bolt\Boltpay\Test\Unit\Plugin\Magento\Framework\Session
 * @coversDefaultClass \Bolt\Boltpay\Plugin\Magento\Framework\Session\ValidatorPlugin
 */
class ValidatorPluginTest extends BoltTestCase
{
    private $objectManager;

    private $plugin;

    private $callback;

    private $proceed;

    /**
     * @var SessionManager
     */
    private $sessionManager;

    protected function setUpInternal()
    {
        if (!class_exists('\Magento\TestFramework\Helper\Bootstrap')) {
            return;
        }
        $this->objectManager = Bootstrap::getObjectManager();
        $this->plugin = $this->objectManager->create(ValidatorPlugin::class);
        $this->sessionManager = $this->objectManager->create(SessionManager::class);
        /** @var callable $callback */
        $this->callback = $callback = $this->getMockBuilder(\stdClass::class)
            ->setMethods(['__invoke'])->getMock();
        $this->proceed = function ($obsrvr) use ($callback) {
            return $callback($obsrvr);
        };
    }

    /**
     * @test
     * @covers ::aroundValidate
     */
    public function aroundValidate_callOriginalMethod()
    {
        $validatorObject = $this->objectManager->create(Validator::class);
        $this->callback->expects(self::once())->method('__invoke')->with($this->sessionManager);
        $this->plugin->aroundValidate($validatorObject, $this->proceed, $this->sessionManager);
    }

    /**
     * @test
     * @covers ::aroundValidate
     */
    public function aroundValidate_ifRequestIsNotFromBolt_callOriginalMethod()
    {
        Hook::$fromBolt = false;
        $validatorObject = $this->objectManager->create(Validator::class);
        $this->callback->expects(self::once())->method('__invoke')->with($this->sessionManager);
        $store = $this->objectManager->get(StoreManagerInterface::class);
        $storeId = $store->getStore()->getId();
        $configData = [
            [
                'path' => Validator::XML_PATH_USE_REMOTE_ADDR,
                'value' => true,
                'scope' => 'default',
                'scopeId' => $storeId,
            ]
        ];
        TestUtils::setupBoltConfig($configData);
        $this->plugin->aroundValidate($validatorObject, $this->proceed, $this->sessionManager);
    }

    /**
     * @test
     * @covers ::aroundValidate
     */
    public function aroundValidate_ifRequestIsNotFromBolt_NotCallOriginalMethod()
    {
        $validatorObject = $this->objectManager->create(Validator::class);
        Hook::$fromBolt = true;
        $this->callback->expects(self::never())->method('__invoke');
        $store = $this->objectManager->get(StoreManagerInterface::class);
        $storeId = $store->getStore()->getId();
        $configData = [
            [
                'path' => Validator::XML_PATH_USE_REMOTE_ADDR,
                'value' => true,
                'scope' => 'default',
                'scopeId' => $storeId,
            ]
        ];
        TestUtils::setupBoltConfig($configData);
        $this->assertNull($this->plugin->aroundValidate($validatorObject, $this->proceed, $this->sessionManager));
    }
}

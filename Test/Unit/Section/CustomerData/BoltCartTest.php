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
 *
 * @copyright  Copyright (c) 2020 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Bolt\Boltpay\Test\Unit\Section\CustomerData;

use Bolt\Boltpay\Helper\Cart as CartHelper;
use Bolt\Boltpay\Section\CustomerData\BoltCart;
use Bolt\Boltpay\Test\Unit\BoltTestCase;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use Bolt\Boltpay\Helper\Config as ConfigHelper;
use Magento\Store\Model\ScopeInterface;
use Bolt\Boltpay\Test\Unit\TestUtils;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\Store\Model\StoreManagerInterface;
use Bolt\Boltpay\Test\Unit\TestHelper;

/**
 * Class BoltCartTest
 *
 * @package Bolt\Boltpay\Test\Unit\Section\CustomerData
 * @coversDefaultClass \Bolt\Boltpay\Section\CustomerData\BoltCart
 */
class BoltCartTest extends BoltTestCase
{
    /**
     * @var CartHelper
     */
    private $cartHelper;

    /**
     * @var BoltCart
     */
    private $boltCart;

    private $storeId;
    private $objectManager;

    /**
     * @inheritdoc
     */
    public function setUpInternal()
    {
        if (!class_exists('\Magento\TestFramework\Helper\Bootstrap')) {
            return;
        }
        $this->cartHelper = $this->createMock(CartHelper::class);
        $this->objectManager = Bootstrap::getObjectManager();
        $this->boltCart = $this->objectManager->create(BoltCart::class);
        $store = $this->objectManager->get(StoreManagerInterface::class);
        $this->storeId = $store->getStore()->getId();
    }

    /**
     * @test
     */
    public function getSectionData()
    {
        $configData = [
            [
                'path' => ConfigHelper::XML_PATH_ACTIVE,
                'value' => true,
                'scope' => ScopeInterface::SCOPE_STORE,
                'scopeId' => $this->storeId,
            ]
        ];
        TestUtils::setupBoltConfig($configData);
        TestHelper::setProperty($this->boltCart, 'cartHelper', $this->cartHelper);
        $this->cartHelper->expects(self::once())->method('calculateCartAndHints');
        $this->boltCart->getSectionData();
    }

    /**
     * @test
     */
    public function getSectionData_returnEmptyArray()
    {
        $configData = [
            [
                'path' => ConfigHelper::XML_PATH_ACTIVE,
                'value' => false,
                'scope' => ScopeInterface::SCOPE_STORE,
                'scopeId' => $this->storeId,
            ]
        ];
        TestUtils::setupBoltConfig($configData);

        $result = $this->boltCart->getSectionData();
        $this->assertEquals($result, []);
    }
}

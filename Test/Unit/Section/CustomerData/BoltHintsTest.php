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
use Bolt\Boltpay\Helper\Config as ConfigHelper;
use Bolt\Boltpay\Section\CustomerData\BoltHints;
use Bolt\Boltpay\Test\Unit\BoltTestCase;
use Bolt\Boltpay\Test\Unit\TestHelper;
use Bolt\Boltpay\Test\Unit\TestUtils;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Class BoltHintsTest
 * @coversDefaultClass \Bolt\Boltpay\Section\CustomerData\BoltHints
 * @package Bolt\Boltpay\Test\Unit\Section\CustomerData
 */
class BoltHintsTest extends BoltTestCase
{
    /**
     * @var CartHelper
     */
    private $cartHelper;

    private $objectManager;

    /**
     * @var BoltHints
     */
    private $boltHints;

    private $storeId;

    /**
     * @inheritdoc
     */
    public function setUpInternal()
    {
        if (!class_exists('\Magento\TestFramework\Helper\Bootstrap')) {
            return;
        }
        $this->objectManager = Bootstrap::getObjectManager();
        $this->boltHints = $this->objectManager->create(BoltHints::class);
        $store = $this->objectManager->get(StoreManagerInterface::class);
        $this->storeId = $store->getStore()->getId();
        $this->cartHelper = $this->createPartialMock(CartHelper::class, ['getHints']);
    }

    /**
     * @test
     * @covers ::getSectionData
     */
    public function getSectionData_returnEmptyIfPPCdisabled()
    {
        $result = $this->boltHints->getSectionData();
        $this->assertEquals($result, []);
    }

    /**
     * @test
     * @covers ::getSectionData
     */
    public function getSectionData_returnHints()
    {
        $this->cartHelper->expects($this->once())->method('getHints')->with(null, 'product')->willReturn('testHints');

        $configData = [
            [
                'path' => ConfigHelper::XML_PATH_PRODUCT_PAGE_CHECKOUT,
                'value' => true,
                'scope' => ScopeInterface::SCOPE_STORE,
                'scopeId' => $this->storeId,
            ]
        ];
        TestUtils::setupBoltConfig($configData);
        TestHelper::setProperty($this->boltHints, 'cartHelper', $this->cartHelper);
        $result = $this->boltHints->getSectionData();

        $this->assertEquals($result, ['data' => 'testHints']);
    }
}

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
 * @copyright  Copyright (c) 2017-2022 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Bolt\Boltpay\Test\Unit\Block\Adminhtml\Customer\CreditCard\Tab;

use Bolt\Boltpay\Block\Adminhtml\Customer\CreditCard\Tab\CreditCard;
use Bolt\Boltpay\Test\Unit\TestHelper;
use Bolt\Boltpay\Test\Unit\TestUtils;
use Magento\Customer\Controller\RegistryConstants;
use Magento\Framework\Registry;
use Bolt\Boltpay\Test\Unit\BoltTestCase;
use Magento\TestFramework\Helper\Bootstrap;

/**
 * Class CreditCardTest
 *
 * @package Bolt\Boltpay\Test\Unit\Block
 */
class CreditCardTest extends BoltTestCase
{
    const CUSTOMER_ID = '11111';
    const URL = 'https://www.bolt.com/boltpay/customer/creditcard';

    /** @var ObjectManager */
    private $objectManager;

    /**
     * @var CreditCard
     */
    private $block;

    /**
     * @var Registry
     */
    private $registry;

    /**
     * @inheritdoc
     */
    protected function setUpInternal()
    {
        $this->objectManager = Bootstrap::getObjectManager();
        $this->block = $this->objectManager->create(CreditCard::class);
        $this->registry = $this->objectManager->create(Registry::class);
    }

    /**
     * @test
     */
    public function getCustomerId()
    {
        $this->registry->register(RegistryConstants::CURRENT_CUSTOMER_ID, self::CUSTOMER_ID);
        TestHelper::setInaccessibleProperty($this->block, '_coreRegistry', $this->registry);
        $result = $this->block->getCustomerId();
        $this->assertEquals(self::CUSTOMER_ID, $result);
    }

    /**
     * @test
     */
    public function getTabLabel()
    {
        $expected = __('Bolt Credit Cards');
        $result = $this->block->getTabLabel();
        $this->assertEquals($expected, $result);
    }

    /**
     * @test
     */
    public function getTabTitle()
    {
        $expected = __('Bolt Credit Cards');
        $result = $this->block->getTabTitle();
        $this->assertEquals($expected, $result);
    }

    /**
     * @test
     */
    public function canShowTab_withLoggedInCustomer()
    {
        $this->registry->register(RegistryConstants::CURRENT_CUSTOMER_ID, self::CUSTOMER_ID);
        TestHelper::setInaccessibleProperty($this->block, '_coreRegistry', $this->registry);
        $result = $this->block->canShowTab();
        $this->assertTrue($result);
    }

    /**
     * @test
     */
    public function canShowTab_withGuestCustomer()
    {
        $this->registry->register(RegistryConstants::CURRENT_CUSTOMER_ID, null);
        TestHelper::setInaccessibleProperty($this->block, '_coreRegistry', $this->registry);
        $result = $this->block->canShowTab();
        $this->assertFalse($result);
    }

    /**
     * @test
     */
    public function isHidden_withLoggedInCustomer()
    {
        $this->registry->register(RegistryConstants::CURRENT_CUSTOMER_ID, self::CUSTOMER_ID);
        TestHelper::setInaccessibleProperty($this->block, '_coreRegistry', $this->registry);
        $result = $this->block->isHidden();
        $this->assertFalse($result);
    }

    /**
     * @test
     */
    public function isHidden_withGuestCustomer()
    {
        $this->registry->register(RegistryConstants::CURRENT_CUSTOMER_ID, null);
        TestHelper::setInaccessibleProperty($this->block, '_coreRegistry', $this->registry);
        $result = $this->block->isHidden();
        $this->assertTrue($result);
    }

    /**
     * @test
     */
    public function getTabClass()
    {
        $result = $this->block->getTabClass();
        $this->assertEmpty($result);
    }

    /**
     * @test
     */
    public function getTabUrl()
    {
        $url = $this->block->getUrl('boltpay/customer/creditcard', ['_current' => true]);
        $result = $this->block->getTabUrl();
        $this->assertEquals($url, $result);
    }

    /**
     * @test
     */
    public function isAjaxLoaded()
    {
        $result = $this->block->isAjaxLoaded();
        $this->assertTrue($result);
    }
}

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

namespace Bolt\Boltpay\Test\Unit\Block\Adminhtml\Customer\CreditCard\Tab;

use Bolt\Boltpay\Block\Adminhtml\Customer\CreditCard\Tab\CreditCard;
use Magento\Customer\Controller\RegistryConstants;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\Registry;

/**
 * Class CreditCardTest
 *
 * @package Bolt\Boltpay\Test\Unit\Block
 */
class CreditCardTest extends \PHPUnit\Framework\TestCase
{
    const CUSTOMER_ID = '11111';
    const URL = 'https://www.bolt.com/boltpay/customer/creditcard';

    /**
     * @var CreditCard
     */
    private $block;

    /**
     * @var Context
     */
    private $contextMock;

    /**
     * @var Registry
     */
    private $registryMock;

    /**
     * @inheritdoc
     */
    protected function setUp()
    {
        $this->initRequiredMocks();
        $this->initCurrentMock();
    }

    private function initRequiredMocks()
    {
        $this->contextMock = $this->createMock(Context::class);
        $this->registryMock = $this->getMockBuilder(Registry::class)
            ->disableOriginalConstructor()
            ->setMethods(['registry'])
            ->getMock();
    }

    private function initCurrentMock()
    {
        $this->block = $this->getMockBuilder(CreditCard::class)
            ->setMethods(['getUrl'])
            ->setConstructorArgs(
                [
                    $this->contextMock,
                    $this->registryMock,
                    []
                ]
            )
            ->getMock();
    }

    /**
     * @test
     */
    public function getCustomerId()
    {
        $this->registryMock->expects(self::once())
            ->method('registry')
            ->with(RegistryConstants::CURRENT_CUSTOMER_ID)
            ->willReturn(self::CUSTOMER_ID);

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
        $this->registryMock->expects(self::once())
            ->method('registry')
            ->with(RegistryConstants::CURRENT_CUSTOMER_ID)
            ->willReturn(self::CUSTOMER_ID);

        $result = $this->block->canShowTab();
        $this->assertTrue($result);
    }

    /**
     * @test
     */
    public function canShowTab_withGuestCustomer()
    {
        $this->registryMock->expects(self::once())
            ->method('registry')
            ->with(RegistryConstants::CURRENT_CUSTOMER_ID)
            ->willReturn(null);

        $result = $this->block->canShowTab();
        $this->assertFalse($result);
    }

    /**
     * @test
     */
    public function isHidden_withLoggedInCustomer()
    {
        $this->registryMock->expects(self::once())
            ->method('registry')
            ->with(RegistryConstants::CURRENT_CUSTOMER_ID)
            ->willReturn(self::CUSTOMER_ID);

        $result = $this->block->isHidden();
        $this->assertFalse($result);
    }

    /**
     * @test
     */
    public function isHidden_withGuestCustomer()
    {
        $this->registryMock->expects(self::once())
            ->method('registry')
            ->with(RegistryConstants::CURRENT_CUSTOMER_ID)
            ->willReturn(null);

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
        $this->block->expects(self::once())
            ->method('getUrl')
            ->with('boltpay/customer/creditcard', ['_current' => true])
            ->willReturn(self::URL);
        $result = $this->block->getTabUrl();
        $this->assertEquals(self::URL, $result);
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

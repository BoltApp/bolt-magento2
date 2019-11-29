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
 * @copyright  Copyright (c) 2018 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Bolt\Boltpay\Test\Unit\Block;

use Bolt\Boltpay\Block\Form as BlockForm;
use Bolt\Boltpay\Helper\Config;
use Magento\Framework\Session\SessionManager as SessionManager;
use Magento\Backend\Model\Session\Quote as BackendQuote;
use Magento\Framework\View\Element\Template\Context;
use Bolt\Boltpay\Model\ResourceModel\CustomerCreditCard\CollectionFactory as CustomerCreditCardCollectionFactory;
use Magento\Quote\Model\Quote\Address;

/**
 * Class FormTest
 *
 * @package Bolt\Boltpay\Test\Unit\Block
 */
class FormTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var Context
     */
    private $contextMock;

    /**
     * @var BlockForm
     */
    private $block;

    /**
     * @var Config
     */
    private $configHelperMock;

    /**
     * @var SessionManager
     */
    private $sessionManager;

    /**
     * @var BackendQuote
     */
    private $quoteMock;

    /**
     * @var CustomerCreditCardCollectionFactory
     */
    private $customerCreditCardCollectionFactoryMock;

    /**
     * @var Address
     */
    private $addressMock;

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
        $this->configHelperMock = $this->createMock(Config::class);
        $this->contextMock = $this->createMock(Context::class);

        $this->quoteMock = $this->getMockBuilder(BackendQuote::class)
            ->disableOriginalConstructor()
            ->setMethods(['getBillingAddress'])
            ->getMock();

        $this->addressMock = $this->getMockBuilder(Address::class)
            ->disableOriginalConstructor()
            ->setMethods(['getCustomerId'])
            ->getMock();

        $this->sessionManager = $this->getMockBuilder(SessionManager::class)
            ->disableOriginalConstructor()
            ->setMethods(['getQuote'])
            ->getMock();

        $this->sessionManager->method('getQuote')->willReturn($this->quoteMock);
        $this->customerCreditCardCollectionFactoryMock = $this->getMockBuilder(CustomerCreditCardCollectionFactory::class)
            ->setMethods(['create', 'getCreditCardInfosByCustomerId'])
            ->getMock();
    }

    private function initCurrentMock()
    {
        $this->block = $this->getMockBuilder(BlockForm::class)
            ->setMethods(['getQuoteData'])
            ->setConstructorArgs(
                [
                    $this->contextMock,
                    $this->configHelperMock,
                    $this->sessionManager,
                    $this->customerCreditCardCollectionFactoryMock
                ]
            )
            ->getMock();
    }

    /**
     * @test
     */
    public function getCustomerCreditCardInfo_withCustomer()
    {
        $this->addressMock->expects(self::once())->method('getCustomerId')->willReturn(11111);
        $this->quoteMock->expects(self::once())->method('getBillingAddress')->willReturn($this->addressMock);
        $this->block->expects(self::once())->method('getQuoteData')->willReturn($this->quoteMock);

        $this->customerCreditCardCollectionFactoryMock->expects(static::once())->method('create')->willReturnSelf();
        $this->customerCreditCardCollectionFactoryMock->expects(static::once())
            ->method('getCreditCardInfosByCustomerId')
            ->withAnyParameters()
            ->willReturnSelf();

        $this->block->getCustomerCreditCardInfo();
    }

    /**
     * @test
     */
    public function getCustomerCreditCardInfo_withGuestCustomer(){
        $this->addressMock->expects(self::once())->method('getCustomerId')->willReturn(null);
        $this->quoteMock->expects(self::once())->method('getBillingAddress')->willReturn($this->addressMock);
        $this->block->expects(self::once())->method('getQuoteData')->willReturn($this->quoteMock);

        $this->customerCreditCardCollectionFactoryMock->expects(static::never())->method('create');
         $this->customerCreditCardCollectionFactoryMock->expects(static::never())
            ->method('getCreditCardInfosByCustomerId')
            ->withAnyParameters();

        $result = $this->block->getCustomerCreditCardInfo();
        $this->assertFalse($result);
    }
}

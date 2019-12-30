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
 * @copyright  Copyright (c) 2019 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Bolt\Boltpay\Test\Unit\Block\Customer;

use Bolt\Boltpay\Block\Customer\CreditCard;
use Magento\Framework\View\Element\Template\Context;
use Bolt\Boltpay\Model\ResourceModel\CustomerCreditCard\CollectionFactory;
use Magento\Framework\App\Request\Http;
use Magento\Customer\Model\Session;


/**
 * Class CreditCardTest
 *
 * @package Bolt\Boltpay\Test\Unit\Block
 */
class CreditCardTest extends \PHPUnit\Framework\TestCase
{
    const CUSTOMER_ID = '11111';
    const PAGE_SIZE = '1';
    const CURRENT_PAGE = '1';

    /**
     * @var CreditCard
     */
    private $block;

    /**
     * @var Context
     */
    private $contextMock;

    /**
     * @var CollectionFactory;
     */
    private $collectionFactoryMock;

    /**
     * @var Session
     */
    private $customerSessionMock;

    /**
     * @var Http
     */
    private $requestMock;

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
        $this->requestMock = $this->getMockBuilder(Http::class)
            ->disableOriginalConstructor()
            ->setMethods(['getParam'])
            ->getMock();

        $this->contextMock->method('getRequest')->willReturn($this->requestMock);

        $this->collectionFactoryMock = $this->getMockBuilder(CollectionFactory::class)
            ->disableOriginalConstructor()
            ->setMethods(['add', 'create', 'getCreditCardInfosByCustomerId', 'setPageSize', 'setCurPage'])
            ->getMock();;
        $this->customerSessionMock = $this->createMock(Session::class);
        $this->customerSessionMock->method('getCustomerId')->willReturn(self::CUSTOMER_ID);
    }

    private function initCurrentMock()
    {
        $this->block = $this->getMockBuilder(CreditCard::class)
            ->setMethods(['getChildHtml'])
            ->setConstructorArgs(
                [
                    $this->contextMock,
                    $this->collectionFactoryMock,
                    $this->customerSessionMock
                ]
            )->getMock();
    }

    /**
     * @test
     */
    public function getPagerHtml()
    {
        $this->block->expects(self::once())->method('getChildHtml')->with('pager')->willReturnSelf();
        return $this->block->getPagerHtml();
    }

    /**
     * @test
     */
    public function getCreditCardCollection()
    {
        $this->requestMock->expects(self::any())->method('getParam')->withAnyParameters()->willReturn(self::CURRENT_PAGE);
        $this->collectionFactoryMock->expects(self::once())->method('create')->willReturnSelf();
        $this->collectionFactoryMock->expects(self::once())->method('getCreditCardInfosByCustomerId')->with(self::CUSTOMER_ID)->willReturnSelf();
        $this->collectionFactoryMock->expects(self::once())->method('setPageSize')->with(self::PAGE_SIZE)->willReturnSelf();
        $this->collectionFactoryMock->expects(self::once())->method('setCurPage')->with(self::CURRENT_PAGE)->willReturnSelf();
        $this->assertSame($this->collectionFactoryMock, $this->block->getCreditCardCollection());
    }

}

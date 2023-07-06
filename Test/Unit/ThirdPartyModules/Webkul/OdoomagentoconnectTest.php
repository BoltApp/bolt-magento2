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

namespace Bolt\Boltpay\Test\Unit\ThirdPartyModules\Webkul;

use Bolt\Boltpay\Test\Unit\BoltTestCase;
use Bolt\Boltpay\ThirdPartyModules\Webkul\Odoomagentoconnect;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\QuoteRepository;
use Magento\Sales\Model\Order;

/**
 * @coversDefaultClass \Bolt\Boltpay\ThirdPartyModules\Webkul\Odoomagentoconnect
 */
class OdoomagentoconnectTest extends BoltTestCase
{
    const QUOTE_ID = 456;

    /**
     * @var QuoteRepository|\PHPUnit_Framework_MockObject_MockObject
     */
    private $quoteRepositoryMock;

    /**
     * @var Odoomagentoconnect|\PHPUnit_Framework_MockObject_MockObject
     */
    private $currentMock;

    /**
     * @var Quote|\PHPUnit_Framework_MockObject_MockObject
     */
    private $quoteMock;

    /**
     * @var Order|\PHPUnit_Framework_MockObject_MockObject
     */
    private $orderMock;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject|\Webkul\Odoomagentoconnect\Observer\SalesOrderAfterObserver
     */
    private $observerMock;

    /**
     * Setup test dependencies, called before each test
     */
    protected function setUpInternal()
    {
        $this->quoteMock = $this->createMock(Quote::class);
        $this->orderMock = $this->createMock(Order::class);
        $this->quoteRepositoryMock = $this->createMock(QuoteRepository::class);
        $this->currentMock = $this->getMockBuilder(Odoomagentoconnect::class)
            ->setConstructorArgs(
                [
                    $this->quoteRepositoryMock
                ]
            )
            ->setMethods(null)
            ->getMock();
        $this->observerMock = $this->getMockBuilder('\Webkul\Odoomagentoconnect\Observer\SalesOrderAfterObserver')
            ->disableAutoload()
            ->disableOriginalConstructor()
            ->setMethods(['execute'])
            ->getMock();
    }

    /**
     * @test
     * that constructor sets the expected internal properties
     *
     * @covers ::__construct
     */
    public function __construct_always_setsInternalProperties()
    {
        $instance = new Odoomagentoconnect($this->quoteRepositoryMock);
        static::assertAttributeEquals($this->quoteRepositoryMock, 'quoteRepository', $instance);
    }

    /**
     * @test
     * that afterUpdateOrderPayment executes {@see \Webkul\Odoomagentoconnect\Observer\SalesOrderAfterObserver}
     * if transaction state is changed to authorized
     *
     * @dataProvider afterUpdateOrderPayment_onlyForAuthorizedState_executesOdooObserverProvider
     * @covers ::afterUpdateOrderPayment
     *
     * @param string      $transactionState
     * @param string      $prevTransactionState
     * @param Quote|false $quote
     * @param bool        $executesObserver
     */
    public function afterUpdateOrderPayment_onlyForAuthorizedState_executesOdooObserver(
        $transactionState,
        $prevTransactionState,
        $quote,
        $executesObserver
    ) {
        $this->orderMock->expects($executesObserver ? static::once() : static::never())
            ->method('getQuoteId')->willReturn(self::QUOTE_ID);
        if ($quote) {
            $this->quoteRepositoryMock->expects($executesObserver ? static::once() : static::never())
                ->method('get')->with(self::QUOTE_ID)
                ->willReturn($quote);
        } else {
            $this->quoteRepositoryMock->expects($executesObserver ? static::once() : static::never())
                ->method('get')->with(self::QUOTE_ID)
                ->willThrowException(NoSuchEntityException::singleField('cartId', self::QUOTE_ID));
        }
        $transaction = new \stdClass();
        $this->observerMock->expects($executesObserver ? static::once() : static::never())->method('execute');
        $this->currentMock->afterUpdateOrderPayment(
            $this->observerMock,
            $this->orderMock,
            $transaction,
            $transactionState,
            $prevTransactionState
        );
    }

    /**
     * Data provider for {@see afterUpdateOrderPayment_onlyForAuthorizedState_executesOdooObserver}
     */
    public function afterUpdateOrderPayment_onlyForAuthorizedState_executesOdooObserverProvider()
    {
        return [
            'Authorized from Pending, without quote, calls observer' => [
                'transactionState'    => \Bolt\Boltpay\Helper\Order::TS_AUTHORIZED,
                'preTransactionState' => \Bolt\Boltpay\Helper\Order::TS_PENDING,
                'quote'               => false,
                'executesObserver'    => true
            ],
            'Authorized from Pending, calls observer'                => [
                'transactionState'    => \Bolt\Boltpay\Helper\Order::TS_AUTHORIZED,
                'preTransactionState' => \Bolt\Boltpay\Helper\Order::TS_PENDING,
                'quote'               => $this->quoteMock,
                'executesObserver'    => true
            ],
            'Canceled from Pending, does not call observer'          => [
                'transactionState'    => \Bolt\Boltpay\Helper\Order::TS_CANCELED,
                'preTransactionState' => \Bolt\Boltpay\Helper\Order::TS_PENDING,
                'quote'               => $this->quoteMock,
                'executesObserver'    => false
            ],
        ];
    }
}

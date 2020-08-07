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

namespace Bolt\Boltpay\Test\Unit\Controller\Adminhtml\Order;

use PHPUnit\Framework\TestCase;
use Bolt\Boltpay\Controller\Adminhtml\Order\ReceivedUrl;
use Magento\Sales\App\Action;
use Magento\Backend\App\Action\Context;
use Bolt\Boltpay\Helper\Log as LogHelper;
use Bolt\Boltpay\Helper\Cart as CartHelper;
use Bolt\Boltpay\Helper\Config as ConfigHelper;
use Bolt\Boltpay\Helper\Bugsnag;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Sales\Model\Order;
use Bolt\Boltpay\Helper\Order as OrderHelper;
use Magento\Backend\Model\UrlInterface;
use Bolt\Boltpay\Test\Unit\TestHelper;
use PHPUnit_Framework_MockObject_MockObject;

/**
 * @coversDefaultClass \Bolt\Boltpay\Controller\Adminhtml\Order\ReceivedUrl
 */
class ReceivedUrlTest extends TestCase
{
    const ORDER_ID = '1234';
    const STORE_ID = '1';

    /**
     * @var Context
     */
    private $context;

    /**
     * @var ConfigHelper
     */
    private $configHelper;

    /**
     * @var CartHelper
     */
    private $cartHelper;

    /**
     * @var Bugsnag
     */
    private $bugsnag;

    /**
     * @var LogHelper
     */
    private $logHelper;

    /**
     * @var CheckoutSession
     */
    private $checkoutSession;

    /**
     * @var OrderHelper
     */
    private $orderHelper;

    /**
     * @var UrlInterface
     */
    private $backendUrl;

    /**
     * @var ReceivedUrl
     */
    private $currentMock;

    private $order;

    public function setUp()
    {
        $this->context = $this->createMock(Context::class, ['getBackendUrl']);
        $this->backendUrl = $this->createMock(UrlInterface::class, ['getUrl']);
        $this->context->method('getBackendUrl')->willReturn($this->backendUrl);
        $this->configHelper = $this->createMock(ConfigHelper::class);
        $this->cartHelper = $this->createMock(CartHelper::class);
        $this->bugsnag = $this->createMock(Bugsnag::class);
        $this->logHelper = $this->createMock(LogHelper::class);
        $this->checkoutSession = $this->createMock(CheckoutSession::class);
        $this->orderHelper = $this->createMock(OrderHelper::class);
        $this->order = $this->createPartialMock(Order::class, ['getStoreId','getId']);
        $this->currentMock = $this->getMockBuilder(ReceivedUrl::class)
            ->setConstructorArgs([
                $this->context,
                $this->configHelper,
                $this->cartHelper,
                $this->bugsnag,
                $this->logHelper,
                $this->checkoutSession,
                $this->orderHelper
            ])
            ->disableProxyingToOriginalMethods()
            ->getMock();
    }

    /**
     * @test
     */
    public function getErrorRedirectUrl()
    {
        $this->backendUrl->expects(self::once())->method('getUrl')
            ->with('sales/order', ['_secure' => true])->willReturnSelf();
        TestHelper::invokeMethod($this->currentMock, 'getErrorRedirectUrl');
    }

    /**
     * @test
     */
    public function getRedirectUrl()
    {
        $this->backendUrl->expects(self::once())->method('getUrl')->with('sales/order/view', [
            '_secure' => true,
            'order_id' => self::ORDER_ID,
            'store_id' => self::STORE_ID
        ]);
        $this->order->expects(self::once())->method('getId')->willReturn(self::ORDER_ID);
        $this->order->expects(self::once())->method('getStoreId')->willReturn(self::STORE_ID);

        TestHelper::invokeMethod($this->currentMock, 'getRedirectUrl', [$this->order]);
    }

    /**
     * @test
     * that _processUrlKeys always returns true
     *
     * @covers ::_processUrlKeys
     */
    public function _processUrlKeys_always_returnsTrue()
    {
        /** @var PHPUnit_Framework_MockObject_MockObject|ReceivedUrl $currentMock */
        $currentMock = $this->getMockBuilder(ReceivedUrl::class)
            ->disableOriginalConstructor()->setMethods(null)->getMock();
        static::assertTrue($currentMock->_processUrlKeys());
    }
}

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

namespace Bolt\Boltpay\Test\Unit\Model;

use Bolt\Boltpay\Model\CustomerCreditCard;
use PHPUnit\Framework\TestCase;
use Bolt\Boltpay\Model\Request as BoltRequest;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\DataObjectFactory;
use Magento\Sales\Model\Order;
use Bolt\Boltpay\Helper\Config as ConfigHelper;
use Bolt\Boltpay\Helper\Api as ApiHelper;
use Bolt\Boltpay\Helper\Cart as CartHelper;
use Magento\Framework\Model\Context;
use Magento\Framework\Registry;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Data\Collection\AbstractDb;

class CustomerCreditCardTest extends TestCase
{
    const QUOTE_ID = '111';
    const QUOTE_GRAND_TOTAL = '112';
    const ORDER_CURRENCY_CODE = 'USD';
    const CONSUMER_ID = '113';
    const CREDIT_CARD_ID = '114';

    /**
     * @var \Bolt\Boltpay\Model\CustomerCreditCard
     */
    private $mockCustomerCreditCard;

    /**
     * @var BoltRequest
     */
    private $boltRequest;

    /**
     * @var Order
     */
    private $order;

    /**
     * @var DataObjectFactory
     */
    private $dataObjectFactory;

    /**
     * @var Context
     */
    private $context;

    /**
     * @var AbstractResource
     */
    private $resource;

    /**
     * @var AbstractDb
     */
    private $resourceCollection;

    /**
     * @var Registry
     */
    private $registry;

    /**
     * @var ConfigHelper
     */
    private $configHelper;

    /**
     * @var ApiHelper
     */
    private $apiHelper;

    /**
     * @var CartHelper
     */
    private $cartHelper;

    /**
     * Setup for CustomerCreditCardTest Class
     */
    public function setUp()
    {
        $this->dataObjectFactory = $this->getMockBuilder(DataObjectFactory::class)
            ->disableOriginalConstructor()
            ->setMethods(['create','setApiData', 'setDynamicApiUrl','setApiKey'])
            ->getMock();

        $this->order = $this->createPartialMock(
            Order::class,
            [
                'getQuoteId',
                'getOrderCurrencyCode',
                'getGrandTotal'
            ]
        );
        $this->boltRequest = $this->createMock(BoltRequest::class);
        $this->registry = $this->createMock(Registry::class);
        $this->context = $this->createMock(Context::class);
        $this->configHelper = $this->createMock(ConfigHelper::class);
        $this->apiHelper = $this->getMockBuilder(ApiHelper::class)
            ->disableOriginalConstructor()
            ->setMethods(['buildRequest','sendRequest'])
            ->getMock();
        $this->cartHelper = $this->createMock(CartHelper::class);
        $this->resource = $this->createMock(AbstractResource::class);
        $this->resourceCollection = $this->createMock(AbstractDb::class);

        $this->mockCustomerCreditCard = $this->getMockBuilder(CustomerCreditCard::class)
            ->setConstructorArgs([
                $this->context,
                $this->registry,
                $this->configHelper,
                $this->dataObjectFactory,
                $this->apiHelper,
                $this->cartHelper,
                $this->resource,
                $this->resourceCollection,
                []
            ])
            ->setMethods(['_init'])
            ->getMock();
    }

    /**
     * @test
     */
    public function testConstruct()
    {
        $this->mockCustomerCreditCard->expects($this->once())->method('_init')
            ->with('Bolt\Boltpay\Model\ResourceModel\CustomerCreditCard')
            ->willReturnSelf();

        $testMethod = new \ReflectionMethod(CustomerCreditCard::class, '_construct');
        $testMethod->setAccessible(true);
        $testMethod->invokeArgs($this->mockCustomerCreditCard, []);
        $this->assertTrue(class_exists('Bolt\Boltpay\Model\ResourceModel\CustomerCreditCard'));
    }

    /**
     * @test
     */
    public function recharge_withException(){
        $this->configHelper->expects(self::once())->method('getApiKey')->willReturnSelf();

        $this->order->expects(self::once())->method('getQuoteId')->willReturn(self::QUOTE_ID);
        $this->order->expects(self::once())->method('getGrandTotal')->willReturn(self::QUOTE_GRAND_TOTAL);
        $this->order->expects(self::once())->method('getOrderCurrencyCode')->willReturn(self::ORDER_CURRENCY_CODE);

        $this->dataObjectFactory->expects(self::once())->method('create')->willReturnSelf();
        $this->dataObjectFactory->expects(self::once())->method('setDynamicApiUrl')->with(ApiHelper::API_AUTHORIZE_TRANSACTION)->willReturnSelf();
        $this->dataObjectFactory->expects(self::once())->method('setApiKey')->willReturnSelf();

        $this->apiHelper->expects(self::once())->method('buildRequest')->with($this->dataObjectFactory)->willReturn($this->boltRequest);
        $this->apiHelper->expects(self::once())->method('sendRequest')->willReturn(null);

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Bad payment response from boltpay');

        $this->mockCustomerCreditCard->recharge($this->order);
    }

    /**
     * @test
     */
    public function recharge(){
        $this->configHelper->expects(self::once())->method('getApiKey')->willReturnSelf();

        $this->order->expects(self::once())->method('getQuoteId')->willReturn(self::QUOTE_ID);
        $this->order->expects(self::once())->method('getGrandTotal')->willReturn(self::QUOTE_GRAND_TOTAL);
        $this->order->expects(self::once())->method('getOrderCurrencyCode')->willReturn(self::ORDER_CURRENCY_CODE);

        $this->dataObjectFactory->expects(self::once())->method('create')->willReturnSelf();
        $this->dataObjectFactory->expects(self::once())->method('setDynamicApiUrl')->with(ApiHelper::API_AUTHORIZE_TRANSACTION)->willReturnSelf();
        $this->dataObjectFactory->expects(self::once())->method('setApiKey')->willReturnSelf();

        $this->apiHelper->expects(self::once())->method('buildRequest')->with($this->dataObjectFactory)->willReturn($this->boltRequest);
        $this->apiHelper->expects(self::once())->method('sendRequest')->willReturn(true);

        $this->assertTrue($this->mockCustomerCreditCard->recharge($this->order));
    }
}
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

namespace Bolt\Boltpay\Test\Unit\Model\Api;

use Bolt\Boltpay\Model\Api\ShippingMethods as BoltShippingMethods;
use Magento\Framework\Phrase;
use PHPUnit\Framework\TestCase;
use Bolt\Boltpay\Helper\Hook as HookHelper;
use Bolt\Boltpay\Helper\Cart as CartHelper;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\TotalsCollector;
use Magento\Directory\Model\Region as RegionModel;
use Magento\Framework\Exception\LocalizedException;
use Bolt\Boltpay\Api\Data\ShippingOptionsInterfaceFactory;
use Bolt\Boltpay\Api\Data\ShippingTaxInterfaceFactory;
use Magento\Quote\Model\Cart\ShippingMethodConverter;
use Bolt\Boltpay\Api\Data\ShippingOptionInterfaceFactory;
use Bolt\Boltpay\Helper\Bugsnag;
use Bolt\Boltpay\Helper\Log as LogHelper;
use Magento\Framework\Webapi\Rest\Response;
use Bolt\Boltpay\Helper\Config as ConfigHelper;
use Magento\Framework\Webapi\Rest\Request;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\Pricing\Helper\Data as PriceHelper;
use Bolt\Boltpay\Model\ErrorResponse as BoltErrorResponse;
use Bolt\Boltpay\Helper\Session as SessionHelper;

/**
 * Class ShippingMethodsTest
 *
 * @package Bolt\Boltpay\Test\Unit\Model\Api
 */
class ShippingMethodsTest extends TestCase
{
    /**
     * @var BoltShippingMethods
     */
    private $currentMock;

    private $hookHelper;

    private $regionModel;

    private $factoryShippingOptionsMock;

    private $shippingTaxInterfaceFactory;

    private $shippingOptionInterfaceFactory;

    private $totalsCollector;

    private $converter;

    private $logHelper;

    private $errorResponse;

    private $response;

    private $configHelper;

    private $request;

    private $cache;

    private $priceHelper;

    private $sessionHelper;

    private $bugsnag;

    /**
     * @inheritdoc
     */
    public function setUp()
    {
        $this->factoryShippingOptionsMock = $this->getMockBuilder(ShippingOptionsInterfaceFactory::class)
            ->disableOriginalConstructor()
            ->setMethods(['create'])
            ->getMock();

        $this->shippingTaxInterfaceFactory = $this->getMockBuilder(ShippingTaxInterfaceFactory::class)
            ->disableOriginalConstructor()
            ->setMethods(['create'])
            ->getMock();

        $this->shippingOptionInterfaceFactory = $this->getMockBuilder(ShippingOptionInterfaceFactory::class)
            ->setMethods(['create'])
            ->disableOriginalConstructor()
            ->getMock();

        $this->hookHelper = $this->createMock(HookHelper::class);
        $this->regionModel = $this->createMock(RegionModel::class);
        $this->totalsCollector = $this->createMock(TotalsCollector::class);
        $this->converter = $this->createMock(ShippingMethodConverter::class);
        $this->logHelper = $this->createMock(LogHelper::class);
        $this->errorResponse = $this->createMock(BoltErrorResponse::class);
        $this->response = $this->createMock(Response::class);
        $this->configHelper = $this->createMock(ConfigHelper::class);
        $this->request = $this->createMock(Request::class);
        $this->cache = $this->createMock(CacheInterface::class);
        $this->priceHelper = $this->createMock(PriceHelper::class);
        $this->sessionHelper = $this->createMock(SessionHelper::class);

        $this->bugsnag = $this->getMockBuilder(Bugsnag::class)
            ->setMethods(['notifyException'])
            ->disableOriginalConstructor()
            ->getMock();
        $this->bugsnag->method('notifyException')
            ->willReturnSelf();
    }

    public function testGetShippingMethodsIfQuoteEmpty()
    {
        $quoteId = 1001;
        $cart = [
            'display_id' => '100050001 / '.$quoteId
        ];
        $shippingAddress = [
            'street_address1' => 'test'
        ];

        $quote = $this->getMockBuilder(Quote::class)
            ->setMethods(['getId'])
            ->disableOriginalConstructor()
            ->getMock();

        $quote->method('getId')
            ->willReturn(false);

        $cartHelper = $this->getMockBuilder(CartHelper::class)
            ->setMethods(['getQuoteById', 'validateEmail'])
            ->disableOriginalConstructor()
            ->getMock();

        $cartHelper->method('validateEmail')
            ->withAnyParameters()
            ->willReturn(true);
        $cartHelper->method('getQuoteById')
            ->willReturn(false);

        $methods = ['sendErrorResponse', 'proceedWithHook', 'checkCartItems', 'getQuoteById', 'notifyException',
            'validateQuote', 'loadSessionByQuote', 'throwUnknownQuoteIdException', 'catchExceptionAndSendError'
        ];
        $this->currentMock = $this->getMockBuilder(BoltShippingMethods::class)
            ->setMethods($methods)
            ->setConstructorArgs([
                $this->hookHelper,
                $this->regionModel,
                $this->factoryShippingOptionsMock,
                $this->shippingTaxInterfaceFactory,
                $cartHelper,
                $this->totalsCollector,
                $this->converter,
                $this->shippingOptionInterfaceFactory,
                $this->bugsnag,
                $this->logHelper,
                $this->errorResponse,
                $this->response,
                $this->configHelper,
                $this->request,
                $this->cache,
                $this->priceHelper,
                $this->sessionHelper
            ])
            ->getMock();

        $message = new Phrase('Unprocessable Entity: Unknown quote id: ' . $quoteId);
        $this->currentMock->method('throwUnknownQuoteIdException')
            ->willThrowException(new LocalizedException($message));
        $this->currentMock->method('catchExceptionAndSendError')
            ->withAnyParameters()
            ->willThrowException(new LocalizedException($message));

        try {
            $this->currentMock->getShippingMethods($cart, $shippingAddress);
        } catch (\Exception $e) {
            $this->assertInstanceOf(\Exception::class, $e);
            $this->assertEquals('Unprocessable Entity: Unknown quote id: '.$quoteId, $e->getMessage());
        }
    }

    public function testGetShippingMethods()
    {
        $quoteId = 1001;
        $parentQuoteId = 1000;

        $cart = [
            'display_id' => '100050001 / '.$quoteId
        ];
        $shippingAddress = [
            'company' => "",
            'country' => "United States",
            'country_code' => "US",
            'email' => "integration@bolt.com",
            'first_name' => "YevhenBolt",
            'last_name' => "BoltTest2",
            'locality' => "New York",
            'phone' => "+1 231 231 1234",
            'postal_code' => "10001",
            'region' => "New York",
            'street_address1' => "228 5th Avenue",
            'street_address2' => "",
        ];

        $quoteItem = $this->getMockBuilder(\Magento\Quote\Model\Quote\Item::class)
            ->setMethods(['getSku', 'getQty'])
            ->disableOriginalConstructor()
            ->getMock();
        $quoteItem->method('getSku')
            ->willReturn('TestProduct');
        $quoteItem->method('getQty')
            ->willReturn(1);

        $quoteMethods = [
            'getId', 'getBoltParentQuoteId', 'getSubtotal', 'getAllVisibleItems',
            'getAppliedRuleIds', 'isVirtual', 'getShippingAddress'
        ];
        $quote = $this->getMockBuilder(Quote::class)
            ->setMethods($quoteMethods)
            ->disableOriginalConstructor()
            ->getMock();

        $quote->method('getId')
            ->willReturn($quoteId);
        $quote->method('getBoltParentQuoteId')
            ->willReturn($parentQuoteId);
        $quote->method('getSubtotal')
            ->willReturn(100);
        $quote->method('getAllVisibleItems')
            ->willReturn([$quoteItem]);
        $quote->method('getAppliedRuleIds')
            ->willReturn('2,3');
        $quote->method('isVirtual')
            ->willReturn(false);
        $quote->method('getShippingAddress')
            ->willReturn($shippingAddress);

        $cartHelper = $this->getMockBuilder(CartHelper::class)
            ->setMethods(['getQuoteById', 'validateEmail'])
            ->disableOriginalConstructor()
            ->getMock();

        $cartHelper->method('validateEmail')
            ->withAnyParameters()
            ->willReturn(true);
        $cartHelper->method('getQuoteById')
            ->with($quoteId)
            ->willReturn($quote);

        $configHelper = $this->getMockBuilder(ConfigHelper::class)
            ->setMethods(['getPrefetchShipping', 'getPrefetchAddressFields'])
            ->disableOriginalConstructor()
            ->getMock();
        $configHelper->method('getPrefetchShipping')
            ->willReturn(true);
        $configHelper->method('getPrefetchAddressFields')
            ->willReturn('');

        $methods = ['sendErrorResponse', 'proceedWithHook', 'checkCartItems', 'getQuoteById',
            'notifyException', 'validateQuote', 'loadSessionByQuote', 'throwQuoteIdException',
            'validateAddressData', 'shippingEstimation'
        ];
        $this->currentMock = $this->getMockBuilder(BoltShippingMethods::class)
            ->setMethods($methods)
            ->setConstructorArgs([
                $this->hookHelper,
                $this->regionModel,
                $this->factoryShippingOptionsMock,
                $this->shippingTaxInterfaceFactory,
                $cartHelper,
                $this->totalsCollector,
                $this->converter,
                $this->shippingOptionInterfaceFactory,
                $this->bugsnag,
                $this->logHelper,
                $this->errorResponse,
                $this->response,
                $configHelper,
                $this->request,
                $this->cache,
                $this->priceHelper,
                $this->sessionHelper
            ])
            ->getMock();

        $this->currentMock->method('validateAddressData')
            ->willReturnSelf();

        $option = new \Bolt\Boltpay\Model\Api\Data\ShippingOption();
        $option
            ->setService('Flat Rate - Fixed')
            ->setCost(5600)
            ->setReference('flatrate_flatrate')
            ->setTaxAmount(0)
        ;

        $shippingOptionData = [$option];

        $this->currentMock->method('shippingEstimation')
            ->willReturn($shippingOptionData);

        $result = $this->currentMock->getShippingMethods($cart, $shippingAddress);

        $this->assertEquals($result, $shippingOptionData);
    }
}

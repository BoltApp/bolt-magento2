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

namespace Bolt\Boltpay\Test\Unit\Helper;

use Bolt\Boltpay\Helper\Bugsnag;
use Bolt\Boltpay\Helper\Cart as BoltHelperCart;
use Bolt\Boltpay\Helper\Config;
use Bolt\Boltpay\Helper\Log;
use Magento\Catalog\Model\Product;
use Magento\Quote\Model\Quote;
use \PHPUnit\Framework\TestCase;
use Magento\Framework\App\Helper\Context as ContextHelper;
use Magento\Framework\Session\SessionManagerInterface as CheckoutSession;
use Magento\Catalog\Model\ProductRepository;
use Bolt\Boltpay\Helper\Api as ApiHelper;
use Bolt\Boltpay\Helper\Config as ConfigHelper;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Helper\Context;
use Bolt\Boltpay\Helper\Log as LogHelper;
use Magento\Framework\DataObjectFactory;
use Magento\Framework\View\Element\BlockFactory;
use Magento\Store\Model\App\Emulation;
use Magento\Quote\Model\QuoteFactory;
use Magento\Quote\Model\Quote\TotalsCollector;
use Magento\Quote\Api\CartRepositoryInterface as QuoteCartRepository;
use Magento\Sales\Api\OrderRepositoryInterface as OrderRepository;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Quote\Model\ResourceModel\Quote as QuoteResource;
use Bolt\Boltpay\Helper\Session as SessionHelper;
use Magento\Checkout\Helper\Data as CheckoutHelper;
use Bolt\Boltpay\Helper\Discount as DiscountHelper;

/**
 * Class ConfigTest
 *
 * @package Bolt\Boltpay\Test\Unit\Helper
 */
class CartTest extends TestCase
{
    const QUOTE_ID = 1001;
    const PARENT_QUOTEI_D = 1000;
    const PRODUCT_ID = 20102;
    const PRODUCT_PRICE = 100;

    private $contextHelper;
    private $checkoutSession;
    private $apiHelper;
    private $configHelper;
    private $customerSession;
    private $logHelper;
    private $bugsnag;
    private $blockFactory;
    private $productRepository;
    private $appEmulation;
    private $dataObjectFactory;
    private $quoteFactory;
    private $totalsCollector;
    private $quoteCartRepository;
    private $orderRepository;
    private $searchCriteriaBuilder;
    private $quoteResource;
    private $sessionHelper;
    private $checkoutHelper;
    private $discountHelper;

    /**
     * @inheritdoc
     */
    public function setUp()
    {
        $this->contextHelper = $this->createMock(ContextHelper::class);

        $this->checkoutSession = $this->createMock(CheckoutSession::class);
        $this->productRepository = $this->getProductRepositoryMock();

        $this->apiHelper = $this->createMock(ApiHelper::class);
        $this->configHelper = $this->createMock(ConfigHelper::class);
        $this->customerSession = $this->createMock(CustomerSession::class);
        $this->logHelper = $this->getMockBuilder(LogHelper::class)
            ->setMethods(['addInfoLog'])
            ->disableOriginalConstructor()
            ->getMock();
        $this->logHelper->method('addInfoLog')
            ->withAnyParameters()
            ->willReturnSelf();

        $this->bugsnag = $this->getMockBuilder(Bugsnag::class)
            ->setMethods(['notifyError', 'notifyException'])
            ->disableOriginalConstructor()
            ->getMock();
        $this->blockFactory = $this->getMockBuilder(BlockFactory::class)
            ->setMethods(['createBlock', 'getImage', 'getImageUrl'])
            ->disableOriginalConstructor()
            ->getMock();
        $this->blockFactory->method('createBlock')
            ->with('Magento\Catalog\Block\Product\ListProduct')
            ->willReturnSelf();
        $this->blockFactory->method('getImage')
            ->withAnyParameters()
            ->willReturnSelf();
        $this->blockFactory->method('getImageUrl')
            ->willReturn('no-image');

        $this->appEmulation = $this->getMockBuilder(Emulation::class)
            ->setMethods(['stopEnvironmentEmulation', 'startEnvironmentEmulation'])
            ->disableOriginalConstructor()
            ->getMock();
        $this->dataObjectFactory = $this->getMockBuilder(DataObjectFactory::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->quoteFactory = $this->getMockBuilder(QuoteFactory::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->totalsCollector = $this->createMock(TotalsCollector::class);
        $this->quoteCartRepository = $this->createMock(QuoteCartRepository::class);
        $this->orderRepository = $this->createMock(OrderRepository::class);
        $this->searchCriteriaBuilder = $this->createMock(SearchCriteriaBuilder::class);
        $this->quoteResource = $this->createMock(QuoteResource::class);
        $this->sessionHelper = $this->createMock(SessionHelper::class);
        $this->checkoutHelper = $this->createMock(CheckoutHelper::class);
        $this->discountHelper = $this->createMock(DiscountHelper::class);
    }

    /**
     * @test
     */
    public function getCartData_multistepNoDiscount()
    {
        $billingAddress = $this->getBillingAddress();
        $shippingAddress = $this->getShippingAddress();
        $quote = $this->getQuoteMock($billingAddress, $shippingAddress);

        $this->checkoutSession = $this->getMockBuilder(\Magento\Framework\Session\SessionManager::class)
            ->setMethods(['getQuote'])
            ->disableOriginalConstructor()
            ->getMock();
        $this->checkoutSession->expects($this->any())
            ->method('getQuote')
            ->willReturn($quote);

        $this->searchCriteriaBuilder = $this->getMockBuilder(SearchCriteriaBuilder::class)
            ->setMethods(['addFilter', 'create'])
            ->disableOriginalConstructor()
            ->getMock();
        $this->searchCriteriaBuilder->expects($this->once())
            ->method('addFilter')
            ->withAnyParameters()
            ->willReturnSelf();
        $this->searchCriteriaBuilder->expects($this->once())
            ->method('create')
            ->willReturn($this->createMock(\Magento\Framework\Api\SearchCriteria::class));

        $methods = ['getList', 'getItems', 'getForCustomer', 'getActive',
            'getActiveForCustomer', 'save', 'delete', 'get'];
        $this->quoteCartRepository = $this->getMockBuilder(QuoteCartRepository::class)
            ->setMethods($methods)
            ->disableOriginalConstructor()
            ->disableOriginalClone()
            ->getMock();
        $this->quoteCartRepository->expects($this->any())
            ->method('getList')
            ->with($this->createMock(\Magento\Framework\Api\SearchCriteria::class))
            ->willReturnSelf();
        $this->quoteCartRepository->expects($this->any())
            ->method('getItems')
            ->will($this->returnValue([$quote]));

        $currentMock = new BoltHelperCart(
            $this->contextHelper,
            $this->checkoutSession,
            $this->productRepository,
            $this->apiHelper,
            $this->configHelper,
            $this->customerSession,
            $this->logHelper,
            $this->bugsnag,
            $this->dataObjectFactory,
            $this->blockFactory,
            $this->appEmulation,
            $this->quoteFactory,
            $this->totalsCollector,
            $this->quoteCartRepository,
            $this->orderRepository,
            $this->searchCriteriaBuilder,
            $this->quoteResource,
            $this->sessionHelper,
            $this->checkoutHelper,
            $this->discountHelper
        );

        $paymentOnly = false;
        $placeOrderPayload = '';
        $immutableQuote = $quote;

        $result = $currentMock->getCartData($paymentOnly, $placeOrderPayload, $immutableQuote);

        $expected = [
            'order_reference' => self::PARENT_QUOTEI_D,
            'display_id' => '100010001 / '.self::QUOTE_ID,
            'currency' => '$',
            'items' => [[
                'reference' => self::PRODUCT_ID,
                'name'  => 'Test Product',
                'total_amount'  => 10000,
                'unit_price'    => 10000,
                'quantity'      => 1,
                'sku'           => 'TestProduct',
                'type'          => 'physical',
                'description'   => 'Product Description',
                'image_url'     => 'no-image'
            ]],
            "billing_address" => [
                'first_name' => "IntegrationBolt",
                'last_name' => "BoltTest",
                'company' => "",
                'phone' => "132 231 1234",
                'street_address1' => "228 7th Avenue",
                'street_address2' => null,
                'locality' => "New York",
                'region' => "New York",
                'postal_code' => "10011",
                'country_code' => "US",
                'email'=> "integration@bolt.com"
            ],
            'discounts' => [],
            'magento_store_id' => 1,
            'total_amount' => 10000,
            'tax_amount' => 0
        ];

        $this->assertEquals($expected, $result);
    }

    /**
     * Get quote mock with quote items
     *
     * @param $billingAddress
     * @param $shippingAddress
     * @return \PHPUnit_Framework_MockObject_MockObject
     */
    private function getQuoteMock($billingAddress, $shippingAddress)
    {
        $quoteItem = $this->getMockBuilder(\Magento\Quote\Model\Quote\Item::class)
            ->setMethods([
                'getSku', 'getQty', 'getCalculationPrice', 'getName', 'getIsVirtual',
                'getProductId', 'getProduct'
            ])
            ->disableOriginalConstructor()
            ->getMock();
        $quoteItem->method('getName')
            ->willReturn('Test Product');
        $quoteItem->method('getSku')
            ->willReturn('TestProduct');
        $quoteItem->method('getQty')
            ->willReturn(1);
        $quoteItem->method('getCalculationPrice')
            ->willReturn(self::PRODUCT_PRICE);
        $quoteItem->method('getIsVirtual')
            ->willReturn(false);
        $quoteItem->method('getProductId')
            ->willReturn(self::PRODUCT_ID);
        $quoteItem->method('getProduct')
            ->willReturn($this->getProductMock());

        $quoteMethods = [
            'getId', 'getBoltParentQuoteId', 'getSubtotal', 'getAllVisibleItems',
            'getAppliedRuleIds', 'isVirtual', 'getShippingAddress', 'collectTotals',
            'getQuoteCurrencyCode', 'getBillingAddress', 'getReservedOrderId', 'getTotals',
            'getStoreId', 'getUseRewardPoints', 'getUseCustomerBalance'
        ];
        $quote = $this->getMockBuilder(Quote::class)
            ->setMethods($quoteMethods)
            ->disableOriginalConstructor()
            ->getMock();

        $quote->method('getId')
            ->willReturn(self::QUOTE_ID);
        $quote->method('getReservedOrderId')
            ->willReturn('100010001');
        $quote->method('getBoltParentQuoteId')
            ->willReturn(self::PARENT_QUOTEI_D);
        $quote->method('getSubtotal')
            ->willReturn(self::PRODUCT_PRICE);
        $quote->method('getAllVisibleItems')
            ->willReturn([$quoteItem]);
        $quote->method('getAppliedRuleIds')
            ->willReturn('2,3');
        $quote->method('isVirtual')
            ->willReturn(false);
        $quote->method('getBillingAddress')
            ->willReturn($billingAddress);
        $quote->method('getShippingAddress')
            ->willReturn($shippingAddress);
        $quote->method('getQuoteCurrencyCode')
            ->willReturn('$');
        $quote->method('collectTotals')
            ->willReturnSelf();
        $quote->method('getTotals')
            ->willReturn([]);
        $quote->expects($this->any())
            ->method('getStoreId')
            ->will($this->returnValue("1"));
        $quote->method('getUseRewardPoints')
            ->willReturn(false);
        $quote->method('getUseCustomerBalance')
            ->willReturn(false);

        return $quote;
    }

    /**
     * @return \PHPUnit_Framework_MockObject_MockObject
     */
    private function getBillingAddress()
    {
        $addressData = $this->getAddressData();
        $billingAddress = $this->getMockBuilder(Quote\Address::class)
            ->setMethods([
                'getFirstname', 'getLastname', 'getCompany', 'getTelephone', 'getStreetLine',
                'getCity', 'getRegion', 'getPostcode', 'getCountryId', 'getEmail',
                'getDiscountAmount'
            ])
            ->disableOriginalConstructor()
            ->getMock();

        $billingAddress->method('getFirstname')
            ->willReturn($addressData['first_name']);
        $billingAddress->method('getLastname')
            ->willReturn($addressData['last_name']);
        $billingAddress->method('getCompany')
            ->willReturn($addressData['company']);
        $billingAddress->method('getTelephone')
            ->willReturn($addressData['phone']);
        $billingAddress->method('getStreetLine')
            ->will($this->returnValueMap([
                [1, $addressData['street_address1']],
                [2, $addressData['street_address2']]
            ]));
        $billingAddress->method('getCity')
            ->willReturn($addressData['locality']);
        $billingAddress->method('getRegion')
            ->willReturn($addressData['region']);
        $billingAddress->method('getPostcode')
            ->willReturn($addressData['postal_code']);
        $billingAddress->method('getCountryId')
            ->willReturn($addressData['country_code']);
        $billingAddress->method('getEmail')
            ->willReturn($addressData['email']);
        $billingAddress->method('getDiscountAmount')
            ->willReturn(0);

        return $billingAddress;
    }

    /**
     * @return \PHPUnit_Framework_MockObject_MockObject
     */
    private function getShippingAddress()
    {
        return $this->getBillingAddress();
    }

    /**
     * @return array
     */
    private function getAddressData()
    {
        return [
            'company' => "",
            'country' => "United States",
            'country_code' => "US",
            'email' => "integration@bolt.com",
            'first_name' => "IntegrationBolt",
            'last_name' => "BoltTest",
            'locality' => "New York",
            'phone' => "132 231 1234",
            'postal_code' => "10011",
            'region' => "New York",
            'street_address1' => "228 7th Avenue",
            'street_address2' => null,
        ];
    }

    /**
     * @return \PHPUnit_Framework_MockObject_MockObject
     */
    private function getProductRepositoryMock()
    {
        $product = $this->getProductMock();
        $this->productRepository = $this->getMockBuilder(ProductRepository::class)
            ->setMethods(['getById'])
            ->disableOriginalConstructor()
            ->getMock();

        $this->productRepository->method('getById')
            ->with(self::PRODUCT_ID)
            ->willReturn($product);

        return $this->productRepository;
    }

    /**
     * @return \PHPUnit_Framework_MockObject_MockObject
     */
    private function getProductMock()
    {
        $product = $this->getMockBuilder(Product::class)
            ->setMethods(['getId', 'getDescription', 'getTypeInstance', 'getOrderOptions'])
            ->disableOriginalConstructor()
            ->getMock();

        $product->method('getId')
            ->willReturn(self::PRODUCT_ID);
        $product->method('getDescription')
            ->willReturn('Product Description');
        $product->method('getTypeInstance')
            ->with(true)
            ->willReturnSelf();
        $product->method('getOrderOptions')
            ->withAnyParameters()
            ->willReturn([]);

        return $product;
    }
}

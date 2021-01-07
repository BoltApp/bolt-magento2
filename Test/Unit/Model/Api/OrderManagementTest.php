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

namespace Bolt\Boltpay\Test\Unit\Model\Api;

use Bolt\Boltpay\Exception\BoltException;
use Bolt\Boltpay\Test\Unit\TestHelper;
use Bolt\Boltpay\Test\Unit\BoltTestCase;
use Bolt\Boltpay\Helper\Order as OrderHelper;
use Bolt\Boltpay\Helper\Cart as CartHelper;
use Bolt\Boltpay\Helper\Hook as HookHelper;
use Bolt\Boltpay\Helper\ArrayHelper;
use Bolt\Boltpay\Helper\Shared\CurrencyUtils;
use Bolt\Boltpay\Helper\FeatureSwitch\Decider;
use Bolt\Boltpay\Model\Api\OrderManagement as BoltOrderManagement;
use Bolt\Boltpay\Model\ResponseFactory as BoltResponseFactory;
use Bolt\Boltpay\Model\RequestFactory as BoltRequestFactory;
use Bolt\Boltpay\Test\Unit\TestUtils;
use Bolt\Boltpay\Model\Payment as BoltPayment;

use Magento\Catalog\Model\Product;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Model\ResourceModel\Product\Collection as ProductCollection;
use Magento\Catalog\Model\Product\Type as ProductType;
use Magento\Catalog\Model\Product\Visibility as ProductVisibility;
use Magento\Catalog\Model\Product\Attribute\Source\Status as ProductStatus;
use Magento\CatalogInventory\Model\StockRegistryStorage;
use Magento\CatalogInventory\Api\Data\StockItemInterface;
use Magento\Config\Model\ResourceModel\Config;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Model\ResourceModel\Customer\Collection as CustomerCollection;
use Magento\Framework\Registry;
use Magento\Framework\Webapi\Rest\Request;
use Magento\Framework\Webapi\Rest\Response;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address as QuoteAddress;
use Magento\Quote\Model\QuoteFactory;
use Magento\Quote\Model\ResourceModel\Quote\Collection as QuoteCollection;
use Magento\Quote\Api\Data\PaymentInterfaceFactory;
use Magento\Quote\Api\PaymentMethodManagementInterface;
use Magento\Quote\Api\Data\PaymentInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\ResourceModel\Order\Collection as OrderCollection;
use Magento\Sales\Model\Order\Address as OrderAddress;
use Magento\Sales\Model\Order\Payment as OrderPayment;
use Magento\Sales\Model\Order\Item as OrderItem;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Api\WebsiteRepositoryInterface;
use Magento\TestFramework\Helper\Bootstrap;



/**
 * Class OrderManagementTest
 *
 * @package Bolt\Boltpay\Test\Unit\Model\Api
 * @coversDefaultClass \Bolt\Boltpay\Model\Api\OrderManagement
 */
class OrderManagementTest extends BoltTestCase
{
    const BOLT_METHOD_CODE = 'boltpay';
    const TRANSACTION_ID = 'TAb6hFi4Upjft';
    const REFERENCE = 'B74N-PQXW-PYQ9';
    const CURRENCY = 'USD';
    const CURRENCY_USD_SYMBOL = '$';
    const PROCESSOR_VANTIV = 'vantiv';
    const TT_PAYMENT = 'cc_payment';
    const TT_CREDIT = 'cc_credit';
    
    const TRACE_ID_HEADER = 'KdekiEGku3j1mU21Mnsx5g==';

    const SECRET = '42425f51e0614482e17b6e913d74788eedb082e2e6f8067330b98ffa99adc809';
    const APIKEY = '3c2d5104e7f9d99b66e1c9c550f6566677bf81de0d6f25e121fdb57e47c2eafc';
    
    const PRODUCT_PRICE = 100;
    const PRODUCT_NAME = 'SIMPLE 1';
    const PRODUCT_SKU = 'simple_1';
    
    const QUOTE_SUBTOTAL = 100;
    const QUOTE_TOTAL = 105;
    const QUOTE_SHIPPING_TOTAL = 5;
    const QUOTE_SHIPPING_TAX_TOTAL = 0;
    const QUOTE_TAX_TOTAL = 0;
    const QUOTE_PRODUCT_QTY = 1;
    
    const ORDER_SUBTOTAL = 100;
    const ORDER_SHIPPING_TOTAL = 5;
    const ORDER_TAX = 0;
    const ORDER_SHIPPING_TAX = 0;
    const ORDER_TOTAL = 105;
    const ORDER_INCREMENTID = '100000001';
    
    /** @var Response */
    private $response;
    
    /** @var Request */
    private $request;
    
    /**
     * @var ObjectManagerInterface
     */
    private $objectManager;
    
    private $storeId;
    
    private $websiteId;
    
    /**
     * @inheritdoc
     */
    protected function setUpInternal()
    {
        if (!class_exists('\Magento\TestFramework\Helper\Bootstrap')) {
            return;    
        }
        
        $this->objectManager = Bootstrap::getObjectManager();

        $registry = $this->objectManager->get(Registry::class);
        $registry->unregister('isSecureArea');
        $registry->register('isSecureArea', true);

        $this->resetRequest();
        $this->resetResponse();
        $this->resetTestObj();
        
        $store = $this->objectManager->get(StoreManagerInterface::class);
        $this->storeId = $store->getStore()->getId();
        
        $websiteRepository = $this->objectManager->get(WebsiteRepositoryInterface::class);
        $this->websiteId = $websiteRepository->get('base')->getId();
        
        $this->setupBoltConfig();
        $this->getResponse();
    }
    
    protected function tearDownInternal()
    {
        if (!class_exists('\Magento\TestFramework\Helper\Bootstrap')) {
            return;    
        }
        
        $this->resetRequest();
        $this->resetResponse();
        $this->resetTestObj();
        
        $registry = $this->objectManager->get(Registry::class);
        $registry->unregister('isSecureArea');
        $registry->register('isSecureArea', false);
    }
    
    /**
     * Response getter
     *
     * @return \Magento\Framework\App\ResponseInterface
     */
    public function getResponse()
    {
        if (!$this->response) {
            $this->response = $this->objectManager->get(Response::class);
        }
        
        $this->response->sendResponse();
        
        return $this->response;
    }
    
    /**
     * Request getter
     *
     * @param array $bodyParams
     * 
     * @return \Magento\Framework\App\RequestInterface
     */
    public function createRequest($bodyParams)
    {
        if (!$this->request) {
            $this->request = $this->objectManager->get(Request::class);
        }
        
        $requestContent = json_encode($bodyParams);
         
        $computed_signature  = base64_encode(hash_hmac('sha256', $requestContent, self::SECRET, true));

        $this->request->getHeaders()->clearHeaders();
        $this->request->getHeaders()->addHeaderLine('X-Bolt-Hmac-Sha256',$computed_signature);
        $this->request->getHeaders()->addHeaderLine('X-bolt-trace-id',self::TRACE_ID_HEADER);
        $this->request->getHeaders()->addHeaderLine('Content-Type','application/json');

        $this->request->setParams($bodyParams);
        
        $this->request->setContent($requestContent);
      
        return $this->request;
    }
    
    public function resetRequest()
    {
        if (!$this->request) {
            $this->request = $this->objectManager->get(Request::class);
        }
     
        $this->objectManager->removeSharedInstance(Request::class);
        $this->request = null;

    }
    
    public function resetResponse()
    {
        if (!$this->response) {
            $this->response = $this->objectManager->get(Response::class);
        }
     
        $this->objectManager->removeSharedInstance(Response::class);
        $this->response = null;

    }
    
    public function resetTestObj()
    {        
        $quoteCollection = $this->objectManager->create(QuoteCollection::class);
        foreach ($quoteCollection as $quote) {
            $quote->delete();
        }
        
        /** @var $order \Magento\Sales\Model\Order */
        $orderCollection = $this->objectManager->create(OrderCollection::class);
        foreach ($orderCollection as $order) {
            $order->delete();
        }
        
        /** @var $product \Magento\Catalog\Model\Product */
        $productCollection = $this->objectManager->create(ProductCollection::class);
        foreach ($productCollection as $product) {
            $product->delete();
        }
        
        $customerCollection = $this->objectManager->create(CustomerCollection::class);
        foreach ($customerCollection as $customer) {
            $customer->delete();
        }        
        
        /** @var \Magento\CatalogInventory\Model\StockRegistryStorage $stockRegistryStorage */
        $stockRegistryStorage = $this->objectManager->get(StockRegistryStorage::class);
        //$stockRegistryStorage->clean();
        $stockRegistryStorage->removeStockItem(1);
    }
    
    public function setupBoltConfig()
    {
        $model = $this->objectManager->get(Config::class);
        $encryptor = $this->objectManager->get(EncryptorInterface::class);
        
        $secret = $encryptor->encrypt(self::SECRET);
        $apikey = $encryptor->encrypt(self::APIKEY);
        
        $model->saveConfig('payment/boltpay/signing_secret', $secret, ScopeInterface::SCOPE_STORE, $this->storeId);
        $model->saveConfig('payment/boltpay/api_key', $apikey, ScopeInterface::SCOPE_STORE, $this->storeId);
        $model->saveConfig('payment/boltpay/active', 1, ScopeInterface::SCOPE_STORE, $this->storeId);
    }
    
    private function getAddressInfo()
    {
        return array(
            "street_address1" => "street",
            "street_address2" => "",
            "locality"        => "Los Angeles",
            "region"          => "California",
            'region_code'     => 'CA',
            'region_id'       => '12',
            "postal_code"     => "11111",
            "country_code"    => "US",
            "country"         => "United States",
            "name"            => "lastname firstname",
            "first_name"      => "firstname",
            "last_name"       => "lastname",
            "phone_number"    => "11111111",
            "email_address"   => "admin@test.com",
        );
    }
    
    private function createRequestData($quoteId, $immutableQuoteId, $status, $display_id = '', $captures = NULL, $type = self::TT_PAYMENT)
    {
        $addressInfo = $this->getAddressInfo();
        $requetData = [
            'id'        => self::TRANSACTION_ID,
            'processor' => self::PROCESSOR_VANTIV,
            'amount'    => [
                'amount' => self::QUOTE_TOTAL*100,
                'currency' => self::CURRENCY,
                'currency_symbol' => self::CURRENCY_USD_SYMBOL,
            ],
            'date' => microtime(true) * 1000,
            "type" => $type,
            "status" => $status,
            "reference" => self::REFERENCE,
            'captures' => $captures,
            "order" => [
                "token" => "f34fff50f8b89db7cbe867326404d782fb688bdd8b26ab3affe8c0ba22b2ced5",
                "cart" => [
                    "order_reference" => $quoteId,
                    "display_id" => $display_id,
                    'total_amount'    => [
                        'amount' => self::QUOTE_TOTAL*100,
                    ],
                    "subtotal_amount" => self::QUOTE_SUBTOTAL*100,
                    "items" => [
                        [
                            "reference" => "",
                            "name" => "AdvancedPricingSimple 1",
                            "total_amount" => [
                                "amount" => self::QUOTE_PRODUCT_QTY*self::PRODUCT_PRICE*100,
                                "currency" => self::CURRENCY,
                                "currency_symbol" => self::CURRENCY_USD_SYMBOL,
                            ],
                            "unit_price" => [
                                "amount" => self::PRODUCT_PRICE*100,
                                "currency" => self::CURRENCY,
                                "currency_symbol" =>  self::CURRENCY_USD_SYMBOL,
                            ],
                            "quantity" => self::QUOTE_PRODUCT_QTY,
                            "type" => "physical"
                        ]
                    ],
                    'billing_address' =>
                    [
                        "street_address1" => $addressInfo["street_address1"],
                        "locality"        => $addressInfo["locality"],
                        "region"          => $addressInfo["region"],
                        "postal_code"     => $addressInfo["postal_code"],
                        "country_code"    => $addressInfo["country_code"],
                        "country"         => $addressInfo["country"],
                        "name"            => $addressInfo["name"],
                        "first_name"      => $addressInfo["first_name"],
                        "last_name"       => $addressInfo["last_name"],
                        "phone_number"    => $addressInfo["phone_number"],
                        "email_address"   => $addressInfo["email_address"],
                    ],
                    "shipments" => [
                        [
                            'cost'             => self::QUOTE_SHIPPING_TOTAL*100,
                            'tax_amount'       => self::QUOTE_SHIPPING_TAX_TOTAL*100,
                            "shipping_address" => [                                
                                "street_address1" => $addressInfo["street_address1"],
                                "locality"        => $addressInfo["locality"],
                                "region"          => $addressInfo["region"],
                                "postal_code"     => $addressInfo["postal_code"],
                                "country_code"    => $addressInfo["country_code"],
                                "country"         => $addressInfo["country"],
                                "name"            => $addressInfo["name"],
                                "first_name"      => $addressInfo["first_name"],
                                "last_name"       => $addressInfo["last_name"],
                            ],
                            'service'          => 'Flat Rate - Fixed',
                            'reference'        => 'flatrate_flatrate',
                            "shipping_method"  => "unknown"
                        ]
                    ],
                    'metadata'        => [
                        'immutable_quote_id' => $immutableQuoteId,
                    ],
                ]
            ]
        ];
        
        return $requetData;
    }
    
    
    
    private function createProduct()
    {
        $product = $this->objectManager->create(ProductInterface::class);
        $productRepository = $this->objectManager->create(ProductRepositoryInterface::class);
        
        $product->setTypeId(ProductType::TYPE_SIMPLE)
            ->setAttributeSetId(4)
            ->setName(self::PRODUCT_NAME)
            ->setSku(self::PRODUCT_SKU)
            ->setPrice(self::PRODUCT_PRICE)
            ->setVisibility(ProductVisibility::VISIBILITY_BOTH)
            ->setStatus(ProductStatus::STATUS_ENABLED)
            ->setStoreId($this->storeId)
            ->setCategoryIds([])
            ->setIsObjectNew(true);
        
        $stockItem = $this->objectManager->create(StockItemInterface::class);
        $stockItem->setQty(100)
            ->setIsInStock(true);
        $extensionAttributes = $product->getExtensionAttributes();
        $extensionAttributes->setStockItem($stockItem);
        
        $product = $productRepository->save($product);
        
        return $product;
    }
    
    
    private function createQoute($product, $parentQuoteId = null)
    {
        $addressInfo = $this->getAddressInfo();
        
        $addressData = [
            'region'     => $addressInfo['region_code'],
            'region_id'  => $addressInfo['region_id'],
            'postcode'   => $addressInfo['postal_code'],
            'lastname'   => $addressInfo['last_name'],
            'firstname'  => $addressInfo['first_name'],
            'street'     => $addressInfo['street_address1'],
            'city'       => $addressInfo['locality'],
            'email'      => $addressInfo['email_address'],
            'telephone'  => $addressInfo['phone_number'],
            'country_id' => $addressInfo['country_code'],
        ];
        
        $quote = TestUtils::createQuote();
        $quoteRepository = $this->objectManager->get(\Magento\Quote\Model\QuoteRepository::class);
        
        $quoteBillingAddress = $this->objectManager->create(\Magento\Quote\Api\Data\AddressInterface::class, ['data' => $addressData]);
        $quoteBillingAddress->setAddressType(QuoteAddress::ADDRESS_TYPE_BILLING)
            ->setCustomerAddressId('not_existing');
        
        $quoteShippingAddress = clone $quoteBillingAddress;
        $quoteShippingAddress->setId(null)->setAddressType(QuoteAddress::ADDRESS_TYPE_SHIPPING)
            ->setCustomerAddressId('not_existing');

        $quoteId = $quote->getId();

        $quote->setStoreId($this->storeId)
            ->setIsActive(true)
            ->setCustomerEmail($addressInfo['email_address'])
            ->setCustomerIsGuest(true)
            ->setIsMultiShipping(false)
            ->setBillingAddress($quoteBillingAddress)
            ->setShippingAddress($quoteShippingAddress)
            ->addProduct($product, self::QUOTE_PRODUCT_QTY);
            
        $quote->setItems($quote->getAllItems())->save();
        
        $quoteShippingAddress = $quote->getShippingAddress();
       
        $rate = $this->objectManager->create(\Magento\Quote\Model\Quote\Address\Rate::class);
        $rate->setCode('flatrate_flatrate')
             ->setPrice(5.00)
             ->setAddressId($quoteShippingAddress->getId())
             ->save();
   
        
        $quoteShippingAddress->setShippingMethod('flatrate_flatrate')
            ->setCollectShippingRates(true);

        $quote->setTotalsCollectedFlag(false)->collectTotals()->setDataChanges(true);
        $quoteRepository->save($quote);
        
        $paymentFactory = $this->objectManager->get(PaymentInterfaceFactory::class);
        $paymentMethodManagement = $this->objectManager->get(PaymentMethodManagementInterface::class);
        $quotePayment = $paymentFactory->create([
            'data' => [
                PaymentInterface::KEY_METHOD => self::BOLT_METHOD_CODE,
            ]
        ]);

        $paymentMethodManagement->set($quoteId, $quotePayment);
        $quote->getPayment()->setMethod(self::BOLT_METHOD_CODE);
        
        $quote->setBoltParentQuoteId($parentQuoteId ?: $quoteId);
        
        $quoteRepository->save($quote);
        
        return $quote;
    }
    
    private function createOrder($quote, $product, $payment, $orderStatus)
    {
        $addressInfo = $this->getAddressInfo();
        
        $quoteId = $quote->getId();
        
        $addressData = [
            'region'     => $addressInfo['region_code'],
            'region_id'  => $addressInfo['region_id'],
            'postcode'   => $addressInfo['postal_code'],
            'lastname'   => $addressInfo['last_name'],
            'firstname'  => $addressInfo['first_name'],
            'street'     => $addressInfo['street_address1'],
            'city'       => $addressInfo['locality'],
            'email'      => $addressInfo['email_address'],
            'telephone'  => $addressInfo['phone_number'],
            'country_id' => $addressInfo['country_code'],
        ];
        
        $billingAddress = $this->objectManager->create(OrderAddress::class, ['data' => $addressData]);
        $billingAddress->setAddressType('billing');
        
        $shippingAddress = clone $billingAddress;
        $shippingAddress->setId(null)->setAddressType('shipping');
        
        $orderItem = $this->objectManager->create(OrderItem::class);
        $orderItem->setProductId($product->getId());
        $orderItem->setQtyOrdered(1);
        $orderItem->setBasePrice($product->getPrice());
        $orderItem->setPrice($product->getPrice());
        $orderItem->setRowTotal($product->getPrice());
        $orderItem->setBaseRowTotal($product->getPrice());
        $orderItem->setRowTotalInclTax($product->getPrice());
        $orderItem->setBaseRowTotalInclTax($product->getPrice());
        $orderItem->setProductType($product->getTypeId());
       
        $order = $this->objectManager->create(Order::class);
        $order->setIncrementId(self::ORDER_INCREMENTID)
            ->setState($orderStatus)
            ->setStatus($order->getConfig()->getStateDefaultStatus($orderStatus))
            ->setCustomerIsGuest(true)
            ->setCustomerEmail($addressInfo['email_address'])
            ->setCustomerFirstname($addressInfo['first_name'])
            ->setCustomerLastname($addressInfo['last_name'])
            ->setBillingAddress($billingAddress)
            ->setShippingAddress($shippingAddress)
            ->setAddresses([$billingAddress, $shippingAddress])            
            ->setStoreId($this->storeId)
            ->setShippingAmount(self::ORDER_SHIPPING_TOTAL)
            ->setBaseShippingAmount(self::ORDER_SHIPPING_TOTAL)
            ->setShippingInclTax(self::ORDER_SHIPPING_TOTAL)
            ->setBaseShippingInclTax(self::ORDER_SHIPPING_TOTAL)
            ->setShippingTaxAmount(self::ORDER_SHIPPING_TAX)
            ->setTaxAmount(self::ORDER_TAX)
            ->setSubtotal(self::ORDER_SUBTOTAL)
            ->setGrandTotal(self::ORDER_TOTAL)
            ->setBaseSubtotal(self::ORDER_SUBTOTAL)
            ->setBaseGrandTotal(self::ORDER_TOTAL)
            ->setBaseCurrencyCode(self::CURRENCY)
            ->setOrderCurrencyCode(self::CURRENCY)
            ->setQuoteId($quoteId)
            ->setPayment($payment)
            ->isObjectNew(true);

        $order->addItem($orderItem);
        $order->save();
          
        $orderRepository = $this->objectManager->create(OrderRepositoryInterface::class);
        $orderRepository->save($order);
       
        return $order;
    }
    
    private function createCustomer()
    {
        $addressInfo = $this->getAddressInfo();
        
        $repository = $this->objectManager->create(\Magento\Customer\Api\CustomerRepositoryInterface::class);
        $customer = $this->objectManager->create(\Magento\Customer\Model\Customer::class);
        /** @var CustomerRegistry $customerRegistry */
        $customerRegistry = $this->objectManager->get(\Magento\Customer\Model\CustomerRegistry::class);
        /** @var Magento\Customer\Model\Customer $customer */
        $customer->setWebsiteId($this->websiteId)
            ->setStoreId($this->storeId)
            ->setId(1)
            ->setEmail($addressInfo['email_address'])
            ->setPassword('password')
            ->setGroupId(1)
            ->setIsActive(1)
            ->setPrefix('Mr.')
            ->setFirstname($addressInfo['first_name'])
            ->setLastname($addressInfo['last_name'])
            ->setDefaultBilling(1)
            ->setDefaultShipping(1)
            ->setTaxvat('12')
            ->setGender(0);
        
        $customer->isObjectNew(true);
        $customer->save();
        $customerRegistry->remove($customer->getId());
        
        return $customer;
    }
    
    private function createOrderHelperStubProperty($boltOrderManagement)
    {
        $orderHelper = $this->objectManager->create(OrderHelper::class);
        
        $stubApiHelper = new stubBoltApiHelper();
        $apiHelperProperty = new \ReflectionProperty(
            OrderHelper::class,
            'apiHelper'
        );
        $apiHelperProperty->setAccessible(true);
        $apiHelperProperty->setValue($orderHelper, $stubApiHelper);
        
        $orderHelperProperty = new \ReflectionProperty(
            BoltOrderManagement::class,
            'orderHelper'
        );
        $orderHelperProperty->setAccessible(true);
        $orderHelperProperty->setValue($boltOrderManagement, $orderHelper);
        
        return $boltOrderManagement;
    }

    /**
     * @test
     * 
     * @covers ::manage
     * @covers ::saveUpdateOrder
     */
    public function testManageCommonPending()
    {
        $this->skipTestInUnitTestsFlow();
        
        global $apiRequestResult;
        
        $boltOrderManagement = $this->objectManager->create(BoltOrderManagement::class);
      
        $product = $this->createProduct();        
        
        $quote = $this->createQoute($product);
        $quoteId = $quote->getId();
        
        $this->createCustomer();
        
        $customerRepository = $this->objectManager->get(CustomerRepositoryInterface::class);
        $customer = $customerRepository->get('admin@test.com');
        
        $quote->setCustomer($customer);
        $quoteRepository = $this->objectManager->get(\Magento\Quote\Model\QuoteRepository::class);
        $quoteRepository->save($quote);
        
        $quoteFactory = $this->objectManager->create(QuoteFactory::class);
        $immutableQuote = $quoteFactory->create();
        $boltCartHelper = $this->objectManager->create(CartHelper::class);
        $boltCartHelper->replicateQuoteData($quote, $immutableQuote);
        $immutableQuoteId = $immutableQuote->getId();

        $requestbodyParams = array (
            'id' => self::TRANSACTION_ID,
            'reference' => self::REFERENCE,
            'order' => $quoteId,
            'type' => 'pending',
            'processor' => self::PROCESSOR_VANTIV,
            'amount' => self::QUOTE_TOTAL*100,
            'currency' => self::CURRENCY,
            'status' => 'authorized',
            'display_id' => '',
        );
        
        $this->createRequest($requestbodyParams);
            
        $boltOrderManagement = $this->createOrderHelperStubProperty($boltOrderManagement);    
        
        $requetData = $this->createRequestData($quoteId, $immutableQuoteId, 'authorized');
        $apiRequestResult = json_decode(json_encode($requetData));

        $boltOrderManagement->manage(
            self::TRANSACTION_ID,
            self::REFERENCE,
            $quoteId,
            'pending',
            self::ORDER_TOTAL*100,
            self::CURRENCY,
            'authorized'
        );
        
        $schema = $this->getResponse()->getBody();
        $responseData = json_decode($schema, true);
        $orderIncrementId = $responseData['display_id'];
        $order = $this->objectManager->create(Order::class);
        $order = $order->loadByIncrementId($orderIncrementId);

        $this->assertEquals('success', $responseData['status']);
        $this->assertEquals(Order::STATE_PAYMENT_REVIEW, $order->getState());
        $this->assertStringContainsString('Order creation / update was successful', $responseData['message']);
    }
    
    /**
     * @test
     * 
     * @covers ::manage
     * @covers ::saveUpdateOrder
     */
    public function testManageCommonAuth()
    {
        $this->skipTestInUnitTestsFlow();
        
        global $apiRequestResult;
        
        $boltOrderManagement = $this->objectManager->create(BoltOrderManagement::class);
      
        $product = $this->createProduct();        
        
        $quote = $this->createQoute($product);
        $quoteId = $quote->getId();
        
        $quoteFactory = $this->objectManager->create(QuoteFactory::class);
        $immutableQuote = $quoteFactory->create();
        $boltCartHelper = $this->objectManager->create(CartHelper::class);
        $boltCartHelper->replicateQuoteData($quote, $immutableQuote);
        $immutableQuoteId = $immutableQuote->getId();

        $requestbodyParams = array (
            'id' => self::TRANSACTION_ID,
            'reference' => self::REFERENCE,
            'order' => $quoteId,
            'type' => 'auth',
            'processor' => self::PROCESSOR_VANTIV,
            'amount' => self::QUOTE_TOTAL*100,
            'currency' => self::CURRENCY,
            'status' => 'authorized',
            'display_id' => self::ORDER_INCREMENTID,
        );
        
        $this->createRequest($requestbodyParams);
        
        $payment = $this->objectManager->create(OrderPayment::class);
        $payment->setMethod(self::BOLT_METHOD_CODE);
        
        $paymentData = [
            'transaction_reference' => self::REFERENCE,
            'transaction_state' => 'cc_payment:pending',
        ];
        $payment->setAdditionalInformation(array_merge((array)$payment->getAdditionalInformation(), $paymentData));
        
        $order = $this->createOrder($quote, $product, $payment, Order::STATE_PAYMENT_REVIEW);
          
        $boltOrderManagement = $this->createOrderHelperStubProperty($boltOrderManagement);        
        
        $requetData = $this->createRequestData($quoteId, $immutableQuoteId, 'authorized', self::ORDER_INCREMENTID);
        $apiRequestResult = json_decode(json_encode($requetData));

        $boltOrderManagement->manage(
            self::TRANSACTION_ID,
            self::REFERENCE,
            $quoteId,
            'auth',
            self::ORDER_TOTAL*100,
            self::CURRENCY,
            'authorized',
            self::ORDER_INCREMENTID
        );
        
        $schema = $this->getResponse()->getBody();
        $responseData = json_decode($schema, true);
        $orderIncrementId = $responseData['display_id'];
        $order = $this->objectManager->create(Order::class);
        $order = $order->loadByIncrementId($orderIncrementId);
        
        $this->assertEquals('success', $responseData['status']);
        $this->assertEquals(Order::STATE_PROCESSING, $order->getState());
        $this->assertStringContainsString('Order creation / update was successful', $responseData['message']);
    }
    
    /**
     * @test
     * 
     * @covers ::manage
     * @covers ::saveUpdateOrder
     */
    public function testManageCommonCapture()
    {
        $this->skipTestInUnitTestsFlow();
        
        global $apiRequestResult;
        
        $boltOrderManagement = $this->objectManager->create(BoltOrderManagement::class);
      
        $product = $this->createProduct();        
        
        $quote = $this->createQoute($product);
        $quoteId = $quote->getId();
        
        $quoteFactory = $this->objectManager->create(QuoteFactory::class);
        $immutableQuote = $quoteFactory->create();
        $boltCartHelper = $this->objectManager->create(CartHelper::class);
        $boltCartHelper->replicateQuoteData($quote, $immutableQuote);
        $immutableQuoteId = $immutableQuote->getId();

        $requestbodyParams = array (
            'id' => self::TRANSACTION_ID,
            'reference' => self::REFERENCE,
            'order' => $quoteId,
            'type' => 'capture',
            'processor' => self::PROCESSOR_VANTIV,
            'amount' => self::QUOTE_TOTAL*100,
            'currency' => self::CURRENCY,
            'status' => 'completed',
            'display_id' => self::ORDER_INCREMENTID,
        );
        
        $this->createRequest($requestbodyParams);
        
        $payment = $this->objectManager->create(OrderPayment::class);
        $payment->setMethod(self::BOLT_METHOD_CODE);
        
        $paymentData = [
            'transaction_reference' => self::REFERENCE,
            'transaction_state' => 'cc_payment:authorized',
        ];
        $payment->setAdditionalInformation(array_merge((array)$payment->getAdditionalInformation(), $paymentData));
        
        $order = $this->createOrder($quote, $product, $payment, Order::STATE_PAYMENT_REVIEW);
          
        $boltOrderManagement = $this->createOrderHelperStubProperty($boltOrderManagement);        
        
        $captures = [
            [
                'id' => 'CA4TxELPMng12',
                'status' => 'succeeded',
                'amount' => [
                    'amount' => self::QUOTE_TOTAL*100,
                    'currency' => self::CURRENCY,
                    'currency_symbol' => self::CURRENCY_USD_SYMBOL,
                ]
            ]
        ];
        $requetData = $this->createRequestData($quoteId, $immutableQuoteId, 'completed', self::ORDER_INCREMENTID, $captures);
        $apiRequestResult = json_decode(json_encode($requetData));

        $boltOrderManagement->manage(
            self::TRANSACTION_ID,
            self::REFERENCE,
            $quoteId,
            'capture',
            self::ORDER_TOTAL*100,
            self::CURRENCY,
            'completed',
            self::ORDER_INCREMENTID
        );
        
        $schema = $this->getResponse()->getBody();
        $responseData = json_decode($schema, true);
        $orderIncrementId = $responseData['display_id'];
        $order = $this->objectManager->create(Order::class);
        $order = $order->loadByIncrementId($orderIncrementId);
        
        $this->assertEquals('success', $responseData['status']);
        $this->assertEquals(Order::STATE_PROCESSING, $order->getState());
        $this->assertStringContainsString('Order creation / update was successful', $responseData['message']);
    }
    
    /**
     * @test
     * 
     * @covers ::manage
     * @covers ::saveUpdateOrder
     */
    public function testManageCommonCaptureIgnoreHookForInvoiceCreationIsEnabled()
    {
        $this->skipTestInUnitTestsFlow();
        
        global $apiRequestResult;
        
        $boltOrderManagement = $this->objectManager->create(BoltOrderManagement::class);
      
        $product = $this->createProduct();        
        
        $quote = $this->createQoute($product);
        $quoteId = $quote->getId();
        
        $quoteFactory = $this->objectManager->create(QuoteFactory::class);
        $immutableQuote = $quoteFactory->create();
        $boltCartHelper = $this->objectManager->create(CartHelper::class);
        $boltCartHelper->replicateQuoteData($quote, $immutableQuote);
        $immutableQuoteId = $immutableQuote->getId();

        $requestbodyParams = array (
            'id' => self::TRANSACTION_ID,
            'reference' => self::REFERENCE,
            'order' => $quoteId,
            'type' => 'capture',
            'processor' => self::PROCESSOR_VANTIV,
            'amount' => self::QUOTE_TOTAL*100,
            'currency' => self::CURRENCY,
            'status' => 'completed',
            'display_id' => self::ORDER_INCREMENTID,
        );
        
        $this->createRequest($requestbodyParams);
        
        $payment = $this->objectManager->create(OrderPayment::class);
        $payment->setMethod(self::BOLT_METHOD_CODE);
        
        $paymentData = [
            'transaction_reference' => self::REFERENCE,
            'transaction_state' => 'cc_payment:authorized',
        ];
        $payment->setAdditionalInformation(array_merge((array)$payment->getAdditionalInformation(), $paymentData));
        
        $order = $this->createOrder($quote, $product, $payment, Order::STATE_PAYMENT_REVIEW);
          
        $boltOrderManagement = $this->createOrderHelperStubProperty($boltOrderManagement);
        
        $featureSwitch = $this->getMockBuilder(Decider::class)
            ->disableOriginalConstructor()
            ->setMethods(['isIgnoreHookForInvoiceCreationEnabled'])
            ->getMock();
        $featureSwitch->expects($this->any())->method('isIgnoreHookForInvoiceCreationEnabled')->willReturn(true);
        
        $featureSwitchProperty = new \ReflectionProperty(
            BoltOrderManagement::class,
            'decider'
        );
        $featureSwitchProperty->setAccessible(true);
        $featureSwitchProperty->setValue($boltOrderManagement, $featureSwitch);        
        
        $captures = [
            [
                'id' => 'CA4TxELPMng12',
                'status' => 'succeeded',
                'amount' => [
                    'amount' => self::QUOTE_TOTAL*100,
                    'currency' => self::CURRENCY,
                    'currency_symbol' => self::CURRENCY_USD_SYMBOL,
                ]
            ]
        ];
        $requetData = $this->createRequestData($quoteId, $immutableQuoteId, 'completed', self::ORDER_INCREMENTID, $captures);
        $apiRequestResult = json_decode(json_encode($requetData));

        $boltOrderManagement->manage(
            self::TRANSACTION_ID,
            self::REFERENCE,
            $quoteId,
            'capture',
            self::ORDER_TOTAL*100,
            self::CURRENCY,
            'completed',
            self::ORDER_INCREMENTID
        );
        
        $schema = $this->getResponse()->getBody();
        $responseData = json_decode($schema, true);
        
        $orderHelper = $this->objectManager->create(OrderHelper::class);
        $order = $orderHelper->getExistingOrder(self::ORDER_INCREMENTID);
        
        $this->assertEquals('success', $responseData['status']);
        $this->assertEquals('Ignore the capture hook for the invoice creation', $responseData['message']);
        $this->assertEquals(Order::STATE_PAYMENT_REVIEW, $order->getState());
    }
    
    /**
     * @test
     * 
     * @covers ::manage
     * @covers ::saveUpdateOrder
     */
    public function testManageCommonFailedPayment()
    {
        $this->skipTestInUnitTestsFlow();
        
        global $apiRequestResult;
        
        $boltOrderManagement = $this->objectManager->create(BoltOrderManagement::class);
      
        $product = $this->createProduct();        
        
        $quote = $this->createQoute($product);
        $quoteId = $quote->getId();
        
        $quoteFactory = $this->objectManager->create(QuoteFactory::class);
        $immutableQuote = $quoteFactory->create();
        $boltCartHelper = $this->objectManager->create(CartHelper::class);
        $boltCartHelper->replicateQuoteData($quote, $immutableQuote);
        $immutableQuoteId = $immutableQuote->getId();

        $requestbodyParams = array (
            'id' => self::TRANSACTION_ID,
            'reference' => self::REFERENCE,
            'order' => $quoteId,
            'type' => 'failed_payment',
            'processor' => self::PROCESSOR_VANTIV,
            'amount' => self::QUOTE_TOTAL*100,
            'currency' => self::CURRENCY,
            'status' => 'failed',
            'display_id' => self::ORDER_INCREMENTID,
        );
        
        $this->createRequest($requestbodyParams);
        
        $payment = $this->objectManager->create(OrderPayment::class);
        $payment->setMethod(self::BOLT_METHOD_CODE);
        
        $paymentData = [];
        $payment->setAdditionalInformation(array_merge((array)$payment->getAdditionalInformation(), $paymentData));
        
        $order = $this->createOrder($quote, $product, $payment, Order::STATE_PENDING_PAYMENT);
          
        $boltOrderManagement = $this->createOrderHelperStubProperty($boltOrderManagement);
        
        $requetData = $this->createRequestData($quoteId, $immutableQuoteId, 'failed', self::ORDER_INCREMENTID);
        $apiRequestResult = json_decode(json_encode($requetData));

        $boltOrderManagement->manage(
            self::TRANSACTION_ID,
            self::REFERENCE,
            $quoteId,
            'failed_payment',
            self::ORDER_TOTAL*100,
            self::CURRENCY,
            'failed',
            self::ORDER_INCREMENTID
        );
        
        $schema = $this->getResponse()->getBody();
        $responseData = json_decode($schema, true);

        $orderHelper = $this->objectManager->create(OrderHelper::class);
        $order = $orderHelper->getExistingOrder(self::ORDER_INCREMENTID);
        
        $this->assertEquals('success', $responseData['status']);
        $this->assertEquals('Order was deleted: '.self::ORDER_INCREMENTID, $responseData['message']);
        $this->assertTrue(empty($order));
    }
    
    /**
     * @test
     * 
     * @covers ::manage
     * @covers ::saveUpdateOrder
     */
    public function testManageCommonFailedPaymentCancelled()
    {
        $this->skipTestInUnitTestsFlow();
        
        global $apiRequestResult;
        
        $boltOrderManagement = $this->objectManager->create(BoltOrderManagement::class);
      
        $product = $this->createProduct();        
        
        $quote = $this->createQoute($product);
        $quoteId = $quote->getId();
        
        $quoteFactory = $this->objectManager->create(QuoteFactory::class);
        $immutableQuote = $quoteFactory->create();
        $boltCartHelper = $this->objectManager->create(CartHelper::class);
        $boltCartHelper->replicateQuoteData($quote, $immutableQuote);
        $immutableQuoteId = $immutableQuote->getId();

        $requestbodyParams = array (
            'id' => self::TRANSACTION_ID,
            'reference' => self::REFERENCE,
            'order' => $quoteId,
            'type' => 'failed_payment',
            'processor' => self::PROCESSOR_VANTIV,
            'amount' => self::QUOTE_TOTAL*100,
            'currency' => self::CURRENCY,
            'status' => 'failed',
            'display_id' => self::ORDER_INCREMENTID,
        );
        
        $this->createRequest($requestbodyParams);
        
        $payment = $this->objectManager->create(OrderPayment::class);
        $payment->setMethod(self::BOLT_METHOD_CODE);
        
        $paymentData = [];
        $payment->setAdditionalInformation(array_merge((array)$payment->getAdditionalInformation(), $paymentData));
        
        $order = $this->createOrder($quote, $product, $payment, Order::STATE_PENDING_PAYMENT);
          
        $boltOrderManagement = $this->createOrderHelperStubProperty($boltOrderManagement);
        
        $orderHelper = $this->objectManager->create(OrderHelper::class);
        
        $stubApiHelper = new stubBoltApiHelper();
        $apiHelperProperty = new \ReflectionProperty(
            OrderHelper::class,
            'apiHelper'
        );
        $apiHelperProperty->setAccessible(true);
        $apiHelperProperty->setValue($orderHelper, $stubApiHelper);
        
        $featureSwitch = $this->getMockBuilder(Decider::class)
            ->disableOriginalConstructor()
            ->setMethods(['isCancelFailedPaymentOrderInsteadOfDeleting'])
            ->getMock();
        $featureSwitch->expects($this->any())->method('isCancelFailedPaymentOrderInsteadOfDeleting')->willReturn(true);
        
        $featureSwitchProperty = new \ReflectionProperty(
            OrderHelper::class,
            'featureSwitches'
        );
        $featureSwitchProperty->setAccessible(true);
        $featureSwitchProperty->setValue($orderHelper, $featureSwitch);
        
        $orderHelperProperty = new \ReflectionProperty(
            BoltOrderManagement::class,
            'orderHelper'
        );
        $orderHelperProperty->setAccessible(true);
        $orderHelperProperty->setValue($boltOrderManagement, $orderHelper);
        
        $requetData = $this->createRequestData($quoteId, $immutableQuoteId, 'failed', self::ORDER_INCREMENTID);
        $apiRequestResult = json_decode(json_encode($requetData));

        $boltOrderManagement->manage(
            self::TRANSACTION_ID,
            self::REFERENCE,
            $quoteId,
            'failed_payment',
            self::ORDER_TOTAL*100,
            self::CURRENCY,
            'failed',
            self::ORDER_INCREMENTID
        );
        
        $schema = $this->getResponse()->getBody();
        $responseData = json_decode($schema, true);
       
        $this->assertEquals('success', $responseData['status']);
        $this->assertEquals('Order was canceled: '.self::ORDER_INCREMENTID, $responseData['message']);
    }
    
    /**
     * @test
     * 
     * @covers ::manage
     * @covers ::saveUpdateOrder
     */
    public function testManageCommonFailedPaymentCancelledInvalidOrderState()
    {
        $this->skipTestInUnitTestsFlow();
        
        global $apiRequestResult;
        
        $boltOrderManagement = $this->objectManager->create(BoltOrderManagement::class);
      
        $product = $this->createProduct();        
        
        $quote = $this->createQoute($product);
        $quoteId = $quote->getId();
        
        $quoteFactory = $this->objectManager->create(QuoteFactory::class);
        $immutableQuote = $quoteFactory->create();
        $boltCartHelper = $this->objectManager->create(CartHelper::class);
        $boltCartHelper->replicateQuoteData($quote, $immutableQuote);
        $immutableQuoteId = $immutableQuote->getId();

        $requestbodyParams = array (
            'id' => self::TRANSACTION_ID,
            'reference' => self::REFERENCE,
            'order' => $quoteId,
            'type' => 'failed_payment',
            'processor' => self::PROCESSOR_VANTIV,
            'amount' => self::QUOTE_TOTAL*100,
            'currency' => self::CURRENCY,
            'status' => 'failed',
            'display_id' => self::ORDER_INCREMENTID,
        );
        
        $this->createRequest($requestbodyParams);
        
        $payment = $this->objectManager->create(OrderPayment::class);
        $payment->setMethod(self::BOLT_METHOD_CODE);
        
        $paymentData = [];
        $payment->setAdditionalInformation(array_merge((array)$payment->getAdditionalInformation(), $paymentData));
        
        $order = $this->createOrder($quote, $product, $payment, Order::STATE_PROCESSING);
          
        $boltOrderManagement = $this->createOrderHelperStubProperty($boltOrderManagement);
        
        $orderHelper = $this->objectManager->create(OrderHelper::class);
        $featureSwitch = $this->getMockBuilder(Decider::class)
            ->disableOriginalConstructor()
            ->setMethods(['isCancelFailedPaymentOrderInsteadOfDeleting'])
            ->getMock();
        $featureSwitch->expects($this->any())->method('isCancelFailedPaymentOrderInsteadOfDeleting')->willReturn(true);
        
        $featureSwitchProperty = new \ReflectionProperty(
            OrderHelper::class,
            'featureSwitches'
        );
        $featureSwitchProperty->setAccessible(true);
        $featureSwitchProperty->setValue($orderHelper, $featureSwitch);
        
        $requetData = $this->createRequestData($quoteId, $immutableQuoteId, 'failed', self::ORDER_INCREMENTID);
        $apiRequestResult = json_decode(json_encode($requetData));

        $boltOrderManagement->manage(
            self::TRANSACTION_ID,
            self::REFERENCE,
            $quoteId,
            'failed_payment',
            self::ORDER_TOTAL*100,
            self::CURRENCY,
            'failed',
            self::ORDER_INCREMENTID
        );
        
        $schema = $this->getResponse()->getBody();
        $responseData = json_decode($schema, true);
       
        $this->assertEquals('failure', $responseData['status']);
        $this->assertEquals(2001001, $responseData['error']['code']);
        $errMsg = __(
            'Order Delete Error. Order is in invalid state. Order #: %1 State: %2 Immutable Quote ID: %3',
            self::ORDER_INCREMENTID,
            Order::STATE_PROCESSING,
            $immutableQuoteId
        );
        $this->assertEquals($errMsg, $responseData['error']['message']);
    }
    
    /**
     * @test
     * 
     * @covers ::manage
     * @covers ::saveUpdateOrder
     */
    public function testManageCommonRejectedIrreversible()
    {
        $this->skipTestInUnitTestsFlow();
        
        global $apiRequestResult;
        
        $boltOrderManagement = $this->objectManager->create(BoltOrderManagement::class);
      
        $product = $this->createProduct();        
        
        $quote = $this->createQoute($product);
        $quoteId = $quote->getId();
        
        $quoteFactory = $this->objectManager->create(QuoteFactory::class);
        $immutableQuote = $quoteFactory->create();
        $boltCartHelper = $this->objectManager->create(CartHelper::class);
        $boltCartHelper->replicateQuoteData($quote, $immutableQuote);
        $immutableQuoteId = $immutableQuote->getId();

        $requestbodyParams = array (
            'id' => self::TRANSACTION_ID,
            'reference' => self::REFERENCE,
            'order' => $quoteId,
            'type' => 'rejected_irreversible',
            'processor' => self::PROCESSOR_VANTIV,
            'amount' => self::QUOTE_TOTAL*100,
            'currency' => self::CURRENCY,
            'status' => 'rejected_irreversible',
            'display_id' => self::ORDER_INCREMENTID,
        );
        
        $this->createRequest($requestbodyParams);
        
        $payment = $this->objectManager->create(OrderPayment::class);
        $payment->setMethod(self::BOLT_METHOD_CODE);
        
        $paymentData = [];
        $payment->setAdditionalInformation(array_merge((array)$payment->getAdditionalInformation(), $paymentData));
        
        $order = $this->createOrder($quote, $product, $payment, Order::STATE_PENDING_PAYMENT);
          
        $boltOrderManagement = $this->createOrderHelperStubProperty($boltOrderManagement);
        
        $requetData = $this->createRequestData($quoteId, $immutableQuoteId, 'rejected_irreversible', self::ORDER_INCREMENTID);
        $apiRequestResult = json_decode(json_encode($requetData));

        $boltOrderManagement->manage(
            self::TRANSACTION_ID,
            self::REFERENCE,
            $quoteId,
            'rejected_irreversible',
            self::ORDER_TOTAL*100,
            self::CURRENCY,
            'rejected_irreversible',
            self::ORDER_INCREMENTID
        );
        
        $schema = $this->getResponse()->getBody();
        $responseData = json_decode($schema, true);

        $this->assertEquals('success', $responseData['status']);
        $this->assertEquals('Order was canceled due to declined payment: '.self::ORDER_INCREMENTID, $responseData['message']);
    }
    
    /**
     * @test
     * 
     * @covers ::manage
     * @covers ::saveUpdateOrder
     */
    public function testManageCommonRejectedIrreversibleOrderNotExist()
    {
        $this->skipTestInUnitTestsFlow();
        
        global $apiRequestResult;
        
        $boltOrderManagement = $this->objectManager->create(BoltOrderManagement::class);
      
        $product = $this->createProduct();        
        
        $quote = $this->createQoute($product);
        $quoteId = $quote->getId();

        $requestbodyParams = array (
            'id' => self::TRANSACTION_ID,
            'reference' => self::REFERENCE,
            'order' => $quoteId,
            'type' => 'rejected_irreversible',
            'processor' => self::PROCESSOR_VANTIV,
            'amount' => self::QUOTE_TOTAL*100,
            'currency' => self::CURRENCY,
            'status' => 'rejected_irreversible',
            'display_id' => self::ORDER_INCREMENTID,
        );
        
        $this->createRequest($requestbodyParams);
          
        $boltOrderManagement = $this->createOrderHelperStubProperty($boltOrderManagement);

        $requetData = $this->createRequestData($quoteId, $quoteId, 'rejected_irreversible', self::ORDER_INCREMENTID);
        $apiRequestResult = json_decode(json_encode($requetData));
        
        $boltOrderManagement->manage(
            self::TRANSACTION_ID,
            self::REFERENCE,
            $quoteId,
            'rejected_irreversible',
            self::ORDER_TOTAL*100,
            self::CURRENCY,
            'rejected_irreversible',
            self::ORDER_INCREMENTID
        );
        
        $schema = $this->getResponse()->getBody();
        $responseData = json_decode($schema, true);

        $this->assertEquals('failure', $responseData['status']);
        $this->assertEquals(2001001, $responseData['error']['code']);
        $errMsg = __(
            'Order Cancelation Error. Order does not exist. Order #: %1 Immutable Quote ID: %2',
            self::ORDER_INCREMENTID,
            $quoteId
        );
        $this->assertEquals($errMsg, $responseData['error']['message']);
    }
    
    /**
     * @test
     * 
     * @covers ::manage
     * @covers ::saveUpdateOrder
     */
    public function testManageCommonCredit()
    {
        $this->skipTestInUnitTestsFlow();
        
        global $apiRequestResult;
        
        $boltOrderManagement = $this->objectManager->create(BoltOrderManagement::class);
      
        $product = $this->createProduct();        
        
        $quote = $this->createQoute($product);
        $quoteId = $quote->getId();

        $requestbodyParams = array (
            'id' => self::TRANSACTION_ID,
            'reference' => self::REFERENCE,
            'order' => $quoteId,
            'type' => 'credit',
            'processor' => self::PROCESSOR_VANTIV,
            'amount' => self::QUOTE_TOTAL*100,
            'currency' => self::CURRENCY,
            'status' => 'completed',
            'display_id' => self::ORDER_INCREMENTID,
        );
        
        $this->createRequest($requestbodyParams);
        
        $payment = $this->objectManager->create(OrderPayment::class);
        $payment->setMethod(self::BOLT_METHOD_CODE);
        
        $paymentData = [
            'transaction_reference' => self::REFERENCE,
            'transaction_state' => 'cc_payment:completed',
        ];
        $payment->setAdditionalInformation(array_merge((array)$payment->getAdditionalInformation(), $paymentData));
        
        $order = $this->createOrder($quote, $product, $payment, Order::STATE_PROCESSING);
        foreach ($order->getAllItems() as $orderItem) {
            $orderItem->setQtyInvoiced(1)
                      ->setRowInvoiced($orderItem->getRowTotal())
                      ->setBaseRowInvoiced($orderItem->getBaseRowTotal())
                      ->setAmountRefunded(0)
                      ->setBaseAmountRefunded(0)
                      ->setQtyRefunded(0);
        }
        $order->setBaseTotalPaid(self::ORDER_TOTAL)
              ->setTotalPaid(self::ORDER_TOTAL)
              ->setShippingRefunded(0)
              ->setBaseShippingRefunded(0)
              ->setShippingTaxRefunded(0)
              ->save();
        
        $orderRepository = $this->objectManager->create(OrderRepositoryInterface::class);
        $orderRepository->save($order);
          
        $orderHelper = $this->objectManager->create(OrderHelper::class);
        
        $stubApiHelper = new stubBoltApiHelper();
        $apiHelperProperty = new \ReflectionProperty(
            OrderHelper::class,
            'apiHelper'
        );
        $apiHelperProperty->setAccessible(true);
        $apiHelperProperty->setValue($orderHelper, $stubApiHelper);
        
        $featureSwitch = $this->getMockBuilder(Decider::class)
            ->disableOriginalConstructor()
            ->setMethods(['isCreatingCreditMemoFromWebHookEnabled', 'isIgnoreHookForInvoiceCreationEnabled'])
            ->getMock();
        $featureSwitch->expects($this->any())->method('isCreatingCreditMemoFromWebHookEnabled')->willReturn(true);
        $featureSwitch->expects($this->any())->method('isIgnoreHookForInvoiceCreationEnabled')->willReturn(false);
        
        $featureSwitchProperty = new \ReflectionProperty(
            OrderHelper::class,
            'featureSwitches'
        );
        $featureSwitchProperty->setAccessible(true);
        $featureSwitchProperty->setValue($orderHelper, $featureSwitch);
        
        $orderHelperProperty = new \ReflectionProperty(
            BoltOrderManagement::class,
            'orderHelper'
        );
        $orderHelperProperty->setAccessible(true);
        $orderHelperProperty->setValue($boltOrderManagement, $orderHelper);
        
        $requetData = $this->createRequestData($quoteId, $quoteId, 'completed', self::ORDER_INCREMENTID, null, self::TT_CREDIT);
        $apiRequestResult = json_decode(json_encode($requetData));

        $boltOrderManagement->manage(
            self::TRANSACTION_ID,
            self::REFERENCE,
            $quoteId,
            'credit',
            self::ORDER_TOTAL*100,
            self::CURRENCY,
            'completed',
            self::ORDER_INCREMENTID
        );
        
        $schema = $this->getResponse()->getBody();
        $responseData = json_decode($schema, true);
        $orderIncrementId = $responseData['display_id'];
        $order = $this->objectManager->create(Order::class);
        $order = $order->loadByIncrementId($orderIncrementId);

        $this->assertEquals('success', $responseData['status']);
        $this->assertEquals(Order::STATE_CLOSED, $order->getState());
        $this->assertStringContainsString('Order creation / update was successful', $responseData['message']);
    }
    
    /**
     * @test
     * 
     * @covers ::manage
     * @covers ::saveUpdateOrder
     */
    public function testManageCommonCreditIgnoreHookForCreditMemoCreationIsEnabled()
    {
        $this->skipTestInUnitTestsFlow();
        
        global $apiRequestResult;
        
        $boltOrderManagement = $this->objectManager->create(BoltOrderManagement::class);
      
        $product = $this->createProduct();        
        
        $quote = $this->createQoute($product);
        $quoteId = $quote->getId();

        $requestbodyParams = array (
            'id' => self::TRANSACTION_ID,
            'reference' => self::REFERENCE,
            'order' => $quoteId,
            'type' => 'credit',
            'processor' => self::PROCESSOR_VANTIV,
            'amount' => self::QUOTE_TOTAL*100,
            'currency' => self::CURRENCY,
            'status' => 'completed',
            'display_id' => self::ORDER_INCREMENTID,
        );
        
        $this->createRequest($requestbodyParams);
        
        $payment = $this->objectManager->create(OrderPayment::class);
        $payment->setMethod(self::BOLT_METHOD_CODE);
        
        $paymentData = [
            'transaction_reference' => self::REFERENCE,
            'transaction_state' => 'cc_payment:completed',
        ];
        $payment->setAdditionalInformation(array_merge((array)$payment->getAdditionalInformation(), $paymentData));
        
        $order = $this->createOrder($quote, $product, $payment, Order::STATE_PROCESSING);
        foreach ($order->getAllItems() as $orderItem) {
            $orderItem->setQtyInvoiced(1)
                      ->setRowInvoiced($orderItem->getRowTotal())
                      ->setBaseRowInvoiced($orderItem->getBaseRowTotal())
                      ->setAmountRefunded(0)
                      ->setBaseAmountRefunded(0)
                      ->setQtyRefunded(0);
        }
        $order->setBaseTotalPaid(self::ORDER_TOTAL)
              ->setTotalPaid(self::ORDER_TOTAL)
              ->setShippingRefunded(0)
              ->setBaseShippingRefunded(0)
              ->setShippingTaxRefunded(0)
              ->save();
        
        $orderRepository = $this->objectManager->create(OrderRepositoryInterface::class);
        $orderRepository->save($order);
          
        $orderHelper = $this->objectManager->create(OrderHelper::class);
        
        $stubApiHelper = new stubBoltApiHelper();
        $apiHelperProperty = new \ReflectionProperty(
            OrderHelper::class,
            'apiHelper'
        );
        $apiHelperProperty->setAccessible(true);
        $apiHelperProperty->setValue($orderHelper, $stubApiHelper);
        
        $featureSwitch = $this->getMockBuilder(Decider::class)
            ->disableOriginalConstructor()
            ->setMethods(['isIgnoreHookForCreditMemoCreationEnabled'])
            ->getMock();
        $featureSwitch->expects($this->any())->method('isIgnoreHookForCreditMemoCreationEnabled')->willReturn(true);
        
        $featureSwitchProperty = new \ReflectionProperty(
            BoltOrderManagement::class,
            'decider'
        );
        $featureSwitchProperty->setAccessible(true);
        $featureSwitchProperty->setValue($boltOrderManagement, $featureSwitch);
        
        $orderHelperProperty = new \ReflectionProperty(
            BoltOrderManagement::class,
            'orderHelper'
        );
        $orderHelperProperty->setAccessible(true);
        $orderHelperProperty->setValue($boltOrderManagement, $orderHelper);
        
        $requetData = $this->createRequestData($quoteId, $quoteId, 'completed', self::ORDER_INCREMENTID, null, self::TT_CREDIT);
        $apiRequestResult = json_decode(json_encode($requetData));

        $boltOrderManagement->manage(
            self::TRANSACTION_ID,
            self::REFERENCE,
            $quoteId,
            'credit',
            self::ORDER_TOTAL*100,
            self::CURRENCY,
            'completed',
            self::ORDER_INCREMENTID
        );
        
        $schema = $this->getResponse()->getBody();
        $responseData = json_decode($schema, true);
        
        $orderHelper = $this->objectManager->create(OrderHelper::class);
        $order = $orderHelper->getExistingOrder(self::ORDER_INCREMENTID);
        
        $this->assertEquals('success', $responseData['status']);
        $this->assertEquals('Ignore the credit hook for the credit memo creation', $responseData['message']);
        $this->assertEquals(Order::STATE_PROCESSING, $order->getState());
    }
    
    /**
     * @test
     * 
     * @covers ::manage
     * @covers ::saveUpdateOrder
     */
    public function testManageCommonVoid()
    {
        $this->skipTestInUnitTestsFlow();
        
        global $apiRequestResult;
        
        $boltOrderManagement = $this->objectManager->create(BoltOrderManagement::class);
      
        $product = $this->createProduct();        
        
        $quote = $this->createQoute($product);
        $quoteId = $quote->getId();

        $requestbodyParams = array (
            'id' => self::TRANSACTION_ID,
            'reference' => self::REFERENCE,
            'order' => $quoteId,
            'type' => 'void',
            'processor' => self::PROCESSOR_VANTIV,
            'amount' => self::QUOTE_TOTAL*100,
            'currency' => self::CURRENCY,
            'status' => 'cancelled',
            'display_id' => self::ORDER_INCREMENTID,
        );
        
        $this->createRequest($requestbodyParams);
        
        $payment = $this->objectManager->create(OrderPayment::class);
        $payment->setMethod(self::BOLT_METHOD_CODE);
        
        $paymentData = [
            'transaction_reference' => self::REFERENCE,
            'transaction_state' => 'cc_payment:authorized',
        ];
        $payment->setAdditionalInformation(array_merge((array)$payment->getAdditionalInformation(), $paymentData));
        
        $order = $this->createOrder($quote, $product, $payment, Order::STATE_PROCESSING);
        
        $boltOrderManagement = $this->createOrderHelperStubProperty($boltOrderManagement);
        
        $requetData = $this->createRequestData($quoteId, $quoteId, 'cancelled', self::ORDER_INCREMENTID);
        $apiRequestResult = json_decode(json_encode($requetData));

        $boltOrderManagement->manage(
            self::TRANSACTION_ID,
            self::REFERENCE,
            $quoteId,
            'void',
            self::ORDER_TOTAL*100,
            self::CURRENCY,
            'cancelled',
            self::ORDER_INCREMENTID
        );
        
        $schema = $this->getResponse()->getBody();
        $responseData = json_decode($schema, true);
        $orderIncrementId = $responseData['display_id'];
        $order = $this->objectManager->create(Order::class);
        $order = $order->loadByIncrementId($orderIncrementId);

        $this->assertEquals('success', $responseData['status']);
        $this->assertEquals(Order::STATE_CANCELED, $order->getState());
        $this->assertStringContainsString('Order creation / update was successful', $responseData['message']);
    }
    
    /**
     * @test
     * 
     * @covers ::manage
     * @covers ::saveUpdateOrder
     */
    public function testManageCommonRejectedReversible()
    {
        $this->skipTestInUnitTestsFlow();
        
        global $apiRequestResult;
        
        $boltOrderManagement = $this->objectManager->create(BoltOrderManagement::class);
      
        $product = $this->createProduct();        
        
        $quote = $this->createQoute($product);
        $quoteId = $quote->getId();

        $requestbodyParams = array (
            'id' => self::TRANSACTION_ID,
            'reference' => self::REFERENCE,
            'order' => $quoteId,
            'type' => 'rejected_reversible',
            'processor' => self::PROCESSOR_VANTIV,
            'amount' => self::QUOTE_TOTAL*100,
            'currency' => self::CURRENCY,
            'status' => 'rejected_reversible',
            'display_id' => self::ORDER_INCREMENTID,
        );
        
        $this->createRequest($requestbodyParams);
        
        $payment = $this->objectManager->create(OrderPayment::class);
        $payment->setMethod(self::BOLT_METHOD_CODE);
        
        $paymentData = [
            'transaction_reference' => self::REFERENCE,
            'transaction_state' => 'cc_payment:pending',
        ];
        $payment->setAdditionalInformation(array_merge((array)$payment->getAdditionalInformation(), $paymentData));
        
        $order = $this->createOrder($quote, $product, $payment, Order::STATE_PAYMENT_REVIEW);
        
        $boltOrderManagement = $this->createOrderHelperStubProperty($boltOrderManagement);
        
        $requetData = $this->createRequestData($quoteId, $quoteId, 'rejected_reversible', self::ORDER_INCREMENTID);
        $apiRequestResult = json_decode(json_encode($requetData));
        
        $boltOrderManagement->manage(
            self::TRANSACTION_ID,
            self::REFERENCE,
            $quoteId,
            'rejected_reversible',
            self::ORDER_TOTAL*100,
            self::CURRENCY,
            'rejected_reversible',
            self::ORDER_INCREMENTID
        );
        
        $schema = $this->getResponse()->getBody();
        $responseData = json_decode($schema, true);
        $orderIncrementId = $responseData['display_id'];
        $order = $this->objectManager->create(Order::class);
        $order = $order->loadByIncrementId($orderIncrementId);

        $this->assertEquals('success', $responseData['status']);
        $this->assertEquals(Order::STATE_PAYMENT_REVIEW, $order->getState());
        $this->assertStringContainsString('Order creation / update was successful', $responseData['message']);
    }
    
    /**
     * @test
     * 
     * @covers ::manage
     * @covers ::saveUpdateOrder
     */
    public function testManageCommonCartCreate()
    {
        $this->skipTestInUnitTestsFlow();
        
        $boltOrderManagement = $this->objectManager->create(BoltOrderManagement::class);
      
        $product = $this->createProduct();
        
        $productOptions = [
            'product' => $product->getId(),
            'selected_configurable_option' => '',
            'related_product' => '',
            'item' => $product->getId(),
            'form_key' => 'eY1ngRv1NFOyFC2N',
            'qty'      => self::QUOTE_PRODUCT_QTY,
            'storeId'  => $this->storeId,
        ];
        
        $requestbodyParams = [
            'type'  => 'cart.create',
            'items' => [
                [
                    'reference'    => $product->getId(),
                    'name'         => $product->getName(),
                    'description'  => NULL,
                    'options'      => json_encode($productOptions),
                    'total_amount' => self::QUOTE_PRODUCT_QTY*self::PRODUCT_PRICE*100,
                    'unit_price'   => self::PRODUCT_PRICE*100,
                    'tax_amount'   => self::QUOTE_TAX_TOTAL*100,
                    'quantity'     => self::QUOTE_PRODUCT_QTY,
                ]
            ],
            'currency' => self::CURRENCY,
            'metadata' => NULL,
        ];
        
        $this->createRequest($requestbodyParams);
        
        $boltOrderManagement->manage(
            NULL,
            NULL,
            NULL,
            'cart.create',
            NULL,
            self::CURRENCY
        );
        
        $schema = $this->getResponse()->getBody();
        $responseData = json_decode($schema, true);

        $quoteId = $responseData['cart']['order_reference'];
        $boltCartHelper = $this->objectManager->create(CartHelper::class);
        $quote = $boltCartHelper->getQuoteById($quoteId);
        $totals = $quote->getTotals();

        $this->assertEquals('success', $responseData['status']);
        $this->assertEquals(CurrencyUtils::toMinor($totals['subtotal']['value'], self::CURRENCY), CurrencyUtils::toMinor(self::QUOTE_SUBTOTAL, self::CURRENCY));

    }
    
    /**
     * @test
     * 
     * @covers ::manage
     * @covers ::saveUpdateOrder
     */
    public function testManageCommonCartCreateError()
    {
        $this->skipTestInUnitTestsFlow();
        
        $boltOrderManagement = $this->objectManager->create(BoltOrderManagement::class);
      
        $product = $this->createProduct();
        
        $productOptions = [
            'product' => $product->getId(),
            'selected_configurable_option' => '',
            'related_product' => '',
            'item' => $product->getId(),
            'form_key' => 'eY1ngRv1NFOyFC2N',
            'qty'      => 101,
            'storeId'  => $this->storeId,
        ];
        
        $requestbodyParams = [
            'type'  => 'cart.create',
            'items' => [
                [
                    'reference'    => $product->getId(),
                    'name'         => $product->getName(),
                    'description'  => NULL,
                    'options'      => json_encode($productOptions),
                    'total_amount' => 101*self::PRODUCT_PRICE*100,
                    'unit_price'   => self::PRODUCT_PRICE*100,
                    'tax_amount'   => self::QUOTE_TAX_TOTAL*100,
                    'quantity'     => 101,
                ]
            ],
            'currency' => self::CURRENCY,
            'metadata' => NULL,
        ];
        
        $this->createRequest($requestbodyParams);
        
        $boltOrderManagement->manage(
            NULL,
            NULL,
            NULL,
            'cart.create',
            NULL,
            self::CURRENCY
        );
        
        $schema = $this->getResponse()->getBody();
        $responseData = json_decode($schema, true);

        $this->assertEquals('failure', $responseData['status']);
        $this->assertEquals(6303, $responseData['error']['code']);
        $this->assertEquals('The requested qty is not available', $responseData['error']['message']);
    }
    
    /**
     * @test
     * 
     * @covers ::manage
     * @covers ::saveUpdateOrder
     */
    public function testManagePreconditionFailed()
    {
        $this->skipTestInUnitTestsFlow();
        
        global $apiRequestResult;
        
        $apiRequestResult = 'exception';
        
        $boltOrderManagement = $this->objectManager->create(BoltOrderManagement::class);
      
        $product = $this->createProduct();
        
        $productOptions = [
            'product' => $product->getId(),
            'selected_configurable_option' => '',
            'related_product' => '',
            'item' => $product->getId(),
            'form_key' => 'eY1ngRv1NFOyFC2N',
            'qty'      => self::QUOTE_PRODUCT_QTY,
            'storeId'  => $this->storeId,
        ];
        
        $requestbodyParams = [
            'type'  => 'cart.create',
            'items' => [
                [
                    'reference'    => $product->getId(),
                    'name'         => $product->getName(),
                    'description'  => NULL,
                    'options'      => json_encode($productOptions),
                    'total_amount' => self::QUOTE_PRODUCT_QTY*self::PRODUCT_PRICE*100,
                    'unit_price'   => self::PRODUCT_PRICE*100,
                    'tax_amount'   => self::QUOTE_TAX_TOTAL*100,
                    'quantity'     => self::QUOTE_PRODUCT_QTY,
                ]
            ],
            'currency' => self::CURRENCY,
            'metadata' => NULL,
        ];
        
        if (!$this->request) {
            $this->request = $this->objectManager->get(Request::class);
        }
        
        $requestContent = json_encode($requestbodyParams);
         
        $computed_signature  = base64_encode(hash_hmac('sha256', $requestContent, '12425f51e0614482e17b6e913d74788eedb082e2e6f8067330b98ffa99adc809', true));

        $this->request->getHeaders()->addHeaderLine('X-Bolt-Hmac-Sha256',$computed_signature);
        $this->request->getHeaders()->addHeaderLine('X-bolt-trace-id',self::TRACE_ID_HEADER);
        $this->request->getHeaders()->addHeaderLine('Content-Type','application/json');

        $this->request->setParams($requestbodyParams);
        
        $this->request->setContent($requestContent);
        
        $hookHelper = $this->objectManager->create(HookHelper::class);
        
        $stubApiHelper = new stubBoltApiHelper();
        $apiHelperProperty = new \ReflectionProperty(
            HookHelper::class,
            'apiHelper'
        );
        $apiHelperProperty->setAccessible(true);
        $apiHelperProperty->setValue($hookHelper, $stubApiHelper);
        
        $orderHelperProperty = new \ReflectionProperty(
            BoltOrderManagement::class,
            'hookHelper'
        );
        $orderHelperProperty->setAccessible(true);
        $orderHelperProperty->setValue($boltOrderManagement, $hookHelper);
        
        $boltOrderManagement->manage(
            NULL,
            NULL,
            NULL,
            'cart.create',
            NULL,
            self::CURRENCY
        );
        
        $schema = $this->getResponse()->getBody();
        $responseData = json_decode($schema, true);
       
        $this->assertEquals('error', $responseData['status']);
        $this->assertEquals(6001, $responseData['code']);
        $this->assertEquals('Precondition Failed', $responseData['message']);
    }
    
    /**
     * @test
     * 
     * @covers ::manage
     */
    public function testManageEmptyReference()
    {
        $this->skipTestInUnitTestsFlow();
        
        $boltOrderManagement = $this->objectManager->create(BoltOrderManagement::class);
        
        $requestbodyParams = array (
            'id' => self::TRANSACTION_ID,
            'reference' => '',
            'order' => '',
            'type' => 'pending',
            'processor' => self::PROCESSOR_VANTIV,
            'amount' => '',
            'currency' => self::CURRENCY,
            'status' => 'authorized',
            'display_id' => '',
        );
        
        $this->createRequest($requestbodyParams);
        
        $boltOrderManagement->manage(
            self::TRANSACTION_ID,
            null,
            null,
            'pending',
            null,
            self::CURRENCY,
            'authorized'
        );
        
        $schema = $this->getResponse()->getBody();
        $responseData = json_decode($schema, true);
        
        $this->assertEquals('error', $responseData['status']);
        $this->assertEquals('6009', $responseData['code']);
        $this->assertEquals('Unprocessable Entity: Missing required parameters.', $responseData['message']);
    }

}

class stubBoltApiHelper {
	public function __construct() {
		
	}

	public function sendRequest($request)
    {
        global $apiRequestResult;
        
        if ($apiRequestResult == 'exception') {
            throw new \Exception('Request fails');
        }
        
        $result = Bootstrap::getObjectManager()->create(BoltResponseFactory::class)->create();
        
        $result->setResponse($apiRequestResult);
        
        return $result;
    }
    
    public function buildRequest($requestData)
    {
        $request = Bootstrap::getObjectManager()->create(BoltRequestFactory::class);
        
        return $request;
    }
}

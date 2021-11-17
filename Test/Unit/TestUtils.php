<?php
namespace Bolt\Boltpay\Test\Unit;

use Magento\Config\Model\ResourceModel\Config;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Type as ProductType;

use Magento\TestFramework\Helper\Bootstrap;

use Magento\Quote\Model\Quote;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote\AddressFactory;
use Magento\Sales\Model\Order\Item as OrderItem;
use Magento\Sales\Model\Order\Address as OrderAddress;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Sales\Api\OrderRepositoryInterface;




use Magento\Framework\App\Config\MutableScopeConfigInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Session\SessionManagerInterface;

class TestUtils
{

    /**
     * @param $quote
     */
    public static function setQuoteToSession($quote)
    {
        Bootstrap::getObjectManager()->get(SessionManagerInterface::class)->setQuote($quote);
    }

    /**
     * @param array $data
     * @return mixed
     */
    public static function createQuote($data = [])
    {
        $quote = Bootstrap::getObjectManager()->create(Quote::class);
        $quote->setQuoteCurrencyCode("USD");

        $addressFactory = Bootstrap::getObjectManager()->get(AddressFactory::class);
        $quote->setBillingAddress($addressFactory->create());
        $quote->setShippingAddress($addressFactory->create());

        if ($data) {
            foreach ($data as $key => $value) {
                $quote->setData($key, $value);
            }
        }

        $quote->save();
        return $quote;
    }

    /**
     * @param $quote_id
     * @return mixed
     */
    public static function getQuoteById($quote_id)
    {
        return Bootstrap::getObjectManager()->get(CartRepositoryInterface::class)->get($quote_id);
    }

    /**
     * @param $orderId
     * @return mixed
     */
    public static function getOrderById($orderId)
    {
        $orderCollection = Bootstrap::getObjectManager()->create(\Magento\Sales\Model\ResourceModel\Order\Collection::class)
            ->addFieldToSelect('*')
            ->addFilter('entity_id', $orderId);

        if ($orderCollection->getSize() > 0) {
            return $orderCollection->getFirstItem();
        }

        return false;
    }

    /**
     * @param $quote
     * @return mixed
     */
    public static function createProduct($quote)
    {
        $quote = self::createQuote();
        $product = self::createSimpleProduct();
        $product->save();
        $quote->addProduct($product, 1);
        return $quote;
    }

    /**
     * @param array $data
     * @return Order
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public static function createDumpyOrder(
        $data = [],
        $addressData = [],
        $orderItems = [],
        $state = Order::STATE_PENDING_PAYMENT,
        $status = Order::STATE_PENDING_PAYMENT,
        $payment = null
    ) {
        $objectManager = Bootstrap::getObjectManager();

        if (empty($addressData)) {
            $addressData = [
                'firstname' => 'John',
                'lastname' => 'McCombs',
                'street' => "4553 Annalee Way",
                'city' => 'Knoxville',
                'postcode' => '37921',
                'telephone' => '111111111',
                'country_id' => 'US',
                'region_id' => '56',
                'email'     => 'test@bolt.com',
            ];
        }

        $billingAddress = $objectManager->create(OrderAddress::class, ['data' => $addressData]);
        $billingAddress->setAddressType('billing');

        $shippingAddress = clone $billingAddress;
        $shippingAddress->setId(null)->setAddressType('shipping');

        /** @var Payment $payment */
        if (empty($payment)) {
            $payment = $objectManager->create(Payment::class);
            $payment->setMethod('boltpay')
                ->setAdditionalInformation('last_trans_id', '11122')
                ->setAdditionalInformation(
                    'metadata',
                    [
                        'type' => 'free',
                        'fraudulent' => false,
                    ]
                );
        }

        if (empty($orderItems)) {
            $productCollection = $objectManager->create(\Magento\Catalog\Model\ResourceModel\Product\Collection::class);

            if ($productCollection->getSize() > 0) {
                /** @var \Magento\Catalog\Model\ResourceModel\Product\Collection $product */
                $product =  $productCollection->getFirstItem();
            } else {
                $product = self::createSimpleProduct();
            }

            /** @var OrderItem $orderItem */
            $orderItem = $objectManager->create(OrderItem::class);
            $orderItem->setProductId($product->getId())
                ->setQtyOrdered(2)
                ->setBasePrice($product->getPrice())
                ->setPrice($product->getPrice())
                ->setRowTotal($product->getPrice())
                ->setProductType('simple')
                ->setName($product->getName());

            $orderItems = [];
            $orderItems[] = $orderItem;
        }

        /** @var Order $order */
        $order = $objectManager->create(Order::class);
        $order->setIncrementId('100000001')
            ->setState($state)
            ->setStatus($order->getConfig()->getStateDefaultStatus($status))
            ->setSubtotal(100)
            ->setGrandTotal(100)
            ->setBaseSubtotal(100)
            ->setBaseGrandTotal(100)
            ->setOrderCurrencyCode('USD')
            ->setCustomerIsGuest(true)
            ->setCustomerEmail($addressData['email'])
            ->setBillingAddress($billingAddress)
            ->setShippingAddress($shippingAddress)
            ->setAddresses([$billingAddress, $shippingAddress])
            ->setStoreId($objectManager->get(StoreManagerInterface::class)->getStore()->getId())
            ->setPayment($payment);

        foreach ($orderItems as $orderItem) {
            $order->addItem($orderItem);
        }

        if ($data) {
            foreach ($data as $key => $value) {
                $order->setData($key, $value);
            }
        }

        /** @var OrderRepositoryInterface $orderRepository */
        $orderRepository = $objectManager->create(OrderRepositoryInterface::class);
        $orderRepository->save($order);
        return $order;
    }

    public static function getSimpleProduct()
    {
        $objectManager = Bootstrap::getObjectManager();
        $productCollection = $objectManager->create(\Magento\Catalog\Model\ResourceModel\Product\Collection::class);

        if ($productCollection->getSize() > 0) {
            /** @var \Magento\Catalog\Model\ResourceModel\Product\Collection $product */
            foreach ($productCollection as $product) {
                if ($product->getTypeId() === ProductType::TYPE_SIMPLE) {
                    return Bootstrap::getObjectManager()->create(Product::class)->load($product->getId());
                }
            }
        }

        return self::createSimpleProduct();
    }

    /**
     * @return mixed
     */
    public static function getVirtualProduct()
    {
        $objectManager = Bootstrap::getObjectManager();
        $productCollection = $objectManager->create(\Magento\Catalog\Model\ResourceModel\Product\Collection::class);

        if ($productCollection->getSize() > 0) {
            /** @var \Magento\Catalog\Model\ResourceModel\Product\Collection $product */
            foreach ($productCollection as $product) {
                if ($product->getTypeId() === ProductType::TYPE_VIRTUAL) {
                    return Bootstrap::getObjectManager()->create(Product::class)->load($product->getId());
                }
            }
        }

        return self::createVirtualProduct();
    }

    /**
     * @return mixed
     */
    public static function createSimpleProduct()
    {
        $product = Bootstrap::getObjectManager()->create(Product::class);
        $product->setTypeId(ProductType::TYPE_SIMPLE)
            ->setAttributeSetId(4)
            ->setWebsiteIds([1])
            ->setName('Test Product')
            ->setSku(md5(uniqid(rand(), true)))
            ->setPrice(100)
            ->setDescription('Product Description')
            ->setMetaTitle('meta title')
            ->setMetaKeyword('meta keyword')
            ->setMetaDescription('meta description')
            ->setVisibility(\Magento\Catalog\Model\Product\Visibility::VISIBILITY_BOTH)
            ->setStatus(\Magento\Catalog\Model\Product\Attribute\Source\Status::STATUS_ENABLED)
            ->setCategoryIds([2])
            ->setStockData(['use_config_manage_stock' => 0])
            ->setCanSaveCustomOptions(true)
            ->setHasOptions(true)
            ->setUrlKey('test-simple-product-'.round(microtime(true) * 1000));
        $product->save();
        return $product;
    }

    /**
     * @return mixed
     */
    public static function createVirtualProduct()
    {
        $product = Bootstrap::getObjectManager()->create(Product::class);
        $product->setTypeId(ProductType::TYPE_VIRTUAL)
            ->setAttributeSetId(4)
            ->setWebsiteIds([1])
            ->setName('Test Virtual Product')
            ->setSku(md5(uniqid(rand(), true)))
            ->setPrice(100)
            ->setDescription('Product Description')
            ->setMetaTitle('meta title')
            ->setMetaKeyword('meta keyword')
            ->setMetaDescription('meta description')
            ->setVisibility(\Magento\Catalog\Model\Product\Visibility::VISIBILITY_BOTH)
            ->setStatus(\Magento\Catalog\Model\Product\Attribute\Source\Status::STATUS_ENABLED)
            ->setCategoryIds([2])
            ->setStockData(['use_config_manage_stock' => 0])
            ->setCanSaveCustomOptions(true)
            ->setHasOptions(true)
            ->setUrlKey('test-virtual-product-'.round(microtime(true) * 1000));
        $product->save();
        return $product;
    }


    /**
     * @return mixed
     */
    public static function createConfigurableProduct()
    {
        $product = Bootstrap::getObjectManager()->create(Product::class);
        $product->setTypeId(\Magento\ConfigurableProduct\Model\Product\Type\Configurable::TYPE_CODE)
            ->setAttributeSetId(4)
            ->setWebsiteIds([1])
            ->setName('Test Configurable Product')
            ->setSku(md5(uniqid(rand(), true)))
            ->setPrice(100)
            ->setDescription('Product Description')
            ->setMetaTitle('meta title')
            ->setMetaKeyword('meta keyword')
            ->setMetaDescription('meta description')
            ->setVisibility(\Magento\Catalog\Model\Product\Visibility::VISIBILITY_BOTH)
            ->setStatus(\Magento\Catalog\Model\Product\Attribute\Source\Status::STATUS_ENABLED)
            ->setCategoryIds([2])
            ->setStockData(['use_config_manage_stock' => 0])
            ->setCanSaveCustomOptions(true)
            ->setHasOptions(true)
            ->setUrlKey('test-virtual-product-'.round(microtime(true) * 1000));
        $product->save();
        return $product;
    }

    /**
     * @return mixed
     */
    public static function createDownloadableProduct()
    {
        $product = Bootstrap::getObjectManager()->create(Product::class);
        $product->setTypeId(\Magento\Downloadable\Model\Product\Type::TYPE_DOWNLOADABLE)
            ->setAttributeSetId(4)
            ->setWebsiteIds([1])
            ->setName('Test Downloadable Product')
            ->setSku(md5(uniqid(rand(), true)))
            ->setPrice(100)
            ->setDescription('Product Description')
            ->setMetaTitle('meta title')
            ->setMetaKeyword('meta keyword')
            ->setMetaDescription('meta description')
            ->setVisibility(\Magento\Catalog\Model\Product\Visibility::VISIBILITY_BOTH)
            ->setStatus(\Magento\Catalog\Model\Product\Attribute\Source\Status::STATUS_ENABLED)
            ->setCategoryIds([2])
            ->setStockData(['use_config_manage_stock' => 0])
            ->setCanSaveCustomOptions(true)
            ->setHasOptions(true)
            ->setUrlKey('test-downloadable-product-'.round(microtime(true) * 1000));
        $product->save();
        return $product;
    }

    /**
     * @return mixed
     */
    public static function createGroupProduct()
    {
        $product = Bootstrap::getObjectManager()->create(Product::class);
        $product->setTypeId(\Magento\GroupedProduct\Model\Product\Type\Grouped::TYPE_CODE)
            ->setAttributeSetId(4)
            ->setWebsiteIds([1])
            ->setName('Test Group Product')
            ->setSku(md5(uniqid(rand(), true)))
            ->setPrice(100)
            ->setDescription('Product Description')
            ->setMetaTitle('meta title')
            ->setMetaKeyword('meta keyword')
            ->setMetaDescription('meta description')
            ->setVisibility(\Magento\Catalog\Model\Product\Visibility::VISIBILITY_BOTH)
            ->setStatus(\Magento\Catalog\Model\Product\Attribute\Source\Status::STATUS_ENABLED)
            ->setCategoryIds([2])
            ->setStockData(['use_config_manage_stock' => 0])
            ->setCanSaveCustomOptions(true)
            ->setHasOptions(true)
            ->setUrlKey('test-group-product-'.round(microtime(true) * 1000));
        $product->save();
        return $product;
    }

    public static function setSecureAreaIfNeeded()
    {
        $registry = Bootstrap::getObjectManager()->get("\Magento\Framework\Registry");
        if ($registry->registry('isSecureArea') === null) {
            $registry->register('isSecureArea', true);
        }
    }

    /**
     * @return bool
     */
    private static function isMagentoIntegrationMode()
    {
        return class_exists('\Magento\TestFramework\Helper\Bootstrap');
    }

    /**
     * @param $objects
     * @throws \Exception
     */
    public static function cleanupSharedFixtures($objects)
    {
        // don't need to clean up on unit test mode
        if (!self::isMagentoIntegrationMode()) {
            return;
        }
        session_unset();
        self::setSecureAreaIfNeeded();
        foreach ($objects as $object) {
            switch (get_class($object)) {
                case "Magento\Catalog\Model\Product\Interceptor":
                    $object->delete();
                    break;
                case "Magento\Sales\Model\Order\Interceptor":
                    $object->delete();
                    break;
                case "Bolt\Boltpay\Model\CustomerCreditCard":
                    $object->delete();
                    break;
                default:
                    throw new \Exception("Unexpected type for delete:".get_class($object));
            }
        }
    }

    /**
     * @param $addressData
     * @param $quote
     * @param $addressType
     */
    public static function setAddressToQuote($addressData, $quote, $addressType)
    {
        if ($addressType === 'billing') {
            $address = $quote->getBillingAddress();
        }

        if ($addressType === 'shipping') {
            $address = $quote->getShippingAddress();
        }

        $address->setFirstname($addressData['first_name']);
        $address->setLastname($addressData['last_name']);
        $address->setCompany($addressData['company']);
        $address->setTelephone($addressData['phone']);
        $address->setStreet([
            $addressData['street_address1'],
            $addressData['street_address2']
        ]);
        $address->setCity($addressData['locality']);
        $address->setRegion($addressData['region']);
        $address->setPostcode($addressData['postal_code']);
        $address->setCountryId($addressData['country_code']);
        $address->setEmail($addressData['email']);
    }

    public static function setupBoltConfig($configData)
    {
        // don't need to clean up on unit test mode
        if (!self::isMagentoIntegrationMode()) {
            return;
        }

        $objectManager = Bootstrap::getObjectManager();

        $model = $objectManager->get(Config::class);
        $scopeConfig = $objectManager->get(MutableScopeConfigInterface::class);

        foreach ($configData as $data) {
            $model->saveConfig($data['path'], $data['value'], $data['scope'], $data['scopeId']);
            $scopeConfig->setValue(
                $data['path'],
                $data['value'],
                $data['scope']
            );
        }
    }

    public static function createCustomer($websiteId, $storeId, $addressInfo)
    {
        $objectManager = Bootstrap::getObjectManager();

        $repository = $objectManager->create(\Magento\Customer\Api\CustomerRepositoryInterface::class);
        $customer = $objectManager->create(\Magento\Customer\Model\Customer::class);
        /** @var CustomerRegistry $customerRegistry */
        $customerRegistry = $objectManager->get(\Magento\Customer\Model\CustomerRegistry::class);
        /** @var Magento\Customer\Model\Customer $customer */
        $customer->setWebsiteId($websiteId)
            ->setStoreId($storeId)
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


    public static function saveFeatureSwitch($name, $value = true, $defaultValue = true, $rolloutPercentage = 100)
    {
        $objectManager = Bootstrap::getObjectManager();
        /** @var \Bolt\Boltpay\Model\FeatureSwitchRepository $featureSwitchRepository */
        $featureSwitchRepository = $objectManager->get(\Bolt\Boltpay\Api\FeatureSwitchRepositoryInterface::class);
        return $featureSwitchRepository->upsertByName($name, $value, $defaultValue, $rolloutPercentage);
    }

    public static function cleanupFeatureSwitch($switch)
    {
        $objectManager = Bootstrap::getObjectManager();
        /** @var \Bolt\Boltpay\Model\FeatureSwitchRepository $featureSwitchRepository */
        $featureSwitchRepository = $objectManager->get(\Bolt\Boltpay\Api\FeatureSwitchRepositoryInterface::class);
        $featureSwitchRepository->delete($switch);
    }

    public static function createSampleAddress()
    {
        return [
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
            "email_address"   => "buy@test.com",
        ];
    }

    public static function createMagentoSampleAddress()
    {
        $sampleAddress = self::createSampleAddress();

        $sampleMagentoData = [
            'region'     => $sampleAddress['region_code'],
            'region_id'  => $sampleAddress['region_id'],
            'postcode'   => $sampleAddress['postal_code'],
            'lastname'   => $sampleAddress['last_name'],
            'firstname'  => $sampleAddress['first_name'],
            'street'     => $sampleAddress['street_address1'],
            'city'       => $sampleAddress['locality'],
            'email'      => $sampleAddress['email_address'],
            'telephone'  => $sampleAddress['phone_number'],
            'country_id' => $sampleAddress['country_code'],
        ];

        return $sampleMagentoData;
    }

    public static function createStockItemForProduct($product, $qty)
    {
        $stockItem = Bootstrap::getObjectManager()->create(\Magento\CatalogInventory\Api\Data\StockItemInterface::class);
        $stockItem->setQty($qty)
            ->setIsInStock(true);
        $extensionAttributes = $product->getExtensionAttributes();
        $extensionAttributes->setStockItem($stockItem);
    }

    public static function createQuoteAddress($addressData, $addressType)
    {
        $quoteAddress = Bootstrap::getObjectManager()->create(\Magento\Quote\Api\Data\AddressInterface::class, ['data' => $addressData]);
        $quoteAddress->setAddressType($addressType)
            ->setCustomerAddressId('not_existing');

        return $quoteAddress;
    }

    public static function createQuoteShippingRate($shippingCode, $shippingCost, $addressId)
    {
        $rate = Bootstrap::getObjectManager()->create(\Magento\Quote\Model\Quote\Address\Rate::class);
        $rate->setCode($shippingCode)
             ->setPrice($shippingCost)
             ->setAddressId($addressId)
             ->save();

        return $rate;
    }

    public static function setQuotePayment($quote, $paymentMethodCode)
    {
        $paymentFactory = Bootstrap::getObjectManager()->get(\Magento\Quote\Api\Data\PaymentInterfaceFactory::class);
        $paymentMethodManagement = Bootstrap::getObjectManager()->get(\Magento\Quote\Api\PaymentMethodManagementInterface::class);
        $quotePayment = $paymentFactory->create([
            'data' => [
                \Magento\Quote\Api\Data\PaymentInterface::KEY_METHOD => $paymentMethodCode,
            ]
        ]);

        $quoteId = $quote->getId();
        $paymentMethodManagement->set($quoteId, $quotePayment);

        $quoteRepository = Bootstrap::getObjectManager()->get(\Magento\Quote\Model\QuoteRepository::class);
        $quoteRepository->save($quote);

        return $quote;
    }

    public static function createOrderItemByProduct($product, $qty)
    {
        $orderItem = Bootstrap::getObjectManager()->create(\Magento\Sales\Model\Order\Item::class);
        $orderItem->setProductId($product->getId());
        $orderItem->setQtyOrdered($qty);
        $orderItem->setBasePrice($product->getPrice());
        $orderItem->setPrice($product->getPrice());
        $orderItem->setRowTotal($product->getPrice());
        $orderItem->setBaseRowTotal($product->getPrice());
        $orderItem->setRowTotalInclTax($product->getPrice());
        $orderItem->setBaseRowTotalInclTax($product->getPrice());
        $orderItem->setProductType($product->getTypeId());
        $orderItem->setName($product->getName());
        $orderItem->setSku($product->getSku());

        return $orderItem;
    }
}

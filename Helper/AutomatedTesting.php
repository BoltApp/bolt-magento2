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
 * @copyright  Copyright (c) 2017-2021 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Bolt\Boltpay\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Catalog\Api\ProductRepositoryInterface as ProductRepository;
use Magento\Catalog\Model\Product;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SortOrder;
use Bolt\Boltpay\Model\Api\Data\AutomatedTesting\ConfigFactory;
use Bolt\Boltpay\Model\Api\Data\AutomatedTesting\StoreItemFactory;
use Bolt\Boltpay\Model\Api\Data\AutomatedTesting\CartFactory;
use Bolt\Boltpay\Model\Api\Data\AutomatedTesting\CartItemFactory;
use Bolt\Boltpay\Model\Api\Data\AutomatedTesting\ShippingMethodFactory;
use Bolt\Boltpay\Model\Api\Data\AutomatedTesting\ShippingMethod;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Quote\Model\QuoteFactory;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Cart\ShippingMethodConverter;
use Magento\Quote\Model\Quote\Address;

/**
 * Helper for automated testing
 */
class AutomatedTesting extends AbstractHelper
{
    /**
     * @var ProductRepository
     */
    private $productRepository;

    /**
     * @var SearchCriteriaBuilder
     */
    private $searchCriteriaBuilder;

    /**
     * @var SortOrder
     */
    private $sortOrder;

    /**
     * @var ConfigFactory
     */
    private $configFactory;

    /**
     * @var StoreItemFactory
     */
    private $storeItemFactory;

    /**
     * @var CartFactory
     */
    private $cartFactory;

    /**
     * @var CartItemFactory
     */
    private $cartItemFactory;

    /**
     * @var ShippingMethodFactory
     */
    private $shippingMethodFactory;

    /**
     * @var ShippingMethodConverter
     */
    private $shippingMethodConverter;

    /**
     * @var Bugsnag
     */
    private $bugsnag;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var QuoteFactory
     */
    private $quoteFactory;

    /**
     * @param Context $context
     * @param ProductRepository $productRepository
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param SortOrder $sortOrder
     * @param ConfigFactory $configFactory
     * @param StoreItemFactory $storeItemFactory
     * @param CartFactory $cartFactory
     * @param CartItemFactory $cartItemFactory
     * @param ShippingMethodFactory $shippingMethodFactory
     * @param Bugsnag $bugsnag
     * @param StoreManagerInterface $storeManager
     * @param ShippingMethodConverter $shippingMethodConverter
     */
    public function __construct(
        Context $context,
        ProductRepository $productRepository,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        SortOrder $sortOrder,
        ConfigFactory $configFactory,
        StoreItemFactory $storeItemFactory,
        CartFactory $cartFactory,
        CartItemFactory $cartItemFactory,
        ShippingMethodFactory $shippingMethodFactory,
        Bugsnag $bugsnag,
        StoreManagerInterface $storeManager,
        QuoteFactory $quoteFactory,
        ShippingMethodConverter $shippingMethodConverter
    ) {
        parent::__construct($context);
        $this->productRepository = $productRepository;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->sortOrder = $sortOrder;
        $this->configFactory = $configFactory;
        $this->storeItemFactory = $storeItemFactory;
        $this->cartFactory = $cartFactory;
        $this->cartItemFactory = $cartItemFactory;
        $this->shippingMethodFactory = $shippingMethodFactory;
        $this->bugsnag = $bugsnag;
        $this->storeManager = $storeManager;
        $this->quoteFactory = $quoteFactory;
        $this->shippingMethodConverter = $shippingMethodConverter;
    }

    /**
     * Generate and return automated testing config
     *
     * @return Config
     */
    public function getAutomatedTestingConfig()
    {
        try {
            $simpleProduct = $this->getProduct(\Magento\Catalog\Model\Product\Type::TYPE_SIMPLE);
            if ($simpleProduct === null) {
                return;
            }

            $virtualProduct = $this->getProduct(\Magento\Catalog\Model\Product\Type::TYPE_VIRTUAL);

            $simpleProductPrice = $this->formatPrice($simpleProduct->getPrice(), false);
            $simpleStoreItem = $this->storeItemFactory->create()
                                                      ->setItemUrl($simpleProduct->getProductUrl())
                                                      ->setName(trim($simpleProduct->getName()))
                                                      ->setPrice($simpleProductPrice)
                                                      ->setType('simple');
            $virtualStoreItem = $virtualProduct === null
                ? null
                : $this->storeItemFactory->create()
                                         ->setItemUrl($virtualProduct->getProductUrl())
                                         ->setName(trim($virtualProduct->getName()))
                                         ->setPrice($this->formatPrice($virtualProduct->getPrice(), false))
                                         ->setType('virtual');

            $storeItems[] = $simpleStoreItem;
            if ($virtualStoreItem !== null) {
                $storeItems[] = $virtualStoreItem;
            }

            $quote = $this->createQuoteWithItem($simpleProduct);
            $shippingMethods = $this->getShippingMethods($quote->getShippingAddress());
            if (empty($shippingMethods)) {
                return null;
            }

            $simpleCartItem = $this->cartItemFactory->create()
                                                    ->setName(trim($simpleProduct->getName()))
                                                    ->setPrice($simpleProductPrice)
                                                    ->setQuantity(1);
            $cart = $this->cartFactory->create()
                                      ->setItems([$simpleCartItem])
                                      ->setShipping(reset($shippingMethods))
                                      ->setExpectedShippingMethods($shippingMethods)
                                      ->setSubTotal($simpleProductPrice);

            return $this->configFactory->create()
                                       ->setStoreItems($storeItems)
                                       ->setCart($cart);
        } catch (\Exception $e) {
            $this->bugsnag->notifyException($e);
        }
    }

    /**
     * Return a product with the specified type
     *
     * @param string $type
     *
     * @return Product|null
     */
    private function getProduct($type)
    {
        $this->sortOrder->setField('price')->setDirection('DESC');

        $searchCriteria = $this->searchCriteriaBuilder
            ->addFilter('type_id', $type)
            ->setSortOrders([$this->sortOrder])
            ->create();

        $products = $this->productRepository
            ->getList($searchCriteria)
            ->getItems();

        return empty($products) ? null : reset($products);
    }

    /**
     * Create a quote containing $product and add the shipping address used by integration tests
     *
     * @param Product $product
     *
     * @return Quote
     */
    private function createQuoteWithItem($product)
    {
        $store = $this->storeManager->getStore();
        $quote = $this->quoteFactory->create();
        $quote->setStore($store);
        $quote->addProduct($product, 1);
        $quote->getShippingAddress()->addData([
            'street'     => '1235 Howard St Ste D',
            'city'       => 'San Francisco',
            'country_id' => 'US',
            'region'     => 'CA',
            'postcode'   => '94103'
        ]);
        return $quote;
    }

    /**
     * Return the shipping methods for $quote
     *
     * @param Address $address
     *
     * @return ShippingMethod[]
     */
    private function getShippingMethods($address)
    {
        $shippingRates = $address->getGroupedAllShippingRates();
        $shippingMethods = [];
        foreach ($shippingRates as $carrierRates) {
            foreach ($carrierRates as $rate) {
                $convertedShippingMethod = $this->shippingMethodConverter->modelToDataObject($rate, 'USD');
                $shippingMethodName = $convertedShippingMethod->getCarrierTitle() . ' - ' . $convertedShippingMethod->getMethodTitle();
                $shippingMethodPrice = $this->formatPrice($convertedShippingMethod->getAmount(), true);
                $shippingMethods[] = $this->shippingMethodFactory->create()
                                                                 ->setName($shippingMethodName)
                                                                 ->setPrice($shippingMethodPrice);
            }
        }
        return $shippingMethods;
    }

    /**
     * Format the price for automated testing config
     *
     * @param float $price
     * @param bool $isShippingPrice
     *
     * @return string
     */
    private function formatPrice($price, $isShippingPrice)
    {
        return $isShippingPrice && $price === 0 ? 'FREE' : '$' . number_format($price, 2, '.', '');
    }
}
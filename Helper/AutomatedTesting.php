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

use Bolt\Boltpay\Model\Api\Data\AutomatedTesting\CartFactory;
use Bolt\Boltpay\Model\Api\Data\AutomatedTesting\CartItemFactory;
use Bolt\Boltpay\Model\Api\Data\AutomatedTesting\Config as AutomatedTestingConfig;
use Bolt\Boltpay\Model\Api\Data\AutomatedTesting\ConfigFactory;
use Bolt\Boltpay\Model\Api\Data\AutomatedTesting\PriceProperty;
use Bolt\Boltpay\Model\Api\Data\AutomatedTesting\PricePropertyFactory;
use Bolt\Boltpay\Model\Api\Data\AutomatedTesting\StoreItem;
use Bolt\Boltpay\Model\Api\Data\AutomatedTesting\StoreItemFactory;
use Magento\Catalog\Api\ProductRepositoryInterface as ProductRepository;
use Magento\Catalog\Model\Product;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SortOrder;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Quote\Api\CartManagementInterface;
use Magento\Quote\Api\CartRepositoryInterface as QuoteRepository;
use Magento\Quote\Model\Cart\ShippingMethodConverter;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\QuoteFactory;
use Magento\Store\Model\StoreManagerInterface;

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
     * @var PricePropertyFactory
     */
    private $pricePropertyFactory;

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
     * @var CartManagementInterface
     */
    private $quoteManagement;

    /**
     * @var QuoteRepository
     */
    private $quoteRepository;

    /**
     * @param Context $context
     * @param ProductRepository $productRepository
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param SortOrder $sortOrder
     * @param ConfigFactory $configFactory
     * @param StoreItemFactory $storeItemFactory
     * @param CartFactory $cartFactory
     * @param CartItemFactory $cartItemFactory
     * @param PricePropertyFactory $pricePropertyFactory
     * @param ShippingMethodConverter $shippingMethodConverter
     * @param Bugsnag $bugsnag
     * @param StoreManagerInterface $storeManager
     * @param CartManagementInterface $quoteManagement
     * @param QuoteRepository $quoteRepository
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
        PricePropertyFactory $pricePropertyFactory,
        ShippingMethodConverter $shippingMethodConverter,
        Bugsnag $bugsnag,
        StoreManagerInterface $storeManager,
        QuoteFactory $quoteFactory,
        CartManagementInterface $quoteManagement,
        QuoteRepository $quoteRepository
    ) {
        parent::__construct($context);
        $this->productRepository = $productRepository;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->sortOrder = $sortOrder;
        $this->configFactory = $configFactory;
        $this->storeItemFactory = $storeItemFactory;
        $this->cartFactory = $cartFactory;
        $this->cartItemFactory = $cartItemFactory;
        $this->pricePropertyFactory = $pricePropertyFactory;
        $this->shippingMethodConverter = $shippingMethodConverter;
        $this->bugsnag = $bugsnag;
        $this->storeManager = $storeManager;
        $this->quoteFactory = $quoteFactory;
        $this->quoteManagement = $quoteManagement;
        $this->quoteRepository = $quoteRepository;
    }

    /**
     * Generate and return automated testing config
     *
     * @return AutomatedTestingConfig|string
     */
    public function getAutomatedTestingConfig()
    {
        try {
            $simpleProduct = $this->getProduct(\Magento\Catalog\Model\Product\Type::TYPE_SIMPLE);
            if ($simpleProduct === null) {
                return 'no simple products found';
            }

            $virtualProduct = $this->getProduct(\Magento\Catalog\Model\Product\Type::TYPE_VIRTUAL);
            $simpleStoreItem = $this->convertToStoreItem($simpleProduct, 'simple');
            $virtualStoreItem = $this->convertToStoreItem($virtualProduct, 'virtual');
            $storeItems[] = $simpleStoreItem;
            if ($virtualStoreItem !== null) {
                $storeItems[] = $virtualStoreItem;
            }

            $quote = $this->createQuoteWithItem($simpleProduct);
            $shippingMethods = $this->getShippingMethods($quote);
            if (empty($shippingMethods)) {
                return 'no shipping methods found';
            }

            $quote->collectTotals();
            $this->quoteRepository->save($quote);
            $simpleCartItem = $this->cartItemFactory->create()
                                                    ->setName($simpleStoreItem->getName())
                                                    ->setPrice($simpleStoreItem->getPrice())
                                                    ->setQuantity(1);
            $cart = $this->cartFactory->create()
                                      ->setItems([$simpleCartItem])
                                      ->setShipping(reset($shippingMethods))
                                      ->setExpectedShippingMethods($shippingMethods)
                                      ->setTax($this->formatPrice($quote->getShippingAddress()->getTaxAmount(), false))
                                      ->setSubTotal($this->formatPrice($quote->getSubtotal(), false));
            $this->quoteRepository->delete($quote);

            return $this->configFactory->create()->setStoreItems($storeItems)->setCart($cart);
        } catch (\Exception $e) {
            $this->bugsnag->notifyException($e);
            return 'error retrieving automated testing config';
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
            ->addFilter('visibility', \Magento\Catalog\Model\Product\Visibility::VISIBILITY_NOT_VISIBLE, 'neq')
            ->setSortOrders([$this->sortOrder])
            ->create();

        $products = $this->productRepository
            ->getList($searchCriteria)
            ->getItems();

        return empty($products) ? null : reset($products);
    }

    /**
     * Convert $product to a StoreItem
     *
     * @param Product|null $product
     * @param string $type
     *
     * @return StoreItem|null
     */
    private function convertToStoreItem($product, $type)
    {
        if ($product === null) {
            return null;
        }
        return $this->storeItemFactory->create()
                                      ->setItemUrl($product->getProductUrl())
                                      ->setName(trim($product->getName()))
                                      ->setPrice($this->formatPrice($product->getPrice(), false))
                                      ->setType($type);
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
        $quoteId = $this->quoteManagement->createEmptyCart();
        $quote = $this->quoteFactory->create()->load($quoteId);
        $quote->setStoreId($this->storeManager->getStore()->getId());
        $quote->addProduct($product, 1);
        $quote->getShippingAddress()->addData([
            'street'     => '1235 Howard St Ste D',
            'city'       => 'San Francisco',
            'country_id' => 'US',
            'region'     => 'CA',
            'postcode'   => '94103'
        ]);
        $this->quoteRepository->save($quote);
        return $quote;
    }

    /**
     * Return the shipping methods for $quote
     *
     * @param Quote $quote
     *
     * @return PriceProperty[]
     */
    private function getShippingMethods($quote)
    {
        $address = $quote->getShippingAddress();

        $flattenedRates = [];
        foreach ($address->getGroupedAllShippingRates() as $carrierRates) {
            foreach ($carrierRates as $rate) {
                $flattenedRates[] = $this->shippingMethodConverter->modelToDataObject($rate, 'USD');
            }
        }

        if (empty($flattenedRates)) {
            return [];
        }

        $firstShippingMethod = reset($flattenedRates);
        $address->setCollectShippingRates(true)
                ->setShippingMethod($firstShippingMethod->getCarrierCode() . '_' . $firstShippingMethod->getMethodCode())
                ->save();

        $shippingMethods = [];
        foreach ($flattenedRates as $rate) {
            $shippingMethodName = $rate->getCarrierTitle() . ' - ' . $rate->getMethodTitle();
            $shippingMethodPrice = $this->formatPrice($rate->getAmount(), true);
            $shippingMethods[] = $this->pricePropertyFactory->create()
                                                            ->setName($shippingMethodName)
                                                            ->setPrice($shippingMethodPrice);
        }

        return $shippingMethods;
    }

    /**
     * Format price
     *
     * @param float $price
     * @param bool $isShipping
     *
     * @return string
     */
    private function formatPrice($price, $isShipping)
    {
        return $price === 0.0 && $isShipping ? 'Free' : '$' . number_format($price, 2, '.', '');
    }
}

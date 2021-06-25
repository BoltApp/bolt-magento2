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

namespace Bolt\Boltpay\Model\Api;

use Bolt\Boltpay\Api\Data\GetProductDataInterface;
use Bolt\Boltpay\Api\GetProductInterface;
use Bolt\Boltpay\Helper\Bugsnag;
use Bolt\Boltpay\Helper\Hook as HookHelper;
use Bolt\Boltpay\Model\Api\Data\ProductInventoryInfo;
use Exception;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\CatalogInventory\Api\StockConfigurationInterface;
use Magento\CatalogInventory\Model\Spi\StockRegistryProviderInterface;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Magento\Eav\Model\Config;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Webapi\Exception as WebapiException;
use Magento\Store\Model\StoreManagerInterface;


class GetProduct implements GetProductInterface
{
    /**
     * @var GetProductDataInterface
     */
    private $productData;


    /**
     * @var integer
     */
    private $storeID;

    /**
     * @var integer
     */
    private $websiteId;

    /**
     * @var ProductRepositoryInterface
     */
    protected $productRepositoryInterface;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var \Magento\ConfigurableProduct\Model\Product\Type\Configurable
     */
    private $configurable;

    /**
     * @var StockRegistryProviderInterface
     */
    private $stockRegistry;

    /**
     * @var StockConfigurationInterface
     */
    private $stockConfiguration;

    /**
     * @var HookHelper
     */
    private $hookHelper;

    /**
     * @var Bugsnag
     */
    private $bugsnag;

    /**
     * @var \Magento\Eav\Model\Config
     */
    private $eavConfig;

    /**
     * @var \Magento\ConfigurableProduct\Helper\Data
     */
    private $configurableProductHelper;

    /**
     * @var \Magento\ConfigurableProduct\Model\ConfigurableAttributeData
     */
    private $configurableProductConfigurableAttributeDataModel;

    /**
     * @var \Magento\Catalog\Helper\Product
     */
    private $productHelper;

    /**
     * @param ProductRepositoryInterface $productRepositoryInterface
     * @param StockRegistryProviderInterface $stockRegistry
     * @param StockConfigurationInterface $stockConfiguration
     * @param GetProductDataInterface $productData
     * @param StoreManagerInterface $storeManager
     * @param Configurable $configurable
     * @param Config $eavConfig
     * @param HookHelper $hookHelper
     * @param Bugsnag $bugsnag
     */
    public function __construct(
        ProductRepositoryInterface  $productRepositoryInterface,
        StockRegistryProviderInterface  $stockRegistry,
        StockConfigurationInterface  $stockConfiguration,
        GetProductDataInterface  $productData,
        StoreManagerInterface $storeManager,
        Configurable          $configurable,
        Config $eavConfig,
        HookHelper $hookHelper,
        Bugsnag $bugsnag,
        \Magento\Catalog\Helper\Product $productHelper,
        \Magento\ConfigurableProduct\Helper\Data $configurableProductHelper,
        \Magento\ConfigurableProduct\Model\ConfigurableAttributeData $configurableProductConfigurableAttributeDataModel
    ) {
        $this->productRepositoryInterface = $productRepositoryInterface;
        $this->stockRegistry = $stockRegistry;
        $this->stockConfiguration = $stockConfiguration;
        $this->productData = $productData;
        $this->storeManager = $storeManager;
        $this->hookHelper = $hookHelper;
        $this->bugsnag = $bugsnag;
        $this->configurable = $configurable;
        $this->eavConfig = $eavConfig;
        $this->productHelper = $productHelper;
        $this->configurableProductHelper = $configurableProductHelper;
        $this->configurableProductConfigurableAttributeDataModel = $configurableProductConfigurableAttributeDataModel;
    }

    private function getStockStatus($product){
        $stockStatus = $this->stockRegistry->getStockStatus($product->getId(), $this->websiteId);
        if($stockStatus->getProductId() === null){
            $stockStatus = $this->stockRegistry->getStockStatus($product->getId(), $this->stockConfiguration->getDefaultScopeId());
        }
        return $stockStatus;
    }

    private function getProduct($productID, $sku){
        $this->websiteId = $this->storeManager->getStore()->getWebsiteId();
        $productInventory = new ProductInventoryInfo();

        if ($productID != "") {
            $product = $this->productRepositoryInterface->getById($productID, false, $this->websiteId, false);
            $productInventory->setProduct($product);
            $productInventory->setStock($this->getStockStatus($product));
            $this->productData->setProductInventory($productInventory);
        } elseif ($sku != "") {
            $product = $this->productRepositoryInterface->get($sku, false, $this->storeID, false);
            $productInventory->setProduct($product);
            $productInventory->setStock($this->getStockStatus($product));
            $this->productData->setProductInventory($productInventory);
        }
    }


    private function getProductFamily(){
        $product = $this->productData->getProductInventory()->getProduct();
        $parent = $this->configurable->getParentIdsByChild($product->getId());
        if(isset($parent[0])){
            $parentProductInventory = new ProductInventoryInfo();
            $parentProduct = $this->productRepositoryInterface->getById($parent[0], false, $this->storeID, false);
            $parentProductInventory->setProduct($parentProduct);
            $parentProductInventory->setStock($this->getStockStatus($parentProduct));
            $this->productData->setParent($parentProductInventory);


            $children = $parentProduct->getTypeInstance()->getUsedProducts($parentProduct);
            $childrenStockArray = array();
            foreach ($children  as $child) {
                $childProductInventory = new ProductInventoryInfo();
                $childProductInventory->setProduct($child);
                $childProductInventory->setStock($this->getStockStatus($child));
                if ($child->getTypeId() == Configurable::TYPE_CODE) {
                    $childProductInventory->setOptions($this->getConfigurableProductOptions($child));
                }
                array_push($childrenStockArray, $childProductInventory);
            }
            $this->productData->setChildren($childrenStockArray);

            $attributes = $parentProduct->getTypeInstance()->getConfigurableOptions($parentProduct);
        } elseif ($product->getTypeId() == Configurable::TYPE_CODE) {
            $children = $product->getTypeInstance()->getUsedProducts($product);
            $childrenStockArray = array();
            foreach ($children  as $child) {
                $childProductInventory = new ProductInventoryInfo();
                $childProductInventory->setProduct($child);
                $childProductInventory->setStock($this->getStockStatus($child));
                array_push($childrenStockArray, $childProductInventory);
            }
            $this->productData->setChildren($childrenStockArray);

            $this->productData->getProductInventory()->setOptions($this->getConfigurableProductOptions($product));
        }
    }

    // TODO: ADD unit tests @ethan
    /**
     * Get product, its stock, and product family
     *
     * @api
     *
     * @param string $productID
     * @param string $sku
     *
     * @return \Bolt\Boltpay\Api\Data\GetProductDataInterface
     *
     * @throws NoSuchEntityException
     * @throws WebapiException
     */
    public function execute($productID = '', $sku = '')
    {
        if ($productID === '' && $sku ==='') {
            throw new WebapiException(__('Missing a product ID or a sku in the request parameters.'), 0, WebapiException::HTTP_BAD_REQUEST);
        }

        try {
            $store = $this->storeManager->getStore();
            $this->storeID = $store->getId();
            $this->productData->setStoreID($this->storeID);
            $baseImageUrl = $store->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_MEDIA) . 'catalog/product';
            $this->productData->setBaseImageUrl($baseImageUrl);

            $this->getProduct($productID, $sku);
            $this->getProductFamily();
            return $this->productData;
        } catch (NoSuchEntityException $nse) {
            throw new NoSuchEntityException(__('Product not found with given identifier.'));
        } catch (Exception $e) {
            $this->bugsnag->notifyException($e);
            throw new WebapiException(__($e->getMessage()), 0, WebapiException::HTTP_INTERNAL_ERROR);
        }
    }

    /**
     * Collects configurable options (super attributes) available for the provided configurable product
     *
     * @param ProductInterface $product for which to collect configurable options
     *
     * @return array
     */
    protected function getConfigurableProductOptions(ProductInterface $product)
    {
        $allowProducts = [];
        foreach ($product->getTypeInstance()->getUsedProducts($product, null) as $usedProduct) {
            if ($usedProduct->isSaleable() || $this->productHelper->getSkipSaleableCheck()) {
                $allowProducts[] = $usedProduct;
            }
        }
        $config = $this->configurableProductHelper->getOptions($product, $allowProducts);
        $attributes = [];
        foreach ($product->getTypeInstance()->getConfigurableAttributes($product) as $attribute) {
            $attributeOptionsData = [];
            $position = 0;
            foreach ($attribute->getOptions() as $attributeOption) {
                if (!$attributeOption['label']) {
                    continue;
                }
                $optionId = $attributeOption['value_index'];
                $attributeOptionsData[] = [
                    'id'       => $optionId,
                    'label'    => $attributeOption['label'],
                    'position' => $position++,
                    'products' => isset($config[$attribute->getAttributeId()][$optionId])
                        ? $config[$attribute->getAttributeId()][$optionId]
                        : [],
                ];
            }
            if ($attributeOptionsData) {
                $productAttribute = $attribute->getProductAttribute();
                $attributeId = $productAttribute->getId();
                $attributes[$attributeId] = [
                    'id'       => $attributeId,
                    'code'     => $productAttribute->getAttributeCode(),
                    'label'    => $productAttribute->getStoreLabel($product->getStoreId()),
                    'options'  => $attributeOptionsData,
                    'position' => $attribute->getPosition(),
                ];
            }
        }
        return $attributes;
    }
}

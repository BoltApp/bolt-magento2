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
use Bolt\Boltpay\Helper\Log as LogHelper;
use Bolt\Boltpay\Model\Api\Data\ProductInventoryInfo;
use Exception;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\CatalogInventory\Api\Data\StockItemInterface;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Webapi\Exception as WebapiException;
use Magento\Framework\Webapi\Rest\Response;
use Magento\Store\Model\StoreManagerInterface;
use Magento\CatalogInventory\Model\Spi\StockRegistryProviderInterface;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Magento\CatalogInventory\Api\StockConfigurationInterface;
use Magento\Eav\Model\Config;
use Magento\Framework\Serialize\SerializerInterface;




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
     * @var LogHelper
     */
    protected $logHelper;

    /**
     * @var SerializerInterface
     */
    protected $serializer;

    /**
     * @param ProductRepositoryInterface $productRepositoryInterface
     * @param StockRegistryProviderInterface $stockRegistry
     * @param StockConfigurationInterface $stockConfiguration
     * @param GetProductDataInterface $productData
     * @param StoreManagerInterface $storeManager
     * @param Configurable $configurable
     * @param Config $eavConfig
     * @param HookHelper $hookHelper
     * @param LogHelper $logHelper
     * @param Bugsnag $bugsnag
     * @param SerializerInterface $serializer
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
        LogHelper $logHelper,
        Bugsnag $bugsnag,
        SerializerInterface $serializer
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
        $this->logHelper = $logHelper;
        $this->serializer = $serializer;
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
            $this->logHelper->addInfoLog("id hit");
            $this->logHelper->addInfoLog($this->websiteId);
            $product = $this->productRepositoryInterface->getById($productID, false, $this->websiteId, false);
            $productInventory->setProduct($product);
            $this->logHelper->addInfoLog(json_encode($productInventory));
            $this->logHelper->addInfoLog(json_encode($product));
            $this->logHelper->addInfoLog($this->serializer->serialize($product));
            $productInventory->setStock($this->getStockStatus($product));
            $this->productData->setProductInventory($productInventory);
        } elseif ($sku != "") {
            $this->logHelper->addInfoLog("sku hit");
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
                array_push($childrenStockArray, $childProductInventory);
            }
            $this->productData->setChildren($childrenStockArray);

            $attributes = $parentProduct->getTypeInstance()->getConfigurableOptions($parentProduct);
            $options = array();

            foreach($attributes as $attr) {
                foreach ($attr as $p) {
                    $attribute = $this->eavConfig->getAttribute('catalog_product', $p['attribute_code']);
                    if ($this->productData->getOptions()) {
                        $update = array_merge($this->productData->getOptions(), $attribute->getSource()->getAllOptions());
                        $this->productData->setOptions($update);
                    } else {
                        $this->productData->setOptions($attribute->getSource()->getAllOptions());
                    }
                }
            }

        } elseif ($product->getTypeId() == "configurable") {
            $children = $product->getTypeInstance()->getUsedProducts($product);
            $childrenStockArray = array();
            foreach ($children  as $child) {
                $childProductInventory = new ProductInventoryInfo();
                $childProductInventory->setProduct($child);
                $childProductInventory->setStock($this->getStockStatus($child));
                array_push($childrenStockArray, $childProductInventory);
            }
            $this->productData->setChildren($childrenStockArray);

            $attributes = $product->getTypeInstance()->getConfigurableOptions($product);

            foreach($attributes as $attr) {
                foreach ($attr as $p) {
                    $attribute = $this->eavConfig->getAttribute('catalog_product', $p['attribute_code']);
                    if ($this->productData->getOptions()) {
                        $update = array_merge($this->productData->getOptions(), $attribute->getSource()->getAllOptions());
                        $this->productData->setOptions($update);
                    } else {
                        $this->productData->setOptions($attribute->getSource()->getAllOptions());
                    }
                }
            }
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
            throw new WebapiException(__('Missing a product ID or a sku in the request body.'), 0, WebapiException::HTTP_BAD_REQUEST);
        }

        try {
            $store = $this->storeManager->getStore();
            $this->storeID = $store->getId();
            $this->productData->setStoreID($this->storeID);
            $baseImageUrl = $store->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_MEDIA) . 'catalog/product';
            $this->productData->setBaseImageUrl($baseImageUrl);

            $this->logHelper->addInfoLog("PRODUCTINFOOOOOOO");
            $this->logHelper->addInfoLog($productID);
            $this->logHelper->addInfoLog($sku);
            $this->getProduct($productID, $sku);
            $this->logHelper->addInfoLog("FIRST");
            $this->logHelper->addInfoLog(json_encode($this->productData));
            $this->getProductFamily();
            $this->logHelper->addInfoLog("Family");
            $this->logHelper->addInfoLog(json_encode($this->productData));
            return $this->productData;
        } catch (NoSuchEntityException $nse) {
            throw new NoSuchEntityException(__('Product not found with given identifier.'));
        } catch (Exception $e) {
            $this->bugsnag->notifyException($e);
            throw new WebapiException(__($e->getMessage()), 0, WebapiException::HTTP_INTERNAL_ERROR);
        }
    }
}

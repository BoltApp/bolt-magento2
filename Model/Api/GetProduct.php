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
use Exception;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\CatalogInventory\Api\Data\StockItemInterface;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Webapi\Exception as WebapiException;
use Magento\Framework\Webapi\Rest\Response;
use Magento\Store\Model\StoreManagerInterface;
use Magento\CatalogInventory\Api\StockRegistryInterface;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;


class GetProduct implements GetProductInterface
{
    /**
     * @var GetProductDataInterface
     */
    private $productData;

    /**
     * @var \Magento\Catalog\Api\Data\ProductInterface
     */
    private $product;

    /**
     * @var \Magento\CatalogInventory\Api\Data\StockItemInterface
     */
    private $stockItem;

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
     * @var StockRegistryInterface
     */
    private $stockRegistry;

    /**
     * @var HookHelper
     */
    private $hookHelper;

    /**
     * @var Bugsnag
     */
    private $bugsnag;

    /**
     * @param ProductRepositoryInterface  $productRepositoryInterface
     * @param StockRegistryInterface  $stockRegistry
     * @param GetProductDataInterface  $productData
     * @param StoreManagerInterface $storeManager
     * @param Configurable          $configurable
     * @param HookHelper            $hookHelper
     * @param Bugsnag               $bugsnag
     */
    public function __construct(
        ProductRepositoryInterface  $productRepositoryInterface,
        StockRegistryInterface  $stockRegistry,
        GetProductDataInterface  $productData,
        StoreManagerInterface $storeManager,
        Configurable          $configurable,
        HookHelper $hookHelper,
        Bugsnag $bugsnag
    ) {
        $this->productRepositoryInterface = $productRepositoryInterface;
        $this->stockRegistry = $stockRegistry;
        $this->productData = $productData;
        $this->storeManager = $storeManager;
        $this->hookHelper = $hookHelper;
        $this->bugsnag = $bugsnag;
        $this->configurable = $configurable;
    }

    private function getProduct($productID, $sku){
        $storeId = $this->storeManager->getStore()->getId();
        // get product
        if ($productID != "") {
            $this->product = $this->productRepositoryInterface->getById($productID, false, $storeId, false);
            $this->productData->setProduct($this->product);
        } elseif ($sku != "") {
            $this->product = $this->productRepositoryInterface->get($sku, false, $storeId, false);
            $this->productData->setProduct($this->product);
        }
    }

    private function getStock(){
        $this->stockItem = $this->stockRegistry->getStockItem($this->product->getId());
        $this->productData->setStock($this->stockItem);
    }

    private function getProductFamily(){
        $parent = $this->configurable->getParentIdsByChild($this->product->getId());
        if(isset($parent[0])){
            $this->productData->setParent($this->productRepositoryInterface->getById($parent[0], false, $storeId, false));
            $children = $this->product->getTypeInstance()->getUsedProducts($this->productData->getParent());
            $this->productData->setChildren($children);
        } elseif ($this->product->getTypeId() == "configurable") {
            $children = $this->product->getTypeInstance()->getUsedProducts($this->product);
            $this->productData->setChildren($children);
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
//        if (!$this->hookHelper->verifyRequest()) {
//            throw new WebapiException(__('Request is not authenticated.'), 0, WebapiException::HTTP_UNAUTHORIZED);
//        }

        if ($productID === '' && $sku ==='') {
            throw new WebapiException(__('Missing a product ID or a sku in the request parameters.'), 0, WebapiException::HTTP_BAD_REQUEST);
        }

        try {
            $this->getProduct($productID, $sku);
            $this->getStock();
            $this->getProductFamily();

            return $this->productData;
        } catch (NoSuchEntityException $nse) {
            throw new NoSuchEntityException(__('Product not found with given identifier.'));
        } catch (Exception $e) {
            $this->bugsnag->notifyException($e);
            throw new WebapiException(__($e->getMessage()), 0, WebapiException::HTTP_INTERNAL_ERROR);
        }
    }
}

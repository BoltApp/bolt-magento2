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
use \Magento\Catalog\Api\Data\ProductInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Webapi\Exception as WebapiException;
use Magento\Framework\Webapi\Rest\Response;
use Magento\Store\Model\StoreManagerInterface;
use Magento\CatalogInventory\Api\StockRegistryInterface;


class GetProduct implements GetProductInterface
{
    /**
     * @var GetProductDataInterface
     */
    private $productData;

    /**
     * @var ProductRepositoryInterface
     */
    protected $productRepositoryInterface;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

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
     * @param HookHelper            $hookHelper
     * @param Bugsnag               $bugsnag
     */
    public function __construct(
        ProductRepositoryInterface  $productRepositoryInterface,
        StockRegistryInterface  $stockRegistry,
        GetProductDataInterface  $productData,
        StoreManagerInterface $storeManager,
        HookHelper $hookHelper,
        Bugsnag $bugsnag
    ) {
        $this->productRepositoryInterface = $productRepositoryInterface;
        $this->stockRegistry = $stockRegistry;
        $this->productData = $productData;
        $this->storeManager = $storeManager;
        $this->hookHelper = $hookHelper;
        $this->bugsnag = $bugsnag;
    }


    /**
     * Get user account associated with email
     *
     * @api
     *
     * @param string $productID
     *
     * @return \Bolt\Boltpay\Api\Data\GetProductDataInterface
     *
     * @throws NoSuchEntityException
     * @throws WebapiException
     */
    public function execute($productID = '')
    {
        if (!$this->hookHelper->verifyRequest()) {
            throw new WebapiException(__('Request is not authenticated.'), 0, WebapiException::HTTP_UNAUTHORIZED);
        }

        if ($productID === '') {
            throw new WebapiException(__('Missing product ID in the request parameters.'), 0, WebapiException::HTTP_BAD_REQUEST);
        }

        try {
            $storeId = $this->storeManager->getStore()->getId();
            $product = $this->productRepositoryInterface->getById($productID, false, $storeId, false);
            $this->productData->setProduct($product);
            $stockItem = $this->stockRegistry->getStockItem($product->getId());
            $this->productData->setStock($stockItem);
            return $this->productData;
        } catch (NoSuchEntityException $nse) {
            throw new NoSuchEntityException(__('Customer not found with given email.'));
        } catch (Exception $e) {
            $this->bugsnag->notifyException($e);
            throw new WebapiException(__($e->getMessage()), 0, WebapiException::HTTP_INTERNAL_ERROR);
        }
    }
}

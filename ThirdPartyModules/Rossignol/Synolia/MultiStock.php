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

namespace Bolt\Boltpay\ThirdPartyModules\Rossignol\Synolia;

use Bolt\Boltpay\Helper\Bugsnag as BugsnagHelper;
use Magento\Quote\Model\Quote;
use Magento\Catalog\Api\ProductRepositoryInterface as ProductRepository;
use Magento\Store\Model\StoreManagerInterface as StoreManager;

/**
 * Class MultiStock
 *
 * @package Bolt\Boltpay\ThirdPartyModules\Rossignol\Synolia
 */
class MultiStock
{

    /**
     * @var ProductRepository
     */
    private $productRepository;
    
    /**
     * @var StoreManager
     */
    private $storeManager;
    
    /**
     * @var BugsnagHelper
     */
    private $bugsnagHelper;

    /**
     * MultiStock constructor.
     *
     * @param ProductRepository $productRepository
     * @param StoreManager $storeManager
     * @param BugsnagHelper $bugsnagHelper
     */
    public function __construct(
        ProductRepository $productRepository,
        StoreManager $storeManager,
        BugsnagHelper $bugsnagHelper
    ) {
        $this->productRepository = $productRepository;
        $this->storeManager      = $storeManager;
        $this->bugsnagHelper = $bugsnagHelper;
    }

    /**
     * @param array $result
     * @param array $request
     * @param null|int $storeId
     * @return array
     */
    public function filterBoltCartData(
        $result,
        $quote,
        $immutableQuote,
        $placeOrderPayload,
        $paymentOnly
    ) {
        $result['metadata']['store_id'] = (string)($quote->getStoreId());
        return $result;
    }
    
    /**
     * Quote $quote
     */
    public function beforePrepareQuote(
        $quote
    ) {
        try {
            $store = $quote->getStore();
            $this->storeManager->setCurrentStore($store->getCode());
        } catch (\Exception $e) {
            $this->bugsnagHelper->notifyException($e);
        }
    }
    
    /**
     * @param mixed $cart
     * @param mixed $shipping_address
     * @param mixed $shipping_option
     * @param mixed $ship_to_store_option
     */
    public function beforeHandleShippingTaxRequest(
        $cart,
        $shipping_address,
        $shipping_option,
        $ship_to_store_option
    ) {
        try {
            if (isset($cart['metadata']) && isset($cart['metadata']['store_id']) && !empty($cart['metadata']['store_id'])) {
                $storeId = $cart['metadata']['store_id'];
                $this->setCurrentDefaultStore($storeId);
            }
        } catch (\Exception $e) {
            $this->bugsnagHelper->notifyException($e);
        }
    }
    
    /**
     * @param array $order
     */
    public function beforeHandleCreateOrderRequest(
        $order
    ) {
        try {
            $cart = $order['cart'];
            if (isset($cart['metadata']) && isset($cart['metadata']['store_id']) && !empty($cart['metadata']['store_id'])) {
                $storeId = $cart['metadata']['store_id'];
                $this->setCurrentDefaultStore($storeId);
            }
        } catch (\Exception $e) {
            $this->bugsnagHelper->notifyException($e);
        }
    }
    
    /**
     * @param array $request
     * @param int $storeId
     */
    public function beforeHandleCreateCartRequest(
        $request,
        $storeId
    ) {
        try {
            $this->setCurrentDefaultStore($storeId);
        } catch (\Exception $e) {
            $this->bugsnagHelper->notifyException($e);
        }
        
    }
    
    /**
     * Set current default store
     *
     * @param int $storeId
     */
    private function setCurrentDefaultStore($storeId)
    {
        $store = $this->storeManager->getStore($storeId);
        $this->storeManager->setCurrentStore($store->getCode());
    }
}

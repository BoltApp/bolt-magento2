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
 * @copyright  Copyright (c) 2018 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Bolt\Boltpay\Model\Api;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Webapi\Exception as WebApiException;
use Magento\Catalog\Api\ProductRepositoryInterface as ProductRepository;
use Magento\CatalogInventory\Api\StockStateInterface as StockState;
use Bolt\Boltpay\Model\ErrorResponse as BoltErrorResponse;
use Bolt\Boltpay\Helper\Bugsnag;
use Bolt\Boltpay\Helper\Shared\CurrencyUtils;
use Bolt\Boltpay\Model\Api\UpdateCartContext;

/**
 * Trait UpdateCartItemTrait
 * 
 * @package Bolt\Boltpay\Model\Api
 */
trait UpdateCartItemTrait
{  
    /**
     * @var ProductRepository
     */
    protected $productRepository;
    
    /**
     * @var StockState
     */
    protected $stockState;
    
    /**
     * @var Bugsnag
     */
    protected $bugsnag;

    /**
     * UpdateCartItemTrait constructor.
     *
     * @param UpdateCartContext $updateCartContext
     */
     public function __construct(
        UpdateCartContext $updateCartContext
    ) {
        $this->productRepository = $updateCartContext->getProductRepositoryInterface();
        $this->stockState = $updateCartContext->getStockStateInterface();
        $this->bugsnag = $updateCartContext->getBugsnag();
    }
    
    /**
     * Get product by id
     *
     * @param string $productId
     * @param string $storeId
     *
     * @return \Magento\Catalog\Api\Data\ProductInterface
     */
    protected function getProduct($productId, $storeId)
    {
        try {
            $product = $this->productRepository->getById($productId, false, $storeId, true);
        } catch (NoSuchEntityException $e) {
            $this->sendErrorResponse(
                BoltErrorResponse::ERR_PRODUCT_DOES_NOT_EXIST,
                sprintf('The item [%s] does not exist.', $productId),
                422
            );

            return false;
        } catch (\Exception $e) {
            $this->sendErrorResponse(
                BoltErrorResponse::ERR_CART_ITEM_ADD_FAILED,
                $e->getMessage(),
                422
            );
            
            return false;
        }
        
        return $product;
    }
    
    /**
     * Verify if the item to add is valid
     *
     * @param Product $product
     * @param array $itemToAdd
     * @param string $websiteId
     *
     * @return boolean
     */
    protected function verifyItemData($product, $updateItem, $quoteItem, $websiteId)
    {
        if (!$this->stockState->verifyStock($updateItem['product_id'])) {
            $this->sendErrorResponse(
                BoltErrorResponse::ERR_ITEM_OUT_OF_STOCK,
                sprintf('The item [%s] is out of stock.', $updateItem['product_id']),
                422
            );

            return false;
        }
        
        if ($quoteItem) {
            if (isset($updateItem['currency']) && CurrencyUtils::toMinor($quoteItem['unit_price'], $updateItem['currency']) != $updateItem['price']) {
                $this->sendErrorResponse(
                    BoltErrorResponse::ERR_ITEM_PRICE_HAS_BEEN_UPDATED,
                    sprintf('The price of item [%s] does not match related quote item\'s price.', $updateItem['product_id']),
                    422
                );
    
                return false;
            }
            
            $qtyToCheck = ($updateItem['update'] == 'add')
                    ? (int)$quoteItem['quantity'] + (int)$updateItem['quantity']
                    : (int)$quoteItem['quantity'] - (int)$updateItem['quantity'];

            // The module Magento_InventorySales has plugin to replace legacy quote item check,
            // so we send $qtyToCheck as $itemQty to follow its logic
            $checkQty = $this->stockState->checkQuoteItemQty(
                $updateItem['product_id'],
                $qtyToCheck,
                $qtyToCheck,
                $quoteItem['quantity'],
                $websiteId
            );

            if ($checkQty->getHasError()) { 
                $this->sendErrorResponse(
                    BoltErrorResponse::ERR_ITEM_OUT_OF_STOCK,
                    $checkQty->getMessage(),
                    422
                );
    
                return false;
            }
        } else {
            if (isset($updateItem['currency']) && CurrencyUtils::toMinor($product->getPrice(), $updateItem['currency']) != $updateItem['price']) {
                $this->sendErrorResponse(
                    BoltErrorResponse::ERR_ITEM_PRICE_HAS_BEEN_UPDATED,
                    sprintf('The price of item [%s] does not match product price.', $updateItem['product_id']),
                    422
                );
    
                return false;
            }
            
            $suggestQty = $this->stockState->suggestQty(
                $product->getId(),
                (int)$updateItem['quantity'],
                $product->getStore()->getWebsiteId()
            );
            
            if ($suggestQty != (int)$updateItem['quantity']) {
                $this->sendErrorResponse(
                    BoltErrorResponse::ERR_ITEM_OUT_OF_STOCK,
                    sprintf('The requested qty of item [%s] is not available.', $updateItem['product_id']),
                    422
                );
    
                return false;
            }
        }
        
        return true;
    }
    
    protected function getQuoteItemByProduct($itemToUpdate, $quoteItems)
    {
        foreach ($quoteItems as $quoteItem) {
            if (empty($quoteItem['quote_item_id'])) {
                continue;
            }
            
            if ($quoteItem['reference'] == $itemToUpdate['product_id']) {
                return $quoteItem;
            }
        }
        
        return null;
    }
    
    /**
     * Add item to quote
     *
     * @param Product $product
     * @param Quote $quote
     * @param array $itemToAdd
     *
     * @return boolean
     */
    protected function addItemToQuote($product, $quote, $itemToAdd, $quoteItem)
    {            
        try {
            $added = false;
            
            if ($quoteItem) {
                $quoteItem['quote_item']->setQty((int)$quoteItem['quantity'] + (int)$itemToAdd['quantity']);
                $quoteItem['quote_item']->save();
                $added = true;
            }

            if (!$added) {
                $result = $quote->addProduct($product, intval($itemToAdd['quantity']));
                if (is_string($result)) {
                    $this->sendErrorResponse(
                        BoltErrorResponse::ERR_CART_ITEM_ADD_FAILED,
                        sprintf('Fail to add item [%s]. Reason: [%s].', $itemToAdd['product_id'], $result),
                        422
                    );
                    
                    return false;
                }
                
                $added = true;
            }
            
            return $added;
        } catch (\Exception $e) {
            $this->sendErrorResponse(
                BoltErrorResponse::ERR_CART_ITEM_ADD_FAILED,
                sprintf('Fail to add item [%s]. Reason: [%s].', $itemToAdd['product_id'], $e->getMessage()),
                422
            );
            
            return false;
        }
        
        return true;
    }
    
    /**
     * Remove item from quote
     *
     * @param array $cartItems
     * @param array $itemToRemove
     * @param Quote $quote
     *
     * @return boolean
     */
    protected function removeItemFromQuote($quoteItem, $itemToRemove, $quote)
    {
        if ($quoteItem['quantity'] > $itemToRemove['quantity']) {
            $quoteItem['quote_item']->setQty((int)$quoteItem['quantity'] - (int)$itemToRemove['quantity']);
            $quoteItem['quote_item']->save();
            
            return true;
        } else if ($quoteItem['quantity'] == $itemToRemove['quantity']) {
            $quote->removeItem($quoteItem['quote_item_id']);
            
            return true;
        } else {
            $this->sendErrorResponse(
                BoltErrorResponse::ERR_CART_ITEM_REMOVE_FAILED,
                sprintf('Could not update the item [%s] with quantity [%s].', $itemToRemove['product_id'], $itemToRemove['quantity']),
                422
            );
            
            return false;
        }
        
        return false;
    }
    
}

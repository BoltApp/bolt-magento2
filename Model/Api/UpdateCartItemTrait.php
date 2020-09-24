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
    final public function __construct(
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
    protected function verifyItemData($product, $itemToAdd, $websiteId)
    {
        if (CurrencyUtils::toMinor($product->getPrice(), $itemToAdd['currency']) != $itemToAdd['price']) {
            $this->sendErrorResponse(
                BoltErrorResponse::ERR_ITEM_PRICE_HAS_BEEN_UPDATED,
                sprintf('The price of item [%s] does not match product price.', $itemToAdd['product_id']),
                422
            );

            return false;
        }
        
        if (!$this->stockState->verifyStock($itemToAdd['product_id'])) {
            $this->sendErrorResponse(
                BoltErrorResponse::ERR_ITEM_OUT_OF_STOCK,
                sprintf('The item [%s] is out of stock.', $itemToAdd['product_id']),
                422
            );

            return false;
        }
        
        $checkQty = $this->stockState->checkQuoteItemQty(
            $itemToAdd['product_id'],
            $itemToAdd['quantity'],
            $itemToAdd['quantity'],
            $itemToAdd['quantity'],
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
        
        return true;
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
    protected function addItemToQuote($product, $quote, $itemToAdd)
    {            
        try {
            $quote->addProduct($product, intval($itemToAdd['quantity']));
        } catch (\Exception $e) {
            $this->sendErrorResponse(
                BoltErrorResponse::ERR_CART_ITEM_ADD_FAILED,
                sprintf('Fail to add item [%s].', $itemToAdd['product_id']),
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
    protected function removeItemFromQuote($cartItems, $itemToRemove, $quote)
    {
        foreach ($cartItems as $cartItem) {
            if (empty($cartItem['quote_item_id'])) {
                continue;
            }
            
            if ($cartItem['reference'] == $itemToRemove['product_id']) {
                if ($cartItem['quantity'] > $itemToRemove['quantity']) {
                    $quote->updateItem($cartItem['quote_item_id'], new \Magento\Framework\DataObject(['qty' => (int)$cartItem['quantity'] - (int)$itemToRemove['quantity']]));
                
                    return true;
                } else if ($cartItem['quantity'] == $itemToRemove['quantity']) {
                    $quote->removeItem($cartItem['quote_item_id']);
                    
                    return true;
                } else {
                    $this->sendErrorResponse(
                        BoltErrorResponse::ERR_CART_ITEM_REMOVE_FAILED,
                        sprintf('Could not update the item [%s] with quantity [%s].', $itemToRemove['product_id'], $itemToRemove['quantity']),
                        422
                    );
                    
                    return false;
                }
            }
        }
        
        // The quote item isn't found.
        $this->sendErrorResponse(
            BoltErrorResponse::ERR_CART_ITEM_REMOVE_FAILED,
            sprintf('The quote item [%s] isn\'t found.', $itemToRemove['product_id']),
            422
        );
        
        return false;
    }
    
}

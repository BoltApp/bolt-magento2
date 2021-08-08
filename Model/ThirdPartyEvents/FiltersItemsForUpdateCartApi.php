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

namespace Bolt\Boltpay\Model\ThirdPartyEvents;

/**
 * Trait FiltersItemsForUpdateCartApi
 *
 * @package Bolt\Boltpay\Model\ThirdPartyEvents
 */
trait FiltersItemsForUpdateCartApi
{
    /**
     * Filter the item before adding to cart.
     *
     * @see \Bolt\Boltpay\Model\Api\UpdateCart::execute
     * 
     * @param bool $flag
     * @param array $addItem
     * @param \Magento\Checkout\Model\Session $checkoutSession
     *
     * @return bool if return true, then move to next item, if return false, just process the normal execution.
     */
    abstract public function filterAddItemBeforeUpdateCart($flag, $addItem, $checkoutSession);
    
    /**
     * Filter the item before removing from cart.
     *
     * @see \Bolt\Boltpay\Model\Api\UpdateCart::execute
     * 
     * @param bool $flag
     * @param array $removeItem
     * @param \Magento\Checkout\Model\Session $checkoutSession
     *
     * @return bool if return true, then move to next item, if return false, just process the normal execution.
     */
    abstract public function filterRemoveItemBeforeUpdateCart($flag, $removeItem, $checkoutSession);
}

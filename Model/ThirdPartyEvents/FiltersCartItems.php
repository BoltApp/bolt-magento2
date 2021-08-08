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

use Magento\Quote\Model\Quote;

/**
 * Trait FiltersCartItems
 *
 * @package Bolt\Boltpay\Model\ThirdPartyEvents
 */
trait FiltersCartItems
{
    /**
     * Filter cart item array after it is collected
     *
     * @see \Bolt\Boltpay\Helper\Cart::getCartItems
     *
     * @param array|array[]|int[] $result containing collected cart items, total amount and diff
     * @param Quote $quote immutable quote from which the cart items were collected
     * @param int $storeId quote store id
     * @param bool $ifOnlyVisibleItems if only collect visible items
     *
     * @return array|array[]|int[] changed or unchanged $result
     */
    abstract public function filterCartItems($result, $quote, $storeId, $ifOnlyVisibleItems);
}

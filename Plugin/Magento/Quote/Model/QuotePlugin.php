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
 * @copyright  Copyright (c) 2017-2022 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
namespace Bolt\Boltpay\Plugin\Magento\Quote\Model;

use Magento\Quote\Model\Quote;
use Bolt\Boltpay\Helper\Cart;

/**
 * Quote plugin for supporting ShipperHQ custom admin shipping rate ability
 */
class QuotePlugin
{
    /**
     * Skipping collect totals for quote with shipper hq custom admin shipping rate and marked flag
     *
     * @param Quote $subject
     * @param callable $proceed
     * @return Quote
     */
    public function aroundCollectTotals(
        Quote $subject,
        callable $proceed
    ) {
        if ($subject->getData(Cart::SHIPPER_HQ_SKIP_QUOTE_COLLECT_TOTALS) &&
            $subject->getShippingAddress()->getShippingMethod() === Cart::SHIPPER_HQ_ADMIN_SHIPPING_METHOD
        ) {
            return $subject;
        }

        return $proceed();
    }
}

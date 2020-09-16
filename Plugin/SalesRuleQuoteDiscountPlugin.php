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
namespace Bolt\Boltpay\Plugin;

use Magento\Checkout\Model\Session as CheckoutSession;

class SalesRuleQuoteDiscountPlugin
{
    /** @var CheckoutSession */
    private $checkoutSession;
    
    public function __construct(
        CheckoutSession $checkoutSession
    ) {
        $this->checkoutSession = $checkoutSession;
    }
    
    public function beforeCollect(\Magento\SalesRule\Model\Quote\Discount $subject, $quote, $shippingAssignment, $total)
    {
        // Each time when collecting address discount amount, the BoltCollectSaleRuleDiscounts session data would be reset,
        // then it can store the updated info of applied sale rules.
        $this->checkoutSession->setBoltCollectSaleRuleDiscounts([]);
       
        return [$quote, $shippingAssignment, $total];
    }
}


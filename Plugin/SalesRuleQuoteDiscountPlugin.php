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
 * @copyright  Copyright (c) 2024 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
namespace Bolt\Boltpay\Plugin;

use Bolt\Boltpay\Helper\Session as SessionHelper;
use Bolt\Boltpay\Helper\Cart as CartHelper;

class SalesRuleQuoteDiscountPlugin
{
    /** @var SessionHelper */
    private $sessionHelper;

    /** @var CartHelper */
    private $cartHelper;
    
    public function __construct(
        SessionHelper $sessionHelper,
        CartHelper $cartHelper
    ) {
        $this->sessionHelper = $sessionHelper;
        $this->cartHelper = $cartHelper;
    }
    
    public function beforeCollect(\Magento\SalesRule\Model\Quote\Discount $subject, $quote, $shippingAssignment, $total)
    {
        if (!$this->cartHelper->isCollectDiscountsByPlugin($quote)) {
            return [$quote, $shippingAssignment, $total];
        }
        
        $items = $shippingAssignment->getItems();
        
        if (!count($items)) {
            return [$quote, $shippingAssignment, $total];
        }
        
        $checkoutSession = $this->sessionHelper->getCheckoutSession();
        // Each time when collecting address discount amount, the BoltCollectSaleRuleDiscounts session data
        // would be reset, then it can store the updated info of applied sale rules.
        $checkoutSession->setBoltCollectSaleRuleDiscounts([]);

        return [$quote, $shippingAssignment, $total];
    }
}

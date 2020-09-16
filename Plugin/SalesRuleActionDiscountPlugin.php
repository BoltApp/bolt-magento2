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

class SalesRuleActionDiscountPlugin
{
    /** @var CheckoutSession */
    private $checkoutSession;
    
    public function __construct(
        CheckoutSession $checkoutSession
    ) {
        $this->checkoutSession = $checkoutSession;
    }
    
    public function afterCalculate(\Magento\SalesRule\Model\Rule\Action\Discount\AbstractDiscount $subject,
                                   $result, $rule, $item, $qty)
    {
        // If the sale rule has no coupon, its discount amount can not be retrieved directly,
        // so we store the discount amount in the checkout session with the rule id as key.
        $boltCollectSaleRuleDiscounts = $this->checkoutSession->getBoltCollectSaleRuleDiscounts([]);
        $ruleId = $rule->getId();
        if (!isset($boltCollectSaleRuleDiscounts[$ruleId])) {
            $boltCollectSaleRuleDiscounts[$ruleId] = $result->getAmount();            
        } else {
            $boltCollectSaleRuleDiscounts[$ruleId] += $result->getAmount();
        }
        $this->checkoutSession->setBoltCollectSaleRuleDiscounts($boltCollectSaleRuleDiscounts);      
        return $result;
    }
}


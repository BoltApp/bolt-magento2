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

use Bolt\Boltpay\Helper\Session as SessionHelper;

class SalesRuleActionDiscountPlugin
{    
    /** @var SessionHelper */
    private $sessionHelper;
    
    public function __construct(
        SessionHelper $sessionHelper
    ) {
        $this->sessionHelper = $sessionHelper;
    }
    
    public function aroundCalculate(\Magento\SalesRule\Model\Rule\Action\Discount\AbstractDiscount $subject,
                                    callable $proceed, $rule, $item, $qty)
    {
        $result = $proceed($rule, $item, $qty);

        $checkoutSession = $this->sessionHelper->getCheckoutSession();

        // If the sale rule has no coupon, its discount amount can not be retrieved directly,
        // so we store the discount amount in the checkout session with the rule id as key.
        $boltCollectSaleRuleDiscounts = $checkoutSession->getBoltCollectSaleRuleDiscounts([]);
        $ruleId = $rule->getId();
        if (!isset($boltCollectSaleRuleDiscounts[$ruleId])) {
            $boltCollectSaleRuleDiscounts[$ruleId] = $result->getAmount();            
        } else {
            $boltCollectSaleRuleDiscounts[$ruleId] += $result->getAmount();
        }
        $checkoutSession->setBoltCollectSaleRuleDiscounts($boltCollectSaleRuleDiscounts);

        return $result;
    }
}


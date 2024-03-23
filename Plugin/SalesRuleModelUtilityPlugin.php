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

use Bolt\Boltpay\Helper\Cart as CartHelper;
use Bolt\Boltpay\Helper\Discount as DiscountHelper;
use Bolt\Boltpay\Helper\Session as SessionHelper;
use Magento\SalesRule\Model\RuleRepository;

class SalesRuleModelUtilityPlugin
{
    /** @var CartHelper */
    private $cartHelper;
    
    /** @var SessionHelper */
    private $sessionHelper;

    /** @var RuleRepository */
    private $ruleRepository;
    
    public function __construct(
        SessionHelper $sessionHelper,
        RuleRepository $ruleRepository,
        CartHelper $cartHelper
    ) {
        $this->sessionHelper = $sessionHelper;
        $this->ruleRepository = $ruleRepository;
        $this->cartHelper = $cartHelper;
    }
    
    /**
     * Save the discount amount of item into checkout session, so we can get actual discount amount of coupon.
     */
    public function beforeMinFix(\Magento\SalesRule\Model\Utility $subject, $discountData, $item, $qty)
    {
        if (!$this->cartHelper->isCollectDiscountsByPlugin($item->getQuote())) {
            return [$discountData, $item, $qty];
        }
        
        $checkoutSession = $this->sessionHelper->getCheckoutSession();
        $savedRuleId = $checkoutSession->getBoltNeedCollectSaleRuleDiscounts('');      
        if (!empty($savedRuleId)) {
            $checkoutSession->setBoltDiscountBreakdown( [
                'item_discount' => $item->getDiscountAmount(),
                'rule_id' => $savedRuleId,
            ]);
        }
        
        return [$discountData, $item, $qty];
    }
    
    /**
     * Save actual discount amount of coupon into checkout session.
     */
    public function afterMinFix(\Magento\SalesRule\Model\Utility $subject,
                                   $result, $discountData, $item, $qty)
    {
        if (!$this->cartHelper->isCollectDiscountsByPlugin($item->getQuote())) {
            return $result;
        }
        
        $checkoutSession = $this->sessionHelper->getCheckoutSession();
        $savedRuleId = $checkoutSession->getBoltNeedCollectSaleRuleDiscounts('');      
        if (!empty($savedRuleId)) {
            // If the sale rule has no coupon, its discount amount can not be retrieved directly,
            // so we store the discount amount in the checkout session with the rule id as key.
            $boltDiscountBreakdown = $checkoutSession->getBoltDiscountBreakdown([]);
            if (!empty($boltDiscountBreakdown) && $boltDiscountBreakdown['rule_id'] == $savedRuleId) {
                $discountAmount = $discountData->getAmount() - $boltDiscountBreakdown['item_discount'];
                if ($discountAmount >= DiscountHelper::MIN_NONZERO_VALUE || $this->ruleRepository->getById($savedRuleId)->getSimpleFreeShipping()) {
                    $boltCollectSaleRuleDiscounts = $checkoutSession->getBoltCollectSaleRuleDiscounts([]);
                    if (!isset($boltCollectSaleRuleDiscounts[$savedRuleId])) {
                        $boltCollectSaleRuleDiscounts[$savedRuleId] = $discountAmount;            
                    } else {
                        $boltCollectSaleRuleDiscounts[$savedRuleId] += $discountAmount;
                    }
                    $checkoutSession->setBoltCollectSaleRuleDiscounts($boltCollectSaleRuleDiscounts);
                }
            }
        }
        $checkoutSession->setBoltNeedCollectSaleRuleDiscounts('');
        $checkoutSession->setBoltDiscountBreakdown([]);

        return $result;
    }
}


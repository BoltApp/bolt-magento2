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
    
    public function afterCalculate(
        \Magento\SalesRule\Model\Rule\Action\Discount\AbstractDiscount $subject,
        $result,
        $rule,
        $item,
        $qty
    ) {
        $checkoutSession = $this->sessionHelper->getCheckoutSession();

        // Save the sale rule id into session,
        // so we can get the applied rule id in after method which is to save discount amount. (@see Bolt\Boltpay\Plugin\SalesRuleModelUtilityPlugin::afterMinFix)
        $ruleId = $rule->getId();
        $checkoutSession->setBoltNeedCollectSaleRuleDiscounts($ruleId);

        return $result;
    }
}

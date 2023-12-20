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
 * @copyright  Copyright (c) 2023 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Bolt\Boltpay\Plugin\Amasty\Shiprestriction\Model;

use Bolt\Boltpay\Helper\Cart as CartHelper;
use Bolt\Boltpay\Helper\Hook as HookHelper;
use Bolt\Boltpay\Helper\Session as SessionHelper;

class ShippingRestrictionRulePlugin
{
    /** @var SessionHelper */
    private $sessionHelper;
    
    public function __construct(
        SessionHelper $sessionHelper
    ) {
        $this->sessionHelper = $sessionHelper;
    }
    
    /**
     * @param \Amasty\Shiprestriction\Model\ShippingRestrictionRule $subject
     * @param \Magento\Quote\Model\Quote\TotalsCollector $nextSubject
     * @param callable $nextProceed
     * @param \Magento\Quote\Model\Quote $quote
     * @param \Magento\Quote\Model\Quote\Address $address
     */
    public function beforeGetRestrictionRules(\Amasty\Shiprestriction\Model\ShippingRestrictionRule $subject, $request)
    {
        /** @var \Magento\Quote\Model\Quote\Item[] $allItems */
        $allItems = $request->getAllItems();
        if (!HookHelper::$fromBolt || !$allItems) {
            return [$request];
        }
        $firstItem = current($allItems);
        /** @var \Magento\Quote\Model\Quote\Address $address */
        $address = $firstItem->getAddress();
        if ($address->getQuote()->getBoltCheckoutType() == CartHelper::BOLT_CHECKOUT_TYPE_BACKOFFICE) {
            $this->sessionHelper->getCheckoutSession()->setBoltBackendOrderShippingRestrictionRule(true);
        }
        return [$request];
    }
    
    public function afterGetRestrictionRules(\Amasty\Shiprestriction\Model\ShippingRestrictionRule $subject, $result, $request)
    {
        $this->sessionHelper->getCheckoutSession()->setBoltBackendOrderShippingRestrictionRule(false);
        return $result;
    }
}
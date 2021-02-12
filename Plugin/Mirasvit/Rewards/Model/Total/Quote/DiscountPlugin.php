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

namespace Bolt\Boltpay\Plugin\Mirasvit\Rewards\Model\Total\Quote;

use Bolt\Boltpay\Helper\Session as SessionHelper;
use Bolt\Boltpay\Model\EventsForThirdPartyModules;

/**
 * Class DiscountPlugin
 *
 * @package Bolt\Boltpay\Plugin
 */
class DiscountPlugin
{
    /**
     * @var SessionHelper
     */
    private $sessionHelper;
    
    /**
     * @var EventsForThirdPartyModules
     */
    private $eventsForThirdPartyModules;

    /**
     * DiscountPlugin constructor.
     *
     * @param CheckoutSession $checkoutSession
     * @param EventsForThirdPartyModules $eventsForThirdPartyModules
     */
    public function __construct(
        SessionHelper $sessionHelper,
        EventsForThirdPartyModules $eventsForThirdPartyModules
    ) {
        $this->sessionHelper = $sessionHelper;
        $this->eventsForThirdPartyModules = $eventsForThirdPartyModules;
    }

    /**
     * Save the shipping discount amount into checkout session before collecting discount from Mirasvit rewards,
     * so we can restore the shipping discount amount later.
     *
     * @return mixed
     */
    public function beforeCollect(
        \Mirasvit\Rewards\Model\Total\Quote\Discount $subject,
        $quote,
        $shippingAssignment,
        $total   
    ) {
        if ($this->eventsForThirdPartyModules->runFilter("checkMirasvitRewardsIsShippingIncluded", false)) {
            $address = $shippingAssignment->getShipping()->getAddress();
            $beforeShippingDiscountAmount = $address->getShippingDiscountAmount();
            $checkoutSession = $this->sessionHelper->getCheckoutSession();
            $checkoutSession->setBeforeMirasvitRewardsShippingDiscountAmount($beforeShippingDiscountAmount);
            $checkoutSession->setMirasvitRewardsShippingDiscountAmount(0);
        }

        return [$quote, $shippingAssignment, $total];
    }
    
    /**
     * Exclude the Mirasvit rewards points from shipping discount, so the Bolt can apply Mirasvit rewards points to shipping properly.
     *
     * @return mixed
     */
    public function afterCollect(
        \Mirasvit\Rewards\Model\Total\Quote\Discount $subject,
        $result,
        $quote,
        $shippingAssignment,
        $total   
    ) {
        if ($this->eventsForThirdPartyModules->runFilter("checkMirasvitRewardsIsShippingIncluded", false)) {
            $address = $shippingAssignment->getShipping()->getAddress();
            $afterShippingDiscountAmount = $address->getShippingDiscountAmount();
            $checkoutSession = $this->sessionHelper->getCheckoutSession();
            $beforeShippingDiscountAmount = $checkoutSession->getBeforeMirasvitRewardsShippingDiscountAmount();
            $mirasvitRewardsShippingDiscountAmount = $afterShippingDiscountAmount - $beforeShippingDiscountAmount;
            if($mirasvitRewardsShippingDiscountAmount > 0) {
                $checkoutSession->setMirasvitRewardsShippingDiscountAmount($mirasvitRewardsShippingDiscountAmount);
            }            
        }

        return $result;
    }
}

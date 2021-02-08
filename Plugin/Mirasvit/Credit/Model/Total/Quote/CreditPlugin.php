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

namespace Bolt\Boltpay\Plugin\Mirasvit\Credit\Model\Total\Quote;

use Magento\Checkout\Model\Session as CheckoutSession;
use Bolt\Boltpay\Model\EventsForThirdPartyModules;

/**
 * Class CreditPlugin
 *
 * @package Bolt\Boltpay\Plugin
 */
class CreditPlugin
{
    /**
     * @var CheckoutSession
     */
    private $checkoutSession;
    
    /**
     * @var EventsForThirdPartyModules
     */
    private $eventsForThirdPartyModules;

    /**
     * MirasvitCreditQuotePaymentImportDataBeforePlugin constructor.
     *
     * @param CheckoutSession $checkoutSession
     * @param EventsForThirdPartyModules $eventsForThirdPartyModules
     */
    public function __construct(
        CheckoutSession $checkoutSession,
        EventsForThirdPartyModules $eventsForThirdPartyModules
    ) {
        $this->checkoutSession = $checkoutSession;
        $this->eventsForThirdPartyModules = $eventsForThirdPartyModules;
    }

    /**
     * Save the shipping discount amount into checkout session before collecting discount from Mirasvit credit,
     * so we can restore the shipping discount amount later.
     *
     * @return mixed
     */
    public function beforeCollect(
        \Mirasvit\Credit\Model\Total\Quote\Credit $subject,
        $quote,
        $shippingAssignment,
        $total   
    ) {
        if ($this->eventsForThirdPartyModules->runFilter("checkMirasvitCreditIsShippingTaxIncluded", false, $quote)) {
            $address = $shippingAssignment->getShipping()->getAddress();
            $beforeShippingDiscountAmount = $address->getShippingDiscountAmount();
            $this->checkoutSession->setBeforeMirasvitStoreCreditShippingDiscountAmount($beforeShippingDiscountAmount);
        }

        return [$quote, $shippingAssignment, $total];
    }
    
    /**
     * Exclude the Mirasvit credit amount from shipping discount, so the Bolt can apply Mirasvit credit to shipping properly.
     *
     * @return mixed
     */
    public function afterCollect(
        \Mirasvit\Credit\Model\Total\Quote\Credit $subject,
        $result,
        $quote,
        $shippingAssignment,
        $total   
    ) {
        if ($this->eventsForThirdPartyModules->runFilter("checkMirasvitCreditIsShippingTaxIncluded", false, $quote)) {
            $address = $shippingAssignment->getShipping()->getAddress();
            $afterShippingDiscountAmount = $address->getShippingDiscountAmount();
            $beforeShippingDiscountAmount = $this->checkoutSession->getBeforeMirasvitStoreCreditShippingDiscountAmount();
            $mirasvitStoreCreditShippingDiscountAmount = $afterShippingDiscountAmount - $beforeShippingDiscountAmount;
            if($mirasvitStoreCreditShippingDiscountAmount > 0) {
                $this->checkoutSession->setMirasvitStoreCreditShippingDiscountAmount($mirasvitStoreCreditShippingDiscountAmount);
            }            
        }

        return $result;
    }
}

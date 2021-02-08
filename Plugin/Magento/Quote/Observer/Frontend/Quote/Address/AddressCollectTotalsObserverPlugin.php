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

namespace Bolt\Boltpay\Plugin\Magento\Quote\Observer\Frontend\Quote\Address;

use Magento\Framework\Event\Observer;
use Magento\Quote\Api\Data\ShippingAssignmentInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address;
use Magento\Quote\Observer\Frontend\Quote\Address\CollectTotalsObserver;
use Magento\Quote\Observer\Frontend\Quote\Address\VatValidator;

/**
 * Plugin for {@see \Magento\Quote\Observer\Frontend\Quote\Address\CollectTotalsObserver}
 */
class AddressCollectTotalsObserverPlugin
{
    /**
     * @var VatValidator
     */
    private $vatValidator;

    /**
     * @var string
     */
    private $emailBefore;

    /**
     * AddressCollectTotalsObserverPlugin constructor.
     *
     * @param VatValidator $vatValidator
     */
    public function __construct(VatValidator $vatValidator)
    {
        $this->vatValidator = $vatValidator;
    }

    /**
     * Collects customer email before they are overwritten by
     *
     * @see \Magento\Quote\Observer\Frontend\Quote\Address\CollectTotalsObserver::execute
     *
     * @param CollectTotalsObserver $subject original observer instance
     * @param Observer              $observer object containing original event parameters
     *
     * @return void
     */
    public function beforeExecute($subject, $observer)
    {
        $this->emailBefore = $this->emailBefore ?: $observer->getQuote()->getCustomerEmail();
    }

    /**
     * Due to known issue with {@see \Magento\Quote\Observer\Frontend\Quote\Address\CollectTotalsObserver::execute}
     * where it overwrites customer data on the quote with empty values when:
     *  1. quote customer is guest (customer_id is null)
     *  2. VAT validation is enabled for shipping addresses in the configuration
     * In this plugin we restore the customer email from value saved before the observer in question is executed
     *
     * @see  beforeExecute
     *
     * @link https://github.com/magento/magento2/pull/25405 PR resolving this issue, not in any releases currently
     *
     * @param CollectTotalsObserver $subject  original observer instance
     * @param void                  $result   result of the original method call
     * @param Observer              $observer object containing original event parameters
     *
     * @return void
     */
    public function afterExecute(CollectTotalsObserver $subject, $result, $observer)
    {
        /** @var ShippingAssignmentInterface $shippingAssignment */
        $shippingAssignment = $observer->getShippingAssignment();
        /** @var Address $address */
        $address = $shippingAssignment->getShipping()->getAddress();
        /** @var Quote $quote */
        $quote = $observer->getQuote();

        $customer = $quote->getCustomer();
        $storeId = $customer->getStoreId();
        if (!$customer->getDisableAutoGroupChange()
            && $this->vatValidator->isEnabled($address, $storeId)
            && !$customer->getId()) {
            $quote->setCustomerEmail($this->emailBefore);
            $quote->getCustomer()->setEmail($this->emailBefore);
        }
        return $result;
    }
}

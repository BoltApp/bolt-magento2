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
 * @copyright  Copyright (c) 2017-2020 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
namespace Bolt\Boltpay\Plugin\Magento;

use Magento\Framework\Event\Observer;

class TogglePaymentMethodsPlugin
{
    /**
     * @param $subject
     * @param callable $proceed
     * @param Observer $observer
     */
    public function aroundExecute(
        $subject,
        callable $proceed,
        Observer $observer
    ) {
        $proceed($observer);

        $paymentMethod = $observer->getEvent()->getMethodInstance()->getCode();
        if ($paymentMethod != \Bolt\Boltpay\Model\Payment::METHOD_CODE) {
            return;
        }

        $result = $observer->getEvent()->getResult();
        if ($result->getData('is_available')) {
            return;
        }

        $quote = $observer->getEvent()->getQuote();
        if (!$quote) {
            return;
        }

        if ($quote->getBaseGrandTotal() == 0) {
            $result->setData('is_available', true);
        }
    }
}
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
namespace Bolt\Boltpay\Plugin;

use Bolt\Boltpay\Helper\Cart as CartHelper;

/**
 * Class QuotePlugin
 *
 * @package Bolt\Boltpay\Plugin
 */
class QuotePlugin
{
    /**
     * Override Quote afterSave method.
     * Skip execution for immutable quotes, thus preventing dispatching the after save events.
     *
     * @param \Magento\Quote\Model\Quote $subject
     * @param callable $proceed
     * @return \Magento\Quote\Model\Quote
     */
    public function aroundAfterSave(\Magento\Quote\Model\Quote $subject, callable $proceed)
    {
        if ($subject->getBoltParentQuoteId() && $subject->getBoltParentQuoteId() != $subject->getId()) {
            return $subject;
        }
        return $proceed();
    }

    /**
     * Always consider PPC quotes active
     * so we can run internal Magento actions on them
     * as they were active.
     * Note: there are restrictions on inactive quotes processing
     * that we need to bypass, eg. calling native shipping and tax methods.
     * After PPC checkout completes we change BoltCheckoutType so
     * the quote is not considered active anymore and can be cleaned up.
     *
     * @param \Magento\Quote\Model\Quote $subject
     * @param bool|null $result
     * @return bool|null
     */
    public function afterGetIsActive(\Magento\Quote\Model\Quote $subject, $result)
    {
        if ($subject->getBoltCheckoutType() == CartHelper::BOLT_CHECKOUT_TYPE_PPC) {
            return true;
        }
        return $result;
    }

    /**
     * Change result of the validateMinimumAmount method for Bolt backoffice quotes to always return true
     * @see \Magento\Quote\Model\Quote::validateMinimumAmount
     *
     * @param \Magento\Quote\Model\Quote $subject
     * @param bool                       $result of the parent method call
     *
     * @return bool true if Bolt backoffice quote, otherwise parent method result
     */
    public function afterValidateMinimumAmount(\Magento\Quote\Model\Quote $subject, $result)
    {
        if ($subject->getBoltCheckoutType() == \Bolt\Boltpay\Helper\Cart::BOLT_CHECKOUT_TYPE_BACKOFFICE
            && $subject->getPayment()->getMethod() == \Bolt\Boltpay\Model\Payment::METHOD_CODE) {
            return true;
        }
        return $result;
    }
}

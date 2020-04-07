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
            return;
        }
        return $proceed();
    }

    /**
     * Always consider immutable quotes as active
     * so we can run internal Magento actions with them
     * the as they were active, except dispatching after save events
     * which is taken care of in the above method.
     * Note: there are restriction on inactive quotes processing
     * that we need to bypass
     *
     * @param \Magento\Quote\Model\Quote $subject
     * @param bool|null $result
     * @return bool|null
     */
    public function afterGetIsActive(\Magento\Quote\Model\Quote $subject, $result)
    {
        if ($subject->getBoltParentQuoteId() && $subject->getBoltParentQuoteId() != $subject->getId()) {
            return true;
        }
        return $result;
    }
}

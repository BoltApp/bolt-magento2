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
 * Trait LoginPluginTrait
 * Common methods for LoginPlugin and LoginPostPlugin.
 *
 * @package Bolt\Boltpay\Plugin
 */
trait LoginPluginTrait
{
    // redirect path
    private static $shoppingCartPath = 'checkout/cart';

    /**
     * @return bool
     */
    private function isCustomerLoggedIn() {
        return $this->customerSession->isLoggedIn();
    }

    /**
     * @return bool
     */
    private function hasCart() {
        return $this->checkoutSession->hasQuote() && count($this->checkoutSession->getQuote()->getAllVisibleItems()) > 0;
    }

    /**
     * @return bool
     */
    private function allowRedirect() {
        return $this->isCustomerLoggedIn() && $this->hasCart();
    }

    /**
     * @return void
     */
    private function setBoltOpenCheckoutFlag() {
        $this->checkoutSession->setBoltOpenCheckoutFlag(true);
    }
}

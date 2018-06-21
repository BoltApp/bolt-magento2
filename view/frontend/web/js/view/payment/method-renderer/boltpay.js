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

/**
 * Bolt payment method renderer
 */
define(
    [
        'jquery',
        'mage/storage',
        'Magento_Checkout/js/view/payment/default',
        'Magento_Checkout/js/model/quote',
        'Magento_Customer/js/model/customer'
    ],
    function ($, storage, Component, quote, customer) {
        'use strict';
        return Component.extend({
            defaults: {
                template: 'Bolt_Boltpay/payment/boltpay',
                timeoutMessage: 'Sorry, but something went wrong. Please contact the seller.'
            },

            /**
             * @returns {String}
             */
            getCode: function () {
                return 'boltpay';
            },

            // called to check if Bolt payment method should be displayed on the checkout page
            isPaymentAvailable: function () {
                return !!window.boltConfig.publishable_key_payment;
            },

            // make quote data available to knockout template
            getPlaceOrderPayload: function () {
                var payload = {
                    cartId: quote.getQuoteId(),
                    billingAddress: quote.billingAddress(),
                    paymentMethod: quote.paymentMethod()
                };
                if (!customer.isLoggedIn()) {
                    payload.email = quote.guestEmail || $('#customer-email').val();
                }
                return JSON.stringify(payload);
            },

            getBillingAddress: function () {
                return JSON.stringify(quote.billingAddress());
            }
        });
    }
);


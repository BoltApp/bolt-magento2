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
                try {
                    return !!window.boltConfig.publishable_key_payment;
                } catch (e) {
                    return false;
                }
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
            },

            getButtonClass: function () {
                var btnClass = 'bolt-checkout-button',
                additionalClass = window.boltConfig.additional_checkout_button_class;

                if (additionalClass.length) {
                    btnClass += ' ' + additionalClass;
                }

                return btnClass;
            }
        });
    }
);


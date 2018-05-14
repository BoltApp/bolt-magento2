/**
 * Copyright © 2013-2017 Bolt, Inc. All rights reserved.
 * See COPYING.txt for license details.
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
                    payload.email = quote.guestEmail;
                }
                return JSON.stringify(payload);
            },

            getBillingAddress: function () {
                return JSON.stringify(quote.billingAddress());
            }
        });
    }
);


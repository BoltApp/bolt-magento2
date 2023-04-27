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
 * @copyright  Copyright (c) 2017-2022 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
define([
    'Magento_Checkout/js/model/quote',
    'Magento_Customer/js/customer-data',
], function (quote, customerData) {
    'use strict';

    const rewardConfig = window.checkoutConfig.payment.reward;

    var mixin = {
        /**
         * Updating isAvailable condition: added additional subtotal cart section value to return condition
         * for fixing visibility on initial mini cart reward button rendering after adding first item to cart
         * @returns {*|boolean}
         */
        isAvailable: function () {
            let grandTotal = parseFloat(quote.totals()['grand_total']),
                rewardUsedAmount = parseFloat(quote.totals()['extension_attributes']['base_reward_currency_amount']);
            let cart = customerData.get('cart');
            let subtotal = 0;
            if (cart) {
                subtotal = parseFloat(cart()['subtotalAmount']);
            }
            return rewardConfig.isAvailable && (grandTotal > 0 || subtotal > 0) && rewardUsedAmount <= 0;
        }
    };

    return function (reward) {
        return reward.extend(mixin);
    };
});

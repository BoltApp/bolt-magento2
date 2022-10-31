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
    'jquery',
    'underscore',
    'Magento_Customer/js/customer-data',
    'Bolt_Boltpay/js/utils/when-defined',
    'domReady!'
], function ($, _, customerData, whenDefined) {
    'use strict';

    /**
     * Main BoltCheckoutApiDriven class
     *
     * @type {{init: BoltCheckoutApiDriven.init, boltCallbacks: {}, cartBarrier: {resolve: resolve, promise: Promise<unknown>, value: (function(): null), isResolved: (function(): boolean)}, magentoCartTimeStamp: null, hintsBarrier: {resolve: resolve, promise: Promise<unknown>, value: (function(): null), isResolved: (function(): boolean)}, magentoCartDataListener: BoltCheckoutApiDriven.magentoCartDataListener, boltCartHints: null, customerCart: null, boltParameters: {}, quoteMaskedId: null, boltCheckoutConfigureCall: BoltCheckoutApiDriven.boltCheckoutConfigureCall}}
     */
    let BoltCheckoutApiDriven = {
        customerCart: null,
        boltCallbacks: {},
        boltParameters: {},
        quoteMaskedId: null,
        boltCartHints: null,
        magentoCartTimeStamp: null,
        cartBarrier: null,
        hintsBarrier: null,

        /**
         * Main init method. Preparing bolt connection config data, events/listeners/promises
         *
         * @param {Object} magentoBoltConfig
         */
        init: function(magentoBoltConfig) {
            //wait while BoltCheckout will be initialized
            if (!window.BoltCheckout) {
                whenDefined(window, 'BoltCheckout', this.init, 'BoltCheckoutApiDrivenInit');
                return;
            }
            //init new barriers
            this.cartBarrier = this.initBarrier();
            this.hintsBarrier = this.initBarrier();
            //call bolt checkout configure immediately with promise parameters
            this.boltCheckoutConfigureCall(this.cartBarrier.promise, this.hintsBarrier.promise);
            this.customerCart = customerData.get('cart');
            //subscription of 'customer-data' cart
            this.customerCart.subscribe(BoltCheckoutApiDriven.magentoCartDataListener);

            if (!this.customerCart()) {
                return;
            }

            this.magentoCartTimeStamp = this.customerCart().data_id;

            //trying to resolve cart promise if data is existed
            if (this.customerCart().quoteMaskedId !== undefined &&
                this.customerCart().quoteMaskedId !== null
            ) {
                this.quoteMaskedId = this.customerCart().quoteMaskedId;
                this.cartBarrier.resolve({"quoteMaskedId": this.quoteMaskedId})
            }

            //trying to resolve hints promise if data is existed
            if (this.customerCart().boltCartHints !== undefined &&
                this.customerCart().boltCartHints !== null
            ) {
                this.boltCartHints = this.customerCart().boltCartHints;
                this.hintsBarrier.resolve({"hints": this.boltCartHints})
            }
        },

        /**
         * Magento "customer-data" cart update handler
         *
         * @param {Object} magentoCart
         */
        magentoCartDataListener: function (magentoCart) {
            //if timestamp is the same no checks needed
            if (magentoCart.data_id === this.magentoCartTimeStamp) {
                return;
            }
            //init default values
            let isBoltCheckoutConfigureCallRequired = false,
                cart = BoltCheckoutApiDriven.cartBarrier.promise,
                hints = BoltCheckoutApiDriven.hintsBarrier.promise;

            //update current update timestamp
            BoltCheckoutApiDriven.magentoCartTimeStamp = magentoCart.data_id;

            //resolve cart promise if not resolved or prepare cart data for bolt config call
            if (magentoCart.quoteMaskedId !== undefined &&
                magentoCart.quoteMaskedId !== null &&
                magentoCart.quoteMaskedId !== BoltCheckoutApiDriven.quoteMaskedId
            ) {
                BoltCheckoutApiDriven.quoteMaskedId = magentoCart.quoteMaskedId;
                cart = {"quoteMaskedId": BoltCheckoutApiDriven.quoteMaskedId};
                if (!BoltCheckoutApiDriven.cartBarrier.isResolved()) {
                    BoltCheckoutApiDriven.cartBarrier.resolve(cart)
                } else {
                    isBoltCheckoutConfigureCallRequired = true;
                }
            }

            //resolve hints promise if not resolved or prepare hints data for bolt config call
            if (magentoCart.boltCartHints !== undefined &&
                magentoCart.boltCartHints !== null &&
                !_.isEqual(BoltCheckoutApiDriven.boltCartHints, magentoCart.boltCartHints)
            ) {
                BoltCheckoutApiDriven.boltCartHints = magentoCart.boltCartHints;
                hints = {"hints": BoltCheckoutApiDriven.boltCartHints};
                if (!BoltCheckoutApiDriven.hintsBarrier.isResolved()) {
                    BoltCheckoutApiDriven.hintsBarrier.resolve(hints)
                } else {
                    isBoltCheckoutConfigureCallRequired = true;
                }
            }

            //update bolt configuration if data was changed
            if (isBoltCheckoutConfigureCallRequired) {
                BoltCheckoutApiDriven.boltCheckoutConfigureCall(cart, hints);
            }
        },

        /**
         * Init new barrier
         *
         * @returns {{resolve: resolve, promise: Promise<unknown>, value: (function(): null), isResolved: (function(): boolean)}}
         */
        initBarrier: function () {
            let resolveHolder,
                isResolved = false,
                value = null,
                promise = new Promise(function(resolve){
                    resolveHolder = resolve;
                });
            return {
                promise: promise,
                resolve: function(t){
                    resolveHolder(t);
                    value = t;
                    isResolved = true;
                },
                value: function() { return value; },
                isResolved: function() { return isResolved; },
            };
        },

        /**
         * Call BoltCheckout configure
         *
         * @param {Object|Promise} boltCart
         * @param {Object|Promise} boltHints
         */
        boltCheckoutConfigureCall: function (boltCart, boltHints) {
            if (window.BoltCheckout === undefined) {
                throw new Error('BoltCheckout is undefined');
            }
            window.BoltCheckout.configure(
                boltCart,
                boltHints,
                this.boltCallbacks,
                this.boltParameters
            );
        }
    }

    /**
     * Entry point
     *
     * @param {Object|Promise} magentoBoltConfig
     */
    return function (magentoBoltConfig) {
        BoltCheckoutApiDriven.init(magentoBoltConfig);
    };
});

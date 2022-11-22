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
    'Magento_Customer/js/model/authentication-popup',
    'domReady!'
], function ($, _, customerData, whenDefined, authenticationPopup) {
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
        readyStatusBarrier: null,
        isPromisesResolved: false,
        isUserLoggedIn: null,
        isGuestCheckoutAllowed: null,
        initiateCheckout: null,
        mutationObserver: window.MutationObserver || window.WebKitMutationObserver,
        elementListeners: [],
        elementReadyObserver: null,
        elementAttributesListeners: [],
        elementAttrObserver: null,
        elementDataListeners: [],
        elementDataObserver: null,
        boltButtonCssClass: 'bolt-checkout-button',
        additionalButtonClass: window.boltConfig.additional_checkout_button_class,
        additionalButtonAttributes: window.boltConfig.additional_checkout_button_attributes,
        boltButtonCssStyles: window.boltConfig.button_css_styles,
        boltButtonSelector: '.bolt-checkout-button,[data-tid="instant-bolt-checkout-button"]',
        multiStepCssClass: 'bolt-multi-step-checkout',
        billingAddressSelector: '#bolt-billing-address',
        placeOrderPayloadId: 'bolt-place-order-payload',
        customerEmailSelector: '#checkoutSteps #customer-email',
        trackCallbacks: window.boltConfig.trackCallbacks,
        popUpOpen: false,
        save_request: null,

        /**
         * Resolving ready status promise
         */
        resolveReadyStatusPromise: function () {
            let customer = customerData.get('customer');
            if (BoltCheckoutApiDriven.readyStatusBarrier.isResolved() === true) {
                BoltCheckoutApiDriven.readyStatusBarrier = BoltCheckoutApiDriven.initBarrier();
            }
            if (BoltCheckoutApiDriven.customerCart === null) {
                return;
            }
            if (!BoltCheckoutApiDriven.initiateCheckout && !BoltCheckoutApiDriven.customerCart().isGuestCheckoutAllowed) {
                if (BoltCheckoutApiDriven.isUserLoggedIn === null) {
                    BoltCheckoutApiDriven.isUserLoggedIn = customer().isLoggedIn;
                    BoltCheckoutApiDriven.resolveReadyStatusPromise();
                    return;
                }
                if (!BoltCheckoutApiDriven.isUserLoggedIn) {
                    if (BoltCheckoutApiDriven.popUpOpen) {
                        // If we resolve promises after user clicked checkout button
                        // we should show authentication popup
                        BoltCheckoutApiDriven.showAuthenticationPopup();
                    }
                    BoltCheckoutApiDriven.resolveReadyStatusPromiseToValue(false);
                    return;
                }
            }

            BoltCheckoutApiDriven.resolveReadyStatusPromiseToValue(true);
        },

        /**
         * Resolve ready status promise to value
         * @param value
         */
        resolveReadyStatusPromiseToValue: function (value) {
            BoltCheckoutApiDriven.readyStatusBarrier.resolve(value);
            if (!value) {
                BoltCheckoutApiDriven.popUpOpen = false;
            }
        },

        /**
         * Showing authentication modal popup
         */
        showAuthenticationPopup: function () {
            // set a cookie for auto opening Bolt checkout after login
            BoltCheckoutApiDriven.setInitiateCheckoutCookie();
            authenticationPopup.showModal();
        },

        /**
         * Set initiate checkout cookie
         */
        setInitiateCheckoutCookie: function () {
            $.cookie('bolt_initiate_checkout', true, {path: '/', domain: window.location.hostname});
        },

        /**
         * Returns initiate checkout cookie
         *
         * @returns {*|string|jQuery}
         */
        getInitiateCheckoutCookie: function () {
            return $.cookie('bolt_initiate_checkout');
        },

        /**
         * Init DOM elements callbacks
         *
         * @param selector
         * @param fn
         */
        initElementReadyCallback: function (selector, fn) {
            // Store the selector and callback to be monitored
            BoltCheckoutApiDriven.elementListeners.push({
                selector: selector,
                fn: fn
            });
            if (!BoltCheckoutApiDriven.elementReadyObserver) {
                // Watch for changes in the document
                BoltCheckoutApiDriven.elementReadyObserver = new BoltCheckoutApiDriven.mutationObserver(BoltCheckoutApiDriven.elementsCheckExisting);
                BoltCheckoutApiDriven.elementReadyObserver.observe(window.document.documentElement, {
                    childList: true,
                    subtree: true
                });
            }
            // Check if the element is currently in the DOM
            BoltCheckoutApiDriven.elementsCheckExisting();
        },

        /**
         * Check if DOM element is ready
         */
        elementsCheckExisting: function () {
            // Check the DOM for elements matching a stored selector
            for (let i = 0, len = BoltCheckoutApiDriven.elementListeners.length, listener, elements; i < len; i++) {
                listener = BoltCheckoutApiDriven.elementListeners[i];
                // Query for elements matching the specified selector
                elements = window.document.querySelectorAll(listener.selector);
                for (let j = 0, jLen = elements.length, element; j < jLen; j++) {
                    element = elements[j];
                    // Make sure the callback isn't invoked with the
                    // same element more than once
                    if (!element.ready) {
                        element.ready = true;
                        // Invoke the callback with the element
                        listener.fn.call(element, element);
                    }
                }
            }
        },

        /**
         * Show bolt modal error message
         * @param msg
         * @param orderReference
         */
        showBoltErrorMessage: function (msg, orderReference) {
            let boltModal = $('#bolt-modal'),
                errorMsg = msg
                    || window.boltConfig.default_error_message + " Order reference: " + orderReference;

            boltModal.find('.bolt-modal-content').html(errorMsg);
            boltModal.modal("openModal");
        },

        /**
         * Returns checkout publish key
         * @returns {*}
         */
        getCheckoutKey: function () {
            let checkoutType = BoltCheckoutApiDriven.getCheckoutType();
            BoltCheckoutApiDriven.boltCartHints.paymentonly = checkoutType === 'payment';
            let key = 'publishable_key_' + checkoutType;
            return window.boltConfig[key];
        },

        /**
         * Returns current page checkout type
         *
         * @returns {string}
         */
        getCheckoutType: function () {
            return this.trim(location.pathname, '/') === 'checkout' ? 'payment' : 'checkout';
        },

        /**
         * Clean special characters from url
         * @param string
         * @returns {*}
         */
        escapeRegex: function (string) {
            return string.replace(/[\[\](){}?*+\^$\\.|\-]/g, "\\$&");
        },

        /**
         * Trim method
         *
         * @param str
         * @param characters
         * @param flags
         * @returns {string}
         */
        trim: function (str, characters, flags) {
            flags = flags || "g";
            if (typeof str !== "string" || typeof characters !== "string" || typeof flags !== "string") {
                throw new TypeError("argument must be string");
            }

            if (!/^[gi]*$/.test(flags)) {
                throw new TypeError("Invalid flags supplied '" + flags.match(new RegExp("[^gi]*")) + "'");
            }

            characters = this.escapeRegex(characters);

            return str.replace(new RegExp("^[" + characters + "]+|[" + characters + "]+$", flags), '');
        },

        /**
         * Magento "customer-data" cart update handler
         *
         * @param {Object} magentoCart
         */
        magentoCartDataListener: function (magentoCart) {
            BoltCheckoutApiDriven.resolveReadyStatusPromise();
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
                    BoltCheckoutApiDriven.cartBarrier.resolve(cart);
                    BoltCheckoutApiDriven.isPromisesResolved = true;
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
                    BoltCheckoutApiDriven.hintsBarrier.resolve(hints);
                    BoltCheckoutApiDriven.isPromisesResolved = true;
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
         * init bolt ui elements
         */
        initUIElements: function () {
            for (let i = 0, length = window.boltConfig.selectors.length; i < length; i++) {
                let selector = window.boltConfig.selectors[i];
                ! function(selector) {
                    let parts = selector.split('|');
                    // the CSS selector
                    let identifier = parts[0].trim();
                    // button placement regarding the selector element, prepend, append
                    let position =  parts[1];
                    /////////////////////////////////////////////////////
                    // replace the selectors with bolt button identifiers
                    // if / when selectors are in the DOM
                    /////////////////////////////////////////////////////
                    BoltCheckoutApiDriven.initElementReadyCallback(identifier, function(element) {
                        if (BoltCheckoutApiDriven.getCheckoutKey() === '') return;
                        let bolt_button = document.createElement('div');
                        if(window.boltConfig.is_instant_checkout_button){
                            bolt_button.setAttribute('data-tid','instant-bolt-checkout-button');
                            let button_object = document.createElement('object');
                            let checkout_button_url = window.boltConfig.cdn_url
                                + '/v1/checkout_button?publishable_key='
                                + window.boltConfig.publishable_key_checkout
                            button_object.setAttribute('data',checkout_button_url);
                            bolt_button.appendChild(button_object);
                        } else {
                            bolt_button.setAttribute('class', BoltCheckoutApiDriven.boltButtonCssClass);
                            if (BoltCheckoutApiDriven.boltButtonCssStyles.length) {
                                bolt_button.setAttribute('style', BoltCheckoutApiDriven.boltButtonCssStyles);
                            }
                            if (BoltCheckoutApiDriven.getCheckoutType() === 'checkout') {
                                bolt_button.classList.add(BoltCheckoutApiDriven.multiStepCssClass);
                            }

                            for (let attribute_name in BoltCheckoutApiDriven.additionalButtonAttributes) {
                                if (BoltCheckoutApiDriven.additionalButtonAttributes.hasOwnProperty(attribute_name)) {
                                    bolt_button.setAttribute(
                                        attribute_name, BoltCheckoutApiDriven.additionalButtonAttributes[attribute_name]
                                    );
                                }
                            }
                        }
                        // place the button before or after selector element
                        if (position && position.trim().toLowerCase() === 'append') {
                            element.parentNode.insertBefore(bolt_button, element.nextSibling);
                        } else {
                            element.parentNode.insertBefore(bolt_button, element);
                        }
                        // if no position is specified remove the selector element
                        if (!position) {
                            $(element).hide();
                        }
                        // if the replacement takes place after BoltCheckout.configure call
                        // call it again to set up the button. Skip if BoltCheckout is not available,
                        // ie. connect.js not loaded / executed yet,
                        // the button will be processed after connect.js loads.
                        if (window.BoltCheckout && BoltCheckoutApiDriven.isPromisesResolved) {
                            BoltCheckoutApiDriven.boltCheckoutConfigureCall(
                                {"quoteMaskedId": BoltCheckoutApiDriven.quoteMaskedId},
                                {"hints": BoltCheckoutApiDriven.boltCartHints}
                            );
                        }
                    });
                    /////////////////////////////////////////////////////
                }(selector);
            }
        },

        /**
         * Init bolt callback methods
         */
        initBoltCallbacks: function () {
            BoltCheckoutApiDriven.boltCallbacks = {
                close: function () {
                    BoltCheckoutApiDriven.popUpOpen = false;
                    window.boltConfig.trackCallbacks.onClose();

                    if (BoltCheckoutApiDriven.boltCallbacks.success_url) {
                        // redirect on success order save
                        location.href = BoltCheckoutApiDriven.boltCallbacks.success_url;
                    } else {
                        // Checkout was closed without success. I.e. user exited the modal via pressing ESC
                        // or clicking the X-close button on the top right of the modal.

                        // re-create order in case checkout was closed
                        // after order was changed from inside the checkout,
                        // i.e. the discount was applied
                        // invalidateBoltCart();
                        customerData.reload(['cart'], true);
                    }
                },

                onCheckoutStart: function() {
                    window.boltConfig.trackCallbacks.onCheckoutStart();
                },

                onShippingDetailsComplete: function(address) {
                    window.boltConfig.trackCallbacks.onShippingDetailsComplete(address);
                },

                onShippingOptionsComplete: function() {
                    window.boltConfig.trackCallbacks.onShippingOptionsComplete();
                },

                onPaymentSubmit: function() {
                    window.boltConfig.trackCallbacks.onPaymentSubmit();
                },

                success: function (transaction, callback) {
                    /**
                     * Success transaction handler.
                     * Sets the success url for the non-preauth flow.
                     * Calls additional javascript if defined in configuration.
                     * Triggers on success track event handler.
                     * Finally, calls the callback function
                     * that finishes the checkout modal operation.
                     *
                     * param object data    response from the non-preauth order/save controller, optional
                     * return void
                     */
                    let processSuccess = function (data) {
                        customerData.invalidate(['cart','bolthints']);
                        try {
                            if (typeof data !== 'undefined') {
                                BoltCheckoutApiDriven.boltCallbacks.success_url = data.success_url;
                            }
                            window.boltConfig.trackCallbacks.onSuccess(data);
                        } finally {
                            callback();
                        }
                    };

                    if (window.boltConfig.is_pre_auth) {
                        processSuccess();
                        return;
                    }

                    // abort previously sent save order request.
                    if (BoltCheckoutApiDriven.save_request) BoltCheckoutApiDriven.save_request.abort();
                    // get thr transaction reference
                    let parameters = [];
                    parameters.push('form_key=' + $('[name="form_key"]').val());
                    parameters.push('reference=' + transaction.reference);
                    parameters = parameters.join('&');
                    // update order ajax request callback
                    // sets the success order page redirect url from received data
                    // and calls the final Bolt defined callback
                    let onSuccess = function(data){
                        if (data.status !== 'success') {
                            if (data.message) {
                                showError();
                            }
                            return;
                        }
                        processSuccess(data);
                    };
                    let showError = function() {
                        BoltCheckoutApiDriven.showBoltErrorMessage('', transaction.reference);
                        // pretend order creation was success...
                        // we need to call this; otherwise bolt modal show infinte spinner.
                        callback();
                    };
                    // ajax call to the update order transaction data endpoint.
                    // passes the bolt transaction reference
                    BoltCheckoutApiDriven.save_request = $.post(window.boltConfig.save_order_url, parameters)
                        .done(onSuccess)
                        .fail(showError);
                },

                check: function () {

                    /**
                     * On Magento checkout page - Bolt payment only checkout
                     * trigger click on boltpay radio if the button clicked is
                     * in minicart panel or other (voluntary) location
                     * and trigger email validation.
                     */
                    if (BoltCheckoutApiDriven.getCheckoutType() === 'payment') {
                        // trigger click on boltpay radio
                        if ($('#boltpay').prop('checked') === false) $('#boltpay').click();

                        // stop if customer email field exists and is not valid on payment only page
                        let customerEmail = $(BoltCheckoutApiDriven.customerEmailSelector);
                        if (customerEmail.get(0)) {
                            let form = customerEmail.closest('form');
                            if (form.validation() && form.validation('isValid') === false) {
                                customerEmail.get(0).scrollIntoView();
                                return false;
                            }
                        }
                        BoltCheckoutApiDriven.popUpOpen = true;
                        return true;
                    }

                    /**
                     * On Bolt button click check if guest checkout is allowed.
                     * Display login popup to guest customers if it is not. The
                     * Magento customer, customerData, authenticationPopup objects are
                     * used.
                     */
                    if (BoltCheckoutApiDriven.readyStatusBarrier.isResolved() === false) {
                        BoltCheckoutApiDriven.popUpOpen = true;
                        return BoltCheckoutApiDriven.readyStatusBarrier.promise;
                    }
                    if (!BoltCheckoutApiDriven.readyStatusBarrier.value()) {
                        // If check resolved to false, guest checkout is disallowed, and user isn't logged in
                        // show authentication popup
                        if (!BoltCheckoutApiDriven.initiateCheckout
                            && !BoltCheckoutApiDriven.customerCart.isGuestCheckoutAllowed
                            && !BoltCheckoutApiDriven.isUserLoggedIn
                        ) {
                            BoltCheckoutApiDriven.showAuthenticationPopup();
                        }
                        BoltCheckoutApiDriven.popUpOpen = false;
                        return false;
                    }

                    BoltCheckoutApiDriven.popUpOpen = true;
                    return true;
                },

                onEmailEnter: function(email) {
                    window.boltConfig.trackCallbacks.onEmailEnter(email);
                    if (BoltCheckoutApiDriven.boltCallbacks.email !== email) {
                        $.post(window.boltConfig.save_email_url, 'email=' + encodeURIComponent(email));
                        BoltCheckoutApiDriven.boltCallbacks.email = email;
                    }
                }
            }
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
        },

        /**
         * Main init method. Preparing bolt connection config data, events/elementListeners/promises
         *
         * @param {Object} magentoBoltConfig
         */
        init: function(config) {
            //wait while BoltCheckout will be initialized
            if (!window.BoltCheckout) {
                whenDefined(window, 'BoltCheckout', this.init, 'BoltCheckoutApiDrivenInit');
                return;
            }
            //init new barriers
            this.cartBarrier = this.initBarrier();
            this.hintsBarrier = this.initBarrier();
            this.readyStatusBarrier = this.initBarrier();
            this.initiateCheckout = window.boltConfig.initiate_checkout && this.getInitiateCheckoutCookie();
            this.customerCart = customerData.get('cart');
            //subscription of 'customer-data' cart
            this.customerCart.subscribe(BoltCheckoutApiDriven.magentoCartDataListener);

            //call bolt checkout configure immediately with promise parameters
            this.boltCheckoutConfigureCall(this.cartBarrier.promise, this.hintsBarrier.promise);
            this.resolveReadyStatusPromise();
            this.initBoltCallbacks();
            this.initUIElements();

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
                BoltCheckoutApiDriven.isPromisesResolved = true;
            }

            //trying to resolve hints promise if data is existed
            if (this.customerCart().boltCartHints !== undefined &&
                this.customerCart().boltCartHints !== null
            ) {
                this.boltCartHints = this.customerCart().boltCartHints;
                this.hintsBarrier.resolve({"hints": this.boltCartHints})
                BoltCheckoutApiDriven.isPromisesResolved = true;
            }
        }
    }

    /**
     * Entry point
     *
     * @param {Object|Promise} magentoBoltConfig
     */
    return function (config) {
        BoltCheckoutApiDriven.init(config);
    };
});

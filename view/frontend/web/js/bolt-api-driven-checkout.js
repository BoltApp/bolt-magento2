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
 * @copyright  Copyright (c) 2017-2023 Bolt Financial, Inc (https://www.bolt.com)
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
        quoteMaskedId: undefined,
        boltCartHints: {prefill:{}},
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
        paymentOnlyCountrySelectors: ['div[name="billingAddressboltpay.country_id"] select[name=country_id]','div[name="shippingAddress.country_id"] select[name=country_id]','.payment-method-boltpay select[name=country_id]'],
        paymentOnlyStateSelectors: ['div[name="billingAddressboltpay.region_id"] select[name=region_id]','div[name="shippingAddress.region_id"] select[name=region_id]','.payment-method-boltpay select[name=region_id]'],
        countrySelectors: ['select[name=country_id]'],
        stateSelectors: ['select[name=region_id]'],
        trackCallbacks: window.boltConfig.trackCallbacks,
        popUpOpen: false,
        saveRequest: null,
        paymentOnlyPromise: null,
        boltConfigure: null,
        createRequest: false,
        cartRestricted: false,
        allowAutoOpen: true,
        inputNameToHintsPrefill: {
            'firstname': 'firstName',
            'lastname':  'lastName',
            'username':  'email',
            'telephone': 'phone',
            'street[0]': 'addressLine1',
            'street[1]': 'addressLine2',
            'city':      'city',
            'postcode':  'zip'
        },
        inputNameToHintsPrefillPrefixes: [
            '.fieldset.estimate',
            '#checkout-step-shipping'
        ],

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
                        if (window.boltConfig.is_sso_enabled) {
                            // we should show Bolt SSO popup if SSO is enabled
                            BoltCheckoutApiDriven.showBoltSSOPopup()
                        } else {
                            // we should show authentication popup
                            BoltCheckoutApiDriven.showAuthenticationPopup();
                        }

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
         * Showing authentication modal popup
         */
        showBoltSSOPopup: function () {
            // set a cookie for auto opening Bolt checkout after login
            BoltCheckoutApiDriven.setInitiateCheckoutCookie();
            $('#bolt-sso-popup .bolt-account-sso [data-tid="shopperDashboardButton"]').click();
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
        initElementReadyCallback: function (selector, callback) {
            // Store the selector and callback to be monitored
            BoltCheckoutApiDriven.elementListeners.push({
                selector: selector,
                fn: callback
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
         * Init callbacks for DOM element data changes
         * @param selector
         * @param callback
         */
        initElementDataChangeCallback: function (selector, callback) {
            // Store the selector and callback to be monitored
            BoltCheckoutApiDriven.elementDataListeners.push({
                selector: selector,
                fn: callback
            });
            if (!BoltCheckoutApiDriven.elementDataObserver) {
                // Watch for data changes in the document
                BoltCheckoutApiDriven.elementDataObserver = new MutationObserver(BoltCheckoutApiDriven.callElementDataChangeCallbacks);
                let config = {
                    characterData: true,
                    subtree: true
                };
                BoltCheckoutApiDriven.elementDataObserver.observe(window.document.documentElement, config);
            }
        },

        /**
         * call elements change callbacks
         */
        callElementDataChangeCallbacks: function () {
            // Check the DOM for elements matching a stored selector
            for (let i = 0, len = BoltCheckoutApiDriven.elementDataListeners.length, listener, elements; i < len; i++) {
                listener = BoltCheckoutApiDriven.elementDataListeners[i];
                // Query for elements matching the specified selector
                elements = window.document.querySelectorAll(listener.selector);
                for (let j = 0, jLen = elements.length, element; j < jLen; j++) {
                    element = elements[j];
                    // Invoke the callback with the element
                    listener.fn.call(element, element);
                }
            }
        },

        /**
         * Init callbacks for DOM element attributes changes
         * @param selectors
         * @param callback
         * @param onReady
         * @param visibleOnly
         */
        initElementAttrChangeCallback: function (selectors, callback, onReady, visibleOnly){
            for (let i = 0, length = selectors.length; i < length; i++) {

                let selector = selectors[i];

                ! function(selector) {

                    BoltCheckoutApiDriven.initElementReadyCallback(selector, function(el) {
                        if (onReady) callback(el);
                        let value = el.value;
                        BoltCheckoutApiDriven.initElementReadyCallback(selector, function(element) {
                            if (visibleOnly && element.offsetParent === null) return;
                            if (element.value !== value) {
                                value = element.value;
                                callback(element);
                            }
                        });
                    });

                }(selector);
            }
        },

        /**
         * Init callbacks for elements list
         * @param selectors
         * @param callback
         * @param nonEmpty
         * @param visibleOnly
         */
        initElementListDataChangeCallback: function(selectors, callback, nonEmpty, visibleOnly){
            for (let i = 0, length = selectors.length; i < length; i++) {

                let selector = selectors[i];

                ! function(selector) {

                    BoltCheckoutApiDriven.initElementReadyCallback(selector, function(el) {
                        let value = el.textContent;
                        onDataChange(selector, function(element) {
                            if (visibleOnly && element.offsetParent === null) return;
                            if (element.textContent !== value) {
                                let originalValue = value;
                                value = element.textContent;
                                if (nonEmpty && !value) return;
                                if (originalValue !== "" && parseInt(originalValue) > -1) return;
                                callback(element);
                            }
                        });
                    });

                }(selector);
            }
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
         * Is object
         * @param item
         * @returns {boolean}
         */
        isObject: function (item) {
            return (item && typeof item === 'object' && !Array.isArray(item));
        },

        /**
         * Deep merging of two objects
         * @returns {{}}
         */
        deepMergeObjects: function () {

            // Variables
            let extended = {};
            let i = 0;

            // Merge the object into the extended object
            let merge = function (obj) {

                for (let prop in obj) {

                    if (obj.hasOwnProperty(prop)) {
                        if (BoltCheckoutApiDriven.isObject(obj[prop])) {
                            // If the property is an object
                            extended[prop] = BoltCheckoutApiDriven.deepMergeObjects(extended[prop], obj[prop]);
                        } else {
                            // Otherwise, do a regular merge
                            extended[prop] = obj[prop];
                        }
                    }
                }

            };

            // Loop through each object and conduct a merge
            for (; i < arguments.length; i++) {
                merge(arguments[i]);
            }

            return extended;
        },

        /**
         * Update bolt hints
         */
        updateHints: function (){
            // Check if cart exists (orderToken received).
            // Otherwise, do not update hints until it becomes available.
            if (!BoltCheckoutApiDriven.quoteMaskedId) {
                whenDefined(BoltCheckoutApiDriven, 'quoteMaskedId', BoltCheckoutApiDriven.updateHints, 'updateHints');
                return;
            }
            let newHints = JSON.stringify(BoltCheckoutApiDriven.boltCartHints);
            if ((BoltCheckoutApiDriven.boltCartHints !== newHints) && !BoltCheckoutApiDriven.isPromisesResolved) {
                BoltCheckoutApiDriven.boltCheckoutConfigureCall(
                    {"id": BoltCheckoutApiDriven.quoteMaskedId},
                    {"hints": BoltCheckoutApiDriven.boltCartHints}
                );
                BoltCheckoutApiDriven.boltCartHints = newHints;
            }
        },

        /**
         * Magento "customer-data" cart update handler
         *
         * @param {Object} magentoCart
         */
        magentoCartDataListener: function (magentoCart) {
            BoltCheckoutApiDriven.resolveReadyStatusPromise();
            //if timestamp is the same no checks needed
            if (magentoCart.data_id && magentoCart.data_id === this.magentoCartTimeStamp) {
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
                cart = {"id": BoltCheckoutApiDriven.quoteMaskedId};
                if (!BoltCheckoutApiDriven.cartBarrier.isResolved()) {
                    BoltCheckoutApiDriven.cartBarrier.resolve(cart);
                    BoltCheckoutApiDriven.isPromisesResolved = true;
                }
                isBoltCheckoutConfigureCallRequired = true;
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
                }
                isBoltCheckoutConfigureCallRequired = true;
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
                                {"id": BoltCheckoutApiDriven.quoteMaskedId},
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
                            window.boltConfig.trackCallbacks.onSuccess(transaction);
                        } finally {
                            callback();
                        }
                    };

                    if (window.boltConfig.is_pre_auth) {
                        processSuccess();
                        return;
                    }

                    // abort previously sent save order request.
                    if (BoltCheckoutApiDriven.saveRequest) BoltCheckoutApiDriven.saveRequest.abort();
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
                    BoltCheckoutApiDriven.saveRequest = $.post(window.boltConfig.save_order_url, parameters)
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
                            if (window.boltConfig.is_sso_enabled) {
                                BoltCheckoutApiDriven.showBoltSSOPopup()
                            } else {
                                BoltCheckoutApiDriven.showAuthenticationPopup();
                            }

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
         * Init order management
         */
        initOrderManagement: function () {
            if (!window.boltConfig.order_management_selector) {
                return;
            }
            let insertAccountScript = function () {
                let scriptTag = document.getElementById('bolt-account');
                if (scriptTag) {
                    return;
                }
                let publishableKey = this.getCheckoutKey();
                scriptTag = document.createElement('script');
                scriptTag.setAttribute('type', 'text/javascript');
                scriptTag.setAttribute('async', '');
                scriptTag.setAttribute('src', window.boltConfig.account_url);
                scriptTag.setAttribute('id', 'bolt-account');
                scriptTag.setAttribute('data-publishable-key', publishableKey);
                document.head.appendChild(scriptTag);
            }
            let insertManagementButtonDivAfterElement = function(element) {
                let orderManagementButton = document.createElement('div');
                orderManagementButton.setAttribute('class', 'bolt-account-login');
                element.parentNode.insertBefore(orderManagementButton, element.nextSibling);
                insertAccountScript();
            }
            let setupOrderManagementReplacement = function() {
                let element = document.querySelector(window.boltConfig.order_management_selector);
                if (element) {
                    insertManagementButtonDivAfterElement(element);
                } else {
                    onElementReady(window.boltConfig.order_management_selector, function(element) {
                        insertManagementButtonDivAfterElement(element);
                    });
                }
            }
            setupOrderManagementReplacement();
        },

        initPaymentOnly: function () {
            if (BoltCheckoutApiDriven.getCheckoutType() !== 'payment') {
                return;
            }
            // define the params sent with the request variable
            let params = [];

            params.push('form_key=' + $('[name="form_key"]').val());

            // stop if customer email field is present and invalid
            let customer_email = $(BoltCheckoutApiDriven.customerEmailSelector);
            let form = customer_email.closest('form');
            if (typeof customer_email.val() !== 'undefined'
                && (customer_email.val().trim() === ''
                    || (form.validation() && form.validation('isValid') === false)
                )) return;

            if (window.boltConfig && !!window.boltConfig.publishable_key_payment) {
                params.push('payment_only=true');
            } else {
                return;
            }

            // get additional page data to be sent with the request,
            // one page checkout billing info, email, ...
            let place_order_payload = document.getElementById(BoltCheckoutApiDriven.placeOrderPayloadId);
            if (place_order_payload && place_order_payload.value) {

                // stop if customer billing address is not set
                if ($(BoltCheckoutApiDriven.billingAddressSelector).val() === 'null') return;

                // decode payload json string
                let place_order_payload_obj = JSON.parse(place_order_payload.value);

                // delete redundant title property
                if (place_order_payload_obj.paymentMethod) {
                    delete place_order_payload_obj.paymentMethod.title;
                }

                // update payload email, use the value from the page
                if (customer_email.val()) {
                    place_order_payload_obj.email = encodeURIComponent(customer_email.val());
                }
                place_order_payload.value = JSON.stringify(place_order_payload_obj);

                params.push('place_order_payload=' + place_order_payload.value);
            }
            params = params.join('&');

            BoltCheckoutApiDriven.paymentOnlyPromise = new Promise(function (resolve, reject) {

                BoltCheckoutApiDriven.createRequest = true;

                // send create order request
                $.get(window.boltConfig.create_order_url, params)
                    .done(function (data) {

                        BoltCheckoutApiDriven.cartRestricted = !!data.restrict;

                        // Stop if Bolt checkout is restricted
                        if (BoltCheckoutApiDriven.cartRestricted) {
                            if (BoltCheckoutApiDriven.popUpOpen) reject(new Error(data.message));
                            BoltCheckoutApiDriven.hintsBarrier.resolve(BoltCheckoutApiDriven.boltCartHints);
                            return;
                        }

                        if (data.status !== 'success') {
                            reject(new Error(data.message || 'Network request failed'));
                            BoltCheckoutApiDriven.hintsBarrier.resolve(BoltCheckoutApiDriven.boltCartHints);
                            return;
                        }

                        if (!data.cart) {
                            reject(new Error('The cart info is missing.'));
                            BoltCheckoutApiDriven.hintsBarrier.resolve(BoltCheckoutApiDriven.boltCartHints);
                            return;
                        }

                        // if (!data.cart.orderToken) {
                        //     reject(new Error('The cart is empty.'));
                        //     BoltCheckoutApiDriven.hintsBarrier.resolve(BoltCheckoutApiDriven.boltCartHints);
                        //     return;
                        // }

                        let prefill = BoltCheckoutApiDriven.isObject(data.hints.prefill)
                            ? BoltCheckoutApiDriven.deepMergeObjects(BoltCheckoutApiDriven.boltCartHints.prefill, data.hints.prefill) : BoltCheckoutApiDriven.boltCartHints.prefill;

                        BoltCheckoutApiDriven.boltCartHints = BoltCheckoutApiDriven.deepMergeObjects(BoltCheckoutApiDriven.boltCartHints, data.hints);
                        BoltCheckoutApiDriven.boltCartHints.prefill = prefill;
                        //////////////////////////

                        resolve(data.cart);
                        BoltCheckoutApiDriven.hintsBarrier.resolve(BoltCheckoutApiDriven.boltCartHints);

                        // open the checkout if auto-open flag is set
                        // one time only on page load
                        if (BoltCheckoutApiDriven.initiateCheckout && BoltCheckoutApiDriven.allowAutoOpen) {
                            BoltCheckoutApiDriven.boltConfigure.open();
                            BoltCheckoutApiDriven.allowAutoOpen = false;
                        }
                    })
                    .fail(function(jqXHR, textStatus, errorThrown) {
                        reject(new Error(errorThrown || jqXHR.statusText || 'Network request failed'));
                    })
                    .always(function() {
                        BoltCheckoutApiDriven.createRequest = false;
                    })
            });
            BoltCheckoutApiDriven.boltCheckoutConfigureCall(
                {"id": BoltCheckoutApiDriven.quoteMaskedId},
                {"hints": BoltCheckoutApiDriven.boltCartHints}
            );
        },

        initPaymentOnlyObservers: function () {
            if (this.getCheckoutType() === 'payment') {
                /////////////////////////////////////////////////////
                // Call Bolt order creation Magento endpoint on
                // customer billing address change on payment only page,
                // storing the live page data in Bolt cart.
                /////////////////////////////////////////////////////
                let billing_address_value;
                this.initElementReadyCallback(this.billingAddressSelector, function(element) {
                    let address = element.value;
                    if (address === 'null' || address === '') return;

                    if (address !== billing_address_value) {
                        billing_address_value = address;
                        BoltCheckoutApiDriven.initPaymentOnly();
                    }
                });
                /////////////////////////////////////////////////////

                /////////////////////////////////////////////////////
                // Call Bolt order creation Magento endpoint on
                // customer email change on payment only page, storing
                // the live page data in Bolt cart.
                /////////////////////////////////////////////////////
                let customer_email_value;
                this.initElementReadyCallback(this.customerEmailSelector, function(element) {

                    let email = element.value.trim();
                    if (email === "") return;

                    if (email !== customer_email_value) {
                        customer_email_value = email;
                        BoltCheckoutApiDriven.initPaymentOnly();
                    }
                });
            }
        },

        initHintsObservers: function () {
            let countrySelectors = (BoltCheckoutApiDriven.getCheckoutType() === "payment") ?
                BoltCheckoutApiDriven.paymentOnlyCountrySelectors : BoltCheckoutApiDriven.countrySelectors;
            let stateSelectors = (BoltCheckoutApiDriven.getCheckoutType() === "payment") ?
                BoltCheckoutApiDriven.paymentOnlyStateSelectors : BoltCheckoutApiDriven.stateSelectors;

            BoltCheckoutApiDriven.initElementAttrChangeCallback(countrySelectors, function(element) {
                if (!element.value) {
                    delete BoltCheckoutApiDriven.boltCartHints.prefill.country;
                }
                else {
                    BoltCheckoutApiDriven.boltCartHints.prefill.country = element.value;
                }
                delete BoltCheckoutApiDriven.boltCartHints.prefill.state;

                BoltCheckoutApiDriven.updateHints();
            }, true);

            BoltCheckoutApiDriven.initElementAttrChangeCallback(stateSelectors, function(element) {
                if (!element.value) {
                    delete BoltCheckoutApiDriven.boltCartHints.prefill.state;
                }
                else {
                    BoltCheckoutApiDriven.boltCartHints.prefill.state = element.options[element.selectedIndex].text;
                }

                BoltCheckoutApiDriven.updateHints();
            });

            // monitor address text input fields and update hints on value change
            for (let i = 0, length = BoltCheckoutApiDriven.inputNameToHintsPrefillPrefixes.length; i < length; i++) {

                let prefix = BoltCheckoutApiDriven.inputNameToHintsPrefillPrefixes[i];

                ! function (prefix) {

                    for (let inputName in BoltCheckoutApiDriven.inputNameToHintsPrefill) {

                        if (BoltCheckoutApiDriven.inputNameToHintsPrefill.hasOwnProperty(inputName)) {

                            ! function (inputName) {

                                let prefillKey = BoltCheckoutApiDriven.inputNameToHintsPrefill[inputName];
                                let inputSelectors = [prefix + ' input[name="' + inputName + '"]'];

                                BoltCheckoutApiDriven.initElementAttrChangeCallback(inputSelectors, function(element) {
                                    if (element.value) {
                                        // set the hints prefill if the correlated input field value is not an empty string
                                        BoltCheckoutApiDriven.boltCartHints.prefill[prefillKey] = element.value;
                                    } else {
                                        // delete hints prefill key if the value is empty
                                        delete BoltCheckoutApiDriven.boltCartHints.prefill[prefillKey];
                                    }
                                    BoltCheckoutApiDriven.updateHints();
                                });

                            } (inputName);
                        }
                    }

                } (prefix);
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
            BoltCheckoutApiDriven.boltConfigure = window.BoltCheckout.configure(
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
            //сall magento card initialisation if event happened before we subscribed to it
            if (this.customerCart()) {
                BoltCheckoutApiDriven.magentoCartDataListener(this.customerCart());
            }
            //call bolt checkout configure immediately with promise parameters
            this.boltCheckoutConfigureCall(this.cartBarrier.promise, this.hintsBarrier.promise);
            this.resolveReadyStatusPromise();
            this.initBoltCallbacks();
            this.initUIElements();
            this.initOrderManagement();
            this.initPaymentOnlyObservers();
            this.initPaymentOnly();
            this.initHintsObservers();
            if (!this.customerCart()) {
                // if magento cart is not present for some reason (wrong setup)
                // we need to get it ourself
                setTimeout(function () {
                    if (!BoltCheckoutApiDriven.customerCart()) {
                        customerData.invalidate(['cart']);
                        customerData.reload(['cart']);
                    }
                }, 4000);
                return;
            }

            this.magentoCartTimeStamp = this.customerCart().data_id;

            //trying to resolve cart promise if data is existed
            if (this.customerCart().quoteMaskedId !== undefined &&
                this.customerCart().quoteMaskedId !== null
            ) {
                this.quoteMaskedId = this.customerCart().quoteMaskedId;
                this.cartBarrier.resolve({"id": this.quoteMaskedId})
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

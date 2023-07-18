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
    'domReady!'
], function ($, _) {
    'use strict';
    /**
     * Main BoltCheckoutApiDriven class
     *
     * @type {{init: BoltCheckoutApiDriven.init, boltCallbacks: {}, cartBarrier: {resolve: resolve, promise: Promise<unknown>, value: (function(): null), isResolved: (function(): boolean)}, magentoCartTimeStamp: null, hintsBarrier: {resolve: resolve, promise: Promise<unknown>, value: (function(): null), isResolved: (function(): boolean)}, magentoCartDataListener: BoltCheckoutApiDriven.magentoCartDataListener, boltCartHints: null, customerCart: null, boltParameters: {}, quoteMaskedId: null, boltCheckoutConfigureCall: BoltCheckoutApiDriven.boltCheckoutConfigureCall}}
     */
    let BoltCheckoutApiDriven = {
        settings: null,
        elementListeners: [],
        elementReadyObserver: null,
        elementAttributesListeners: [],
        elementAttributesObserver: null,
        mutationObserver: window.MutationObserver || window.WebKitMutationObserver,
        boltButtonSelector: '.bolt-checkout-button',
        saveRequest: null,
        cart: {},
        hints: {prefill:{}},
        createRequest: null,
        oldHints: null,
        paymentOnlyKey: null,
        inputNameToHintsPrefill: {
            'order[billing_address][firstname]': 'firstname',
            'order[billing_address][lastname]': 'lastname',
            'order[account][email]': 'email',
            'order[billing_address][telephone]': 'phone',
            'order[billing_address][street][0]': 'addressLine1',
            'order[billing_address][street][1]': 'addressLine2',
            'order[billing_address][city]': 'city',
            'order[billing_address][postcode]': 'zip'
        },

        inputNameToHintsPrefillPrefixes: [
            '#order-billing_address_fields'
        ],
        callbacks: {
            close: function () {
                // redirect on success order save
                if (BoltCheckoutApiDriven.callbacks.success_url) {
                    location.href = BoltCheckoutApiDriven.callbacks.success_url;
                }
            },

            success: function (transaction, callback) {
                /**
                 * Success transaction handler.
                 * Sets the success url for the non-preauth flow.
                 * Calls the callback function
                 * that finishes the checkout modal operation.
                 *
                 * param object data    response from the non-preauth order/save controller, optional
                 * return void
                 */
                let processSuccess = function (data) {
                    if (typeof data !== 'undefined') {
                        BoltCheckoutApiDriven.callbacks.success_url = data.success_url;
                    }
                    callback();
                };

                if (BoltCheckoutApiDriven.settings.isPreAuth) {
                    processSuccess();
                    return;
                }

                // abort previously sent save order request.
                if (BoltCheckoutApiDriven.saveRequest) BoltCheckoutApiDriven.saveRequest.abort();
                // add the transaction reference and admin form key to the request parameters
                let parameters = [];
                parameters.push('form_key=' + $('[name="form_key"]').val());
                parameters.push('reference=' + transaction.reference);
                parameters.push('store_id=' + BoltCheckoutApiDriven.settings.storeId);
                parameters = parameters.join('&');
                // update order ajax request callback
                // sets the success order page redirect url from received data
                // and calls the final Bolt defined callback
                let onSuccess = function(data){
                    processSuccess(data);
                };
                // ajax call to the update order transaction data endpoint.
                // passes the bolt transaction reference
                BoltCheckoutApiDriven.saveRequest = $.post(BoltCheckoutApiDriven.settings.save_order_url, parameters)
                    .done(onSuccess);
            },

            // the Bolt order is created right after the checkout button is clicked
            // and the checkout modal popup is opened only if order creation was successfull
            check: function () {
                return BoltCheckoutApiDriven.isValidToken();
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

        elementsAttributesCheckExisting: function () {
            // Check the DOM for elements matching a stored selector
            for (let i = 0, len = BoltCheckoutApiDriven.elementAttributesListeners.length, listener, elements; i < len; i++) {
                listener = BoltCheckoutApiDriven.elementAttributesListeners[i];
                // Query for elements matching the specified selector
                elements = document.querySelectorAll(listener.selector);
                for (let j = 0, jLen = elements.length, element; j < jLen; j++) {
                    element = elements[j];
                    // Invoke the callback with the element
                    listener.fn.call(element, element);
                }
            }
        },

        /**
         * Init DOM elements callbacks
         *
         * @param selector
         * @param callback
         *
         * win.onElementReady
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
         * win.onAttributesChange
         * @param selector
         * @param callback
         */
        initElementAttributesChangeCallback: function (selector, callback) {
            // Store the selector and callback to be monitored
            BoltCheckoutApiDriven.elementAttributesListeners.push({
                selector: selector,
                fn: callback
            });
            if (!BoltCheckoutApiDriven.elementAttributesObserver) {
                // Watch for attribute changes in the document
                BoltCheckoutApiDriven.elementAttributesObserver = new BoltCheckoutApiDriven.mutationObserver(BoltCheckoutApiDriven.elementsAttributesCheckExisting);
                let config = {
                    attributes: true,
                    subtree: true
                };
                BoltCheckoutApiDriven.elementAttributesObserver.observe(document.documentElement, config);
            }
        },

        isObject: function (item) {
            return (item && typeof item === 'object' && !Array.isArray(item));
        },

        deepMergeObjects: function () {
            // Variables
            let extended = {},
                i = 0;

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

        insertConnectScript: function(publishableKey, connectUrl) {
            let scriptTag = document.getElementById('bolt-connect');
            if (scriptTag) {
                BoltCheckout.configure(BoltCheckoutApiDriven.cart, BoltCheckoutApiDriven.hints, BoltCheckoutApiDriven.callbacks);
                return;
            }
            if (!publishableKey) {
                console.error('No publishable key set');
                return;
            }
            if (!connectUrl) {
                console.error('No connect url set');
                return;
            }
            scriptTag = document.createElement('script');
            scriptTag.setAttribute('type', 'text/javascript');
            scriptTag.setAttribute('src', connectUrl);
            scriptTag.setAttribute('id', 'bolt-connect');
            scriptTag.setAttribute('data-publishable-key', publishableKey);
            scriptTag.onload = function() {BoltCheckout.configure(BoltCheckoutApiDriven.cart, BoltCheckoutApiDriven.hints, BoltCheckoutApiDriven.callbacks);}

            // Due to a conflict between Magento and Bolt polyfill of Map class we use an intermediary constructor
            // to negate conflicting change to the class constructor
            if (window.isMapPolyfilled) {
                let originalMap = Map;
                let originalConstructor = Map.prototype.constructor;
                function boltMap() {
                    this.constructor = originalConstructor;
                    originalMap.apply(this, arguments);
                    this.constructor = boltMap;
                }
                boltMap.prototype = originalMap.prototype;
                Map = boltMap;
            }
            document.head.appendChild(scriptTag);
        },

        isValidToken: function () {
            try {
                let // shipping address mask overlay
                    addressShippingOverlay = $('#address-shipping-overlay')[0],
                    // if the shipping address is not masked (i.e. overlay is not visible) shipping is required
                    shippingRequired = addressShippingOverlay && addressShippingOverlay.offsetParent === null,
                    isShippingMethodSelected = $('input[name="order[shipping_method]"][type=radio]:checked').length;

                if (BoltCheckoutApiDriven.createRequest) {
                    throw 'There is a pending Bolt Cart creation request, please wait.';
                }

                if (BoltCheckoutApiDriven.cart.errorMessage) {
                    throw BoltCheckoutApiDriven.cart.errorMessage;
                }

                if (shippingRequired && !isShippingMethodSelected) {
                    throw 'Please specify the "Shipping Method"!';
                }
                return true;
            } catch (e) {
                if (typeof e === 'string') {
                    console.error('BoltPay Check: ', e);
                    require(['Magento_Ui/js/modal/alert'], function (alert) {
                        alert({ content: e });
                    });
                }
                return false;
            }
        },

        createOrder: function () {
            if (BoltCheckoutApiDriven.createRequest) {
                return;
            }
            // define the params sent with the request variable
            let params = [];

            params.push('form_key=' + $('[name="form_key"]').val());
            params.push('payment_only=true');
            params = params.join('&');

            // set cart and hints data in a response callback
            let onSuccess = function(data) {

                BoltCheckoutApiDriven.cart = data.cart;
                let prefill = BoltCheckoutApiDriven.isObject(data.hints.prefill)
                    ? BoltCheckoutApiDriven.deepMergeObjects(BoltCheckoutApiDriven.hints.prefill, data.hints.prefill)
                    : BoltCheckoutApiDriven.hints.prefill;
                BoltCheckoutApiDriven.hints = BoltCheckoutApiDriven.deepMergeObjects(BoltCheckoutApiDriven.hints, data.hints);
                BoltCheckoutApiDriven.hints.prefill = prefill;

                BoltCheckoutApiDriven.settings.storeId = data.storeId;
                BoltCheckoutApiDriven.settings.isPreAuth = data.isPreAuth;

                if (data.backOfficeKey && data.connectUrl) {
                    BoltCheckoutApiDriven.insertConnectScript(data.backOfficeKey, data.connectUrl);
                }
                if (data.paymentOnlyKey && BoltCheckoutApiDriven.settings.pay_by_link_url) {
                    BoltCheckoutApiDriven.paymentOnlyKey = data.paymentOnlyKey
                    $(".bolt-checkout-pay-by-link-generate").show();
                    $(".bolt-checkout-options-separator").toggle(!!data.backOfficeKey);
                }
                BoltCheckoutApiDriven.createRequest = null;
            };

            let onError = function(error) {
                BoltCheckoutApiDriven.createRequest = null;
            };

            // send create order request
            BoltCheckoutApiDriven.createRequest = $.get(BoltCheckoutApiDriven.settings.create_order_url, params)
                .done(onSuccess)
                .fail(onError);
        },

        configureHints: function () {
            // wait 3 seconds for the field(s) to be fully filled,
            // do not react on every keypress
            setTimeout(
                function() {
                    let newHints = JSON.stringify(BoltCheckoutApiDriven.hints);

                    if (BoltCheckoutApiDriven.oldHints !== newHints && window.BoltCheckout) {
                        BoltCheckout.configure(BoltCheckoutApiDriven.cart, BoltCheckoutApiDriven.hints, BoltCheckoutApiDriven.callbacks);
                        BoltCheckoutApiDriven.oldHints = BoltCheckoutApiDriven.newHints;
                    }
                },
                3000
            );
        },

        monitorAttributesChange: function(selectors, fn, on_ready, visible_only) {
            for (let i = 0, length = selectors.length; i < length; i++) {
                let selector = selectors[i];
                ! function(selector) {
                    BoltCheckoutApiDriven.initElementReadyCallback(selector, function(el) {
                        if (on_ready) {
                            fn(el);
                        }
                        let value = el.value;
                        BoltCheckoutApiDriven.initElementAttributesChangeCallback(selector, function(element) {
                            if (visible_only && element.offsetParent === null) return;
                            if (element.value !== value) {
                                value = element.value;
                                fn(element);
                            }
                        });
                    })
                }(selector);
            }
        },

        init: function(config) {
            this.settings = config.boltConfig;
            this.initUiEvents()
        },

        initUiEvents: function () {
            $(document).on('click', '#bolt-pay-by-link', function(e){
                if (!BoltCheckoutApiDriven.isValidToken()) {
                    e.preventDefault();
                }
            });

            // generate order link handler
            $(document).on('click', '#bolt-pay-by-link-generate', function(e){
                e.preventDefault();
                if (!BoltCheckoutApiDriven.paymentOnlyKey) {
                    return;
                }
                $('#bolt-pay-by-link-generate span').text('Generating...');
                $('#bolt-pay-by-link-generate').prop('disabled', true);
                // gets order token by quote masked id
                BoltCheckout.createBoltOrder(BoltCheckoutApiDriven.cart.id).then((result) => {
                    $('#bolt-pay-by-link-generate').prop('disabled', false);
                    $('#bolt-pay-by-link-generate').hide();
                    if (result.error) {
                        $('#bolt-pay-by-link-generate span').text('Generate order link');
                        $('#bolt-pay-by-link-generate').prop('disabled', false);
                        $('#bolt-pay-by-link-generate').show();
                        throw result.error;
                    }
                    // show order pay by link
                    $(".bolt-checkout-pay-by-link").html("Send <a id='bolt-pay-by-link' href='"
                        + BoltCheckoutApiDriven.settings.pay_by_link_url + "?"
                        + $.param({ publishable_key: BoltCheckoutApiDriven.paymentOnlyKey, token: result.orderToken })
                        + "'>this link</a> to consumer to receive payment");

                }).catch((error) => {
                    $('#bolt-pay-by-link-generate span').text('Generate order link');
                    $('#bolt-pay-by-link-generate').prop('disabled', false);
                    $('#bolt-pay-by-link-generate').show();
                    alert(error);
                });
            });

            BoltCheckoutApiDriven.initElementReadyCallback(BoltCheckoutApiDriven.boltButtonSelector, function () {
                BoltCheckoutApiDriven.createOrder();
            });

            BoltCheckoutApiDriven.initElementReadyCallback('.bolt-checkout-pay-by-link', function () {
                BoltCheckoutApiDriven.createOrder();
            });

            for (let i = 0, length = BoltCheckoutApiDriven.inputNameToHintsPrefillPrefixes.length; i < length; i++)
            {
                let prefix = BoltCheckoutApiDriven.inputNameToHintsPrefillPrefixes[i];
                ! function (prefix)
                {
                    for (let input_name in BoltCheckoutApiDriven.inputNameToHintsPrefill) {
                        if (BoltCheckoutApiDriven.inputNameToHintsPrefill.hasOwnProperty(input_name))
                        {
                            ! function (input_name) {
                                let prefill_key = BoltCheckoutApiDriven.inputNameToHintsPrefill[input_name],
                                    input_selectors = [prefix + ' input[name="' + input_name + '"]'];
                                BoltCheckoutApiDriven.monitorAttributesChange(input_selectors, function(element) {
                                    if (element.value) {
                                        // set the hints prefill if the correlated input field value is not an empty string
                                        BoltCheckoutApiDriven.hints.prefill[prefill_key] = element.value;
                                    } else {
                                        // delete hints prefill key if the value is empty
                                        delete BoltCheckoutApiDriven.hints.prefill[prefill_key];
                                    }
                                    BoltCheckoutApiDriven.configureHints();
                                });
                            } (input_name);
                        }
                    }
                } (prefix);
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

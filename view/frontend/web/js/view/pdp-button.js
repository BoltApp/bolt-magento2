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
    'Magento_Customer/js/model/authentication-popup',
    'Magento_Customer/js/customer-data',
    'Bolt_Boltpay/js/utils/when-defined',
    'mage/validation/validation',
    'mage/cookies',
    'jquery-ui-modules/widget',
    'domReady!'
], function ($, authenticationPopup, customerData, whenDefined) {
    'use strict';

    $.widget('bolt.pdpButton', {
        options: {
            isGuestCheckoutAllowed: '',
            itemPrice: '',
            storeId: '',
            currency: '',
            productId: '',
            productSku: '',
            productName: '',
            productType: '',
            basePrice: '',
            isGrouped: '',
            isBoltOrderCachingEnabled: '',
            childGroupedProducts: '',
            isSaveHintsInSections: '',
            isExtendWarrantyModuleEnabled: '',
        },

        /** @inheritdoc */
        _create: function () {
            this._init();
        },

        _init: function () {
            let customer = customerData.get('customer');
            let isGuestCheckoutAllowed = this.options.isGuestCheckoutAllowed;
            let itemPrice = this.options.itemPrice;
            let storeId = this.options.storeId;
            let currency = this.options.currency;
            let productId = this.options.productId;
            let productSku = this.options.productSku;
            let productName = this.options.productName;
            let productType = this.options.productType;
            let basePrice = this.options.basePrice;
            let isGrouped = this.options.isGrouped;
            let isBoltOrderCachingEnabled = this.options.isBoltOrderCachingEnabled;
            let childGroupedProducts = this.options.childGroupedProducts;
            let isSaveHintsInSections = this.options.isSaveHintsInSections;
            let isExtendWarrantyModuleEnabled = this.options.isExtendWarrantyModuleEnabled;
            let cart;

            let settings = window.boltConfig;
            let trackCallbacks = settings.trackCallbacks;

            // On multiple checkout open/close actions the success event remains registered
            // resulting in making the success call multiple times. This variable stores
            // the last request to be aborted before new one is sent.
            let save_request;

            let callbacks = {

                close: function () {
                    trackCallbacks.onClose();

                    if (callbacks.success_url) {
                        // redirect on success order save
                        location.href = callbacks.success_url;
                    }
                },

                onCheckoutStart: function() {
                    trackCallbacks.onCheckoutStart();
                },

                onShippingDetailsComplete: function(address) {
                    trackCallbacks.onShippingDetailsComplete(address);
                },

                onShippingOptionsComplete: function() {
                    trackCallbacks.onShippingOptionsComplete();
                },

                onPaymentSubmit: function() {
                    trackCallbacks.onPaymentSubmit();
                },

                onNotify: function() {
                    trackCallbacks.onNotify();
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
                        customerData.invalidate(['bolthints']);
                        try {
                            if (typeof data !== 'undefined') {
                                callbacks.success_url = data.success_url;
                            }
                            trackCallbacks.onSuccess(data);
                        } finally {
                            callback();
                        }
                    };

                    if (settings.is_pre_auth) {
                        processSuccess();
                        return;
                    }

                    // abort previously sent save order request.
                    if (save_request) save_request.abort();
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
                                showBoltErrorMessage('', data.reference);
                                // pretend order creation was success...
                                // we need to call this; otherwise bolt modal show infinte spinner.
                                callback();
                            }
                            return;
                        }
                        processSuccess(data);
                    };
                    // ajax call to the update order transaction data endpoint.
                    // passes the bolt transaction reference
                    save_request = $.post(settings.save_order_url, parameters)
                        .done(onSuccess);
                },

                check: function () {
                    if (isExtendWarrantyModuleEnabled) {
                        // Extend_Warranty module collect current product plans
                        extendWarrantyCollectPlans();
                    }

                    let showBoltSSOPopup = function() {
                        // set a cookie for auto opening Bolt checkout after login
                        $('#bolt-sso-popup .bolt-account-sso [data-tid="shopperDashboardButton"]').click();
                    }

                    let showMagentoAuthenticationPopup = function () {
                        authenticationPopup.showModal();
                    }

                    /**
                     * On Bolt button click check if guest checkout is allowed.
                     * Display login popup to guest customers if it is not. The
                     * Magento customerData and authenticationPopup objects are
                     * used.
                     */
                    // check if login is required
                    if ( (!customer || !customer() || !customer().firstname) && !isGuestCheckoutAllowed) {
                        // if authentication is required for checkout set a cookie
                        // for auto opening Bolt checkout after login
                        if (window.boltConfig.is_sso_enabled) {
                            showBoltSSOPopup()
                        } else {
                            showMagentoAuthenticationPopup();
                        }
                        return false;
                    }

                    // wait for validation module init
                    try {
                        $('#product_addtocart_form').validation('isValid');
                    } catch (e) {
                        return false;
                    }

                    // validate form and show error messages
                    if ( ! $('#product_addtocart_form').validate().form()) {
                        return false;
                    }

                    if (isBoltOrderCachingEnabled) {
                        //if order caching is enabled cart request must contain a valid form key
                        try {
                            let options = cart['items'][0]['options'];
                            if (!JSON.parse(options)['form_key']) {
                                return false;
                            }
                        }  catch (e) {
                            return false;
                        }
                    }
                    return true;
                },

                onEmailEnter: function(email) {
                    trackCallbacks.onEmailEnter(email);
                    if (callbacks.email !== email) {
                        $.post(settings.save_email_url, 'email='+encodeURIComponent(email));
                        callbacks.email = email;
                    }
                }
            };

            let getQty = function() {
                let quantity = Number($('#qty').val());
                return quantity > 0 ? quantity : 1;
            };

            let getGroupedProductChildQty = function(productId) {
                return Number($('input[name="super_group['+productId+']').val());
            };

            let hints = null;
            if (isSaveHintsInSections) {
                let hintsSection = customerData.get('bolthints');
                hints = hintsSection.data !== undefined ? hintsSection.data : {};
                customerData.get('bolthints').subscribe(function (newValue) {
                    let newHints = newValue.data !== undefined ? newValue.data : {};
                    if (JSON.stringify(newHints) !== JSON.stringify(hints)) {
                        hints = newHints;
                        setupProductPage();
                    }
                });
            } else {
                hints = new Promise(function (resolve, reject) {
                    $.get(settings.get_hints_url)
                        .done(function (data) {
                            resolve(data.hints);
                        })
                        .fail(function () {
                            resolve({prefill: {}});
                        })
                        .always(function () {
                            // dispatch event when required data is ready
                            $(document).trigger('ppc-hints-set');
                        });
                });
            }

            let getItemsData = function (additionalData) {
                let options = {};
                let compositeAttributes = ['super_attribute', 'options', 'bundle_option', 'bundle_option_qty'];
                let checkboxAttributes = ['links'];
                let formData = $("#product_addtocart_form").serializeArray();
                $.each( formData, function( key, input ) {
                    let name = input.name, value = input.value;
                    let matchResult;
                    for (let index in compositeAttributes) {
                        if (!compositeAttributes.hasOwnProperty(index)) {
                            continue;
                        }
                        let compositeAttribute = compositeAttributes[index];
                        if (matchResult = name.match(compositeAttribute+'\\[(\\d+)\\]')) {
                            if (!(compositeAttribute in options)) {
                                options[compositeAttribute] = {};
                            }
                            if (matchResult[1] in options[compositeAttribute]
                                && options[compositeAttribute][matchResult[1]]) {
                                options[compositeAttribute][matchResult[1]] += ',' + value;
                            } else {
                                options[compositeAttribute][matchResult[1]] = value;
                            }
                            return;
                        }
                    }
                    for (let index in checkboxAttributes) {
                        if (!checkboxAttributes.hasOwnProperty(index)) {
                            continue;
                        }
                        let checkboxAttribute = checkboxAttributes[index];
                        if (name === checkboxAttribute+'[]') {
                            if (!(checkboxAttribute in options)) {
                                options[checkboxAttribute] = [];
                            }
                            options[checkboxAttribute].push(value);
                            return;
                        }
                    }

                    options[name] = value;
                });

                if (isExtendWarrantyModuleEnabled) {
                    // Extend_Warranty 3-th party module support: add collected plans as additional product options
                    if (additionalData && additionalData.extendWarrantyPlans) {
                        options['extend_warranty_plans'] = additionalData.extendWarrantyPlans;
                    }
                }

                options['storeId'] = storeId;
                let formKey = $.mage.cookies.get('form_key');
                if (!formKey && $('[name="form_key"]').length) {
                    formKey = $('[name="form_key"]').val();
                }
                options['form_key'] = formKey;
                let items = [];
                if (isGrouped) {
                    childGroupedProducts.forEach((childProduct) => {
                        let childProductQty = getGroupedProductChildQty(childProduct.id);
                        if (childProductQty > 0) {
                            items.push({
                                reference: childProduct.id,
                                price: childProduct.price,
                                name: childProduct.name,
                                quantity: childProductQty,
                                options: JSON.stringify(options),
                                type: childProduct.productType
                            });
                        }
                    });
                } else {
                    items.push({
                        reference: productId,
                        price: itemPrice,
                        name: productName,
                        quantity: getQty(),
                        options: JSON.stringify(options),
                        type: productType
                    });
                }

                return items;
            };


            let setupProductPage = function (additionalData) {
                cart = {
                    currency: currency,
                    items: getItemsData(additionalData)
                };
                // if connect.js is not loaded postpone until it is
                whenDefined(window, 'BoltCheckout', function(){
                    BoltCheckout.configureProductCheckout(cart, hints, callbacks);
                }, 'configureProductCheckout');
            };

            if (isExtendWarrantyModuleEnabled) {
                // Extend_Warranty 3-th party module support
                // collecting selected warranty plans and add as additional product options in request
                let extendWarrantyCollectPlans = function () {
                    // check if Extend_Warranty global variable = "Extend" is defined
                    if (window.Extend === undefined || window.Extend.buttons === undefined) {
                        return;
                    }
                    let extendWarrantyPlanItems = [];
                    if (isGrouped) {
                        let childProducts = new Map();
                        // collect child product id's and sku's
                        childGroupedProducts.forEach((childProduct) => {
                            childProducts.set(childProduct.id, {sku: childProduct.sku});
                        });

                        childProducts.forEach((product, productId) => {
                            // get warranty plan configuration instance for current child product
                            // the "#warranty-offers" part is hardcoded in module
                            let extendWarrantyInstance = Extend.buttons.instance('#warranty-offers-' + productId);
                            // if plan is exist for child product
                            if (extendWarrantyInstance) {
                                // get current selected warranty plan for child product
                                let extendWarrantyPlan = extendWarrantyInstance.getPlanSelection();
                                if (extendWarrantyPlan) {
                                    // fill product sku to warranty plan. The key should be product it is module restriction
                                    extendWarrantyPlan.product = product.sku;
                                    // push child product warranty plan selected to the full list of selected plans
                                    extendWarrantyPlanItems.push(extendWarrantyPlan);
                                }
                            }
                        });
                    } else {
                        // get warranty plan configuration instance for current non-group product
                        // the "#warranty-offers" part is hardcoded in module
                        let extendWarrantyInstance = Extend.buttons.instance('#warranty-offers-' + productId);
                        if (extendWarrantyInstance) {
                            // get warranty plan selected
                            let extendWarrantyPlan = extendWarrantyInstance.getPlanSelection();
                            if (extendWarrantyPlan) {
                                // fill product sku to warranty plan, The key should be product it is module restriction
                                extendWarrantyPlan.product = productSku;
                                extendWarrantyPlanItems.push(extendWarrantyPlan);
                            }
                        }
                    }
                    // if we collected at least one warranty plan call bolt product page reconfiguration
                    if (extendWarrantyPlanItems.length) {
                        setupProductPage({extendWarrantyPlans: extendWarrantyPlanItems});
                    }
                }
            }

            setupProductPage();

            $('#qty').on('change', setupProductPage);
            $('.table-wrapper.grouped .input-text.qty').on('change', setupProductPage);

            // Object holding the base item price
            // and price deltas for every custom option selected.
            // The properties are added / updated on custom
            // options change in updatePrice event listened below.
            // The final item price is the sum of all prices stored here.
            let itemPrices = {
                basePrice: basePrice
        };

            /**
             * Sum the values of all (numeric) object properties
             * @param obj
             * @returns {number}
             */
            let sum = function (obj) {
                return Object.keys(obj).reduce(
                    function(sum,key) {
                        return sum+(parseFloat(obj[key])||0);
                    }, 0
                );
            };

            $(document).on( 'updatePrice', function (event, data) {
                if (data) {
                    // the name of the property that holds the price data
                    // varies depending on the product type, eg. prices, options
                    for (let index in data) {
                        if (!data.hasOwnProperty(index) || !data[index].hasOwnProperty('finalPrice')) {
                            continue;
                        }
                        let finalPrice = data[index].finalPrice;
                        itemPrices[index] =
                            finalPrice && typeof finalPrice === 'object' && finalPrice.amount ? finalPrice.amount : 0;
                        itemPrice = sum(itemPrices);
                    }
                    setupProductPage();
                }
            });

            /**
             * Wait for the form_key cookie and reconfigure BoltCheckout.
             * @see mage.formKey._create
             * @see https://api.jqueryui.com/jQuery.widget/ (widgetEventPrefix)
             */
            $('body').on('formkeycreate formkey:create', setupProductPage);
            // wait for add to cart validation form initialization
            $(document).on('validationcreate', '#product_addtocart_form', function () {
                $(".bolt-product-checkout-button-disabled").removeClass("bolt-product-checkout-button-disabled")
            });

            // If the form has the novalidate attribute, remove the disabled class. This means that form validation is already initialized,
            // and we don't need to wait for the validationcreate event to be triggered, as it might have already been triggered before this script loaded.
            if ($('#product_addtocart_form').is('[novalidate]')) {
                $(".bolt-product-checkout-button-disabled").removeClass("bolt-product-checkout-button-disabled")
            }
        },
    });

    return $.bolt.pdpButton;
});

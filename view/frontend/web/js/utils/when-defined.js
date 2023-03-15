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

define([], function () {
    /**
     * Map of watched objects to maps of their respective watched properties to configured callbacks
     * @type {Map<Object, Map<string, Map<string, function>>>}
     */
    var whenDefinedCallbacks = new Map();

    /**
     * Executes provided callback when a property gets defined on provided object.
     * The most common use is waiting for a variable to be defined by an external library
     * using {@see window} as {@see object}
     *
     * @param {Object} object to check for property definition
     * @param {number|string} property that is expected to be defined on {@see object}
     * @param {Function} callback function to be called when {@see property} gets defined on {@see object}
     * @param {null} key deprecated parameter used for setting multiple callbacks per property
     */
    function whenDefined (object, property, callback, key) {
        if (object.hasOwnProperty(property) && typeof object[property] !== 'undefined') {
            callback();
            return;
        }
        var overloadedPropertyName = '_' + property;
        if (!whenDefinedCallbacks.has(object)) {
            whenDefinedCallbacks.set(object, new Map());
        }
        if (!whenDefinedCallbacks.get(object).has(property)) {
            whenDefinedCallbacks.get(object).set(property, new Map());
            Object.defineProperty(object, property, {
                configurable: true,
                enumerable: true,
                writeable: true,
                /**
                 * Retrieves the watched property from overloaded index
                 *
                 * @returns {*} {@see property} value on {@see object}
                 */
                get: function () {
                    return this[overloadedPropertyName];
                },
                /**
                 * Sets the overloaded property index with the provided value then executes configured callbacks
                 *
                 * @param {mixed} value
                 */
                set: function (value) {
                    this[overloadedPropertyName] = value;
                    for (var propertyCallback of whenDefinedCallbacks.get(object).get(property).values()) {
                        propertyCallback();
                    }
                }
            });
        }
        if (typeof key == 'undefined') {
            key = whenDefinedCallbacks.get(object).get(property).size;
        }
        whenDefinedCallbacks.get(object).get(property).set(key, callback);
    }

    return whenDefined;
});

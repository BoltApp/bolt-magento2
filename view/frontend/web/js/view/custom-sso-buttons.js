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
    'Bolt_Boltpay/js/utils/when-defined',
    'domReady!',
    'jquery-ui-modules/widget'
], function ($, whenDefined) {
    'use strict';

    $.widget('bolt.customSsoButtons', {
        options: {
            selectors: '',
            boltSSOCustomClass: 'bolt-sso-custom',
        },

        /** @inheritdoc */
        _create: function () {
            this._init();
        },

        _init: function () {
            const self = this;
            whenDefined(window, 'BoltAccount', function () {
                self.initializeBoltSSOCustomLinks();
                /**
                 * Handles buttons matching configured selectors that weren't converted to Custom Bolt SSO buttons
                 */
                $(document).on('click', Object.keys(self.options.selectors).join(','), function (e) {
                    if ($(e.currentTarget).hasClass(self.options.boltSSOCustomClass)) {
                        return;
                    }
                    var redirect;
                    for (var selector in self.options.selectors) {
                        if (e.currentTarget.matches(selector)) {
                            var selectorConfig = self.options.selectors[selector];
                            if (selectorConfig.hasOwnProperty('redirect') && selectorConfig.redirect) {
                                redirect = selectorConfig.redirect;
                            }
                            if (selectorConfig.hasOwnProperty('logout') && selectorConfig.logout) {
                                window.BoltAccount.logoutButtonClicked(redirect);
                            } else {
                                window.BoltAccount.loginButtonClicked(redirect);
                            }
                            return false; // event stop propagation and prevent default
                        }
                    }
                });
            });
        },

        /**
         * Converts all present elements matching {@see loginSelectors} and {@see logoutSelectors}
         * to custom Bolt SSO buttons
         */
        initializeBoltSSOCustomLinks: function () {
            var selectorConfig, link, linkIndex, links, selector;
            for (selector in this.options.selectors) {
                links = document.querySelectorAll(selector);
                for (linkIndex = 0; linkIndex < links.length; linkIndex++) {
                    link = links[linkIndex];
                    selectorConfig = this.options.selectors[selector];
                    if (selectorConfig.hasOwnProperty('logout') && selectorConfig.logout) {
                        link.setAttribute('data-logged-in', 'true');
                    }
                    if (selectorConfig.hasOwnProperty('redirect') && selectorConfig.redirect) {
                        link.setAttribute('data-destination-on-success', selectorConfig.redirect);
                    }

                    link.removeAttribute('href');
                    link.removeAttribute('data-post');

                    // add {@see boltSSOCustomClass} only once
                    link.setAttribute(
                        'class',
                        (link.getAttribute('class') ?? '').replace(this.options.boltSSOCustomClass, '') + ' ' + this.options.boltSSOCustomClass
                    );
                    link.setAttribute('style', 'cursor:pointer;');
                }
            }
            window.BoltAccount.injectButtons();
        }
    });

    return $.bolt.customSsoButtons;
});

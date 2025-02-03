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
    'matchMedia',
    'jquery-ui-modules/widget'
], function ($, mediaCheck) {
    'use strict';

    $.widget('bolt.account', {
        options: {
            accountJsUrl: '',
            checkoutKey: '',
        },

        /** @inheritdoc */
        _create: function () {
            this._init();
        },

        _init: function () {
            const self = this;
            mediaCheck({
                media: '(max-width: 768px)',
                entry: function () {
                    // When magento constructs mobile menu it copies elements instead of moving
                    // We need to wait for this coping, remove first bolt-account-login div
                    // And only ofter that insert account.js script
                    const ATTEMPT_LIMIT = 3;
                    let attempts = 0;
                    let timerId = setTimeout(function boltAccountLookup() {
                        let account_div = $("div.bolt-account-login");
                        if (account_div.length === 2) {
                            account_div.eq(0).remove();
                            self.insertAccountScript();
                        } else {
                            if(attempts === ATTEMPT_LIMIT){
                                return self.insertAccountScript();
                            }
                            attempts ++;
                            timerId = setTimeout(boltAccountLookup, 1000)
                        }
                    }, 1000);
                },
                exit: function () {
                    // For desktop we are ready to insert script
                    self.insertAccountScript();
                }
            });
        },
        insertAccountScript: function () {
            let scriptTag = document.getElementById('bolt-account');
            if (scriptTag) {
                return;
            }
            scriptTag = document.createElement('script');
            scriptTag.setAttribute('type', 'text/javascript');
            scriptTag.setAttribute('async', '');
            scriptTag.setAttribute('src', this.options.accountJsUrl);
            scriptTag.setAttribute('id', 'bolt-account');
            scriptTag.setAttribute('data-publishable-key', this.options.checkoutKey);
            document.head.appendChild(scriptTag);
        }
    });

    return $.bolt.account;
});

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
    'Magento_Ui/js/modal/modal',
    'mage/translate',
    'jquery-ui-modules/widget'
], function ($, modal, $t) {
    'use strict';

    $.widget('bolt.popup', {
        options: {
            popupSelector: '#bolt-modal',
        },

        /** @inheritdoc */
        _create: function () {
            var options = {
                type: 'popup',
                responsive: true,
                innerScroll: true,
                modalClass: 'bolt-error-modal',
                title: '',
                buttons: [{
                    text: $t('Close'),
                    class: 'bolt-error-close-button',
                    click: function () {
                        this.closeModal();
                    }
                }]
            };
            modal(options, $(this.element));
        },
    });

    return $.bolt.popup;
});

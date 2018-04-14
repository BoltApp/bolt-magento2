/**
 * Copyright © 2013-2017 Bolt, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
/**
 * Bolt payment method register renderer
 */
define(
    [
        'uiComponent',
        'Magento_Checkout/js/model/payment/renderer-list'
    ],
    function (
        Component,
        rendererList
    ) {
        'use strict';
        rendererList.push(
            {
                type: 'boltpay',
                component: 'Bolt_Boltpay/js/view/payment/method-renderer/boltpay'
            }
        );
        /** Add view logic here if needed */
        return Component.extend({});
    }
);

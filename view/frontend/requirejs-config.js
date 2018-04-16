/**
 * requirejs configuration.
 * Replaces page-cache javascript.
 */
var config = {
    map: {
        '*': {
            pageCache: 'Bolt_Boltpay/js/page-cache',
            'Magento_PageCache/js/page-cache': 'Bolt_Boltpay/js/page-cache'
        }
    }
};


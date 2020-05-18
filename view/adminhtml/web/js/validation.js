require([
        'jquery',
        'mage/translate',
        'jquery/validate'],
    function ($) {
        $.validator.addMethod(
            'validate-color', function (v) {
                return v == "" || /^#[0-9A-F]{6}$/i.test(v);
            }, $.mage.__('Please enter hex color code like "#00cccc"')
        );

        $.validator.addMethod(
            'validate-custom-url-api', function (v) {
                var $length = v.length;
                var last7Characters = v.substr($length - 7);
                var last8Characters = v.substr($length - 8);
                return (v == "" || last7Characters ==='bolt.me' || last8Characters === 'bolt.me/');
            }, $.mage.__('Please enter custom url ends with "bolt.me"')
        );
    }
);
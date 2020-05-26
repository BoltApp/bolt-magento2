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
                return (v == "" || /^https?:\/\/([a-zA-Z0-9]+\.)?bolt.me\/?$/.test(v));
            }, $.mage.__('Please enter custom url like "https://test.bolt.me"')
        );
    }
);
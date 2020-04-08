require([
        'jquery',
        'mage/translate',
        'jquery/validate'],
    function($){
        $.validator.addMethod(
            'validate-color', function (v) {
                return v=="" || /^#[0-9A-F]{6}$/i.test(v);
            }, $.mage.__('Please enter hex color code like "#00cccc"'));
    }
);
/* Track checkout funnel transition in magento's default checkout */

// TODO: support logged in user

var currentStep = '';

function waitFor(condition, doWhenReady, pollIntervalMillis) {
    var defaultPollIntervalMillis = 100;
    var maxRetryCount = 30;
    var checkCondition = function (retryCount) {
        if (condition()) {
            doWhenReady();
        } else if (retryCount < maxRetryCount) {
            setTimeout(function(){checkCondition(retryCount + 1)}, pollIntervalMillis || defaultPollIntervalMillis);
        }
    };
    checkCondition(0);
}

function trackFunnel(step) {
    if (step === currentStep) {
        // prevent duplicate tracking
        return;
    }
    BoltTrack.recordEvent(step);
    currentStep = step;
}

function setupListnerForShippingForm() {
    var requiredFields = jQuery("#shipping-new-address-form .field._required");
    var inputElements = [];
    requiredFields.each(function(_, e) {
        $elm = jQuery(e).find("input,select");
        inputElements.push($elm);
        $elm.change(function() {
           var complete = true;
           for (var i = 0; i < inputElements.length; i++) {
               if (!inputElements[i].val() && inputElements[i].attr("name") !== "region") {
                   complete = false;
                   break;
               }
           }
           if (complete) {
               trackFunnel("onShippingAddressComplete");
           }
        });
    });
}

function init() {
    trackFunnel("onCheckoutStart");

    waitFor(
        // wait for shipping form to be fully rendered, ie., we have 9 required fields in the page.
        function() { return jQuery("#shipping-new-address-form .field._required input,select").length >= 9; },
        setupListnerForShippingForm
    );

    window.addEventListener("hashchange", function() {
        if (location.hash === "#payment") {
            trackFunnel("onShippingOptionsComplete");
        }
    });

    // on method can detect checkout button added after this query is executed.
    jQuery(".page-wrapper").on("click", ".action.primary.checkout", function() {
        trackFunnel("onPaymentSubmit")
        return false;
    });
}

require(["jquery"], init);

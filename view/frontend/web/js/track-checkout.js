/* Track checkout funnel transition in magento's default checkout */

var currentStep = '';

function trackFunnel(step) {
    if (step === currentStep) {
        // prevent duplicate tracking
        return;
    }
    currentStep = step;
    BoltTrack.recordEvent(step);
}

trackFunnel("onCheckoutStart");

// TODO: track onShippingAddressComplete when user complete filling in addresses
// TODO: track onPaymentSubmit when user clicks pay

window.addEventListener("hashchange", function() {
   if (location.hash === "#payment") {
       trackFunnel("onShippingOptionsComplete");
   }
});

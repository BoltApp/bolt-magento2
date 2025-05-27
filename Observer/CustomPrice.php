namespace Bolt\Boltpay\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Framework\Webapi\Rest\Request as RestRequest;
use Bolt\Boltpay\Model\RestApiRequestValidator;

class CustomPrice implements ObserverInterface
{
    /** @var RequestInterface */
    private $request;

    /** @var PriceCurrencyInterface */
    private $priceCurrency;

    /** @var RestRequest */
    private $restRequest;

    /** @var RestApiRequestValidator */
    private $restApiRequestValidator;

    public function __construct(
        RequestInterface $request,
        PriceCurrencyInterface $priceCurrency,
        RestRequest $restRequest,
        RestApiRequestValidator $restApiRequestValidator
    ) {
        $this->request = $request;
        $this->priceCurrency = $priceCurrency;
        $this->restRequest = $restRequest;
        $this->restApiRequestValidator = $restApiRequestValidator;
    }
    /**
     * Set custom price for Bolt item in cart
     *
     * @param Observer $observer
     */
    public function execute(Observer $observer)
    {
        // Validate that this is a legitimate Bolt request
        if (!$this->restApiRequestValidator->isValidBoltRequest($this->restRequest)) {
            return;
        }
        $quote = $observer->getEvent()->getQuote();
        if (!$quote || !$quote->getId()) {
            return;
        }

        // Attempt to read fee from parsed params
        $body = $this->request->getContent();
        $data = json_decode($body, true) ?: [];
        $sentFee = 0; // by default
        if (isset($data['cart_item']['sku']) && $data['cart_item']['sku'] == 'BOLT_PRIVATE_CHECKOUT' &&
            isset($data['cart_item']['extension_attributes']['bolt_privacy_fee'])) {
            $sentFee = (float) $data['cart_item']['extension_attributes']['bolt_privacy_fee'];
        }
        $subtotal = 0;
        $boltItem = null;

        foreach ($quote->getAllItems() as $item) {
            if ($item->getSku() === 'BOLT_PRIVATE_CHECKOUT') {
                $boltItem = $item;
                continue;
            }
            $subtotal += $item->getRowTotal();
        }

        if ($boltItem && $subtotal > 0) {
            $customPrice = ($sentFee > 0) ? $sentFee : $this->priceCurrency->round($subtotal * 0.01);
            $boltItem->setCustomPrice($customPrice);
            $boltItem->setOriginalCustomPrice($customPrice);
            $boltItem->getProduct()->setIsSuperMode(true);
        }
    }
}

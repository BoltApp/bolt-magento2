<?php

namespace Bolt\Boltpay\Block;

use Bolt\Boltpay\Helper\Config;
use Magento\Backend\Model\Session\Quote as BackendQuote;
use Magento\Payment\Block\Form as PaymentForm;
use Magento\Framework\View\Element\Template\Context;

/**
 * Class Form
 */
class Form extends PaymentForm
{
    private $_quote;

    /**
     * Template
     *
     * @var string
     */
    protected $_template = 'Bolt_Boltpay::boltpay/button.phtml';

    /**
     * @var Config
     */
    private $configHelper;

    /**
     * @param Context      $context
     * @param Config       $configHelper
     * @param BackendQuote $backendQuote
     * @param array        $data
     */
    public function __construct(
        Context $context,
        Config $configHelper,
        BackendQuote $backendQuote,
        array $data = []
    ) {
        parent::__construct($context, $data);

        $this->configHelper = $configHelper;
        $this->_quote = $backendQuote->getQuote();
    }

    public function getQuoteData()
    {
        return $this->_quote;
    }

    /**
     * Return billing address information formatted to be used for magento order creation.
     *
     * Example return value:
     * {"customerAddressId":"404","countryId":"US","regionId":"12","regionCode":"CA","region":"California",
     * "customerId":"202","street":["adasdasd 1234"],"telephone":"314-123-4125","postcode":"90014",
     * "city":"Los Angeles","firstname":"YevhenTest","lastname":"BoltDev",
     * "extensionAttributes":{"checkoutFields":{}},"saveInAddressBook":null}
     *
     * @param bool $needJsonEncode
     *
     * @return string
     */
    public function getBillingAddress($needJsonEncode = true)
    {
        $quote = $this->getQuoteData();
        /** @var \Magento\Quote\Model\Quote\Address $billingAddress */
        $billingAddress = $quote->getBillingAddress();

        $streetAddressLines = [];
        $streetAddressLines[] = $billingAddress->getStreetLine(1);
        if ($billingAddress->getStreetLine(2)) {
            $streetAddressLines[] = $billingAddress->getStreetLine(2);
        }

        $result = [
            'customerAddressId' => $billingAddress->getId(),
            'countryId'         => $billingAddress->getCountryId(),
            'regionId'          => $billingAddress->getRegionId(),
            'regionCode'        => $billingAddress->getRegionCode(),
            'region'            => $billingAddress->getRegion(),
            'customerId'        => $billingAddress->getCustomerId(),
            'street'            => $streetAddressLines,
            'telephone'         => $billingAddress->getTelephone(),
            'postcode'          => $billingAddress->getPostcode(),
            'city'              => $billingAddress->getCity(),
            'firstname'         => $billingAddress->getFirstname(),
            'lastname'          => $billingAddress->getLastname(),
            'extensionAttributes'   => ['checkoutFields' => []],
            'saveInAddressBook'     => $billingAddress->getSaveInAddressBook()
        ];

        return ($needJsonEncode) ? json_encode($result) : $result;
    }

    public function getPlaceOrderPayload()
    {
        $quote = $this->getQuoteData();

        $result = [
            'cartId' => $quote->getId(),
            'billingAddress' => $this->getBillingAddress(false),
            'paymentMethod' => [
                'method' => $quote->getPayment()->getMethod(),
                'po_number' => null,
                'additional_data' => null
            ],
            'email' => $quote->getCustomerEmail()
        ];

        return json_encode($result);
    }

    /**
     * @return string
     */
    public function getJavascriptSuccess()
    {
        return $this->configHelper->getJavascriptSuccess();
    }

    /**
     * Get Replace Button Selectors.
     *
     * @return string
     */
    public function getGlobalCSS()
    {
        return $this->configHelper->getGlobalCSS();
    }

    /**
     * Get Javascript page settings.
     *
     * @return string
     */
    public function getSettings()
    {
        return json_encode([
            'connect_url'                   => $this->getConnectJsUrl(),
            'publishable_key_back_office'   => $this->getBackOfficeKey(),
            'create_order_url'              => $this->getUrl(Config::CREATE_ORDER_ACTION),
            'save_order_url'                => $this->getUrl(Config::SAVE_ORDER_ACTION),
        ]);
    }

    /**
     * @return string
     */
    public function getConnectJsUrl()
    {
        // Get cdn url
        $cdnUrl = $this->configHelper->getCdnUrl();

        return $cdnUrl . '/connect.js';
    }

    /**
     * Get back office key
     *
     * @return  string
     */
    public function getBackOfficeKey()
    {
        return $this->configHelper->getPublishableKeyBackOffice();
    }

    /**
     * Get Bolt Payment module active state.
     * @return bool
     */
    public function isBoltEnabled()
    {
        return $this->configHelper->isActive();
    }
}

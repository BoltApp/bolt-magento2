<?php

namespace Bolt\Boltpay\Block;

use Bolt\Boltpay\Helper\Config;
use Magento\Framework\Session\SessionManager as MagentoQuote;
use Magento\Payment\Block\Form as PaymentForm;
use Magento\Framework\View\Element\Template\Context;

/**
 * Class Form
 */
class Form extends PaymentForm
{
    /**
     * @var \Magento\Backend\Model\Session\Quote
     */
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
     * @param MagentoQuote $magentoQuote
     * @param array        $data
     */
    public function __construct(
        Context $context,
        Config $configHelper,
        MagentoQuote $magentoQuote,
        array $data = []
    ) {
        parent::__construct($context, $data);

        $this->configHelper = $configHelper;
        $this->_quote = $magentoQuote->getQuote();
    }

    /**
     * @return \Magento\Backend\Model\Session\Quote
     */
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

    /**
     * @return false|string
     */
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
}

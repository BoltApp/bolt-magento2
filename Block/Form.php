<?php
/**
 * Bolt magento2 plugin
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 *
 * @category   Bolt
 * @package    Bolt_Boltpay
 * @copyright  Copyright (c) 2017-2023 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Bolt\Boltpay\Block;

use Bolt\Boltpay\Helper\Config;
use Magento\Framework\Session\SessionManager as MagentoQuote;
use Magento\Payment\Block\Form as PaymentForm;
use Magento\Framework\View\Element\Template\Context;
use Bolt\Boltpay\Model\ResourceModel\CustomerCreditCard\CollectionFactory as CustomerCreditCardCollectionFactory;
use Bolt\Boltpay\Helper\FeatureSwitch\Decider;
use Magento\Quote\Model\Quote;

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
     * @var CustomerCreditCardCollectionFactory
     */
    private $customerCreditCardCollectionFactory;

    /**
     * @var Decider
     */
    private $featureSwitch;

    /**
     * Form constructor.
     * @param Context $context
     * @param Config $configHelper
     * @param MagentoQuote $magentoQuote
     * @param CustomerCreditCardCollectionFactory $customerCreditCardCollectionFactory
     * @param Decider $featureSwitch
     * @param array $data
     */
    public function __construct(
        Context $context,
        Config $configHelper,
        MagentoQuote $magentoQuote,
        CustomerCreditCardCollectionFactory $customerCreditCardCollectionFactory,
        Decider $featureSwitch,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->configHelper = $configHelper;
        /** @var \Magento\Backend\Model\Session|\Magento\Checkout\Model\Session $magentoQuote */
        $this->_quote = $magentoQuote->getQuote();
        $this->customerCreditCardCollectionFactory = $customerCreditCardCollectionFactory;
        $this->featureSwitch = $featureSwitch;
    }

    /**
     * @return \Magento\Backend\Model\Session\Quote|\Magento\Quote\Api\Data\CartInterface|Quote
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
        /** @var Quote $quote */
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
     * @return array|bool
     */
    public function getCustomerCreditCardInfo()
    {
        /** @var \Magento\Quote\Model\Quote\Address $billingAddress */
        $billingAddress = $this->getQuoteData()->getBillingAddress();
        if ($customerId = $billingAddress->getCustomerId()) {
            /** @var \Bolt\Boltpay\Model\ResourceModel\CustomerCreditCard\Collection $customerCreditCardCollection */
            $customerCreditCardCollection = $this->customerCreditCardCollectionFactory->create();
            return $customerCreditCardCollection->getCreditCardInfosByCustomerId($customerId);
        }

        return false;
    }

    /**
     * @return bool
     */
    public function isAdminReorderForLoggedInCustomerFeatureEnabled()
    {
        return $this->featureSwitch->isAdminReorderForLoggedInCustomerFeatureEnabled();
    }

    /**
     * @return bool
     */
    public function isPayByLinkEnabled()
    {
        return $this->featureSwitch->isPayByLinkEnabled();
    }

    /**
     * @return string
     */
    public function getPublishableKeyBackOffice()
    {
        return $this->configHelper->getPublishableKeyBackOffice($this->getQuoteData()->getStoreId());
    }

    /**
     * @return string
     */
    public function getPublishableKeyPaymentOnly()
    {
        return $this->configHelper->getPublishableKeyPayment($this->getQuoteData()->getStoreId());
    }

    /**
     * @return string
     */
    public function getAdditionalCheckoutButtonAttributes()
    {
        return $this->configHelper->getAdditionalCheckoutButtonAttributes();
    }
}

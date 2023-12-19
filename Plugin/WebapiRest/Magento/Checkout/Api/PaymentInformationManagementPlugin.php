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

namespace Bolt\Boltpay\Plugin\WebapiRest\Magento\Checkout\Api;

use Bolt\Boltpay\Helper\Cart;
use Magento\Checkout\Api\PaymentInformationManagementInterface;
use \Bolt\Boltpay\Api\RouteInsuranceManagementInterface;
use Magento\Customer\Api\CustomerRepositoryInterface as CustomerRepository;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\Webapi\Rest\Request as WebApiRequest;

/**
 * Plugin for {@see \PaymentInformationManagementInterface}
 */
class PaymentInformationManagementPlugin
{
    /**
     * @var \Magento\Checkout\Model\Session
     */
    private $checkoutSession;

    /**
     * @var \Bolt\Boltpay\Helper\Cart
     */
    private $cartHelper;

    /**
     * @var \Magento\Quote\Model\MaskedQuoteIdToQuoteIdInterface
     */
    private $maskedQuoteIdToQuoteId;

    /**
     * @var \Magento\Framework\Module\Manager
     */
    private $moduleManager;

    /**
     * @var CustomerRepository
     */
    private $customerRepository;

    /**
     * @var CustomerSession
     */
    private $customerSession;

    /** @var \Magento\Backend\Model\Auth\Session $backendSession */
    protected $backendSession;
    
    /** @var WebApiRequest $webApiRequest */
    protected $webApiRequest;

    /**
     * @param \Magento\Checkout\Model\Session $checkoutSession
     * @param \Bolt\Boltpay\Helper\Cart $cartHelper
     * @param \Magento\Quote\Model\MaskedQuoteIdToQuoteIdInterface $maskedQuoteIdToQuoteId
     * @param \Magento\Framework\Module\Manager $moduleManager
     * @param CustomerRepository $customerRepository
     * @param CustomerSession $customerSession
     */
    public function __construct(
        \Magento\Checkout\Model\Session $checkoutSession,
        \Bolt\Boltpay\Helper\Cart $cartHelper,
        \Magento\Quote\Model\MaskedQuoteIdToQuoteIdInterface $maskedQuoteIdToQuoteId,
        \Magento\Framework\Module\Manager $moduleManager,
        CustomerRepository $customerRepository,
        CustomerSession $customerSession,
        \Magento\Backend\Model\Auth\Session $backendSession,
          WebApiRequest $webApiRequest
    ) {
        $this->checkoutSession = $checkoutSession;
        $this->cartHelper = $cartHelper;
        $this->maskedQuoteIdToQuoteId = $maskedQuoteIdToQuoteId;
        $this->moduleManager = $moduleManager;
        $this->customerRepository = $customerRepository;
        $this->customerSession = $customerSession;
        $this->backendSession = $backendSession;
        $this->webApiRequest = $webApiRequest;
    }

    public function beforeSavePaymentInformationAndPlaceOrder(
        PaymentInformationManagementInterface $subject,
        $cartId,
        \Magento\Quote\Api\Data\PaymentInterface $paymentMethod,
        \Magento\Quote\Api\Data\AddressInterface $billingAddress = null
    ) {
        if ($this->moduleManager->isEnabled(RouteInsuranceManagementInterface::ROUTE_MODULE_NAME))
        {
            $quote = $this->cartHelper->getQuoteById($cartId);
            $routeIsInsured = $quote->getRouteIsInsured();
            if ($routeIsInsured) {
                $this->checkoutSession->setQuoteId($cartId);
                $this->checkoutSession->setInsured($routeIsInsured);

                $customerID = (int)$quote->getData('customer_id');
                if ($customerID) {
                    $customer = $this->customerRepository->getById($customerID);
                    $this->customerSession->setCustomerDataAsLoggedIn($customer);
                }
            }
        }
        // verify if api request is from Bolt side
        if ($this->webApiRequest->getHeader('x-bolt-trace-id')) {
            // verify if Mageside_CustomShippingPrice module is enabled
            if ($this->moduleManager->isEnabled("Mageside_CustomShippingPrice")) {
                $quote = $this->cartHelper->getQuoteById($cartId);
                $checkoutType = $quote->getBoltCheckoutType();
                if ($checkoutType == Cart::BOLT_CHECKOUT_TYPE_BACKOFFICE) {
                    $this->backendSession->setIsBoltBackOffice(true);
                }
            }
        }

        return [$cartId, $paymentMethod, $billingAddress];
    }
}

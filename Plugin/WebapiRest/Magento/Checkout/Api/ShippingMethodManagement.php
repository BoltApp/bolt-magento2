<?php

namespace Bolt\Boltpay\Plugin\WebapiRest\Magento\Checkout\Api;

use Bolt\Boltpay\Helper\Cart;
use Magento\Framework\Webapi\Rest\Request as WebApiRequest;
use Magento\Quote\Api\Data\AddressInterface;

class ShippingMethodManagement
{
    /**
     * @var WebApiRequest
     */
    protected $webApiRequest;
    
    /**
     * @var \Magento\Quote\Api\CartRepositoryInterface
     */
    protected $quoteRepository;

    /** @var \Magento\Backend\Model\Auth\Session $backendSession */
    protected $backendSession;

    /** @var \Magento\Framework\Module\Manager $moduleManager */
    protected $moduleManager;

    /**
     * @param WebApiRequest $webApiRequest
     * @param \Magento\Checkout\Model\Session $checkoutSession
     * @param \Magento\Quote\Api\CartRepositoryInterface $quoteRepository
     * @param \Magento\Backend\Model\Auth\Session $backendSession
     */
    public function __construct(
        WebApiRequest $webApiRequest,
        \Magento\Quote\Api\CartRepositoryInterface $quoteRepository,
        \Magento\Backend\Model\Auth\Session $backendSession,
        \Magento\Framework\Module\Manager $moduleManager
    )
    {
        $this->webApiRequest = $webApiRequest;
        $this->quoteRepository = $quoteRepository;
        $this->backendSession = $backendSession;
        $this->moduleManager = $moduleManager;
    }

    /**
     * @param \Magento\Quote\Model\ShippingMethodManagement $subject
     * @param $cartId
     * @param AddressInterface $address
     * @return array
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function beforeEstimateByExtendedAddress(
        \Magento\Quote\Model\ShippingMethodManagement $subject,
                                                      $cartId,
        AddressInterface $address
    ) {
        // verify if api request is from Bolt side
        if ($this->webApiRequest->getHeader('x-bolt-trace-id')) {
            // verify if Mageside_CustomShippingPrice module is enabled
            if ($this->moduleManager->isEnabled("Mageside_CustomShippingPrice")) {
                $quote = $this->quoteRepository->getActive($cartId);
                $checkoutType = $quote->getBoltCheckoutType();
                if ($checkoutType == Cart::BOLT_CHECKOUT_TYPE_BACKOFFICE) {
                    $this->backendSession->setIsBoltBackOffice(true);
                }
            }
        }

        return [$cartId, $address];
    }
}

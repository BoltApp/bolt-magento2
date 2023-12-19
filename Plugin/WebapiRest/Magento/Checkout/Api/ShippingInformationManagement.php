<?php
namespace Bolt\Boltpay\Plugin\WebapiRest\Magento\Checkout\Api;

use Bolt\Boltpay\Helper\Cart;
use Magento\Framework\Webapi\Rest\Request as WebApiRequest;
use Magento\Quote\Api\CartRepositoryInterface;

class ShippingInformationManagement
{
    /**
     * @var WebApiRequest
     */
    protected $webApiRequest;

    /** @var \Magento\Checkout\Model\Session  */
    protected $backendSession;

    /** @var \Magento\Framework\Module\Manager $moduleManager */
    protected $moduleManager;
    
    /**
     * @var \Magento\Quote\Api\CartRepositoryInterface
     */
    protected $quoteRepository;

    /**
     * @param WebApiRequest $webApiRequest
     * @param \Magento\Checkout\Model\Session $checkoutSession
     */
    public function __construct(
        WebApiRequest $webApiRequest,
        CartRepositoryInterface $quoteRepository,
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
     * @param \Magento\Checkout\Model\ShippingInformationManagement $addressModel
     * @param $cartId
     * @param \Magento\Checkout\Api\Data\ShippingInformationInterface $addressInformation
     * @return array
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function beforeSaveAddressInformation(
        \Magento\Checkout\Model\ShippingInformationManagement $addressModel,
                                                              $cartId,
        \Magento\Checkout\Api\Data\ShippingInformationInterface $addressInformation
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
        
        return [$cartId, $addressInformation];
    }
}

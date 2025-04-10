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

namespace Bolt\Boltpay\Plugin\Magento\Checkout\Api;

use Magento\Checkout\Model\Session;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Area;
use Magento\Framework\App\State;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\Module\Manager;
use Magento\Framework\Session\SessionManagerInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\QuoteIdMaskFactory;
use Bolt\Boltpay\Helper\Bugsnag;
use Bolt\Boltpay\Model\Payment;
use Bolt\Boltpay\ThirdPartyModules\MageWorx\Pickup as MageWorxPickup;

/**
 * Plugin for {@see \Magento\Checkout\Api\PaymentInformationManagementPlugin}
 */
class PaymentInformationManagementPlugin
{
    /**
     * @var \Magento\Framework\App\State
     */
    private $appState;

    /**
     * @var \Magento\Quote\Api\CartRepositoryInterface
     */
    private $cartRepository;

    /**
     * @var \Magento\Checkout\Model\Session
     */
    private $checkoutSession;

    /**
     * @var \Magento\Customer\Model\Session
     */
    private $customerSession;

    /**
     * @var \Magento\Framework\Module\Manager
     */
    private $moduleManager;

    /**
     * @var \Magento\Quote\Model\QuoteIdMaskFactory
     */
    private $quoteIdMaskFactory;

    /**
     * @var \Magento\Framework\Session\SessionManagerInterface
     */
    private $sessionManager;

    /**
     * @var Bolt\Boltpay\Helper\Bugsnag
     */
    private $bugsnagHelper;

    /**
     * PaymentInformationManagementPlugin constructor.
     *
     * @param \Magento\Framework\App\State $appState
     * @param \Magento\Framework\Module\Manager $moduleManager
     * @param \Magento\Quote\Api\CartRepositoryInterface $cartRepository
     * @param \Magento\Checkout\Model\Session $checkoutSession
     * @param \Magento\Quote\Model\QuoteIdMaskFactory $quoteIdMaskFactory
     * @param \Magento\Framework\Session\SessionManagerInterface $sessionManager
     * @param Bolt\Boltpay\Helper\Bugsnag $bugsnagHelper
     * @param \Magento\Customer\Model\Session|null $customerSession
     *
     */
    public function __construct(
        State $appState,
        Manager $moduleManager,
        CartRepositoryInterface $cartRepository,
        Session $checkoutSession,
        QuoteIdMaskFactory $quoteIdMaskFactory,
        SessionManagerInterface $sessionManager,
        Bugsnag $bugsnagHelper,
        CustomerSession $customerSession = null,
    ) {
        $this->appState = $appState;
        $this->moduleManager = $moduleManager;
        $this->cartRepository = $cartRepository;
        $this->checkoutSession = $checkoutSession;
        $this->quoteIdMaskFactory = $quoteIdMaskFactory;
        $this->sessionManager  = $sessionManager;
        $this->bugsnagHelper = $bugsnagHelper;
        $this->customerSession = $customerSession ?? ObjectManager::getInstance()->get(CustomerSession::class);
    }

    /**
     * Set payment information for a specified cart.
     *
     * @param \Magento\Checkout\Api\PaymentInformationManagementInterface $subject
     * @param int $cartId
     * @param \Magento\Quote\Api\Data\PaymentInterface $paymentMethod
     * @param \Magento\Quote\Api\Data\AddressInterface|null $billingAddress
     *
     * @return array
     */
    public function beforeSavePaymentInformation(
        \Magento\Checkout\Api\PaymentInformationManagementInterface $subject,
        $cartId,
        \Magento\Quote\Api\Data\PaymentInterface $paymentMethod,
        ?\Magento\Quote\Api\Data\AddressInterface $billingAddress = null
    ) {
        try {
            if ($this->appState->getAreaCode() !== Area::AREA_WEBAPI_REST ||
                !$this->moduleManager->isEnabled(MageWorxPickup::MAGEWORX_PICKUP_MODULE_NAME) ||
                $paymentMethod->getMethod() != Payment::METHOD_CODE ||
                !$cartId || !filter_var($cartId, FILTER_VALIDATE_INT)
            ) {
                return [$cartId, $paymentMethod, $billingAddress];
            }

            $additionalData = $paymentMethod->getAdditionalData();
            if (!empty($additionalData) &&
                isset($additionalData['shippingMethod']) &&
                $additionalData['shippingMethod'] == MageWorxPickup::DELIVERY_METHOD
            ) {
                $quote = $this->cartRepository->getActive($cartId);
                $quoteCustomerGroupId = $quote->getCustomerGroupId();
                $customerGroupId = $this->customerSession->getCustomerGroupId();
                if ($quoteCustomerGroupId !== $customerGroupId) {
                    $this->checkoutSession->setMageWorxPickupQuoteId($cartId);
                } else {
                    $this->checkoutSession->setQuoteId($cartId);
                }
                $locationId = $quote->getMageworxPickupLocationId();
                if ($locationId) {
                    $this->sessionManager->setData('mageworx_pickup_location_id', $locationId);
                    $_COOKIE[MageWorxPickup::COOKIE_NAME] = $locationId;
                }
            }
        } catch (\Exception $e) {
            $this->bugsnagHelper->notifyException($e);
        }
        
        return [$cartId, $paymentMethod, $billingAddress];
    }
}

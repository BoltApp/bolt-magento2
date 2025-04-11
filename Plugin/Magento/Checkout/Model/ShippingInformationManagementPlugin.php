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

namespace Bolt\Boltpay\Plugin\Magento\Checkout\Model;

use Magento\Checkout\Api\Data\ShippingInformationInterface;
use Magento\Checkout\Api\ShippingInformationManagementInterface;
use Magento\Checkout\Model\ShippingInformationManagement;
use Magento\Checkout\Model\Session;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Area;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\App\State;
use Magento\Framework\Module\Manager;
use Magento\Framework\Session\SessionManagerInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Bolt\Boltpay\Helper\Bugsnag;
use Bolt\Boltpay\Model\EventsForThirdPartyModules;
use Bolt\Boltpay\ThirdPartyModules\MageWorx\Pickup as MageWorxPickup;

/**
 * Plugin for {@see \Magento\Checkout\Model\ShippingInformationManagement}
 */
class ShippingInformationManagementPlugin
{
    /**
     * @var \Magento\Framework\App\State
     */
    private $appState;

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
     * @var \Magento\Quote\Api\CartRepositoryInterface
     */
    private $quoteRepository;

    /**
     * @var \Magento\Framework\Session\SessionManagerInterface
     */
    private $sessionManager;

    /**
     * @var Bolt\Boltpay\Helper\Bugsnag
     */
    private $bugsnagHelper;

    /**
     * @var Bolt\Boltpay\Model\EventsForThirdPartyModules
     */
    private $eventsForThirdPartyModules;

    /**
     * ShippingInformationManagementPlugin constructor.
     *
     * @param \Magento\Framework\App\State $appState
     * @param \Magento\Framework\Module\Manager $moduleManager
     * @param \Magento\Quote\Api\CartRepositoryInterface $quoteRepository
     * @param \Magento\Framework\Session\SessionManagerInterface $sessionManager
     * @param \Magento\Checkout\Model\Session $checkoutSession
     * @param Bolt\Boltpay\Helper\Bugsnag $bugsnagHelper
     * @param Bolt\Boltpay\Model\EventsForThirdPartyModules $eventsForThirdPartyModules
     * @param \Magento\Customer\Model\Session|null $customerSession
     *
     */
    public function __construct(
        State $appState,
        Manager $moduleManager,
        CartRepositoryInterface $quoteRepository,
        SessionManagerInterface $sessionManager,
        Session $checkoutSession,
        Bugsnag $bugsnagHelper,
        EventsForThirdPartyModules $eventsForThirdPartyModules,
        CustomerSession $customerSession = null
    ) {
        $this->appState = $appState;
        $this->moduleManager = $moduleManager;
        $this->quoteRepository = $quoteRepository;
        $this->sessionManager  = $sessionManager;
        $this->checkoutSession = $checkoutSession;
        $this->bugsnagHelper = $bugsnagHelper;
        $this->eventsForThirdPartyModules = $eventsForThirdPartyModules;
        $this->customerSession = $customerSession ?? ObjectManager::getInstance()->get(CustomerSession::class);
    }

    /**
     *
     * @param ShippingInformationManagement $subject
     * @param int $cartId
     * @param ShippingInformationInterface $addressInformation
     *
     * @return array
     */
    public function beforeSaveAddressInformation(ShippingInformationManagement $subject, $cartId, ShippingInformationInterface $addressInformation)
    {
        try {
            if ($this->appState->getAreaCode() !== Area::AREA_WEBAPI_REST ||
                !$this->moduleManager->isEnabled(MageWorxPickup::MAGEWORX_PICKUP_MODULE_NAME) ||
                !$cartId || !filter_var($cartId, FILTER_VALIDATE_INT)
            ) {
                return [$cartId, $addressInformation];
            }

            $quote = $this->quoteRepository->getActive($cartId);
            if ($quote->isVirtual() || !$quote->getItemsCount()) {
                return [$cartId, $addressInformation];
            }
            $carrierCode = $addressInformation->getShippingCarrierCode();
            if ($carrierCode != MageWorxPickup::MAGEWORX_PICKUP_CARRIER_CODE) {
                return [$cartId, $addressInformation];
            }
            $methodCode = $addressInformation->getShippingMethodCode();
            $mixCodes = explode('_', $methodCode);
            if (count($mixCodes) != 2 || $mixCodes[0] != MageWorxPickup::MAGEWORX_PICKUP_CARRIER_CODE) {
                return [$cartId, $addressInformation];
            }

            $locationId = $mixCodes[1];
            $this->sessionManager->setData('mageworx_pickup_location_id', $locationId);
            $_COOKIE[MageWorxPickup::COOKIE_NAME] = $locationId;

            $storeAddress = $this->eventsForThirdPartyModules->runFilter("getInStoreShippingStoreAddress", null, $locationId);
            if ($storeAddress->getPhone()) {
                $address = $addressInformation->getShippingAddress();
                $address->setTelephone($storeAddress->getPhone());
            }
            $addressInformation->setShippingMethodCode(MageWorxPickup::MAGEWORX_PICKUP_CARRIER_CODE);
            
            $quoteCustomerGroupId = $quote->getCustomerGroupId();
            $customerGroupId = $this->customerSession->getCustomerGroupId();
            if ($quoteCustomerGroupId !== $customerGroupId) {
                $this->checkoutSession->setMageWorxPickupQuoteId($cartId);
            } else {
                $this->checkoutSession->setQuoteId($cartId);
            }
        } catch (\Exception $e) {
            $this->bugsnagHelper->notifyException($e);
        }
        
        return [$cartId, $addressInformation];
    }
}

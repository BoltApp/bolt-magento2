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

namespace Bolt\Boltpay\Plugin\Magento\Quote\Api;

use Magento\Checkout\Model\Session;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Area;
use Magento\Framework\App\State;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\Module\Manager;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\ShipmentEstimationInterface;
use Magento\Quote\Api\Data\AddressInterface;
use Magento\Quote\Api\Data\ShippingMethodInterface;
use Magento\Quote\Api\Data\ShippingMethodExtensionFactory;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address;
use Bolt\Boltpay\Api\Data\ShippingOptionInterfaceFactory;
use Bolt\Boltpay\Helper\Bugsnag;
use Bolt\Boltpay\Helper\Hook;
use Bolt\Boltpay\Helper\Shared\CurrencyUtils;
use Bolt\Boltpay\Model\EventsForThirdPartyModules;
use Bolt\Boltpay\ThirdPartyModules\MageWorx\Pickup as MageWorxPickup;

/**
 * Plugin for {@see \Magento\Quote\Api\ShipmentEstimationInterface}
 */
class ShippingMethodManagementPlugin
{
    /**
     * @var \Magento\Framework\App\State
     */
    private $appState;

    /**
     * @var \Magento\Framework\Module\Manager
     */
    private $moduleManager;

    /**
     * @var \Magento\Catalog\Api\Data\ShippingMethodExtensionFactory
     */
    private $extensionFactory;

    /**
     * @var \Magento\Checkout\Model\Session
     */
    private $checkoutSession;

    /**
     * @var CartRepositoryInterface
     */
    private $quoteRepository;

    /**
     * @var Json
     */
    private $json;

    /**
     * @var Bugsnag
     */
    private $bugsnagHelper;

    /**
     * @var EventsForThirdPartyModules
     */
    private $eventsForThirdPartyModules;

    /**
     * @var ShippingOptionInterfaceFactory
     */
    private $shippingOptionFactory;

    /**
     * @var CustomerSession
     */
    private $customerSession;

    /**
     * ShippingMethodManagementPlugin constructor.
     *
     * @param Bolt\Boltpay\Helper\Bugsnag $bugsnagHelper
     * @param Bolt\Boltpay\Model\EventsForThirdPartyModules $eventsForThirdPartyModules
     * @param Bolt\Boltpay\Api\Data\ShippingOptionInterfaceFactory $shippingOptionFactory
     * @param \Magento\Framework\App\State $appState
     * @param \Magento\Framework\Module\Manager $moduleManager
     * @param \Magento\Catalog\Api\Data\ShippingMethodExtensionFactory $extensionFactory
     * @param \Magento\Checkout\Model\Session $checkoutSession
     * @param \Magento\Quote\Api\CartRepositoryInterface $quoteRepository
     * @param \Magento\Customer\Model\Session|null $customerSession
     * @param \Magento\Framework\Serialize\Serializer\Json|null $json
     *
     */
    public function __construct(
        Bugsnag $bugsnagHelper,
        EventsForThirdPartyModules $eventsForThirdPartyModules,
        ShippingOptionInterfaceFactory $shippingOptionFactory,
        State $appState,
        Manager $moduleManager,
        ShippingMethodExtensionFactory $extensionFactory,
        Session $checkoutSession,
        CartRepositoryInterface $quoteRepository,
        CustomerSession $customerSession = null,
        Json $json = null
    ) {
        $this->bugsnagHelper = $bugsnagHelper;
        $this->eventsForThirdPartyModules = $eventsForThirdPartyModules;
        $this->shippingOptionFactory = $shippingOptionFactory;
        $this->appState = $appState;
        $this->moduleManager = $moduleManager;
        $this->extensionFactory = $extensionFactory;
        $this->checkoutSession = $checkoutSession;
        $this->quoteRepository = $quoteRepository;
        $this->customerSession = $customerSession ?? ObjectManager::getInstance()->get(CustomerSession::class);
        $this->json = $json ?: ObjectManager::getInstance()->get(Json::class);
    }

    /**
     *
     * @param ShipmentEstimationInterface   $subject    original ShippingMethodManagement instance.
     * @param int                           $cartId     The shopping cart ID.
     * @param AddressInterface              $address    The estimate address
     *
     * @return array
     */
    public function beforeEstimateByExtendedAddress(ShipmentEstimationInterface $subject, $cartId, AddressInterface $address)
    {
        if ($this->appState->getAreaCode() !== Area::AREA_WEBAPI_REST ||
            !$this->moduleManager->isEnabled(MageWorxPickup::MAGEWORX_PICKUP_MODULE_NAME) ||
            !$cartId || !filter_var($cartId, FILTER_VALIDATE_INT)
        ) {
            return [$cartId, $address];
        }
        try {
            $quote = $this->quoteRepository->getActive($cartId);
            if ($quote) {
                $quoteCustomerGroupId = $quote->getCustomerGroupId();
                $customerGroupId = $this->customerSession->getCustomerGroupId();
                if ($quoteCustomerGroupId !== $customerGroupId) {
                    $this->checkoutSession->setMageWorxPickupQuoteId($cartId);
                } else {
                    $this->checkoutSession->setQuoteId($cartId);
                }
            }
        } catch (\Exception $e) {
            $this->bugsnagHelper->notifyException($e);
        }

        return [$cartId, $address];
    }

    /**
     *
     * @param ShipmentEstimationInterface   $subject    original ShippingMethodManagement instance.
     * @param ShippingMethodInterface[]     $result     result of the original method call.
     * @param int                           $cartId     The shopping cart ID.
     * @param AddressInterface              $address    The estimate address
     *
     * @return ShippingMethodInterface[] An array of shipping methods.
     */
    public function afterEstimateByExtendedAddress(ShipmentEstimationInterface $subject, $result, $cartId, AddressInterface $address)
    {
        if ($this->appState->getAreaCode() !== Area::AREA_WEBAPI_REST ||
            !$this->moduleManager->isEnabled(MageWorxPickup::MAGEWORX_PICKUP_MODULE_NAME) ||
            empty($result) || !$cartId || !filter_var($cartId, FILTER_VALIDATE_INT)
        ) {
            return $result;
        }
        try {
            $tmpResult = [];
            foreach ($result as $shippingMethod) {
                if ($shippingMethod->getCarrierCode() == MageWorxPickup::MAGEWORX_PICKUP_CARRIER_CODE) {
                    $shippingOptions = [];
                    $service = $shippingMethod->getCarrierTitle() . ' - ' . $shippingMethod->getMethodTitle();
                    $method  = $shippingMethod->getCarrierCode() . '_' . $shippingMethod->getMethodCode();
                    $majorAmount = $shippingMethod->getAmount();
                    $quote = $this->quoteRepository->getActive($cartId);
                    $currencyCode = $quote->getQuoteCurrencyCode();
                    $cost = CurrencyUtils::toMinor($majorAmount, $currencyCode);
                    $shippingOptions[] = $this->shippingOptionFactory
                                            ->create()
                                            ->setService($service)
                                            ->setCost($cost)
                                            ->setReference($method);
                    $shippingAddress = $quote->getShippingAddress();
                    if ($shippingAddress &&
                        ($country_code = $shippingAddress->getCountryId()) &&
                        ($postal_code  = $shippingAddress->getPostcode())
                    ) {
                        $addressData = [
                            'country_code' => $country_code,
                            'postal_code'  => $postal_code,
                            'region'       => $shippingAddress->getRegion(),
                            'locality'     => $shippingAddress->getCity(),
                            'street_address1' => $shippingAddress->getStreetLine(1),
                            'street_address2' => $shippingAddress->getStreetLine(2),
                        ];
                        list($shipToStoreOptions, $shippingOptions) = $this->eventsForThirdPartyModules->runFilter("getShipToStoreOptions", [[],$shippingOptions], $quote, $shippingOptions, $addressData);
                        if (!empty($shipToStoreOptions)) {
                            $extensibleAttribute =  ($shippingMethod->getExtensionAttributes())
                                ? $shippingMethod->getExtensionAttributes()
                                : $this->extensionFactory->create();
                            $locations = $this->json->serialize($shipToStoreOptions);
                            $extensibleAttribute->setMageWorxPickupLocations($locations);
                            $shippingMethod->setExtensionAttributes($extensibleAttribute);
                        }
                    }
                }
                $tmpResult[] = $shippingMethod;
            }
            $result = $tmpResult;
        } catch (\Exception $e) {
            $this->bugsnagHelper->notifyException($e);
        }

        return $result;
    }
}

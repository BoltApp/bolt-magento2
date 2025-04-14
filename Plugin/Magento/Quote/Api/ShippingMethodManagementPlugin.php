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

use Magento\Framework\App\Area;
use Magento\Framework\App\State;
use Magento\Framework\Webapi\Rest\Request;
use Magento\Quote\Api\ShipmentEstimationInterface;
use Magento\Quote\Api\Data\AddressInterface;
use Bolt\Boltpay\Helper\Bugsnag;
use Bolt\Boltpay\Model\EventsForThirdPartyModules;
use Bolt\Boltpay\Model\RestApiRequestValidator;

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
     * @var Bolt\Boltpay\Helper\Bugsnag
     */
    private $bugsnagHelper;

    /**
     * @var Bolt\Boltpay\Model\EventsForThirdPartyModules
     */
    private $eventsForThirdPartyModules;

    /**
     * @var Magento\Framework\Webapi\Rest\Request
     */
    private $request;

    /**
     * @var Bolt\Boltpay\Model\RestApiRequestValidator
     */
    private $boltRestApiRequestValidator;

    /**
     * ShippingMethodManagementPlugin constructor.
     *
     * @param Bolt\Boltpay\Helper\Bugsnag $bugsnagHelper
     * @param Bolt\Boltpay\Model\EventsForThirdPartyModules $eventsForThirdPartyModules
     * @param Bolt\Boltpay\Model\RestApiRequestValidator $boltRestApiRequestValidator
     * @param \Magento\Framework\App\State $appState
     * @param \Magento\Framework\Webapi\Rest\Request $request
     *
     */
    public function __construct(
        Bugsnag $bugsnagHelper,
        EventsForThirdPartyModules $eventsForThirdPartyModules,
        RestApiRequestValidator $boltRestApiRequestValidator,
        State $appState,
        Request $request
    ) {
        $this->bugsnagHelper = $bugsnagHelper;
        $this->eventsForThirdPartyModules = $eventsForThirdPartyModules;
        $this->boltRestApiRequestValidator = $boltRestApiRequestValidator;
        $this->appState = $appState;
        $this->request = $request;
    }

    /**
     *
     * @param ShipmentEstimationInterface   $subject    original ShippingMethodManagement instance.
     * @param int                           $cartId     The shopping cart ID.
     * @param AddressInterface              $address    The estimate address
     *
     * @return array
     */
    public function beforeEstimateByExtendedAddress(
        ShipmentEstimationInterface $subject,
        $cartId,
        AddressInterface $address
    ) {
        if ($this->appState->getAreaCode() !== Area::AREA_WEBAPI_REST ||
            !$this->boltRestApiRequestValidator->isValidBoltRequest($this->request) ||
            !$cartId || !filter_var($cartId, FILTER_VALIDATE_INT)
        ) {
            return [$cartId, $address];
        }
        try {
            $this->eventsForThirdPartyModules->dispatchEvent(
                "beforeEstimateByExtendedAddress",
                $cartId,
                $address
            );
        } catch (\Exception $e) {
            $this->bugsnagHelper->notifyException($e);
        }

        return [$cartId, $address];
    }

    /**
     *
     * @param ShipmentEstimationInterface   $subject    original ShippingMethodManagement instance.
     * @param \Magento\Quote\Api\Data\ShippingMethodInterface[]     $result     result of the original method call.
     * @param int                           $cartId     The shopping cart ID.
     * @param AddressInterface              $address    The estimate address
     *
     * @return \Magento\Quote\Api\Data\ShippingMethodInterface[] An array of shipping methods.
     */
    public function afterEstimateByExtendedAddress(
        ShipmentEstimationInterface $subject,
        $result,
        $cartId,
        AddressInterface $address
    ) {
        if ($this->appState->getAreaCode() !== Area::AREA_WEBAPI_REST ||
            !$this->boltRestApiRequestValidator->isValidBoltRequest($this->request) ||
            empty($result) || !$cartId || !filter_var($cartId, FILTER_VALIDATE_INT)
        ) {
            return $result;
        }
        try {
            $result = $this->eventsForThirdPartyModules->runFilter(
                "afterEstimateByExtendedAddress",
                $result,
                $cartId,
                $address
            );
        } catch (\Exception $e) {
            $this->bugsnagHelper->notifyException($e);
        }

        return $result;
    }
}

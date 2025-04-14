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

use Bolt\Boltpay\Helper\Bugsnag;
use Bolt\Boltpay\Model\EventsForThirdPartyModules;
use Bolt\Boltpay\Model\RestApiRequestValidator;
use Magento\Framework\App\Area;
use Magento\Framework\App\State;
use Magento\Framework\Webapi\Rest\Request;

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
     * ShippingInformationManagementPlugin constructor.
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
     * @param \Magento\Checkout\Model\ShippingInformationManagement $subject
     * @param int $cartId
     * @param \Magento\Checkout\Api\Data\ShippingInformationInterface $addressInformation
     *
     * @return array
     */
    public function beforeSaveAddressInformation(
        \Magento\Checkout\Model\ShippingInformationManagement $subject,
        $cartId,
        \Magento\Checkout\Api\Data\ShippingInformationInterface $addressInformation
    ) {
        try {
            if ($this->appState->getAreaCode() !== Area::AREA_WEBAPI_REST ||
                !$this->boltRestApiRequestValidator->isValidBoltRequest($this->request) ||
                !$cartId || !filter_var($cartId, FILTER_VALIDATE_INT)
            ) {
                return [$cartId, $addressInformation];
            }
            $this->eventsForThirdPartyModules->dispatchEvent(
                "beforeSaveAddressInformation",
                $cartId,
                $addressInformation
            );
        } catch (\Exception $e) {
            $this->bugsnagHelper->notifyException($e);
        }
        
        return [$cartId, $addressInformation];
    }
}

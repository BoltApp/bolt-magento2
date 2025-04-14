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

use Bolt\Boltpay\Helper\Bugsnag;
use Bolt\Boltpay\Model\EventsForThirdPartyModules;
use Bolt\Boltpay\Model\Payment;
use Bolt\Boltpay\Model\RestApiRequestValidator;
use Magento\Framework\App\Area;
use Magento\Framework\App\State;
use Magento\Framework\Webapi\Rest\Request;

/**
 * Plugin for {@see \Magento\Checkout\Api\GuestPaymentInformationManagementInterface}
 */
class GuestPaymentInformationManagementPlugin
{
    /**
     * @var \Magento\Framework\App\State
     */
    private $appState;

    /**
     * @var \Bolt\Boltpay\Helper\Bugsnag
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
     * GuestPaymentInformationManagementPlugin constructor.
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
     * @param \Magento\Checkout\Api\GuestPaymentInformationManagementInterface $subject
     * @param string $cartId
     * @param string $email
     * @param \Magento\Quote\Api\Data\PaymentInterface $paymentMethod
     * @param \Magento\Quote\Api\Data\AddressInterface|null $billingAddress
     *
     * @return array
     */
    public function beforeSavePaymentInformationAndPlaceOrder(
        \Magento\Checkout\Api\GuestPaymentInformationManagementInterface $subject,
        $cartId,
        $email,
        \Magento\Quote\Api\Data\PaymentInterface $paymentMethod,
        ?\Magento\Quote\Api\Data\AddressInterface $billingAddress = null
    ) {
        try {
            if ($this->appState->getAreaCode() !== Area::AREA_WEBAPI_REST ||
                !$this->boltRestApiRequestValidator->isValidBoltRequest($this->request) ||
                $paymentMethod->getMethod() != Payment::METHOD_CODE
            ) {
                return [$cartId, $email, $paymentMethod, $billingAddress];
            }
            $this->eventsForThirdPartyModules->dispatchEvent(
                "beforeSavePaymentInformationAndPlaceOrder",
                $cartId,
                $email,
                $paymentMethod,
                $billingAddress
            );
        } catch (\Exception $e) {
            $this->bugsnagHelper->notifyException($e);
        }
        
        return [$cartId, $email, $paymentMethod, $billingAddress];
    }
}

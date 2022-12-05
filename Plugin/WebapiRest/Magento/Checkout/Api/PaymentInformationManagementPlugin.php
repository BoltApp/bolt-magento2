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
 * @copyright  Copyright (c) 2017-2022 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Bolt\Boltpay\Plugin\WebapiRest\Magento\Checkout\Api;

use Magento\Checkout\Api\PaymentInformationManagementInterface;
use \Bolt\Boltpay\Api\RouteInsuranceManagementInterface;

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
     * @param \Magento\Checkout\Model\Session $checkoutSession
     * @param \Bolt\Boltpay\Helper\Cart $cartHelper
     * @param \Magento\Quote\Model\MaskedQuoteIdToQuoteIdInterface $maskedQuoteIdToQuoteId
     * @param \Magento\Framework\Module\Manager $moduleManager
     */
    public function __construct(
        \Magento\Checkout\Model\Session $checkoutSession,
        \Bolt\Boltpay\Helper\Cart $cartHelper,
        \Magento\Quote\Model\MaskedQuoteIdToQuoteIdInterface $maskedQuoteIdToQuoteId,
        \Magento\Framework\Module\Manager $moduleManager
    ) {
        $this->checkoutSession = $checkoutSession;
        $this->cartHelper = $cartHelper;
        $this->maskedQuoteIdToQuoteId = $maskedQuoteIdToQuoteId;
        $this->moduleManager = $moduleManager;
    }

    public function beforeSavePaymentInformationAndPlaceOrder(
        PaymentInformationManagementInterface $subject,
        $cartId,
        \Magento\Quote\Api\Data\PaymentInterface $paymentMethod,
        \Magento\Quote\Api\Data\AddressInterface $billingAddress
    ) {
        if ($this->moduleManager->isEnabled(RouteInsuranceManagementInterface::ROUTE_MODULE_NAME))
        {
            $quote = $this->cartHelper->getQuoteById($cartId);
            $routeIsInsured = $quote->getRouteIsInsured();
            if ($routeIsInsured) {
                $this->checkoutSession->setQuoteId($cartId);
                $this->checkoutSession->setInsured($routeIsInsured);
            }
        }

        return [$cartId, $paymentMethod, $billingAddress];
    }
}
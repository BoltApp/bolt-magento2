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

namespace Bolt\Boltpay\Plugin\WebapiRest\Route\Route\Helper;

use \Bolt\Boltpay\Api\RouteInsuranceManagementInterface;

/**
 * Plugin for {@see \Route\Route\Helper\Data}
 */
class DataPlugin
{
    /**
     * @var \Magento\Framework\Webapi\Rest\Request
     */
    private $request;

    /**
     * @var \Magento\Quote\Api\CartRepositoryInterface
     */
    private $quoteRepository;

    /**
     * @var \Bolt\Boltpay\Model\ThirdPartyModuleFactory
     */
    private $boltRouteQuoteClient;

    public function __construct(
        \Magento\Framework\Webapi\Rest\Request $request,
        \Magento\Quote\Api\CartRepositoryInterface $quoteRepository,
        \Bolt\Boltpay\Model\ThirdPartyModuleFactory $boltRouteQuoteClient
    ){
        $this->request = $request;
        $this->quoteRepository = $quoteRepository;
        $this->boltRouteQuoteClient = $boltRouteQuoteClient;
    }

    public function afterIsInsured(\Route\Route\Helper\Data $subject, $result): bool
    {
        $routeIsInsured = $this->getRouteIsInsuredFromRequest();
        if ($routeIsInsured !== null) {
            return $routeIsInsured;
        }
        return $result;
    }

    public function afterGetQuoteResponse(\Route\Route\Helper\Data $subject, $result): bool
    {
        $routeIsInsured = $this->getRouteIsInsuredFromRequest();
        if ($routeIsInsured !== null) {
            $cartId = $this->getCartIdFromRequest();
            $quote = $this->quoteRepository->get($cartId);

            if (!$quote) {
                return false;
            }

            $subtotal = $quote->getSubtotal() ? $quote->getSubtotal() : 0;
            $amountCovered = $subject->getShippableItemsSubtotal($quote);
            if (!$subtotal || $subtotal==0 || !$amountCovered || $amountCovered==0) {
                return true;
            }

            $quoteClient = $this->boltRouteQuoteClient->getInstance();
            if ($quoteClient) {
                return $quoteClient->getQuote(
                    $subtotal,
                    $amountCovered,
                    $routeIsInsured,
                    $quote->getQuoteCurrencyCode()
                );
            }
        }
        return $result;
    }

    private function getCartIdFromRequest()
    {
        if ($this->request->getParam(RouteInsuranceManagementInterface::ROUTE_IS_INSURED_PARAM)) {
            return $this->request->getParam(RouteInsuranceManagementInterface::CART_ID_PARAM);
        }
        return null;
    }

    private function getRouteIsInsuredFromRequest()
    {
        $routeIsInsured = $this->request->getParam(RouteInsuranceManagementInterface::ROUTE_IS_INSURED_PARAM);
        if ($routeIsInsured !== null) {
            return filter_var($routeIsInsured, FILTER_VALIDATE_BOOLEAN);
        }
        return null;
    }
}

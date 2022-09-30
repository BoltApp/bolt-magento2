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

/**
 * Plugin for {@see \Route\Route\Helper\Data}
 */
class DataPlugin
{
    public const CART_ID_PARAM = 'cartId';
    public const IS_INSURED_PARAM = 'route_is_insured';
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
        $requestParams = $this->request->getParams();
        if (array_key_exists(self::IS_INSURED_PARAM, $requestParams)) {
            return filter_var($requestParams[self::IS_INSURED_PARAM], FILTER_VALIDATE_BOOLEAN);
        }
        return $result;
    }

    public function afterGetQuoteResponse(\Route\Route\Helper\Data $subject, $result): bool
    {
        $requestParams = $this->request->getParams();
        if (array_key_exists(self::IS_INSURED_PARAM, $requestParams)) {
            $cartId = $requestParams[self::CART_ID_PARAM];
            $quote = $this->quoteRepository->get($cartId);

            if (!$quote) {
                return false;
            }

            $subtotal = $quote->getSubtotal() ? $quote->getSubtotal() : 0;
            $amountCovered = $this->getShippableItemsSubtotal($quote);
            if (!$subtotal || $subtotal==0 || !$amountCovered || $amountCovered==0) {
                return true;
            }

            $quoteClient = $this->boltRouteQuoteClient->getInstance();

            return $quoteClient->getQuote(
                $subtotal,
                $amountCovered,
                filter_var($requestParams[self::IS_INSURED_PARAM], FILTER_VALIDATE_BOOLEAN),
                $quote->getQuoteCurrencyCode()
            );
        }
        return $result;
    }
}

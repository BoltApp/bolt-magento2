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
 *
 * @copyright  Copyright (c) 2017-2023 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Bolt\Boltpay\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Framework\Webapi\Rest\Request as RestRequest;
use Bolt\Boltpay\Model\RestApiRequestValidator;

class CustomPrice implements ObserverInterface
{
    /** @var RequestInterface */
    private $request;

    /** @var PriceCurrencyInterface */
    private $priceCurrency;

    /** @var RestRequest */
    private $restRequest;

    /** @var RestApiRequestValidator */
    private $restApiRequestValidator;

    public function __construct(
        RequestInterface $request,
        PriceCurrencyInterface $priceCurrency,
        RestRequest $restRequest,
        RestApiRequestValidator $restApiRequestValidator
    ) {
        $this->request = $request;
        $this->priceCurrency = $priceCurrency;
        $this->restRequest = $restRequest;
        $this->restApiRequestValidator = $restApiRequestValidator;
    }
    /**
     * Set custom price for Bolt item in cart
     *
     * @param Observer $observer
     */
    public function execute(Observer $observer)
    {
        // Validate that this is a legitimate Bolt request
        if (!$this->restApiRequestValidator->isValidBoltRequest($this->restRequest)) {
            return;
        }
        $quote = $observer->getEvent()->getQuote();
        if (!$quote || !$quote->getId()) {
            return;
        }

        // Attempt to read fee from parsed params
        $body = $this->request->getContent();
        $data = json_decode($body, true) ?: [];
        $sentFee = 0; // by default
        if (isset($data['cart_item']['sku']) && $data['cart_item']['sku'] == 'BOLT_PRIVATE_CHECKOUT' &&
            isset($data['cart_item']['extension_attributes']['bolt_privacy_fee'])) {
            $sentFee = (float) $data['cart_item']['extension_attributes']['bolt_privacy_fee'];
        }
        $subtotal = 0;
        $boltItem = null;

        foreach ($quote->getAllItems() as $item) {
            if ($item->getSku() === 'BOLT_PRIVATE_CHECKOUT') {
                $boltItem = $item;
                continue;
            }
            $subtotal += $item->getRowTotal();
        }
        // If client sent a price, use that; otherwise 1% fee
        if ($boltItem && $subtotal > 0) {
            $customPrice = ($sentFee > 0) ? $sentFee : (floor((float) $subtotal * 0.01 * 100) / 100);
            $boltItem->setCustomPrice($customPrice);
            $boltItem->setOriginalCustomPrice($customPrice);
            $boltItem->getProduct()->setIsSuperMode(true);
        }
    }
}

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

class CustomPrice implements ObserverInterface
{
    /** @var RequestInterface */
    private $request;

    /** @var PriceCurrencyInterface */
    private $priceCurrency;

    public function __construct(
        RequestInterface $request,
        PriceCurrencyInterface $priceCurrency
    ) {
        $this->request       = $request;
        $this->priceCurrency = $priceCurrency;
    }
    /**
     * Set custom price for Bolt item in cart
     *
     * @param Observer $observer
     */
    public function execute(Observer $observer)
    {
        $quote = $observer->getEvent()->getQuote();
        if (!$quote || !$quote->getId()) {
            return;
        }

        // Attempt to read fee from parsed params
        $body = $this->request->getContent();
        $data = json_decode($body, true) ?: [];
        $sentFee = 0; // by default
        if (isset($data['cart_item']['sku']) && $data['cart_item']['sku'] == 'BOLT_PRIVATE_CHECKOUT' &&
            isset($data['cart_item']['extension_attributes']['bolt_private_item_fee'])) {
            $sentFee = (float) $data['cart_item']['extension_attributes']['bolt_private_item_fee'];
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

        if ($boltItem && $subtotal > 0 && $sentFee > 0) {
            // If client sent a price, use that; otherwise 1% fee
            $customPrice = $this->priceCurrency->round($subtotal * $sentFee);
            $boltItem->setCustomPrice($customPrice);
            $boltItem->setOriginalCustomPrice($customPrice);
            $boltItem->getProduct()->setIsSuperMode(true);
        }
    }
}

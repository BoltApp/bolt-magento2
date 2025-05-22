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
        // not a Bolt API request → do nothing
        if (strpos($path, '/rest/') === false || strpos($path, '/V1/boltpay/') === false) {
            return;
        }

        $quote = $observer->getEvent()->getQuote();
        if (!$quote || !$quote->getId()) {
            return;
        }

        // Attempt to read price from parsed params
        $cartItem = $this->request->getParam('cartItem', []);
        $sentPrice = isset($cartItem['price'])
            ? (float) $cartItem['price']
            : null;

        // Fallback: read raw JSON body if not in params
        if ($sentPrice === null) {
            $body = $this->request->getContent();
            $data = json_decode($body, true) ?: [];
            if (isset($data['cartItem']['price'])) {
                $sentPrice = (float) $data['cartItem']['price'];
            }
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

        if ($boltItem && $subtotal > 0) {
            // If client sent a price, use that; otherwise 1% fee
            $customPrice = $sentPrice !== null
                ? $sentPrice
                : $this->priceCurrency->round($subtotal * 0.01);


            $boltItem->setCustomPrice($customPrice);
            $boltItem->setOriginalCustomPrice($customPrice);
            $boltItem->getProduct()->setIsSuperMode(true);
        }
    }
}

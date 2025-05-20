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

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\App\RequestInterface;

class CustomPrice implements ObserverInterface
{
    public function execute(\Magento\Framework\Event\Observer $observer) {
        $quote = $observer->getEvent()->getQuote();
        if (!$quote || !$quote->getId()) {
            return;
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
            $customPrice = round($subtotal * 0.01, 2); // 1% fee
            $boltItem->setCustomPrice($customPrice);
            $boltItem->setOriginalCustomPrice($customPrice);
            $boltItem->getProduct()->setIsSuperMode(true);
        }
    }
}

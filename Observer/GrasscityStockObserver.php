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
 * @copyright  Copyright (c) 2017-2022 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Bolt\Boltpay\Observer;

use Bolt\Boltpay\Model\Payment;
use Bolt\Boltpay\Helper\Bugsnag;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Module\Manager;

class GrasscityStockObserver implements ObserverInterface
{
    /**
     * @var Bugsnag
     */
    private $bugsnag;

    /**
     * @var \Magento\Framework\Module\Manager
     */
    private $moduleManager;

    /**
     * @param Bugsnag  $bugsnag
     * @param Manager  $moduleManager
     */
    public function __construct(
        Bugsnag $bugsnag,
        Manager $moduleManager
    ) {
        $this->bugsnag = $bugsnag;
        $this->moduleManager = $moduleManager;
    }

    /**
     * Support stock service of Grasscity.
     * Add additional data 'stock_processor_reserve_items' to payment for new order.
     * 
     * @param Observer $observer
     */
    public function execute(Observer $observer)
    {
        try {
            if ($this->moduleManager->isEnabled('Grasscity_StockReservationManagement')) {
                $order = $observer->getEvent()->getOrder();
                $orderPayment = $order->getPayment();
                if ($orderPayment && $orderPayment->getMethod() === Payment::METHOD_CODE) {
                    $orderPayment->setAdditionalInformation(array_merge((array)$orderPayment->getAdditionalInformation(), ['stock_processor_reserve_items' => true]));
                }
            }
        } catch (\Exception $exception) {
            $this->bugsnag->notifyException($exception);
        }
    }
}

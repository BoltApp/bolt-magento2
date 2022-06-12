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

namespace Bolt\Boltpay\ThirdPartyModules\Grasscity;

use Bolt\Boltpay\Helper\Bugsnag;
use Bolt\Boltpay\Model\Payment;
use Bolt\Boltpay\Exception\BoltException;
use Bolt\Boltpay\Model\Api\CreateOrder;

class StockReservationManagement
{
    /**
     * @var Bugsnag
     */
    private $bugsnagHelper;

    /**
     * StockReservationManagement constructor.
     * @param Bugsnag $bugsnagHelper
     */
    public function __construct(
        Bugsnag $bugsnagHelper
    ) {
        $this->bugsnagHelper = $bugsnagHelper;
    }

    /**
     * When the feature `Fail authorization when we timeout on create order` is enabled,
     * if the payment of existing order has additional data 'stock_processor_reserve_items' and its value is true,
     * it means the stock service is down for some reasons.
     *
     * @param \Magento\Sales\Model\Order $order
     * @param \Magento\Quote\Model\Quote $quote
     * @param \stdClass $transaction
     */
    public function processExistingOrder($order, $quote, $transaction) {
        $orderPayment = $order->getPayment();
        if ($orderPayment
            && $orderPayment->getMethod() === Payment::METHOD_CODE
            && (bool)$orderPayment->getAdditionalInformation('stock_processor_reserve_items')) {
            throw new BoltException(
                __(
                    'Order creation timeout due to the stock service is down. Quote ID: %1 Order Increment ID %2',
                    $quote->getId(),
                    $order->getIncrementId()
                ),
                null,
                CreateOrder::E_BOLT_REJECTED_ORDER
            );
        }
    }
    
    /**
     * Reset the additional data 'stock_processor_reserve_items' of order payment.
     *
     * @param \Magento\Sales\Model\Order $result
     * @param \Magento\Sales\Model\Order $order
     */
    public function beforeGetOrderByIdProcessNewOrder($result, $giftCardAccountSaveHandler, $order)
    {
        try {
            $orderPayment = $order->getPayment();
            if ($orderPayment && $orderPayment->getMethod() === Payment::METHOD_CODE) {
                $orderPayment->setAdditionalInformation(array_merge((array)$orderPayment->getAdditionalInformation(), ['stock_processor_reserve_items' => false]));
                $orderPayment->save();
            }
        } catch (\Exception $e) {
            $this->bugsnagHelper->notifyException($e);
        } finally {
            return $result;
        }
    }
}

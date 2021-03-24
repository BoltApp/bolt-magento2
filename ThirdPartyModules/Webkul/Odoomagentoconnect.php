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
 * @copyright  Copyright (c) 2017-2021 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Bolt\Boltpay\ThirdPartyModules\Webkul;

use Bolt\Boltpay\Helper\Order as OrderHelper;
use Bolt\Boltpay\Model\ThirdPartyEvents\ListensAfterUpdateOrderPayment;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Model\QuoteRepository;
use Magento\Sales\Model\Order;

/**
 * Class Odoomagentoconnect
 *
 * @package Bolt\Boltpay\ThirdPartyModules\Webkul
 */
class Odoomagentoconnect
{

    /**
     * @var QuoteRepository
     */
    private $quoteRepository;

    /**
     * Odoomagentoconnect constructor.
     *
     * @param QuoteRepository $quoteRepository
     */
    public function __construct(QuoteRepository $quoteRepository)
    {
        $this->quoteRepository = $quoteRepository;
    }

    /**
     * Third party event executed after {@see \Bolt\Boltpay\Helper\Order::updateOrderPayment}
     * Executes {@see \Webkul\Odoomagentoconnect\Observer\SalesOrderAfterObserver}
     * when order state changes to authorized
     *
     * @param \Webkul\Odoomagentoconnect\Observer\SalesOrderAfterObserver $observer
     * @param Order                                                       $order                order that has been
     *                                                                                          updated
     * @param \stdClass                                                   $transaction          Bolt transaction object
     * @param string                                                      $transactionState     newly set transaction
     *                                                                                          state
     * @param string                                                      $prevTransactionState previous transaction
     *                                                                                          state
     *
     * @return void
     */
    public function afterUpdateOrderPayment($observer, $order, $transaction, $transactionState, $prevTransactionState)
    {
        if ($transactionState == OrderHelper::TS_AUTHORIZED && $prevTransactionState != OrderHelper::TS_AUTHORIZED) {
            try {
                $quote = $this->quoteRepository->get($order->getQuoteId());
            } catch (NoSuchEntityException $e) {
                $quote = false;
            }
            $eventData = ['order' => $order, 'quote' => $quote];
            $event = new \Magento\Framework\Event($eventData);
            $event->setName('checkout_submit_all_after');

            $wrapper = new \Magento\Framework\Event\Observer();
            $wrapper->setData(array_merge(['event' => $event], $eventData));
            $observer->execute($wrapper);
        }
    }
}

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
 * @copyright  Copyright (c) 2017-2020 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Bolt\Boltpay\Observer\Adminhtml\Sales;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\App\RequestInterface;
use Bolt\Boltpay\Model\CustomerCreditCardFactory as CustomerCreditCardFactory;
use Bolt\Boltpay\Helper\Bugsnag;
use Bolt\Boltpay\Helper\Order as OrderHelper;

/**
 * Class OrderCreateProcessDataObserver
 * @package Bolt\Boltpay\Observer\Adminhtml\Sales
 */
class RechargeCustomer implements ObserverInterface
{
    /**
     * @var CustomerCreditCardFactory
     */
    private $boltCustomerCreditCardFactory;

    /**
     * @var RequestInterface
     */
    private $request;

    /**
     * @var Bugsnag
     */
    private $bugsnag;

    /**
     * @var OrderHelper
     */
    private $orderHelper;

    /**
     * RechargeCustomer constructor.
     * @param Bugsnag $bugsnag
     * @param RequestInterface $request
     * @param CustomerCreditCardFactory $boltCustomerCreditCardFactory
     * @param Order $orderHelper
     */
    public function __construct(
        Bugsnag $bugsnag,
        RequestInterface $request,
        CustomerCreditCardFactory $boltCustomerCreditCardFactory,
        OrderHelper $orderHelper
    ) {
        $this->orderHelper = $orderHelper;
        $this->bugsnag = $bugsnag;
        $this->request = $request;
        $this->boltCustomerCreditCardFactory = $boltCustomerCreditCardFactory;
    }

    public function execute(Observer $observer)
    {
        try {
            $event = $observer->getEvent();
            $order = $event->getOrder();

            if (!$order->getPayment() || $order->getPayment()->getMethod() != \Bolt\Boltpay\Model\Payment::METHOD_CODE) {
                return false;
            }

            if ($creditCardValue = $this->request->getParam('bolt-credit-cards')) {
                /** @var \Bolt\Boltpay\Model\CustomerCreditCard $boltCustomerCreditCard */
                $boltCustomerCreditCard = $this->boltCustomerCreditCardFactory->create()->load($creditCardValue);
                $response = $boltCustomerCreditCard->recharge($order);

                $responseData = $response->getResponse();
                $reference = @$responseData->transaction->reference;
                if ($reference) {
                    $order->addStatusHistoryComment(
                        __(
                            'Bolt recharged transaction: %1',
                            $this->orderHelper->formatReferenceUrl($reference)
                        )
                    );
                }
                $order->setData('is_recharged_order',true);

                return true;
            }
        } catch (\Exception $e) {
            $this->bugsnag->notifyException($e);
            throw $e;
        }
    }
}

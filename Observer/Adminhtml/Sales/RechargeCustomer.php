<?php

namespace Bolt\Boltpay\Observer\Adminhtml\Sales;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\App\RequestInterface;
use Bolt\Boltpay\Model\CustomerCreditCardFactory as CustomerCreditCardFactory;
use Bolt\Boltpay\Helper\Bugsnag;

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
     * RechargeCustomer constructor.
     * @param Bugsnag $bugsnag
     * @param RequestInterface $request
     * @param CustomerCreditCardFactory $boltCustomerCreditCardFactory
     */
    public function __construct(
        Bugsnag $bugsnag,
        RequestInterface $request,
        CustomerCreditCardFactory $boltCustomerCreditCardFactory
    )
    {
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
                $boltCustomerCreditCard->recharge($order);
                return true;
            }
        } catch (\Exception $e) {
            $this->bugsnag->notifyException($e);
            throw $e;
        }
    }
}

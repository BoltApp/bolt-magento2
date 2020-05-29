<?php

namespace Bolt\Boltpay\Observer;

use Bolt\Boltpay\Helper\Api as ApiHelper;
use Bolt\Boltpay\Helper\Bugsnag;
use Bolt\Boltpay\Helper\Config as ConfigHelper;
use Bolt\Boltpay\Helper\Cart as CartHelper;
use Bolt\Boltpay\Helper\Log as LogHelper;
use Bolt\Boltpay\Helper\MetricsClient;
use Bolt\Boltpay\Model\Payment;
use Bolt\Boltpay\Model\Response;
use Exception;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Framework\DataObjectFactory;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Exception\LocalizedException;
use Zend_Http_Client_Exception;

/**
 * Class NonBoltOrderObserver
 *
 * @package Bolt\Boltpay\Observer
 */
class NonBoltOrderObserver implements ObserverInterface
{
    /**
     * @var ApiHelper
     */
    private $apiHelper;

    /**
     * @var Bugsnag
     */
    private $bugsnag;

    /**
     * @var CartHelper
     */
    private $cartHelper;

    /**
     * @var DataObjectFactory
     */
    private $dataObjectFactory;

    /**
     * @var ConfigHelper
     */
    private $configHelper;

    /**
     * @var LogHelper
     */
    private $logHelper;

    /**
     * @var MetricsClient
     */
    private $metricsClient;

    /**
     * @param ApiHelper $apiHelper
     * @param Bugsnag $bugsnag
     * @param CartHelper $cartHelper
     * @param ConfigHelper $configHelper
     * @param DataObjectFactory $dataObjectFactory
     * @param LogHelper $logHelper
     * @param MetricsClient $metricsClient
     */
    public function __construct(
        ApiHelper $apiHelper,
        Bugsnag $bugsnag,
        CartHelper $cartHelper,
        ConfigHelper $configHelper,
        DataObjectFactory $dataObjectFactory,
        LogHelper $logHelper,
        MetricsClient $metricsClient
    ) {
        $this->apiHelper = $apiHelper;
        $this->bugsnag = $bugsnag;
        $this->cartHelper = $cartHelper;
        $this->configHelper = $configHelper;
        $this->dataObjectFactory = $dataObjectFactory;
        $this->logHelper = $logHelper;
        $this->metricsClient = $metricsClient;
    }

    /**
     * @param Observer $observer
     */
    public function execute(Observer $observer)
    {
        try {
            $order = $observer->getEvent()->getOrder();
            if (!$order) {
                // for multi-address orders, the event will have a list of orders instead
                $orders = $observer->getEvent()->getOrders();
                if (!$orders || count($orders) < 1) {
                    $this->metricsClient->processCountMetric("non_bolt_order_creation.no_order", 1);
                    return;
                }

                $order = $orders[0];
            }

            $payment = $order->getPayment();
            if ($payment && $payment->getMethod() == Payment::METHOD_CODE) {
                return;
            }

            $quote = $observer->getEvent()->getQuote();
            if (!$quote) {
                $this->metricsClient->processCountMetric("non_bolt_order_creation.no_quote", 1);
                return;
            }

            $items = $quote->getAllVisibleItems();
            if (!$items) {
                $this->metricsClient->processCountMetric("non_bolt_order_creation.no_items", 1);
                return;
            }

            $cart = $this->cartHelper->buildCartFromQuote($quote, $quote, $items, null, true);
            $customer = $quote->getCustomer();
            $storeId = $order->getStoreId();
            $result = $this->createNonBoltOrder($cart, $customer, $storeId);
            if ($result != 200) {
                $this->metricsClient->processCountMetric("non_bolt_order_creation.failure", 1);
                return;
            }
        } catch (Exception $exception) {
            $this->metricsClient->processCountMetric("non_bolt_order_creation.failure", 1);
            $this->bugsnag->notifyException($exception);
            return;
        }

        $this->metricsClient->processCountMetric("non_bolt_order_creation.success", 1);
    }

    /**
     * Call Bolt Create Non-Bolt Order API
     *
     * @param array $cart
     * @param CustomerInterface $customer
     * @param int $storeId
     * @return Response|int
     * @throws LocalizedException
     * @throws Zend_Http_Client_Exception
     */
    protected function createNonBoltOrder($cart, $customer, $storeId)
    {
        $apiKey = $this->configHelper->getApiKey($storeId);

        $phone = $this->getPhone($cart);
        $requestData = $this->dataObjectFactory->create();
        $requestData->setApiData([
            'cart' => $cart,
            'user_identifier' => [
                'email' => $customer->getEmail(),
                'phone' => $phone,
            ],
            'user_identity' => [
                'first_name' => $customer->getFirstname(),
                'last_name' => $customer->getLastname(),
            ],
        ]);
        $requestData->setDynamicApiUrl(ApiHelper::API_CREATE_NON_BOLT_ORDER);
        $requestData->setApiKey($apiKey);
        $requestData->setStatusOnly(true);

        $request = $this->apiHelper->buildRequest($requestData);
        return $this->apiHelper->sendRequest($request);
    }

    /**
     * Get phone number from the cart if it exists
     *
     * @param array $cart
     * @return string
     */
    protected function getPhone($cart)
    {
        if (count($cart['shipments']) < 1) {
            return null;
        }

        if ($cart['shipments'][0]['shipping_address'] == null) {
            return null;
        }

        return $cart['shipments'][0]['shipping_address']['phone'];
    }
}

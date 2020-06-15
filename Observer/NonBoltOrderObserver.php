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
use Error;
use Exception;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Framework\DataObjectFactory;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Api\CartRepositoryInterface as QuoteRepository;
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
     * @var QuoteRepository
     */
    private $quoteRepository;

    /**
     * @param ApiHelper $apiHelper
     * @param Bugsnag $bugsnag
     * @param CartHelper $cartHelper
     * @param ConfigHelper $configHelper
     * @param DataObjectFactory $dataObjectFactory
     * @param LogHelper $logHelper
     * @param MetricsClient $metricsClient
     * @param QuoteRepository $quoteRepository
     */
    public function __construct(
        ApiHelper $apiHelper,
        Bugsnag $bugsnag,
        CartHelper $cartHelper,
        ConfigHelper $configHelper,
        DataObjectFactory $dataObjectFactory,
        LogHelper $logHelper,
        MetricsClient $metricsClient,
        QuoteRepository $quoteRepository
    ) {
        $this->apiHelper = $apiHelper;
        $this->bugsnag = $bugsnag;
        $this->cartHelper = $cartHelper;
        $this->configHelper = $configHelper;
        $this->dataObjectFactory = $dataObjectFactory;
        $this->logHelper = $logHelper;
        $this->metricsClient = $metricsClient;
        $this->quoteRepository = $quoteRepository;
    }

    /**
     * @param Observer $observer
     */
    public function execute(Observer $observer)
    {
        try {
            $order = $observer->getEvent()->getOrder();
            if ($order == null) {
                return;
            }

            $payment = $order->getPayment();
            $paymentMethod = $payment ? $payment->getMethod() : "unknown";
            if ($paymentMethod == Payment::METHOD_CODE) {
                // ignore Bolt orders
                return;
            }

            $quote = $this->quoteRepository->get($order->getQuoteId());
            if (!$quote) {
                $this->metricsClient->processCountMetric("non_bolt_order_creation.no_quote", 1);
                return;
            }

            $items = $quote->getAllVisibleItems();
            if (!$items) {
                $this->metricsClient->processCountMetric("non_bolt_order_creation.no_items", 1);
                return;
            }

            $this->handleMissingName($quote);

            $cart = $this->cartHelper->buildCartFromQuote($quote, $quote, $items, null, true, false);
            $customer = $quote->getCustomer();
            $storeId = $order->getStoreId();
            $result = $this->createNonBoltOrder($cart, $customer, $storeId, $paymentMethod);
            $response = $result->getResponse();
            if (empty($response)) {
                $this->metricsClient->processCountMetric("non_bolt_order_creation.failure", 1);
                return;
            }

            $payment->setAdditionalInformation("transaction_reference", $response->reference);
            $payment->save();
        } catch (Exception $exception) {
            $this->metricsClient->processCountMetric("non_bolt_order_creation.failure", 1);
            $this->bugsnag->notifyException($exception);
            return;
        } catch (Error $error) {
            // catch errors so failures here don't prevent orders from being created
            $this->metricsClient->processCountMetric("non_bolt_order_creation.failure", 1);
            $this->bugsnag->notifyException($error);
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
     * @param string $paymentMethod
     * @return Response|int
     * @throws LocalizedException
     * @throws Zend_Http_Client_Exception
     */
    protected function createNonBoltOrder($cart, $customer, $storeId, $paymentMethod)
    {
        $apiKey = $this->configHelper->getApiKey($storeId);

        $phone = $this->getPhone($cart);
        $email = $this->getEmail($cart, $customer);
        $firstName = $this->getFirstName($cart, $customer);
        $lastName = $this->getLastName($cart, $customer);
        $requestData = $this->dataObjectFactory->create();
        $requestData->setApiData([
            'cart' => $cart,
            'user_identifier' => [
                'email' => $email,
                'phone' => $phone,
            ],
            'user_identity' => [
                'first_name' => $firstName,
                'last_name' => $lastName,
            ],
            'payment_method' => $paymentMethod,
        ]);
        $requestData->setDynamicApiUrl(ApiHelper::API_CREATE_NON_BOLT_ORDER);
        $requestData->setApiKey($apiKey);

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
        if (!@$cart['shipments']) {
            return null;
        }

        if (isset($cart['shipments'][0]['shipping_address'])) {
            return $cart['shipments'][0]['shipping_address']['phone'];
        }

        return null;
    }

    /**
     * @param array $cart
     * @param CustomerInterface $customer
     * @return string
     */
    protected function getEmail($cart, $customer)
    {
        if (!is_null($customer->getEmail())) {
            return $customer->getEmail();
        }

        if (isset($cart['shipments'][0]['shipping_address'])) {
            return $cart['shipments'][0]['shipping_address']['email'];
        }

        return null;
    }

    /**
     * @param array $cart
     * @param CustomerInterface $customer
     * @return string
     */
    protected function getFirstName($cart, $customer)
    {
        if (!is_null($customer->getFirstname())) {
            return $customer->getFirstname();
        }

        if (isset($cart['shipments'][0]['shipping_address'])) {
            return $cart['shipments'][0]['shipping_address']['first_name'];
        }

        return null;
    }

    /**
     * @param array $cart
     * @param CustomerInterface $customer
     * @return string
     */
    protected function getLastName($cart, $customer)
    {
        if (!is_null($customer->getLastname())) {
            return $customer->getLastname();
        }

        if (isset($cart['shipments'][0]['shipping_address'])) {
            return $cart['shipments'][0]['shipping_address']['last_name'];
        }

        return null;
    }

    /**
     * Workaround/hack for PayPal orders that might have first and last name in the address's FirstName attribute
     *
     * @param $quote
     */
    protected function handleMissingName($quote) {
        $address = $quote->isVirtual() ? $quote->getBillingAddress() : $quote->getShippingAddress();
        if ($address->getLastName() == null) {
            $name = $address->getFirstName();
            $names = preg_split("/[\s]+/", $name);
           if (count($names) > 1) {
                $address->setFirstName(array_shift($names));
                $address->setLastName(join(" ", $names));
            } else {
                $address->setLastName($name);
            }
        }
    }
}

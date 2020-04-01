<?php

namespace Bolt\Boltpay\Observer;

use Bolt\Boltpay\Helper\Api as ApiHelper;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer;
use Bolt\Boltpay\Helper\Config as ConfigHelper;
use Magento\Framework\DataObjectFactory;
use Bolt\Boltpay\Helper\Bugsnag;
use Bolt\Boltpay\Helper\MetricsClient;
use Bolt\Boltpay\Helper\FeatureSwitch\Decider;
use Magento\Framework\Exception\LocalizedException;

/**
 * Class TrackingSaveObserver
 *
 * @package Bolt\Boltpay\Observer
 */
class TrackingSaveObserver implements ObserverInterface
{
    /**
     * @var ConfigHelper
     */
    private $configHelper;

    /**
     * @var DataObjectFactory
     */
    private $dataObjectFactory;

    /**
     * @var ApiHelper
     */
    private $apiHelper;

    /**
     * @var Bugsnag
     */
    private $bugsnag;

    /**
     * @var MetricsClient
     */
    private $metricsClient;

    /**
     * @var Decider
     */
    private $decider;

    /**
     * @param ConfigHelper      $configHelper
     * @param DataObjectFactory $dataObjectFactory
     * @param ApiHelper $apiHelper
     * @param Bugsnag $bugsnag
     * @param MetricsClient $metricsClient
     *
     */
    public function __construct(
        ConfigHelper $configHelper,
        DataObjectFactory $dataObjectFactory,
        ApiHelper $apiHelper,
        Bugsnag $bugsnag,
        MetricsClient $metricsClient,
        Decider $decider
    ) {
        $this->configHelper = $configHelper;
        $this->dataObjectFactory = $dataObjectFactory;
        $this->apiHelper = $apiHelper;
        $this->bugsnag = $bugsnag;
        $this->metricsClient = $metricsClient;
        $this->decider = $decider;
    }

    /**
     * @param Observer $observer
     */
    public function execute(Observer $observer)
    {
        if (!$this->decider->isTrackingSaveEventsEnabled()) {
            return;
        }

        try {
            $startTime = $this->metricsClient->getCurrentTime();
            $tracking = $observer->getEvent()->getTrack();
            $shipment = $tracking->getShipment();
            $order = $shipment->getOrder();
            $payment = $order->getPayment();

            // If this is not a bolt payment, ignore it
            if (!$payment || $payment->getMethod() != \Bolt\Boltpay\Model\Payment::METHOD_CODE) {
                return;
            }

            $transactionReference = $payment->getAdditionalInformation('transaction_reference');

            $items = [];
            foreach ($shipment->getItemsCollection() as $item) {
                $items[] = $item->getOrderItem()->getProductId();
            }

            $apiKey = $this->configHelper->getApiKey($order->getStoreId());

            $trackingData = [
                'transaction_reference' => $transactionReference,
                'tracking_number'       => $tracking->getTrackNumber(),
                'carrier'               => $tracking->getCarrierCode(),
                'items'                 => $items
            ];

            //Request Data
            $requestData = $this->dataObjectFactory->create();
            $requestData->setApiData($trackingData);
            $requestData->setDynamicApiUrl(ApiHelper::API_CREATE_TRACKING);
            $requestData->setApiKey($apiKey);

            //Build Request
            $request = $this->apiHelper->buildRequest($requestData);
            $result = $this->apiHelper->sendRequest($request);

            if ($result != 200) {
                $this->metricsClient->processMetric("tracking_creation.failure", 1, "tracking_creation.latency", $startTime);
                return;
            }
            $this->metricsClient->processMetric("tracking_creation.success", 1, "tracking_creation.latency", $startTime);
        } catch (Exception $e) {
            $this->bugsnag->notifyException($e);
            $this->metricsClient->processMetric("tracking_creation.failure", 1, "tracking_creation.latency", $startTime);
        } catch (LocalizedException $e) {
            $this->bugsnag->notifyException($e);
            $this->metricsClient->processMetric("tracking_creation.failure", 1, "tracking_creation.latency", $startTime);
        }
    }
}

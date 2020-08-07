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
     * @param Decider $decider
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
     * Convert item options into bolt format
     * @param array item options
     * @return array
     */
    private function getPropertiesByProductOptions($itemOptions)
    {
        if (!isset($itemOptions['attributes_info'])) {
            return [];
        }
        $properties = [];
        foreach ($itemOptions['attributes_info'] as $attributeInfo) {
            // Convert attribute to string if it's a boolean before sending to the Bolt API
            $attributeValue = is_bool($attributeInfo['value']) ? var_export($attributeInfo['value'], true) : $attributeInfo['value'];
            $attributeLabel = $attributeInfo['label'];
            $properties[] = (object) [
                'name' => $attributeLabel,
                'value' => $attributeValue
            ];
        }
        return $properties;
    }

    /**
     * @param Observer $observer
     */
    public function execute(Observer $observer)
    {
        if (!$this->decider->isTrackShipmentEnabled()) {
            return;
        }

        try {
            $startTime = $this->metricsClient->getCurrentTime();
            $tracking = $observer->getEvent()->getTrack();
            $shipment = $tracking->getShipment();
            $order = $shipment->getOrder();
            $payment = $order->getPayment();

            $isNonBoltOrder = !$payment || $payment->getMethod() != \Bolt\Boltpay\Model\Payment::METHOD_CODE;
            if ($isNonBoltOrder) {
                $transactionReference = $order->getBoltTransactionReference();
            } else {
                $transactionReference = $payment->getAdditionalInformation('transaction_reference');
            }

            if (is_null($transactionReference)) {
                $quoteId = $order->getQuoteId();
                $this->bugsnag->notifyError("Missing transaction reference", "QuoteID: {$quoteId}");
                $this->metricsClient->processMetric("tracking_creation.failure", 1, "tracking_creation.latency", $startTime);
                return;
            }

            $items = [];
            foreach ($shipment->getItemsCollection() as $item) {
                $orderItem = $item->getOrderItem();
                if ($orderItem->getParentItem()) {
                    continue;
                }

                $items[] = (object)[
                    'reference' => $orderItem->getProductId(),
                    'options'   => $this->getPropertiesByProductOptions($orderItem->getProductOptions()),
                ];
            }

            $apiKey = $this->configHelper->getApiKey($order->getStoreId());

            $trackingData = [
                'transaction_reference' => $transactionReference,
                'tracking_number'       => $tracking->getTrackNumber(),
                'carrier'               => $tracking->getCarrierCode(),
                'items'                 => $items,
                'is_non_bolt_order'     => $isNonBoltOrder,
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

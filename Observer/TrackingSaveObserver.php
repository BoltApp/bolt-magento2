<?php

namespace Bolt\Boltpay\Observer;

use Bolt\Boltpay\Helper\Api as ApiHelper;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer;
use Bolt\Boltpay\Helper\Config as ConfigHelper;
use Magento\Framework\DataObjectFactory;
use Bolt\Boltpay\Helper\Bugsnag;

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
     * @param ConfigHelper      $configHelper
     * @param DataObjectFactory $dataObjectFactory
     *
     */
    public function __construct(
        ConfigHelper $configHelper,
        DataObjectFactory $dataObjectFactory,
        ApiHelper $apiHelper,
        Bugsnag $bugsnag
    ) {
        $this->configHelper = $configHelper;
        $this->dataObjectFactory = $dataObjectFactory;
        $this->apiHelper = $apiHelper;
        $this->bugsnag = $bugsnag;
    }

    /**
     * @param Observer $observer
     */
    public function execute(Observer $observer)
    {
        try {
            $tracking = $observer->getEvent()->getTrack();
            $shipment = $tracking->getShipment();
            $order = $shipment->getOrder();
            $payment = $order->getPayment();
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
            $this->apiHelper->sendRequest($request);
        } catch (Exception $e) {
            $this->bugsnag->notifyException($e);
        }
    }
}

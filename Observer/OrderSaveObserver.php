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
 * @copyright  Copyright (c) 2017-2021 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Bolt\Boltpay\Observer;

use Bolt\Boltpay\Helper\Api as ApiHelper;
use Bolt\Boltpay\Helper\Bugsnag;
use Bolt\Boltpay\Helper\Config as ConfigHelper;
use Bolt\Boltpay\Helper\Cart as CartHelper;
use Bolt\Boltpay\Helper\FeatureSwitch\Decider;
use Bolt\Boltpay\Helper\MetricsClient;
use Bolt\Boltpay\Helper\Shared\CurrencyUtils;
use Exception;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\DataObjectFactory;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

class OrderSaveObserver implements ObserverInterface
{
    const RESULT_SUCCESS = 'order_update.success';
    const RESULT_FAILURE = 'order_update.failure';
    const RESULT_CACHED = 'order_update.cached';
    
    /** @var CacheInterface */
    private $cache;
    
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
     * @var CartHelper
     */
    private $cartHelper;

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
     * @param CacheInterface    $cache
     * @param ConfigHelper      $configHelper
     * @param DataObjectFactory $dataObjectFactory
     * @param ApiHelper         $apiHelper
     * @param CartHelper        $cartHelper
     * @param Bugsnag           $bugsnag
     * @param MetricsClient     $metricsClient
     * @param Decider           $decider
     */
    public function __construct(
        CacheInterface $cache,
        ConfigHelper $configHelper,
        DataObjectFactory $dataObjectFactory,
        ApiHelper $apiHelper,
        CartHelper $cartHelper,
        Bugsnag $bugsnag,
        MetricsClient $metricsClient,
        Decider $decider
    ) {
        $this->cache = $cache;
        $this->configHelper = $configHelper;
        $this->dataObjectFactory = $dataObjectFactory;
        $this->apiHelper = $apiHelper;
        $this->cartHelper = $cartHelper;
        $this->bugsnag = $bugsnag;
        $this->metricsClient = $metricsClient;
        $this->decider = $decider;
    }

    /**
     * @param Observer $observer
     */
    public function execute(Observer $observer)
    {
        if (!$this->decider->isOrderUpdateEnabled()) {
            return;
        }

        $result = self::RESULT_FAILURE;
        try {
            $startTime = $this->metricsClient->getCurrentTime();
            $order = $observer->getEvent()->getOrder();
            $storeId = $order->getStoreId();

            $currencyCode = $order->getOrderCurrencyCode();
            $items = $order->getAllVisibleItems();
            $itemData = $this->cartHelper->getCartItemsFromItems($items, $currencyCode, $storeId);

            $orderUpdateData = [
                'order_reference' => $order->getQuoteId(),
                'cart' => [
                    'display_id' => $order->getIncrementId(),
                    'total_amount' => CurrencyUtils::toMinor($order->getGrandTotal(), $currencyCode),
                    'tax_amount' => CurrencyUtils::toMinor($order->getTaxAmount(), $currencyCode),
                    'items' => $itemData[0],
                ]
            ];

            $cacheIdentifier = base64_encode(json_encode($orderUpdateData));
            // If we've already sent this update, don't send it again
            $cached = $this->cache->load($cacheIdentifier);
            if ($cached) {
                $result = self::RESULT_CACHED;
                return;
            }
            $this->cache->save(true, $cacheIdentifier);

            $requestData = $this->dataObjectFactory->create();
            $requestData->setApiData($orderUpdateData);
            $requestData->setDynamicApiUrl(ApiHelper::API_UPDATE_ORDER);
            $apiKey = $this->configHelper->getApiKey($storeId);
            $requestData->setApiKey($apiKey);

            $request = $this->apiHelper->buildRequest($requestData);
            $result = $this->apiHelper->sendRequest($request);
            if ($result == 200) {
                $result = self::RESULT_SUCCESS;
            }
        } catch (Exception $e) {
            $this->bugsnag->notifyException($e);
        } finally {
            $this->metricsClient->processMetric($result, 1, 'order_update.latency', $startTime);
        }
    }
}

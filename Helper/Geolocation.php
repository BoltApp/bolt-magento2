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
 * @copyright  Copyright (c) 2017-2024 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Bolt\Boltpay\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Bolt\Boltpay\Helper\Config as ConfigHelper;
use Bolt\Boltpay\Model\HttpClientAdapterFactory;
use Magento\Framework\App\CacheInterface;

/**
 * Boltpay Geolocation helper
 * Look up customer address by IP
 *
 * @SuppressWarnings(PHPMD.TooManyFields)
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class Geolocation extends AbstractHelper
{
    const CACHE_PREFIX = 'bolt_cached_location_';

    /**
     * @var ConfigHelper
     */
    private $configHelper;

    /**
     * @var Bugsnag
     */
    private $bugsnag;

    /**
     * @var HttpClientAdapterFactory
     */
    private $httpClientAdapterFactory;

    /**
     * @var CacheInterface
     */
    private $cache;

    // GeoLocation API endpoint format
    // Tthe first argument is IP, the second one is API key
    private $endpointFormat = "http://api.ipstack.com/%s?access_key=%s";

    /**
     * @param Context $context
     * @param Config $configHelper
     * @param Bugsnag $bugsnag
     * @param HttpClientAdapterFactory $httpClientFactory
     * @param CacheInterface $cache
     */
    public function __construct(
        Context $context,
        ConfigHelper $configHelper,
        Bugsnag $bugsnag,
        HttpClientAdapterFactory $httpClientAdapterFactory,
        CacheInterface $cache
    ) {
        parent::__construct($context);
        $this->configHelper = $configHelper;
        $this->bugsnag = $bugsnag;
        $this->httpClientAdapterFactory = $httpClientAdapterFactory;
        $this->cache = $cache;
    }

    /**
     * @param null $storeId
     *
     * @return string
     */
    private function getApiKey($storeId = null)
    {
        return $this->configHelper->getGeolocationApiKey($storeId);
    }

    /**
     * Make a Geolocation API call
     *
     * @param string $ip        The IP address
     * @param $apiKey           ipstack.com API key
     * @return null|string      JSON formated response
     * @throws \Zend_Http_Client_Exception
     */
    private function getLocationJson($ip, $apiKey)
    {

        $endpoint = sprintf($this->endpointFormat, $ip, $apiKey);

        $client = $this->httpClientAdapterFactory->create();
        $client->setUri($endpoint);
        $client->setConfig(['maxredirects' => 0, 'timeout' => 30]);

        // Dependant on third party API, wrapping in try/catch block.
        // On error notify bugsnag and proceed (return null)
        try {
            return $client->request()->getBody();
        } catch (\Exception $e) {
            $this->bugsnag->notifyException($e);
            return null;
        }
    }

    /**
     * Fetch / cache Geolocation data.
     *
     * @param null $storeId
     *
     * @return null|string      JSON formated response
     * @throws \Zend_Http_Client_Exception
     */
    public function getLocation($storeId = null)
    {

        if (! $apiKey = $this->getApiKey($storeId)) {
            return null;
        }

        $ip = $this->configHelper->getClientIp();

        // try getting location from cache
        $cacheIdentifier = hash('md5', self::CACHE_PREFIX.$ip);
        $locationJson = $this->cache->load($cacheIdentifier);

        // if found return it
        if ($locationJson) {
            return $locationJson;
        }

        // otherwise call the API
        $locationJson = $this->getLocationJson($ip, $apiKey);

        // if no error cache it and return it
        if ($locationJson) {
            $this->cache->save($locationJson, $cacheIdentifier, [], 86400);
            return $locationJson;
        }

        return null;
    }

    /**
     * Calculates the great-circle distance between two points, with the Vincenty formula.
     *
     * @param float $latitudeFrom Latitude of start point in [deg decimal]
     * @param float $longitudeFrom Longitude of start point in [deg decimal]
     * @param float $latitudeTo Latitude of target point in [deg decimal]
     * @param float $longitudeTo Longitude of target point in [deg decimal]
     * @param float $earthRadius Mean earth radius in [m]
     *
     * @return float Distance between points in [m] (same as earthRadius)
     */
    public function calculateCircleDistance(
        $latitudeFrom,
        $longitudeFrom,
        $latitudeTo,
        $longitudeTo,
        $earthRadius = 6371000
    ) {
        // convert from degrees to radians
        $latFrom = deg2rad($latitudeFrom);
        $lonFrom = deg2rad($longitudeFrom);
        $latTo = deg2rad($latitudeTo);
        $lonTo = deg2rad($longitudeTo);

        $lonDelta = $lonTo - $lonFrom;
        $a = pow(cos($latTo) * sin($lonDelta), 2) +
            pow(cos($latFrom) * sin($latTo) - sin($latFrom) * cos($latTo) * cos($lonDelta), 2);
        $b = sin($latFrom) * sin($latTo) + cos($latFrom) * cos($latTo) * cos($lonDelta);

        $angle = atan2(sqrt($a), $b);
        return $angle * $earthRadius;
    }
}

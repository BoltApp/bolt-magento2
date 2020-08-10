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

namespace Bolt\Boltpay\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Bolt\Boltpay\Helper\Config as ConfigHelper;
use Bolt\Boltpay\Helper\Bugsnag;
use Magento\Framework\HTTP\ZendClientFactory;
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
     * @var ZendClientFactory
     */
    private $httpClientFactory;

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
     * @param ZendClientFactory $httpClientFactory
     * @param CacheInterface $cache
     */
    public function __construct(
        Context $context,
        ConfigHelper $configHelper,
        Bugsnag $bugsnag,
        ZendClientFactory $httpClientFactory,
        CacheInterface $cache
    ) {
        parent::__construct($context);
        $this->configHelper = $configHelper;
        $this->bugsnag = $bugsnag;
        $this->httpClientFactory = $httpClientFactory;
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

        $client = $this->httpClientFactory->create();
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
        $cacheIdentifier = md5(self::CACHE_PREFIX.$ip);
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
}

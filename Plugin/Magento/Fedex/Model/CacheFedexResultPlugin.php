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
 * @copyright  Copyright (c) 2023 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
namespace Bolt\Boltpay\Plugin\Magento\Fedex\Model;

use Bolt\Boltpay\Helper\ArrayHelper;
use Bolt\Boltpay\Helper\Bugsnag;
use Bolt\Boltpay\Helper\Hook as HookHelper;
use Bolt\Boltpay\Helper\Session as SessionHelper;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\Serialize\Serializer\Json;

/**
 * Plugin for {@see \Magento\Fedex\Model\Carrier}
 * Tested up to: Magento 2.4.3-p1
 */
class CacheFedexResultPlugin
{
    const BOLT_CACHE_FEDEX_PREFIX  = 'BOLT_CACHE_FEDEX_';
    const CACHE_LIFE_TIME = 1800;

    /**
     * @var Bugsnag
     */
    private $bugsnag;

    /**
     * @var CacheInterface
     */
    private $cache;

    /**
     * @var Json
     */
    private $json;

    /**
     * @var SessionHelper
     */
    private $sessionHelper;

    /**
     * @var Closure proxy
     */
    private $methodCaller;

    /**
     * @param Bugsnag          $bugsnag
     * @param SessionHelper    $sessionHelper
     * @param CacheInterface   $cache
     * @param Json|null        $json
     */
    public function __construct(
        Bugsnag $bugsnag,
        CacheInterface $cache,
        SessionHelper $sessionHelper,
        ?Json $json = null
    ) {
        $this->bugsnag = $bugsnag;
        $this->sessionHelper = $sessionHelper;
        $this->cache = $cache;
        $this->json = $json ?: ObjectManager::getInstance()->get(Json::class);
        // Call protected method with a Closure proxy
        $this->methodCaller = function ($methodName, ...$params) {
            return $this->$methodName(...$params);
        };
    }

    /**
     * Restore cache of Fedex API response.
     *
     * @param \Magento\Fedex\Model\Carrier $subject
     * @param \Magento\Fedex\Model\Carrier $result
     * @param \Magento\Quote\Model\Quote\Address\RateRequest $request
     * @return \Magento\Fedex\Model\Carrier
     */
    public function afterSetRequest(\Magento\Fedex\Model\Carrier $subject, $result, $request)
    {
        if (!HookHelper::$fromBolt || !$subject->canCollectRates()) {
            return $result;
        }
        $this->restoreFedexResponseFromCacheIfExist($subject, \Magento\Fedex\Model\Carrier::RATE_REQUEST_GENERAL);
        $this->restoreFedexResponseFromCacheIfExist($subject, \Magento\Fedex\Model\Carrier::RATE_REQUEST_SMARTPOST);

        return $result;
    }

    /**
     * Save Fedex API response into cache.
     *
     * @param \Magento\Fedex\Model\Carrier $subject
     * @param \Magento\Shipping\Model\Rate\Result|bool|null $result
     * @param \Magento\Quote\Model\Quote\Address\RateRequest $request
     * @return \Magento\Shipping\Model\Rate\Result|bool|null
     */
    public function afterCollectRates(\Magento\Fedex\Model\Carrier $subject, $result, $request)
    {
        if (!HookHelper::$fromBolt || !$subject->canCollectRates() || !$result || $result->getError()) {
            return $result;
        }
        $this->saveFedexResponseToCacheIfExist($subject, \Magento\Fedex\Model\Carrier::RATE_REQUEST_GENERAL);
        $this->saveFedexResponseToCacheIfExist($subject, \Magento\Fedex\Model\Carrier::RATE_REQUEST_SMARTPOST);

        return $result;
    }

    /**
     * Forming request for rate estimation depending to the purpose
     *
     * @param \Magento\Fedex\Model\Carrier $subject
     * @param string $purpose
     * @return string
     */
    private function getFedexRequestString($subject, $purpose)
    {
        $ratesRequest = $this->methodCaller->call($subject, '_formRateRequest', $purpose);
        unset($ratesRequest['RequestedShipment']['ShipTimestamp']);

        return $this->json->serialize($ratesRequest);
    }

    /**
     * Save Fedex API results into cache.
     *
     * @param \Magento\Fedex\Model\Carrier $subject
     * @param string $purpose
     */
    private function saveFedexResponseToCacheIfExist($subject, $purpose)
    {
        $requestString = $this->getFedexRequestString($subject, $purpose);
        $response = $this->methodCaller->call($subject, '_getCachedQuotes', $requestString);
        if ($response !== null) {
            $quoteId = $this->sessionHelper->getCheckoutSession()->getQuote()->getId();
            $cacheId = crc32(self::BOLT_CACHE_FEDEX_PREFIX . $quoteId . $requestString);
            $this->cache->save($this->json->serialize($response), $cacheId, [], self::CACHE_LIFE_TIME);
            $responseStructure = ArrayHelper::saveStructureMixedArrayObject($response);
            $this->cache->save($this->json->serialize($responseStructure), $cacheId . 'structure', [], self::CACHE_LIFE_TIME);
        }
    }

    /**
     * Restore cache of Fedex API results.
     *
     * @param \Magento\Fedex\Model\Carrier $subject
     * @param string $purpose
     */
    private function restoreFedexResponseFromCacheIfExist($subject, $purpose)
    {
        $requestString = $this->getFedexRequestString($subject, $purpose);
        $quoteId = $this->sessionHelper->getCheckoutSession()->getQuote()->getId();
        $cacheId = crc32(self::BOLT_CACHE_FEDEX_PREFIX . $quoteId . $requestString);
        $fedexCache = $this->cache->load($cacheId);
        $fedexStructureCache = $this->cache->load($cacheId . 'structure');
        if (!empty($fedexCache) && !empty($fedexStructureCache)) {
            $fedexCache = $this->json->unserialize($fedexCache);
            $fedexStructureCache = $this->json->unserialize($fedexStructureCache);
            $fedexCache = ArrayHelper::restoreMixedArrayObject($fedexCache, $fedexStructureCache);
            $this->methodCaller->call($subject, '_setCachedQuotes', $requestString, $fedexCache);
        }
    }
}


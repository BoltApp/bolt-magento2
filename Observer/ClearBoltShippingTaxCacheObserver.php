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
 * @copyright  Copyright (c) 2017-2024 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Bolt\Boltpay\Observer;

use Bolt\Boltpay\Model\Api\ShippingMethods;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Bolt\Boltpay\Helper\FeatureSwitch\Decider;

class ClearBoltShippingTaxCacheObserver implements ObserverInterface
{
    /**
     * @var CacheInterface
     */
    private $cache;

    /**
     * @var Decider
     */
    private $featureSwitches;


    /**
     * ClearBoltCacheObserver constructor.
     *
     * @param CacheInterface|null $cache
     * @param Decider $featureSwitches
     */
    public function __construct(
        Decider $featureSwitches,
        ?CacheInterface $cache = null
    )
    {
        $this->cache = $cache ?: \Magento\Framework\App\ObjectManager::getInstance()->get(CacheInterface::class);
        $this->featureSwitches = $featureSwitches;
    }

    /**
     * @param Observer $observer
     *
     * @return $this|void
     */
    public function execute(Observer $observer)
    {
        if ($this->featureSwitches->isAPIDrivenIntegrationEnabled()) {
            return $this;
        }

        $this->cache->clean([ShippingMethods::BOLT_SHIPPING_TAX_CACHE_TAG]);
    }
}

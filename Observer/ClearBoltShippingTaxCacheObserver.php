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
 * @copyright  Copyright (c) 2017-2021 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Bolt\Boltpay\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\App\CacheInterface;
use Bolt\Boltpay\Model\Api\ShippingMethods;

class ClearBoltShippingTaxCacheObserver implements ObserverInterface
{
    /**
     * @var CacheInterface
     */
    private $cache;

    /**
     * ClearBoltCacheObserver constructor.
     * @param CacheInterface|null $cache
     */
    public function __construct(CacheInterface $cache = null)
    {
        $this->cache = $cache ?: \Magento\Framework\App\ObjectManager::getInstance()
            ->get(CacheInterface::class);
    }

    /**
     * @param Observer $observer
     * @return $this|void
     */
    public function execute(Observer $observer)
    {
        $this->cache->clean([ShippingMethods::BOLT_SHIPPING_TAX_CACHE_TAG]);
    }
}

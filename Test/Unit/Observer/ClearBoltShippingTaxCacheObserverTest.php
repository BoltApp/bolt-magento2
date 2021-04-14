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

namespace Bolt\Boltpay\Test\Unit\Observer;

use Bolt\Boltpay\Model\Api\ShippingMethods;
use Bolt\Boltpay\Observer\ClearBoltShippingTaxCacheObserver;
use Bolt\Boltpay\Test\Unit\BoltTestCase;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\Event\Observer;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\Framework\App\CacheInterface;

/**
 * Class ClearBoltShippingTaxCacheObserver
 *
 * @coversDefaultClass \Bolt\Boltpay\Observer\ClearBoltShippingTaxCacheObserver
 */
class ClearBoltShippingTaxCacheObserverTest extends BoltTestCase
{
    /**
     * @var ObjectManager
     */
    private $objectManager;

    /**
     * @var ClearBoltShippingTaxCacheObserver
     */
    private $clearBoltShippingTaxCacheObserver;

    /**
     * @var Observer
     */
    private $eventObserver;

    /**
     * @var CacheInterface
     */
    private $cache;

    public function setUpInternal()
    {
        if (!class_exists('\Magento\TestFramework\Helper\Bootstrap')) {
            return;
        }
        $this->objectManager = Bootstrap::getObjectManager();
        $this->clearBoltShippingTaxCacheObserver = $this->objectManager->create(ClearBoltShippingTaxCacheObserver::class);
        $this->eventObserver = $this->objectManager->create(Observer::class);
        $this->cache = $this->objectManager->create(CacheInterface::class);
    }

    /**
     * @test
     */
    public function execute()
    {
        $this->cache->save('test', 'test', [ShippingMethods::BOLT_SHIPPING_TAX_CACHE_TAG]);
        $this->clearBoltShippingTaxCacheObserver->execute($this->eventObserver);
        $this->assertFalse($this->cache->load('test'));
    }
}

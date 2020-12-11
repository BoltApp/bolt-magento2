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

namespace Bolt\Boltpay\Test\Unit\Observer;

use Bolt\Boltpay\Observer\ClearBoltShippingTaxCacheObserver;
use \PHPUnit\Framework\TestCase;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\Event\Observer;
use Bolt\Boltpay\Model\Api\ShippingMethods;

/**
 * Class ClearBoltShippingTaxCacheObserver
 * @coversDefaultClass \Bolt\Boltpay\Observer\ClearBoltShippingTaxCacheObserver
 */
class ClearBoltShippingTaxCacheObserverTest extends TestCase
{
    /**
     * @var ClearBoltShippingTaxCacheObserver
     */
    protected $currentMock;

    /**
     * @var CacheInterface
     */
    private $cache;

    protected function setUp(): void
    {
        $this->cache = $this->createPartialMock(CacheInterface::class, ['clean', 'remove', 'save', 'load', 'getFrontend']);
        $this->currentMock = new ClearBoltShippingTaxCacheObserver($this->cache);
    }

    /**
     * @test
     */
    public function execute()
    {
        $eventObserver = $this->createMock(Observer::class);
        $this->cache->expects(self::once())->method('clean')
            ->with([ShippingMethods::BOLT_SHIPPING_TAX_CACHE_TAG]);
        $this->currentMock->execute($eventObserver);
    }
}

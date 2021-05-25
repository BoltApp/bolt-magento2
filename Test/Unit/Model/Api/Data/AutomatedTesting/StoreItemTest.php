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

namespace Bolt\Boltpay\Test\Unit\Model\Api\Data\AutomatedTesting;

use Bolt\Boltpay\Test\Unit\BoltTestCase;
use Bolt\Boltpay\Model\Api\Data\AutomatedTesting\StoreItem;

/**
 * Class StoreItemTest
 * @package Bolt\Boltpay\Test\Unit\Model\Api\Data\AutomatedTesting
 */
class StoreItemTest extends BoltTestCase
{
    const STORE_ITEM_NAME = 'name';
    const STORE_PRICE = '10.000';
    const STORE_ITEM_URL = 'https:bolt.com';
    const STORE_TYPE = 'item_type';

    /**
     * @var StoreItem
     */
    protected $storeItem;

    protected function setUpInternal()
    {
        $this->storeItem = new StoreItem();
        $this->storeItem->setName(self::STORE_ITEM_NAME);
        $this->storeItem->setPrice(self::STORE_PRICE);
        $this->storeItem->setItemUrl(self::STORE_ITEM_URL);
        $this->storeItem->setType(self::STORE_TYPE);
    }

    /**
     * @test
     */
    public function setAndGetName()
    {
        $this->assertEquals('name', $this->storeItem->getName());
    }

    /**
     * @test
     */
    public function setAndGetPrice()
    {
        $this->assertEquals('10.000', $this->storeItem->getPrice());
    }

    /**
     * @test
     */
    public function jsonSerialize()
    {
        $result = $this->storeItem->jsonSerialize();
        $this->assertEquals([
            'name' => self::STORE_ITEM_NAME,
            'price' => self::STORE_PRICE,
            'itemUrl' => self::STORE_ITEM_URL,
            'type' => self::STORE_TYPE,
        ], $result);
    }
}

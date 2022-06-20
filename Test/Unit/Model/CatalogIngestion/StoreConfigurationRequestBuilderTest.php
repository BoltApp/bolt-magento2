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
 * @copyright  Copyright (c) 2017-2022 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Bolt\Boltpay\Test\Unit\Model\CatalogIngestion;

use Bolt\Boltpay\Model\CatalogIngestion\StoreConfigurationRequestBuilder;
use Bolt\Boltpay\Test\Unit\BoltTestCase;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\Framework\ObjectManagerInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Class ProductEventRequestBuilderTest
 * @coversDefaultClass \Bolt\Boltpay\Model\CatalogIngestion\StoreConfigurationRequestBuilder
 */
class StoreConfigurationRequestBuilderTest extends BoltTestCase
{
    /**
     * @var ObjectManagerInterface
     */
    private $objectManager;

    /**
     * @var StoreConfigurationRequestBuilder
     */
    private $storeConfigurationRequestBuilder;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;


    /**
     * @inheritDoc
     */
    protected function setUpInternal()
    {
        $this->objectManager = Bootstrap::getObjectManager();
        $this->storeConfigurationRequestBuilder =$this->objectManager->get(StoreConfigurationRequestBuilder::class);
        $this->storeManager = $this->objectManager->get(StoreManagerInterface::class);
    }

    /**
     * @test
     */
    public function testGetRequest()
    {
        $request = $this->storeConfigurationRequestBuilder->getRequest($this->storeManager->getStore()->getCode());
        $apiData = $request->getApiData();
        $expectedApiData = [
            'store_code' => $this->storeManager->getStore()->getCode()
        ];
        $this->assertEquals($apiData, $expectedApiData);
    }
}

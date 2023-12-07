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
 * @copyright  Copyright (c) 2024 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Bolt\Boltpay\Test\Unit\Model;

use Bolt\Boltpay\Model\ExternalCustomerEntity;
use Bolt\Boltpay\Model\ResourceModel;
use Bolt\Boltpay\Test\Unit\BoltTestCase;
use Bolt\Boltpay\Test\Unit\TestHelper;
use Magento\Framework\App\ObjectManager;
use Magento\TestFramework\Helper\Bootstrap;

class ExternalCustomerEntityTest extends BoltTestCase
{

    /**
     * @var \Bolt\Boltpay\Model\ExternalCustomerEntity
     */
    private $externalCustomerEntity;

    /**
     * @var ObjectManager
     */
    private $objectManager;

    /**
     * @inheritdoc
     */
    public function setUpInternal()
    {
        if (!class_exists('\Magento\TestFramework\Helper\Bootstrap')) {
            return;
        }
        $this->objectManager = Bootstrap::getObjectManager();
        $this->externalCustomerEntity = $this->objectManager->create(ExternalCustomerEntity::class);
    }

    /**
     * @test
     */
    public function testConstruct()
    {
        TestHelper::invokeMethod($this->externalCustomerEntity, '_construct');
        self::assertEquals(ResourceModel\ExternalCustomerEntity::class, $this->externalCustomerEntity->getResourceName());
    }

    /**
     * @test
     */
    public function setAndGetExternalID()
    {
        $this->externalCustomerEntity->setExternalID('test_external_id');
        $this->assertEquals('test_external_id', $this->externalCustomerEntity->getExternalID());
    }

    /**
     * @test
     */
    public function setAndGetCustomerID()
    {
        $this->externalCustomerEntity->setCustomerID(123);
        $this->assertEquals(123, $this->externalCustomerEntity->getCustomerID());
    }
}

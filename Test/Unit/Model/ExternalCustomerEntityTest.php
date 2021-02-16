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
 * @copyright  Copyright (c) 2020 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Bolt\Boltpay\Test\Unit\Model;

use Bolt\Boltpay\Model\ExternalCustomerEntity;
use Bolt\Boltpay\Model\ResourceModel;
use Bolt\Boltpay\Test\Unit\BoltTestCase;
use Bolt\Boltpay\Test\Unit\TestHelper;
use PHPUnit_Framework_MockObject_MockObject as MockObject;

class ExternalCustomerEntityTest extends BoltTestCase
{
    /**
     * @var ExternalCustomerEntity|MockObject
     */
    private $externalCustomerEntityMock;

    /**
     * @inheritdoc
     */
    public function setUpInternal()
    {
        $this->externalCustomerEntityMock = $this->getMockBuilder(ExternalCustomerEntity::class)
            ->disableOriginalConstructor()
            ->setMethods(['_init'])
            ->getMock();
    }

    /**
     * @test
     */
    public function testConstruct()
    {
        $this->externalCustomerEntityMock->expects($this->once())->method('_init')
            ->with(ResourceModel\ExternalCustomerEntity::class)
            ->willReturnSelf();
        TestHelper::invokeMethod($this->externalCustomerEntityMock, '_construct');
    }

    /**
     * @test
     */
    public function setAndGetExternalID()
    {
        $this->externalCustomerEntityMock->setExternalID('test_external_id');
        $this->assertEquals('test_external_id', $this->externalCustomerEntityMock->getExternalID());
    }

    /**
     * @test
     */
    public function setAndGetCustomerID()
    {
        $this->externalCustomerEntityMock->setCustomerID(123);
        $this->assertEquals(123, $this->externalCustomerEntityMock->getCustomerID());
    }
}

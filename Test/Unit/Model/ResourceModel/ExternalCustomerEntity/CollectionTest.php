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

namespace Bolt\Boltpay\Test\Unit\Model\ResourceModel\ExternalCustomerEntity;

use Bolt\Boltpay\Model\ResourceModel\ExternalCustomerEntity\Collection as ExternalCustomerEntityCollection;
use Bolt\Boltpay\Test\Unit\BoltTestCase;
use Bolt\Boltpay\Test\Unit\TestHelper;
use PHPUnit_Framework_MockObject_MockObject as MockObject;

class CollectionTest extends BoltTestCase
{
    /**
     * @var ExternalCustomerEntityCollection|MockObject
     */
    private $externalCustomerEntityCollectionMock;

    /**
     * Setup for CollectionTest Class
     */
    public function setUpInternal()
    {
        $this->externalCustomerEntityCollectionMock = $this->getMockBuilder(ExternalCustomerEntityCollection::class)
            ->disableOriginalConstructor()
            ->setMethods(['_init', 'addFilter', 'getSize', 'getFirstItem'])
            ->getMock();
    }

    /**
     * @test
     */
    public function construct()
    {
        $this->externalCustomerEntityCollectionMock->expects($this->once())->method('_init')
            ->with('Bolt\Boltpay\Model\ExternalCustomerEntity', 'Bolt\Boltpay\Model\ResourceModel\ExternalCustomerEntity')
            ->willReturnSelf();
        TestHelper::invokeMethod($this->externalCustomerEntityCollectionMock, '_construct');
        $this->assertTrue(class_exists('Bolt\Boltpay\Model\ExternalCustomerEntity'));
        $this->assertTrue(class_exists('Bolt\Boltpay\Model\ResourceModel\ExternalCustomerEntity'));
    }

    /**
     * @test
     */
    public function getExternalCustomerEntityByExternalID_returnsCorrectObject_ifCollectionIsNotEmpty()
    {
        $this->externalCustomerEntityCollectionMock->expects(self::once())->method('addFilter')->with('external_id', 'test_external_id')->willReturnSelf();
        $this->externalCustomerEntityCollectionMock->expects(self::once())->method('getSize')->willReturn(1);
        $this->externalCustomerEntityCollectionMock->expects(self::once())->method('getFirstItem')->willReturnSelf();
        $this->assertNotEquals(
            null,
            TestHelper::invokeMethod($this->externalCustomerEntityCollectionMock, 'getExternalCustomerEntityByExternalID', ['test_external_id'])
        );
    }

    /**
     * @test
     */
    public function getExternalCustomerEntityByExternalID_returnsNull_ifNoItemsFound()
    {
        $this->externalCustomerEntityCollectionMock->expects(self::once())->method('addFilter')->with('external_id', 'test_external_id')->willReturnSelf();
        $this->externalCustomerEntityCollectionMock->expects(self::once())->method('getSize')->willReturn(0);
        $this->externalCustomerEntityCollectionMock->expects(self::never())->method('getFirstItem');
        $this->assertEquals(
            null,
            TestHelper::invokeMethod($this->externalCustomerEntityCollectionMock, 'getExternalCustomerEntityByExternalID', ['test_external_id'])
        );
    }
}

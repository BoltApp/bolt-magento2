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
use Bolt\Boltpay\Model\ExternalCustomerEntityFactory;
use Bolt\Boltpay\Model\ExternalCustomerEntityRepository;
use Bolt\Boltpay\Model\ResourceModel\ExternalCustomerEntity as ExternalCustomerEntityResource;
use Bolt\Boltpay\Model\ResourceModel\ExternalCustomerEntity\Collection as ExternalCustomerEntityCollection;
use Bolt\Boltpay\Model\ResourceModel\ExternalCustomerEntity\CollectionFactory as ExternalCustomerEntityCollectionFactory;
use Bolt\Boltpay\Test\Unit\BoltTestCase;
use Bolt\Boltpay\Test\Unit\TestHelper;
use Magento\Framework\Exception\NoSuchEntityException;
use PHPUnit_Framework_MockObject_MockObject as MockObject;

class ExternalCustomerEntityRepositoryTest extends BoltTestCase
{
    /**
     * @var ExternalCustomerEntityFactory|MockObject
     */
    private $externalCustomerEntityFactoryMock;

    /**
     * @var ExternalCustomerEntityCollectionFactory|MockObject
     */
    private $externalCustomerEntityCollectionFactoryMock;

    /**
     * @var ExternalCustomerEntityResource|MockObject
     */
    private $externalCustomerEntityResourceMock;

    /**
     * @var ExternalCustomerEntityRepository|MockObject
     */
    private $currentMock;

    /**
     * @inheritdoc
     */
    public function setUpInternal()
    {
        $this->externalCustomerEntityFactoryMock = $this->createMock(ExternalCustomerEntityFactory::class);
        $this->externalCustomerEntityCollectionFactoryMock = $this->createMock(ExternalCustomerEntityCollectionFactory::class);
        $this->externalCustomerEntityResourceMock = $this->createMock(ExternalCustomerEntityResource::class);
        $this->currentMock = $this->getMockBuilder(ExternalCustomerEntityRepository::class)
            ->setMethods(['getByExternalID'])
            ->setConstructorArgs([
                $this->externalCustomerEntityFactoryMock,
                $this->externalCustomerEntityCollectionFactoryMock,
                $this->externalCustomerEntityResourceMock
            ])
            ->getMock();
    }

    /**
     * @test
     */
    public function getByExternalID_throwsNoSuchEntityException_ifNotFound()
    {
        $externalCustomerEntityCollectionMock = $this->createMock(ExternalCustomerEntityCollection::class);
        $this->externalCustomerEntityCollectionFactoryMock->expects(static::once())->method('create')->willReturn($externalCustomerEntityCollectionMock);
        $externalCustomerEntityCollectionMock->expects(static::once())->method('getExternalCustomerEntityByExternalID')->with('test_external_id')->willReturn(null);
        $this->expectException(NoSuchEntityException::class);
        TestHelper::invokeMethod($this->currentMock, 'getByExternalID', ['test_external_id']);
    }

    /**
     * @test
     */
    public function getByExternalID_returnsCorrectResult_ifFound()
    {
        $externalCustomerEntityCollectionMock = $this->createMock(ExternalCustomerEntityCollection::class);
        $this->externalCustomerEntityCollectionFactoryMock->expects(static::once())->method('create')->willReturn($externalCustomerEntityCollectionMock);
        $externalCustomerEntityMock = $this->createMock(ExternalCustomerEntity::class);
        $externalCustomerEntityCollectionMock->expects(static::once())->method('getExternalCustomerEntityByExternalID')
            ->with('test_external_id')
            ->willReturn($externalCustomerEntityMock);
        $this->assertEquals(
            $externalCustomerEntityMock,
            TestHelper::invokeMethod($this->currentMock, 'getByExternalID', ['test_external_id'])
        );
    }

    /**
     * @test
     */
    public function upsert_createsNewEntry_ifNotFound()
    {
        $this->currentMock->expects(static::once())->method('getByExternalID')->with('test_external_id')->willReturn(null);
        $externalCustomerEntityMock = $this->createMock(ExternalCustomerEntity::class);
        $this->externalCustomerEntityFactoryMock->expects(static::once())->method('create')->willReturn($externalCustomerEntityMock);
        $externalCustomerEntityMock->expects(static::once())->method('setExternalID')->with('test_external_id');
        $externalCustomerEntityMock->expects(static::once())->method('setCustomerID')->with(123);
        $this->externalCustomerEntityResourceMock->expects(static::once())->method('save')
            ->with($externalCustomerEntityMock)
            ->willReturn($externalCustomerEntityMock);
        $this->assertEquals(
            $externalCustomerEntityMock,
            TestHelper::invokeMethod($this->currentMock, 'upsert', ['test_external_id', 123])
        );
    }

    /**
     * @test
     */
    public function upsert_updatesEntry_ifFound()
    {
        $externalCustomerEntityMock = $this->createMock(ExternalCustomerEntity::class);
        $this->currentMock->expects(static::once())->method('getByExternalID')->with('test_external_id')->willReturn($externalCustomerEntityMock);
        $this->externalCustomerEntityFactoryMock->expects(static::never())->method('create');
        $externalCustomerEntityMock->expects(static::never())->method('setExternalID');
        $externalCustomerEntityMock->expects(static::once())->method('setCustomerID')->with(123);
        $this->externalCustomerEntityResourceMock->expects(static::once())->method('save')
            ->with($externalCustomerEntityMock)
            ->willReturn($externalCustomerEntityMock);
        $this->assertEquals(
            $externalCustomerEntityMock,
            TestHelper::invokeMethod($this->currentMock, 'upsert', ['test_external_id', 123])
        );
    }
}

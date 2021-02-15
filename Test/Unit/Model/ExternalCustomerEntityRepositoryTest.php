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

use Bolt\Boltpay\Api\Data\ExternalCustomerEntityInterface;
use Bolt\Boltpay\Model\ExternalCustomerEntity;
use Bolt\Boltpay\Model\ExternalCustomerEntityFactory;
use Bolt\Boltpay\Model\ExternalCustomerEntityRepository;
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
     * @var ExternalCustomerEntityRepository|MockObject
     */
    private $externalCustomerEntityRepositoryMock;

    /**
     * @inheritdoc
     */
    public function setUpInternal()
    {
        $this->externalCustomerEntityFactoryMock = $this->createMock(ExternalCustomerEntityFactory::class);
        $this->externalCustomerEntityCollectionFactoryMock = $this->createMock(ExternalCustomerEntityCollectionFactory::class);
        $this->externalCustomerEntityRepositoryMock = $this->getMockBuilder(ExternalCustomerEntityRepository::class)
            ->setMethods(['save'])
            ->setConstructorArgs([
                $this->externalCustomerEntityFactoryMock,
                $this->externalCustomerEntityCollectionFactoryMock
            ])
            ->getMock();
    }

    /**
     * @test
     */
    public function getByExternalID_throwsNoSuchEntityException_ifNotFound()
    {
        $externalCustomerEntityCollectionMock = $this->createMock(ExternalCustomerEntityCollection::class);
        $this->externalCustomerEntityCollectionFactoryMock->expects(self::once())->method('create')->willReturn($externalCustomerEntityCollectionMock);
        $externalCustomerEntityCollectionMock->expects(self::once())->method('getExternalCustomerEntityByExternalID')->with('test_external_id')->willReturn(null);
        $this->expectException(NoSuchEntityException::class);
        TestHelper::invokeMethod($this->externalCustomerEntityRepositoryMock, 'getByExternalID', ['test_external_id']);
    }

    /**
     * @test
     */
    public function getByExternalID_returnsCorrectResult_ifFound()
    {
        $externalCustomerEntityCollectionMock = $this->createMock(ExternalCustomerEntityCollection::class);
        $this->externalCustomerEntityCollectionFactoryMock->expects(self::once())->method('create')->willReturn($externalCustomerEntityCollectionMock);
        $externalCustomerEntityInterfaceMock = $this->createMock(ExternalCustomerEntityInterface::class);
        $externalCustomerEntityCollectionMock->expects(self::once())->method('getExternalCustomerEntityByExternalID')
            ->with('test_external_id')
            ->willReturn($externalCustomerEntityInterfaceMock);
        $this->assertEquals(
            $externalCustomerEntityInterfaceMock,
            TestHelper::invokeMethod($this->externalCustomerEntityRepositoryMock, 'getByExternalID', ['test_external_id'])
        );
    }

    /**
     * @test
     */
    public function create()
    {
        $externalCustomerEntityMock = $this->createMock(ExternalCustomerEntity::class);
        $this->externalCustomerEntityFactoryMock->expects(self::once())->method('create')->willReturn($externalCustomerEntityMock);
        $externalCustomerEntityMock->expects(self::once())->method('setExternalID')->with('test_external_id');
        $externalCustomerEntityMock->expects(self::once())->method('setCustomerID')->with(123);
        $externalCustomerEntityInterfaceMock = $this->createMock(ExternalCustomerEntityInterface::class);
        $this->externalCustomerEntityRepositoryMock->expects(self::once())->method('save')
            ->with($externalCustomerEntityMock)
            ->willReturn($externalCustomerEntityInterfaceMock);
        $this->assertEquals(
            $externalCustomerEntityInterfaceMock,
            TestHelper::invokeMethod($this->externalCustomerEntityRepositoryMock, 'create', ['test_external_id', 123])
        );
    }
}

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

namespace Bolt\Boltpay\Test\Unit\Model\ResourceModel\Log;

use Bolt\Boltpay\Model\ResourceModel\Log\Collection;
use PHPUnit\Framework\TestCase;
use Bolt\Boltpay\Test\Unit\TestHelper;

class CollectionTest extends TestCase
{
    const TRANSACTION_ID = '1111';
    const HOOK_TYPE = 'pending';

    /**
     * @var \Bolt\Boltpay\Model\ResourceModel\Log\Collection
     */
    private $logCollectionMock;

    /**
     * Setup for CollectionTest Class
     */
    public function setUp()
    {
        $this->logCollectionMock = $this->getMockBuilder(Collection::class)
            ->disableOriginalConstructor()
            ->setMethods(['_init', 'addFilter', 'getSize', 'getFirstItem'])
            ->getMock();
    }

    /**
     * @test
     */
    public function construct()
    {
        $this->logCollectionMock->expects($this->once())->method('_init')
            ->with('Bolt\Boltpay\Model\Log', 'Bolt\Boltpay\Model\ResourceModel\Log')
            ->willReturnSelf();

        TestHelper::invokeMethod($this->logCollectionMock, '_construct');

        $this->assertTrue(class_exists('Bolt\Boltpay\Model\ResourceModel\Log'));
        $this->assertTrue(class_exists('Bolt\Boltpay\Model\Log'));
    }

    /**
     * @test
     */
    public function getLogByTransactionId()
    {
        $this->logCollectionMock->expects(self::any())->method('addFilter')->willReturnSelf();
        $this->logCollectionMock->expects(self::once())->method('getSize')->willReturn(1);
        $this->logCollectionMock->expects(self::once())->method('getFirstItem')->willReturnSelf();
        $this->logCollectionMock->getLogByTransactionId(self::TRANSACTION_ID, self::HOOK_TYPE);
    }

    /**
     * @test
     */
    public function getLogByTransactionId_returnNoItems()
    {
        $this->logCollectionMock->expects(self::any())->method('addFilter')->willReturnSelf();
        $this->logCollectionMock->expects(self::once())->method('getSize')->willReturn(0);
        $this->logCollectionMock->expects(self::never())->method('getFirstItem')->willReturnSelf();
        $result = $this->logCollectionMock->getLogByTransactionId(self::TRANSACTION_ID, self::HOOK_TYPE);
        $this->assertFalse($result);
    }
}
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

namespace Bolt\Boltpay\Test\Unit\Model;

use Bolt\Boltpay\Model\WebhookLog;
use PHPUnit\Framework\TestCase;
use Bolt\Boltpay\Test\Unit\TestHelper;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Framework\Model\Context;
use Magento\Framework\Registry;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Data\Collection\AbstractDb;

class WebhookLogTest extends TestCase
{
    const TRANSACTION_ID = '1111';
    const HOOK_TYPE = 'pending';

    /**
     * @var WebhookLog
     */
    private $webhookLogMock;

    /**
     * @var DateTime
     */
    private $coreDate;

    /**
     * @var Context
     */
    private $context;

    /**
     * @var Registry
     */
    private $registry;

    /**
     * @var AbstractResource
     */
    private $resource;

    /**
     * @var AbstractDb
     */
    private $resourceCollection;

    public function setUp()
    {
        $this->coreDate = $this->createPartialMock(DateTime::class, ['gmtDate']);
        $this->context = $this->createMock(Context::class);
        $this->registry = $this->createMock(Registry::class);
        $this->resource =  $this->createMock(AbstractResource::class);
        $this->resourceCollection =  $this->createMock(AbstractDb::class);

        $this->webhookLogMock = $this->getMockBuilder(WebhookLog::class)
            ->setConstructorArgs([
                $this->coreDate,
                $this->context,
                $this->registry,
                $this->resource,
                $this->resourceCollection,
                []
            ])
            ->setMethods([
                '_init',
                'setTransactionId',
                'setHookType',
                'getNumberOfMissingQuoteFailedHooks',
                'setNumberOfMissingQuoteFailedHooks',
                'save',
                'load',
                'setUpdatedAt'
            ])
            ->getMock();
    }

    /**
     * @test
     */
    public function construct()
    {
        $this->webhookLogMock->expects($this->once())->method('_init')
            ->with('Bolt\Boltpay\Model\ResourceModel\WebhookLog')
            ->willReturnSelf();

        TestHelper::invokeMethod($this->webhookLogMock, '_construct');
        $this->assertTrue(class_exists('Bolt\Boltpay\Model\ResourceModel\WebhookLog'));
    }

    /**
     * @test
     */
    public function recordAttempt()
    {
        $this->coreDate->expects($this->once())->method('gmtDate')->willReturnSelf();
        $this->webhookLogMock->expects($this->once())->method('setTransactionId')->with(self::TRANSACTION_ID)->willReturnSelf();
        $this->webhookLogMock->expects($this->once())->method('setHookType')->with(self::HOOK_TYPE)->willReturnSelf();
        $this->webhookLogMock->expects($this->once())->method('setNumberOfMissingQuoteFailedHooks')->with(1)->willReturnSelf();
        $this->webhookLogMock->expects($this->once())->method('setUpdatedAt')->willReturnSelf();
        $this->webhookLogMock->expects($this->once())->method('save')->willReturnSelf();
        $this->webhookLogMock->recordAttempt(self::TRANSACTION_ID, self::HOOK_TYPE);
    }

    /**
     * @test
     */
    public function incrementAttemptCount()
    {
        $this->coreDate->expects($this->once())->method('gmtDate')->willReturnSelf();
        $this->webhookLogMock->expects($this->once())->method('getNumberOfMissingQuoteFailedHooks')->willReturn(1);
        $this->webhookLogMock->expects($this->once())->method('setNumberOfMissingQuoteFailedHooks')->with(2)->willReturnSelf();
        $this->webhookLogMock->expects($this->once())->method('setUpdatedAt')->willReturnSelf();
        $this->webhookLogMock->expects($this->once())->method('save')->willReturnSelf();
        $this->webhookLogMock->incrementAttemptCount();
    }
}

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

use Bolt\Boltpay\Model\WebhookLog;
use PHPUnit\Framework\TestCase;
use Bolt\Boltpay\Test\Unit\TestHelper;

class WebhookLogTest extends TestCase
{
    const TRANSACTION_ID = '1111';
    const HOOK_TYPE = 'pending';
    const NUMBER_OF_MISSING_QUOTE_FAILED_HOOKS = 1;
    /**
     * @var \Bolt\Boltpay\Model\WebhookLog
     */
    private $webhookLogMock;

    /**
     * Setup for CustomerCreditCardTest Class
     */
    public function setUp()
    {
        $this->webhookLogMock = $this->getMockBuilder(WebhookLog::class)
            ->disableOriginalConstructor()
            ->setMethods(['_init', 'setTransactionId', 'setHookType', 'setNumberOfMissingQuoteFailedHooks', 'save', 'load'])
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
        $this->webhookLogMock->expects($this->once())->method('setTransactionId')->with(self::TRANSACTION_ID)->willReturnSelf();
        $this->webhookLogMock->expects($this->once())->method('setHookType')->with(self::HOOK_TYPE)->willReturnSelf();
        $this->webhookLogMock->expects($this->once())->method('setNumberOfMissingQuoteFailedHooks')->with(1)->willReturnSelf();
        $this->webhookLogMock->expects($this->once())->method('save')->willReturnSelf();
        $this->webhookLogMock->recordAttempt(self::TRANSACTION_ID, self::HOOK_TYPE);
    }

    /**
     * @test
     */
    public function incrementAttemptCount()
    {
        $this->webhookLogMock->expects($this->once())->method('load')->with(self::TRANSACTION_ID)->willReturnSelf();
        $this->webhookLogMock->expects($this->once())->method('setNumberOfMissingQuoteFailedHooks')->with(2)->willReturnSelf();
        $this->webhookLogMock->expects($this->once())->method('save')->willReturnSelf();
        $this->webhookLogMock->incrementAttemptCount(self::TRANSACTION_ID, self::NUMBER_OF_MISSING_QUOTE_FAILED_HOOKS);
    }
}
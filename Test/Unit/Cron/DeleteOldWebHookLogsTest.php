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

namespace Bolt\Boltpay\Test\Unit\Helper;

use PHPUnit\Framework\TestCase;
use Bolt\Boltpay\Model\ResourceModel\WebhookLogFactory;
use Bolt\Boltpay\Cron\DeleteOldWebHookLogs;

/**
 * Class DeleteOldWebHookLogsTest
 * @package Bolt\Boltpay\Test\Unit\Helper
 */
class DeleteOldWebHookLogsTest extends TestCase
{

    /**
     * @var DeleteOldWebHookLogs
     */
    private $currentMock;

    /**
     * @var WebhookLogFactory
     */
    private $webhookLogFactory;

    public function setUp()
    {
        $this->webhookLogFactory = $this->createPartialMock(
            WebhookLogFactory::class,
            ['create', 'deleteOldAttempts']
        );

        $this->currentMock = $this->getMockBuilder(DeleteOldWebHookLogs::class)
            ->setConstructorArgs([
               $this->webhookLogFactory
            ])
            ->enableProxyingToOriginalMethods()
            ->getMock();
    }

    /**
     * @test
     */
    public function execute()
    {
        $this->webhookLogFactory->expects(self::once())->method('create')->willReturnSelf();
        $this->webhookLogFactory->expects(self::once())->method('deleteOldAttempts')->willReturnSelf();
        ;
        $this->currentMock->execute();
    }
}

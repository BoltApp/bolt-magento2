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
 * @copyright  Copyright (c) 2017-2024 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Bolt\Boltpay\Test\Unit\Helper;

use Bolt\Boltpay\Test\Unit\BoltTestCase;
use Bolt\Boltpay\Model\WebhookLogFactory;
use Bolt\Boltpay\Cron\DeleteOldWebHookLogs;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Bolt\Boltpay\Model\ResourceModel\WebhookLog\CollectionFactory;
use Magento\TestFramework\Helper\Bootstrap;

/**
 * Class DeleteOldWebHookLogsTest
 * @package Bolt\Boltpay\Test\Unit\Helper
 */
class DeleteOldWebHookLogsTest extends BoltTestCase
{
    /**
     * @var ObjectManager
     */
    private $objectManager;

    /**
     * @var DeleteOldWebHookLogs
     */
    private $deleteOldWebHookLogs;

    /**
     * @var WebhookLogFactory
     */
    private $webhookLogFactory;

    /**
     * @var CollectionFactory
     */
    private $collectionFactory;

    /**
     * @var DateTime
     */
    private $coreDate;

    public function setUpInternal()
    {
        if (!class_exists('\Magento\TestFramework\Helper\Bootstrap')) {
            return;
        }
        $this->objectManager = Bootstrap::getObjectManager();
        $this->deleteOldWebHookLogs = $this->objectManager->create(DeleteOldWebHookLogs::class);
        $this->webhookLogFactory = $this->objectManager->create(WebhookLogFactory::class);
        $this->collectionFactory = $this->objectManager->create(CollectionFactory::class);
        $this->coreDate = $this->objectManager->create(DateTime::class);
    }

    /**
     * @test
     */
    public function execute()
    {
        $this->webhookLogFactory->create()->setTransactionId('XXXX')
            ->setHookType('pending')
            ->setNumberOfMissingQuoteFailedHooks(1)
            ->setUpdatedAt($this->coreDate->gmtDate(null, time() - 86400 * 31))
            ->save();

        $this->deleteOldWebHookLogs->execute();

        self::assertEquals(0, $this->collectionFactory->create()->getSize());
    }
}

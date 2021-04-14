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

namespace Bolt\Boltpay\Test\Unit\Model\ResourceModel\WebhookLog;

use Bolt\Boltpay\Model\ResourceModel\WebhookLog\Collection;
use Bolt\Boltpay\Model\WebhookLogFactory;
use Bolt\Boltpay\Test\Unit\BoltTestCase;
use Magento\TestFramework\Helper\Bootstrap;

class CollectionTest extends BoltTestCase
{
    const TRANSACTION_ID = '11112';
    const HOOK_TYPE = 'pending';

    /**
     * @var \Bolt\Boltpay\Model\ResourceModel\WebhookLog\Collection
     */
    private $webhookLogCollection;

    /**
     * @var WebhookLogFactory
     */
    private $webhookLogFactory;

    private $objectManager;

    /**
     * Setup for CollectionTest Class
     */
    public function setUpInternal()
    {
        if (!class_exists('\Magento\TestFramework\Helper\Bootstrap')) {
            return;
        }
        $this->objectManager = Bootstrap::getObjectManager();
        $this->webhookLogCollection = $this->objectManager->create(Collection::class);
        $this->webhookLogFactory = $this->objectManager->create(WebhookLogFactory::class);
    }


    /**
     * @test
     */
    public function getWebhookLogByTransactionId_returnNoItems()
    {
        $result = $this->webhookLogCollection->getWebhookLogByTransactionId(self::TRANSACTION_ID, 'notfound');
        $this->assertFalse($result);
    }

    /**
     * @test
     */
    public function getWebhookLogByTransactionId()
    {
        $webhookFactory = $this->webhookLogFactory->create()->recordAttempt(self::TRANSACTION_ID, self::HOOK_TYPE);;
        $result = $this->webhookLogCollection->getWebhookLogByTransactionId(self::TRANSACTION_ID, self::HOOK_TYPE);
        $this->assertEquals($webhookFactory->getId(), $result->getId());
    }
}

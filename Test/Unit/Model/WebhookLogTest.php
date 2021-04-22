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

namespace Bolt\Boltpay\Test\Unit\Model;

use Bolt\Boltpay\Model\WebhookLog;
use Bolt\Boltpay\Test\Unit\BoltTestCase;
use Bolt\Boltpay\Model\ResourceModel\WebhookLog\CollectionFactory;
use Bolt\Boltpay\Model\WebhookLogFactory;
use Magento\TestFramework\Helper\Bootstrap;

class WebhookLogTest extends BoltTestCase
{
    const TRANSACTION_ID = '1111';
    const HOOK_TYPE = 'pending';

    /**
     * @var WebhookLog
     */
    private $webhookLogFactory;

    private $objectManager;

    /**
     * @var CollectionFactory
     */
    private $collectionFactory;

    public function setUpInternal()
    {
        if (!class_exists('\Magento\TestFramework\Helper\Bootstrap')) {
            return;
        }
        $this->objectManager = Bootstrap::getObjectManager();
        $this->collectionFactory = $this->objectManager->create(CollectionFactory::class);
        $this->webhookLogFactory = $this->objectManager->create(WebhookLogFactory::class);
        $this->webhookLogFactory->create()->recordAttempt(self::TRANSACTION_ID, self::HOOK_TYPE);

    }

    /**
     * @test
     */
    public function recordAttempt()
    {
        self::assertEquals(1, $this->collectionFactory->create()->addFilter('transaction_id', self::TRANSACTION_ID)->getSize());
    }

    /**
     * @test
     */
    public function incrementAttemptCount()
    {
        $webhookLogFactory = $this->collectionFactory->create()
            ->addFilter('transaction_id', self::TRANSACTION_ID)
            ->getFirstItem();

        $webhookLogFactory->incrementAttemptCount();
        self::assertEquals(2, $webhookLogFactory->getNumberOfMissingQuoteFailedHooks());
    }
}

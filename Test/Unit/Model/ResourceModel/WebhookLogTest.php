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
 * @copyright  Copyright (c) 2017-2023 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Bolt\Boltpay\Test\Unit\Model\ResourceModel;

use Bolt\Boltpay\Model\ResourceModel\WebhookLog;
use Bolt\Boltpay\Test\Unit\BoltTestCase;
use Magento\TestFramework\Helper\Bootstrap;

class WebhookLogTest extends BoltTestCase
{
    /**
     * @var WebhookLog
     */
    private $webhookLog;

    private $objectManager;

    /**
     * @inheritdoc
     */
    public function setUpInternal()
    {
        if (!class_exists('\Magento\TestFramework\Helper\Bootstrap')) {
            return;
        }
        $this->objectManager = Bootstrap::getObjectManager();
        $this->webhookLog = $this->objectManager->create(WebhookLog::class);
    }

    /**
     * @test
     */
    public function construct()
    {
        self::assertEquals('bolt_webhook_log', $this->webhookLog->getMainTable());
        self::assertEquals('id', $this->webhookLog->getIdFieldName());
    }
}

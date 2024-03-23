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

namespace Bolt\Boltpay\Cron;
use Bolt\Boltpay\Helper\FeatureSwitch\Decider;
class DeleteOldWebHookLogs
{
    /**
     * @var \Bolt\Boltpay\Model\ResourceModel\WebhookLogFactory
     */
    protected $webhookLogFactory;

    /**
     * @var Decider
     */
    private $featureSwitches;

    /**
     * DeleteOldWebHookLogs constructor.
     * @param \Bolt\Boltpay\Model\ResourceModel\WebhookLogFactory $webhookLogFactory
     */
    public function __construct(
        \Bolt\Boltpay\Model\ResourceModel\WebhookLogFactory $webhookLogFactory,
        Decider $featureSwitches
    ) {
        $this->webhookLogFactory = $webhookLogFactory;
        $this->featureSwitches = $featureSwitches;
    }

    /**
     * Delete attempts older than 30 days
     * @return $this
     */
    public function execute()
    {
        if ($this->featureSwitches->isAPIDrivenIntegrationEnabled()) {
            return $this;
        }

        $this->webhookLogFactory->create()->deleteOldAttempts();
        return $this;
    }
}

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
 * @copyright  Copyright (c) 2017-2022 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Bolt\Boltpay\Model\CatalogIngestion\Command;

use Bolt\Boltpay\Api\ProductEventManagerInterface;
use Bolt\Boltpay\Api\Data\ProductEventInterfaceFactory;
use Bolt\Boltpay\Helper\Config;
use Bolt\Boltpay\Logger\Logger;
use Magento\Framework\Stdlib\DateTime;

/**
 * Instant product event publisher
 */
class RunInstantProductEvent
{
    /**
     * @var ProductEventManagerInterface
     */
    private $productEventManager;

    /**
     * @var ProductEventInterfaceFactory
     */
    private $productEventFactory;

    /**
     * @var Config
     */
    private $config;

    /**
     * @var DateTime
     */
    private $dateTime;

    /**
     * @var Logger
     */
    private $logger;

    /**
     * @param ProductEventManagerInterface $productEventManager
     * @param ProductEventInterfaceFactory $productEventFactory
     * @param Config $config
     * @param DateTime $dateTime
     * @param Logger $logger
     */
    public function __construct(
        ProductEventManagerInterface $productEventManager,
        ProductEventInterfaceFactory $productEventFactory,
        Config $config,
        DateTime $dateTime,
        Logger $logger
    ) {
        $this->productEventManager = $productEventManager;
        $this->productEventFactory = $productEventFactory;
        $this->config = $config;
        $this->dateTime = $dateTime;
        $this->logger = $logger;
    }

    /**
     * Run product event instant update (async/sync mode configuration based)
     *
     * @param int $productId
     * @param string $type
     * @param int|null $websiteId
     * @return void
     */
    public function execute(int $productId, string $type, int $websiteId = null): void
    {
        if ($this->config->getIsCatalogIngestionInstantAsyncEnabled($websiteId)) {
            $this->productEventManager->publishProductEventAsyncJob($productId, $type);
        } else {
            $productEvent = $this->productEventFactory->create();
            $productEvent->setProductId($productId);
            $productEvent->setType($type);
            $productEvent->setCreatedAt($this->dateTime->formatDate(true));
            $this->productEventManager->requestProductEvent($productEvent);
        }
    }
}

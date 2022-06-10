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

namespace Bolt\Boltpay\Cron;

use Bolt\Boltpay\Api\ProductEventRepositoryInterface;
use Bolt\Boltpay\Api\ProductEventManagerInterface;
use Bolt\Boltpay\Helper\Config;
use Bolt\Boltpay\Helper\FeatureSwitch\Decider;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\LocalizedException;

/**
 * Catalog ingestion catalog event schedule processor cron class
 */
class CatalogIngestion
{
    private const DEFAULT_MAX_ITEMS_COUNT = 100;

    /**
     * @var ProductEventRepositoryInterface
     */
    private $productEventRepository;

    /**
     * @var SearchCriteriaBuilder
     */
    private $searchCriteriaBuilder;

    /**
     * @var ProductEventManagerInterface
     */
    private $productEventManager;

    /**
     * @var Config
     */
    private $config;

    /**
     * @var Decider
     */
    private $featureSwitches;

    /**
     * @param ProductEventRepositoryInterface $productEventRepository
     * @param ProductEventManagerInterface $productEventManager
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param Config $config
     * @param Decider $featureSwitches
     */
    public function __construct(
        ProductEventRepositoryInterface $productEventRepository,
        ProductEventManagerInterface $productEventManager,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        Config $config,
        Decider $featureSwitches
    ) {
        $this->productEventRepository = $productEventRepository;
        $this->productEventManager = $productEventManager;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->config = $config;
        $this->featureSwitches = $featureSwitches;
    }

    /**
     * Sending product event list to bolt
     *
     * @return void
     * @throws CouldNotSaveException
     * @throws LocalizedException
     * @throws \Zend_Http_Client_Exception
     */
    public function execute(): void
    {
        if (!$this->featureSwitches->isCatalogIngestionEnabled()) {
            return;
        }
        $pageSize = ($this->config->getCatalogIngestionCronMaxItems()) ?
            $this->config->getCatalogIngestionCronMaxItems() : self::DEFAULT_MAX_ITEMS_COUNT;
        $searchCriteria = $this->searchCriteriaBuilder->create();
        $searchCriteria->setPageSize((int)$pageSize);
        $productEvents = $this->productEventRepository->getList($searchCriteria);
        if (empty($productEvents->getItems())) {
            return;
        }
        foreach ($productEvents->getItems() as $productEvent) {
            $result = $this->productEventManager->sendProductEvent($productEvent);
            if ($result === true) {
                $this->productEventRepository->delete($productEvent);
            }
        }
    }
}

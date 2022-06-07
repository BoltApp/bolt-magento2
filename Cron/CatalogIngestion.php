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
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Exception\CouldNotSaveException;

/**
 * Catalog ingestion catalog event schedule processor cron class
 */
class CatalogIngestion
{
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
     * @param ProductEventRepositoryInterface $productEventRepository
     * @param ProductEventManagerInterface $productEventManager
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     */
    public function __construct(
        ProductEventRepositoryInterface $productEventRepository,
        ProductEventManagerInterface $productEventManager,
        SearchCriteriaBuilder $searchCriteriaBuilder
    ) {
        $this->productEventRepository = $productEventRepository;
        $this->productEventManager = $productEventManager;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
    }

    /**
     * Request product events
     *
     * @return void
     * @throws CouldNotSaveException
     */
    public function execute(): void
    {
        $searchCriteria = $this->searchCriteriaBuilder->create();

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

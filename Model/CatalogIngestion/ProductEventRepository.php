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

namespace Bolt\Boltpay\Model\CatalogIngestion;

use Bolt\Boltpay\Api\Data\ProductEventInterface;
use Bolt\Boltpay\Api\Data\ProductEventInterfaceFactory;
use Bolt\Boltpay\Api\ProductEventRepositoryInterface;
use Bolt\Boltpay\Model\ResourceModel\CatalogIngestion\ProductEvent as ProductEventResource;
use Bolt\Boltpay\Model\ResourceModel\CatalogIngestion\ProductEvent\CollectionFactory;
use Bolt\Boltpay\Model\ResourceModel\CatalogIngestion\ProductEvent\Collection;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Api\SearchResultsInterfaceFactory;
use Magento\Framework\Api\SearchResultsInterface;
use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\StateException;

/**
 * Product event repository class to make CRUD operations
 */
class ProductEventRepository implements ProductEventRepositoryInterface
{
    /**
     * @var ProductEventInterfaceFactory
     */
    private $productEventFactory;

    /**
     * @var ProductEventResource
     */
    private $productEventResource;

    /**
     * @var CollectionFactory
     */
    private $collectionFactory;

    /**
     * @var SearchResultsInterfaceFactory
     */
    private $searchResultsFactory;

    /**
     * @var CollectionProcessorInterface
     */
    private $collectionProcessor;

    /**
     * @param ProductEventInterfaceFactory $productEventFactory
     * @param ProductEventResource $productEventResource
     * @param CollectionFactory $collectionFactory
     * @param SearchResultsInterfaceFactory $searchResultsFactory
     * @param CollectionProcessorInterface $collectionProcessor
     */
    public function __construct(
        ProductEventInterfaceFactory $productEventFactory,
        ProductEventResource $productEventResource,
        CollectionFactory $collectionFactory,
        SearchResultsInterfaceFactory $searchResultsFactory,
        CollectionProcessorInterface $collectionProcessor
    ) {
        $this->productEventFactory = $productEventFactory;
        $this->productEventResource = $productEventResource;
        $this->collectionFactory = $collectionFactory;
        $this->searchResultsFactory = $searchResultsFactory;
        $this->collectionProcessor = $collectionProcessor;
    }

    /**
     * @inheritDoc
     */
    public function get(int $id): ProductEventInterface
    {
        $productEvent = $this->productEventFactory->create();
        $this->productEventResource->load($productEvent, $id);
        if (!$productEvent->getId()) {
            throw new NoSuchEntityException(
                __("The bolt product event that was requested doesn't exist.")
            );
        }
        return $productEvent;
    }

    /**
     * @inheritDoc
     */
    public function getByProductId(int $productId): ProductEventInterface
    {
        $productEvent = $this->productEventFactory->create();
        $this->productEventResource->load(
            $productEvent,
            $productId,
            ProductEventInterface::PRODUCT_ID
        );
        if (!$productEvent->getId()) {
            throw new NoSuchEntityException(
                __("The bolt product event that was requested doesn't exist.")
            );
        }
        return $productEvent;
    }

    /**
     * @inheritDoc
     */
    public function save(ProductEventInterface $productEvent): ProductEventInterface
    {
        try {
            $this->productEventResource->save($productEvent);
        } catch (\Exception $e) {
            throw new CouldNotSaveException(__($e->getMessage()));
        }
        return $productEvent;
    }

    /**
     * @inheritDoc
     */
    public function delete(ProductEventInterface $productEvent): bool
    {
        try {
            $this->productEventResource->delete($productEvent);
        } catch (\Exception $e) {
            throw new StateException(__($e->getMessage()));
        }
        return true;
    }

    /**
     * @inheritDoc
     */
    public function deleteByProductId(int $productId): bool
    {
        $productEvent = $this->getByProductId($productId);
        return $this->delete($productEvent);
    }

    /**
     * @inheritDoc
     */
    public function getList(SearchCriteriaInterface $searchCriteria): SearchResultsInterface
    {
        /** @var SearchResultsInterface $searchResults */
        $searchResults = $this->searchResultsFactory->create();
        $searchResults->setSearchCriteria($searchCriteria);

        /** @var Collection $collection */
        $collection = $this->collectionFactory->create();

        $this->collectionProcessor->process($searchCriteria, $collection);
        $searchResults->setTotalCount($collection->getSize());
        $searchResults->setItems($collection->getItems());

        return $searchResults;
    }
}

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

namespace Bolt\Boltpay\Model\VersionNotifier;

use Bolt\Boltpay\Api\Data\PluginVersionNotificationInterface;
use Bolt\Boltpay\Api\Data\PluginVersionNotificationInterfaceFactory;
use Bolt\Boltpay\Api\PluginVersionNotificationRepositoryInterface;
use Bolt\Boltpay\Model\ResourceModel\VersionNotifier\PluginVersionNotification as PluginVersionNotificationResource;
use Bolt\Boltpay\Model\ResourceModel\VersionNotifier\PluginVersionNotification\CollectionFactory;
use Bolt\Boltpay\Model\ResourceModel\VersionNotifier\PluginVersionNotification\Collection;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Api\SearchResultsInterfaceFactory;
use Magento\Framework\Api\SearchResultsInterface;
use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\StateException;

/**
 * Plugin Version Notification class to make CRUD operations
 */
class PluginVersionNotificationRepository implements PluginVersionNotificationRepositoryInterface
{
    /**
     * @var PluginVersionNotificationInterfaceFactory
     */
    private $pluginVersionNotificationInterfaceFactory;

    /**
     * @var PluginVersionNotificationResource
     */
    private $pluginVersionNotificationRecource;

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
     * @param PluginVersionNotificationInterfaceFactory $pluginVersionNotificationInterfaceFactory
     * @param PluginVersionNotificationResource $pluginVersionNotificationRecource
     * @param CollectionFactory $collectionFactory
     * @param SearchResultsInterfaceFactory $searchResultsFactory
     * @param CollectionProcessorInterface $collectionProcessor
     */
    public function __construct(
        PluginVersionNotificationInterfaceFactory $pluginVersionNotificationInterfaceFactory,
        PluginVersionNotificationResource $pluginVersionNotificationRecource,
        CollectionFactory $collectionFactory,
        SearchResultsInterfaceFactory $searchResultsFactory,
        CollectionProcessorInterface $collectionProcessor
    ) {
        $this->pluginVersionNotificationInterfaceFactory = $pluginVersionNotificationInterfaceFactory;
        $this->pluginVersionNotificationRecource = $pluginVersionNotificationRecource;
        $this->collectionFactory = $collectionFactory;
        $this->searchResultsFactory = $searchResultsFactory;
        $this->collectionProcessor = $collectionProcessor;
    }

    /**
     * @inheritDoc
     */
    public function get(string $version): PluginVersionNotificationInterface
    {
        $pluginNotification = $this->pluginVersionNotificationInterfaceFactory->create();
        $this->pluginVersionNotificationInterfaceFactory->load($pluginNotification, $version);
        if (!$pluginNotification->getLetestVersion()) {
            throw new NoSuchEntityException(
                __("The bolt product event that was requested doesn't exist.")
            );
        }
        return $pluginNotification;
    }

    /**
     * @inheritDoc
     */
    public function save(PluginVersionNotificationInterface $pluginNotification): PluginVersionNotificationInterface
    {
        try {
            $this->pluginVersionNotificationRecource->save($pluginNotification);
        } catch (\Exception $e) {
            throw new CouldNotSaveException(__($e->getMessage()));
        }
        return $pluginNotification;
    }

    /**
     * @inheritDoc
     */
    public function delete(PluginVersionNotificationInterface $pluginNotification): bool
    {
        try {
            $this->pluginVersionNotificationRecource->delete($pluginNotification);
        } catch (\Exception $e) {
            throw new StateException(__($e->getMessage()));
        }
        return true;
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

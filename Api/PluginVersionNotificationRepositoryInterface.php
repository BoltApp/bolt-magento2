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

namespace Bolt\Boltpay\Api;

use Bolt\Boltpay\Api\Data\PluginVersionNotificationInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Api\SearchResultsInterface;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;

interface PluginVersionNotificationRepositoryInterface
{
    /**
     * Get latest version data
     *
     * @param string $version
     * @return PluginVersionNotificationInterface
     * @throws NoSuchEntityException
     */
    public function get(string $version): PluginVersionNotificationInterface;

    /**
     * Save latest version data
     *
     * @param PluginVersionNotificationInterface $pluginVersionNotification
     * @return PluginVersionNotificationInterface
     */
    public function save(PluginVersionNotificationInterface $pluginVersionNotification): PluginVersionNotificationInterface;

    /**
     * Delete latest version data
     *
     * @param PluginVersionNotificationInterface $pluginVersionNotification
     * @return bool will return true if deleted
     * @throws CouldNotSaveException
     */
    public function delete(PluginVersionNotificationInterface $pluginVersionNotification): bool;

    /**
     * Get product event list
     *
     * @param SearchCriteriaInterface $searchCriteria
     * @return SearchResultsInterface
     */
    public function getList(SearchCriteriaInterface $searchCriteria): SearchResultsInterface;
}

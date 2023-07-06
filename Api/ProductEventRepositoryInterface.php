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

use Bolt\Boltpay\Api\Data\ProductEventInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Api\SearchResultsInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\StateException;

/**
 * Product event repository interface
 * @api
 */
interface ProductEventRepositoryInterface
{
    /**
     * @param int $id
     * @return ProductEventInterface
     * @throws NoSuchEntityException
     */
    public function get(int $id): ProductEventInterface;

    /**
     * Get product event by product id
     *
     * @param int $productId
     * @return ProductEventInterface
     * @throws NoSuchEntityException
     */
    public function getByProductId(int $productId): ProductEventInterface;

    /**
     * Save product event
     *
     * @param ProductEventInterface $productEvent
     * @return ProductEventInterface
     */
    public function save(ProductEventInterface $productEvent): ProductEventInterface;

    /**
     * Delete product event
     *
     * @param ProductEventInterface $productEvent
     * @return bool will return true if deleted
     * @throws CouldNotSaveException
     */
    public function delete(ProductEventInterface $productEvent): bool;

    /**
     * Delete product event by product id
     *
     * @param int $productId
     * @return bool will return true if deleted
     * @throws NoSuchEntityException
     * @throws StateException
     */
    public function deleteByProductId(int $productId): bool;

    /**
     * Get product event list
     *
     * @param SearchCriteriaInterface $searchCriteria
     * @return SearchResultsInterface
     */
    public function getList(SearchCriteriaInterface $searchCriteria): SearchResultsInterface;
}

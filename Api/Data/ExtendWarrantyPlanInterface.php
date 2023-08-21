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

namespace Bolt\Boltpay\Api\Data;

/**
 * Extend warranty plan
 */
interface ExtendWarrantyPlanInterface extends \Magento\Framework\Api\ExtensibleDataInterface
{
    const PRODUCT = 'product';
    const PLAN_ID = 'planId';
    const OFFER_ID = 'offerId';
    const COVERAGE_TYPE = 'coverageType';
    const TITLE = 'title';
    const TOKEN = 'token';
    const TERM = 'term';
    const PRICE = 'price';
    const QTY = 'qty';

    /**
     * Get product sku
     *
     * @api
     * @return string
     */
    public function getProduct(): string;

    /**
     * Set product sku
     *
     * @api
     * @param string $product
     *
     * @return ExtendWarrantyPlanInterface
     */
    public function setProduct(string $product): ExtendWarrantyPlanInterface;

    /**
     * Get plan id
     *
     * @api
     * @return string
     */
    public function getPlanId(): string;

    /**
     * Set plan id
     *
     * @api
     * @param string $planId
     *
     * @return ExtendWarrantyPlanInterface
     */
    public function setPlanId(string $planId): ExtendWarrantyPlanInterface;

    /**
     * Get offer id
     *
     * @api
     * @return string
     */
    public function getOfferId(): string;

    /**
     * Set offer id
     *
     * @api
     * @param string $offerId
     *
     * @return ExtendWarrantyPlanInterface
     */
    public function setOfferId(string $offerId): ExtendWarrantyPlanInterface;

    /**
     * Get coverage type
     *
     * @api
     * @return string
     */
    public function getCoverageType(): string;

    /**
     * Set coverage type
     *
     * @api
     * @param string $coverageType
     *
     * @return ExtendWarrantyPlanInterface
     */
    public function setCoverageType(string $coverageType): ExtendWarrantyPlanInterface;

    /**
     * Get title
     *
     * @api
     * @return string
     */
    public function getTitle(): string;

    /**
     * Set title
     *
     * @api
     * @param string $title
     *
     * @return ExtendWarrantyPlanInterface
     */
    public function setTitle(string $title): ExtendWarrantyPlanInterface;

    /**
     * Get token
     *
     * @api
     * @return string
     */
    public function getToken(): string;

    /**
     * Set token
     *
     * @api
     * @param string $token
     *
     * @return ExtendWarrantyPlanInterface
     */
    public function setToken(string $token): ExtendWarrantyPlanInterface;

    /**
     * Get term
     *
     * @api
     * @return int
     */
    public function getTerm(): int;

    /**
     * Set term
     *
     * @api
     * @param int $term
     *
     * @return ExtendWarrantyPlanInterface
     */
    public function setTerm(int $term): ExtendWarrantyPlanInterface;

    /**
     * Get price
     *
     * @api
     * @return int
     */
    public function getPrice(): int;

    /**
     * Set price
     *
     * @api
     * @param int $price
     *
     * @return ExtendWarrantyPlanInterface
     */
    public function setPrice(int $price): ExtendWarrantyPlanInterface;

    /**
     * Get qty
     *
     * @api
     * @return int
     */
    public function getQty(): int;

    /**
     * Set qty
     *
     * @api
     * @param int $qty
     *
     * @return ExtendWarrantyPlanInterface
     */
    public function setQty(int $qty): ExtendWarrantyPlanInterface;

    /**
     * Retrieve existing extension attributes object or create a new one.
     *
     * @return \Bolt\Boltpay\Api\Data\ExtendWarrantyPlanExtensionInterface|null
     */
    public function getExtensionAttributes();

    /**
     * Set an extension attributes object.
     *
     * @param \Bolt\Boltpay\Api\Data\ExtendWarrantyPlanExtensionInterface $extensionAttributes
     * @return $this
     */
    public function setExtensionAttributes(\Bolt\Boltpay\Api\Data\ExtendWarrantyPlanExtensionInterface $extensionAttributes);
}

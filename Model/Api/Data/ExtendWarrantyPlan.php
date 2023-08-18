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

namespace Bolt\Boltpay\Model\Api\Data;

use Magento\Framework\Model\AbstractExtensibleModel;
use Bolt\Boltpay\Api\Data\ExtendWarrantyPlanInterface;
use Magento\Framework\DataObject;

/**
 * Extend Warranty
 */
class ExtendWarrantyPlan extends AbstractExtensibleModel implements ExtendWarrantyPlanInterface
{
    /**
     * @inheritDoc
     */
    public function getProduct(): string
    {
        return $this->getData(self::PRODUCT);
    }

    /**
     * @inheritDoc
     */
    public function setProduct(string $product): ExtendWarrantyPlanInterface
    {
        return $this->setData(self::PRODUCT, $product);
    }

    /**
     * @inheritDoc
     */
    public function getPlanId(): string
    {
        return $this->getData(self::PLAN_ID);
    }

    /**
     * @inheritDoc
     */
    public function setPlanId(string $planId): ExtendWarrantyPlanInterface
    {
        return $this->setData(self::PLAN_ID, $planId);
    }

    /**
     * @inheritDoc
     */
    public function getOfferId(): string
    {
        return $this->getData(self::OFFER_ID);
    }

    /**
     * @inheritDoc
     */
    public function setOfferId(string $offerId): ExtendWarrantyPlanInterface
    {
        return $this->setData(self::OFFER_ID, $offerId);
    }

    /**
     * @inheritDoc
     */
    public function getCoverageType(): string
    {
        return $this->getData(self::COVERAGE_TYPE);
    }

    /**
     * @inheritDoc
     */
    public function setCoverageType(string $coverageType): ExtendWarrantyPlanInterface
    {
        return $this->setData(self::COVERAGE_TYPE, $coverageType);
    }

    /**
     * @inheritDoc
     */
    public function getTitle(): string
    {
        return $this->getData(self::TITLE);
    }

    /**
     * @inheritDoc
     */
    public function setTitle(string $title): ExtendWarrantyPlanInterface
    {
        return $this->setData(self::TITLE, $title);
    }

    /**
     * @inheritDoc
     */
    public function getToken(): string
    {
        return $this->getData(self::TOKEN);
    }

    /**
     * @inheritDoc
     */
    public function setToken(string $token): ExtendWarrantyPlanInterface
    {
        return $this->setData(self::TOKEN, $token);
    }

    /**
     * @inheritDoc
     */
    public function getTerm(): int
    {
        return $this->getData(self::TERM);
    }

    /**
     * @inheritDoc
     */
    public function setTerm(int $term): ExtendWarrantyPlanInterface
    {
        return $this->setData(self::TERM, $term);
    }

    /**
     * @inheritDoc
     */
    public function getPrice(): int
    {
        return $this->getData(self::PRICE);
    }

    /**
     * @inheritDoc
     */
    public function setPrice(int $price): ExtendWarrantyPlanInterface
    {
        return $this->setData(self::PRICE, $price);
    }

    /**
     * @inheritDoc
     */
    public function getQty(): int
    {
        return $this->getData(self::PRICE);
    }

    /**
     * @inheritDoc
     */
    public function setQty(int $qty): ExtendWarrantyPlanInterface
    {
        return $this->setData(self::QTY, $qty);
    }

    /**
     * Returns buy request based on plan data
     *
     * @return DataObject
     */
    public function getBuyRequest(): DataObject
    {
        $buyRequest = new DataObject();
        $buyRequest->setData($this->getData());
        return $buyRequest;
    }

    /**
     * @inheritDoc
     */
    public function getExtensionAttributes()
    {
        return $this->_getExtensionAttributes();
    }

    /**
     * @inheritDoc
     */
    public function setExtensionAttributes(\Bolt\Boltpay\Api\Data\ExtendWarrantyPlanExtensionInterface $extensionAttributes)
    {
        return $this->_setExtensionAttributes($extensionAttributes);
    }
}

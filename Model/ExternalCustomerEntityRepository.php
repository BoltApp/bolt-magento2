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
 * @copyright  Copyright (c) 2017-2021 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Bolt\Boltpay\Model;

use Bolt\Boltpay\Api\Data\ExternalCustomerEntityInterface;
use Bolt\Boltpay\Api\ExternalCustomerEntityRepositoryInterface;
use Bolt\Boltpay\Model\ResourceModel\ExternalCustomerEntity\CollectionFactory as ExternalCustomerEntityCollectionFactory;
use Magento\Framework\Exception\NoSuchEntityException;

class ExternalCustomerEntityRepository implements ExternalCustomerEntityRepositoryInterface
{
    /**
     * @var ExternalCustomerEntityFactory
     */
    private $externalCustomerEntityFactory;

    /**
     * @var ExternalCustomerEntityCollectionFactory
     */
    private $externalCustomerEntityCollectionFactory;

    /**
     * @param ExternalCustomerEntityFactory           $externalCustomerEntityFactory
     * @param ExternalCustomerEntityCollectionFactory $externalCustomerEntityCollectionFactory
     */
    public function __construct(
        ExternalCustomerEntityFactory $externalCustomerEntityFactory,
        ExternalCustomerEntityCollectionFactory $externalCustomerEntityCollectionFactory
    ) {
        $this->externalCustomerEntityFactory = $externalCustomerEntityFactory;
        $this->externalCustomerEntityCollectionFactory = $externalCustomerEntityCollectionFactory;
    }

    /**
     * @param string $externalID
     *
     * @return ExternalCustomerEntityInterface
     * @throws NoSuchEntityException
     */
    public function getByExternalID($externalID)
    {
        $externalCustomerEntityCollection = $this->externalCustomerEntityCollectionFactory->create();
        $externalCustomerEntity = $externalCustomerEntityCollection->getExternalCustomerEntityByExternalID($externalID);
        if ($externalCustomerEntity === null) {
            throw new NoSuchEntityException(__('Unable to find external customer entity with external ID "%1"', $externalID));
        }
        return $externalCustomerEntity;
    }

    /**
     * @param string $externalID
     * @param int    $customerID
     *
     * @return ExternalCustomerEntityInterface
     */
    public function create($externalID, $customerID)
    {
        $externalCustomerEntity = $this->externalCustomerEntityFactory->create();
        $externalCustomerEntity->setExternalID($externalID);
        $externalCustomerEntity->setCustomerID($customerID);
        return $this->save($externalCustomerEntity);
    }
}

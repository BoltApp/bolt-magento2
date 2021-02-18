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

namespace Bolt\Boltpay\Model\ResourceModel\ExternalCustomerEntity;

use Bolt\Boltpay\Model\ExternalCustomerEntity;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

/**
 * Class Collection
 *
 * @package Bolt\Boltpay\Model\ResourceModel\ExternalCustomerEntity
 */
class Collection extends AbstractCollection
{
    protected function _construct()
    {
        $this->_init(
            'Bolt\Boltpay\Model\ExternalCustomerEntity',
            'Bolt\Boltpay\Model\ResourceModel\ExternalCustomerEntity'
        );
    }

    /**
     * @param string $externalID
     *
     * @return ExternalCustomerEntity|null
     */
    public function getExternalCustomerEntityByExternalID($externalID)
    {
        $externalCustomerEntityCollection = $this->addFilter('external_id', $externalID);
        if ($externalCustomerEntityCollection->getSize() > 0) {
            return $externalCustomerEntityCollection->getFirstItem();
        }
        return null;
    }
}

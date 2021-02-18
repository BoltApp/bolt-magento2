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
use Magento\Framework\Model\AbstractModel;

class ExternalCustomerEntity extends AbstractModel implements ExternalCustomerEntityInterface
{
    const EXTERNAL_ID = 'external_id';
    const CUSTOMER_ID = 'customer_id';

    protected function _construct()
    {
        parent::_construct();
        $this->_init(ResourceModel\ExternalCustomerEntity::class);
    }

    /**
     * @return string
     */
    public function getExternalID()
    {
        return $this->_getData(self::EXTERNAL_ID);
    }

    /**
     * @param string $externalID
     *
     * @return void
     */
    public function setExternalID($externalID)
    {
        $this->setData(self::EXTERNAL_ID, $externalID);
    }

    /**
     * @return int
     */
    public function getCustomerID()
    {
        return $this->_getData(self::CUSTOMER_ID);
    }

    /**
     * @param int $customerID
     *
     * @return void
     */
    public function setCustomerID($customerID)
    {
        $this->setData(self::CUSTOMER_ID, $customerID);
    }
}

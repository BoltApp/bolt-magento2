<?php


namespace Bolt\Boltpay\Model\ResourceModel;

use \Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class FeatureSwitch extends AbstractDb
{
    /**
     * Post Abstract Resource Constructor
     * @return void
     */
    protected function _construct()
    {
        $this->_init('bolt_feature_switches', 'id');
    }
}